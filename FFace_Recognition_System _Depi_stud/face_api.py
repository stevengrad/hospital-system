import os
import io
import re
import pickle
import base64
from datetime import datetime
import os
import boto3
import cv2
import numpy as np
from flask import Flask, request, jsonify
from flask_cors import CORS
from deepface import DeepFace


app = Flask(__name__)
CORS(app)

MODEL_NAME = os.getenv("FACE_MODEL_NAME", "Facenet")
DETECTOR_BACKEND = os.getenv("FACE_DETECTOR_BACKEND", "opencv")
DISTANCE_METRIC = os.getenv("FACE_DISTANCE_METRIC", "cosine")
THRESHOLD = float(os.getenv("FACE_THRESHOLD", "0.40"))

S3_BUCKET = os.getenv("FACE_S3_BUCKET", "")
AWS_REGION = os.getenv("AWS_REGION", "eu-central-1")
S3_PREFIX = os.getenv("FACE_S3_PREFIX", "faces").strip("/")

REPRESENTATIONS_KEY = f"{S3_PREFIX}/representations.pkl"

s3_client = None
if S3_BUCKET:
    s3_client = boto3.client("s3", region_name=AWS_REGION)

def sync_representations_from_s3():
    """
    Download the latest representations.pkl from S3 into the local db_folder
    when the Face API starts.
    """
    bucket = os.getenv("FACE_S3_BUCKET", "cairo-hospital-face-images-137068224200")
    pkl_key = os.getenv("FACE_PKL_KEY", "faces/representations.pkl")

    local_pkl_path = os.getenv(
        "LOCAL_PKL_PATH",
        "/app/build_database/db_folder/representations.pkl"
    )

    try:
        os.makedirs(os.path.dirname(local_pkl_path), exist_ok=True)

        s3 = boto3.client("s3")
        s3.download_file(bucket, pkl_key, local_pkl_path)

        print(f"[PKL SYNC] Downloaded latest PKL from s3://{bucket}/{pkl_key}")
        print(f"[PKL SYNC] Local PKL updated: {local_pkl_path}")

        return True

    except Exception as e:
        print(f"[PKL SYNC ERROR] Could not download representations.pkl from S3: {e}")
        return False



def decode_base64_image(data_url: str):
    """
    Accepts:
    data:image/jpeg;base64,xxxx
    or raw base64 xxxx
    """
    if "," in data_url:
        data_url = data_url.split(",", 1)[1]

    image_bytes = base64.b64decode(data_url)
    np_arr = np.frombuffer(image_bytes, np.uint8)
    img = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

    if img is None:
        raise ValueError("Invalid image data")

    return img


def image_to_jpeg_bytes(img):
    success, buffer = cv2.imencode(".jpg", img)
    if not success:
        raise ValueError("Could not encode image")
    return buffer.tobytes()


def upload_bytes_to_s3(file_bytes: bytes, key: str, content_type="image/jpeg"):
    if not s3_client:
        raise RuntimeError("S3 is not configured. Missing FACE_S3_BUCKET.")

    s3_client.upload_fileobj(
        io.BytesIO(file_bytes),
        S3_BUCKET,
        key,
        ExtraArgs={
            "ContentType": content_type,
            "ServerSideEncryption": "AES256"
        }
    )
    return key


def download_representations():
    """
    Download saved embeddings from S3.
    If file does not exist, return empty list.
    """
    if not s3_client:
        return []

    try:
        obj = s3_client.get_object(Bucket=S3_BUCKET, Key=REPRESENTATIONS_KEY)
        return pickle.loads(obj["Body"].read())
    except s3_client.exceptions.NoSuchKey:
        return []
    except Exception:
        return []


def upload_representations(representations):
    if not s3_client:
        return

    data = pickle.dumps(representations)
    s3_client.upload_fileobj(
        io.BytesIO(data),
        S3_BUCKET,
        REPRESENTATIONS_KEY,
        ExtraArgs={
            "ContentType": "application/octet-stream",
            "ServerSideEncryption": "AES256"
        }
    )


def get_embedding_from_image(img):
    """
    DeepFace expects RGB image or image path.
    cv2 reads BGR, so convert to RGB.
    """
    rgb_img = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)

    embeddings = DeepFace.represent(
        img_path=rgb_img,
        model_name=MODEL_NAME,
        detector_backend=DETECTOR_BACKEND,
        enforce_detection=False
    )

    if not embeddings:
        raise ValueError("No face detected")

    return embeddings[0]["embedding"]


def cosine_distance(a, b):
    a = np.array(a)
    b = np.array(b)

    if np.linalg.norm(a) == 0 or np.linalg.norm(b) == 0:
        return 1.0

    return 1 - np.dot(a, b) / (np.linalg.norm(a) * np.linalg.norm(b))



@app.route("/face/health", methods=["GET"])
def health():
    reps = download_representations()
    return jsonify({
        "status": "ok",
        "s3_enabled": bool(s3_client),
        "s3_bucket": S3_BUCKET,
        "representations_key": REPRESENTATIONS_KEY,
        "database_size": len(reps),
        "model": MODEL_NAME,
        "detector": DETECTOR_BACKEND
    })
@app.route("/health", methods=["GET"])
def root_health():
    return health()

def safe_username(username: str) -> str:
    username = str(username or "").strip()
    username = re.sub(r"[^A-Za-z0-9_\-\.]", "_", username)
    return username

@app.route("/face/register_face", methods=["POST"])
def register_face():
    """
    Expected JSON:
    {
      "username": "patient_username",
      "patient_id": 123,
      "images": [
        "data:image/jpeg;base64,...",
        ...
      ]
    }
    """
    data = request.get_json(silent=True) or {}

    username = safe_username(data.get("username", ""))
    patient_id = data.get("patient_id")
    images = data.get("images", [])

    if not username:
        return jsonify({"success": False, "error": "username is required"}), 400

    if not isinstance(images, list) or len(images) < 1:
        return jsonify({"success": False, "error": "images list is required"}), 400

    if not s3_client:
        return jsonify({
            "success": False,
            "error": "S3 is not configured. Add FACE_S3_BUCKET environment variable."
        }), 500

    representations = download_representations()

    saved_keys = []
    saved_embeddings = []

    for index, image_data in enumerate(images, start=1):
        try:
            img = decode_base64_image(image_data)

            # Create embedding first. If no face exists, skip this image.
            embedding = get_embedding_from_image(img)

            jpg_bytes = image_to_jpeg_bytes(img)

            timestamp = datetime.utcnow().strftime("%Y%m%dT%H%M%SZ")
            image_key = f"{S3_PREFIX}/{username}/image_{index}_{timestamp}.jpg"

            upload_bytes_to_s3(jpg_bytes, image_key)

            record = {
                "identity": username,
                "patient_id": patient_id,
                "image_key": image_key,
                "embedding": embedding,
                "created_at": timestamp
            }

            representations.append(record)
            saved_keys.append(image_key)
            saved_embeddings.append(record)

        except Exception as e:
            # Continue with other images, but report failed image
            print(f"Failed image {index} for {username}: {str(e)}")

    if len(saved_keys) == 0:
        return jsonify({
            "success": False,
            "error": "No valid face images were saved. Make sure the face is clear."
        }), 400

    upload_representations(representations)

    return jsonify({
        "success": True,
        "username": username,
        "patient_id": patient_id,
        "saved": len(saved_keys),
        "image_keys": saved_keys,
        "first_image_key": saved_keys[0],
        "representations_key": REPRESENTATIONS_KEY
    })



def delete_s3_prefix(prefix: str):
    """Delete all S3 objects under a prefix."""
    if not s3_client:
        return 0

    deleted_count = 0
    paginator = s3_client.get_paginator("list_objects_v2")

    for page in paginator.paginate(Bucket=S3_BUCKET, Prefix=prefix):
        objects = page.get("Contents", [])
        if not objects:
            continue

        delete_payload = {
            "Objects": [{"Key": obj["Key"]} for obj in objects],
            "Quiet": True
        }

        response = s3_client.delete_objects(
            Bucket=S3_BUCKET,
            Delete=delete_payload
        )

        deleted_count += len(response.get("Deleted", []))

    return deleted_count


def delete_s3_keys(keys):
    """Delete exact S3 object keys."""
    if not s3_client or not keys:
        return 0

    keys = list(set(keys))
    deleted_count = 0

    for i in range(0, len(keys), 1000):
        batch = keys[i:i + 1000]

        response = s3_client.delete_objects(
            Bucket=S3_BUCKET,
            Delete={
                "Objects": [{"Key": key} for key in batch],
                "Quiet": True
            }
        )

        deleted_count += len(response.get("Deleted", []))

    return deleted_count


def delete_user_images_from_s3(username, bucket=None, prefix="faces"):
    """
    Delete S3 images for a username.

    It deletes:
    1. faces/<username>/
    2. faces/<username lowercase>/
    3. Any key under faces/ where the first folder equals username case-insensitive.
    """
    if not s3_client:
        return 0, []

    bucket = bucket or S3_BUCKET
    prefix = (prefix or S3_PREFIX).strip("/")

    raw_username = str(username).strip()
    lower_username = raw_username.lower()

    keys_to_delete = set()
    paginator = s3_client.get_paginator("list_objects_v2")

    candidate_prefixes = [
        f"{prefix}/{raw_username}/",
        f"{prefix}/{lower_username}/",
    ]

    for folder_prefix in candidate_prefixes:
        for page in paginator.paginate(Bucket=bucket, Prefix=folder_prefix):
            for obj in page.get("Contents", []):
                keys_to_delete.add(obj["Key"])

    # Fallback scan: faces/<folder_name>/...
    for page in paginator.paginate(Bucket=bucket, Prefix=f"{prefix}/"):
        for obj in page.get("Contents", []):
            key = obj["Key"]
            parts = key.split("/")

            if len(parts) >= 2:
                folder_name = parts[1].strip()
                if folder_name == raw_username or folder_name.lower() == lower_username:
                    keys_to_delete.add(key)

    keys_to_delete = list(keys_to_delete)
    deleted_count = delete_s3_keys(keys_to_delete)

    return deleted_count, keys_to_delete


def record_belongs_to_user(record, username, patient_id=None):
    """
    Supports both old pkl structure:
        ['username', embedding]
    and new pkl structure:
        {'identity': username, 'patient_id': ..., 'image_key': ..., 'embedding': ...}
    """
    username = str(username).strip()
    username_lower = username.lower()

    if isinstance(record, dict):
        identity = str(record.get("identity", "")).strip()
        record_patient_id = record.get("patient_id")

        same_username = identity.lower() == username_lower
        same_patient_id = (
            patient_id is not None
            and record_patient_id is not None
            and str(record_patient_id) == str(patient_id)
        )

        return same_username or same_patient_id

    if isinstance(record, (list, tuple)) and len(record) > 0:
        identity = str(record[0]).strip()
        return identity.lower() == username_lower

    return False


def get_record_image_key(record):
    if isinstance(record, dict):
        return record.get("image_key")
    return None



@app.route("/face/delete_user", methods=["POST"])
def delete_face_user():
    print("[DELETE USER] endpoint reached", flush=True)
    import traceback

    try:
        data = request.get_json(silent=True) or {}

        username = safe_username(data.get("username", ""))
        patient_id = data.get("patient_id")
        bucket = data.get("bucket") or S3_BUCKET
        prefix = (data.get("prefix") or S3_PREFIX).strip("/")

        if not username and patient_id is None:
            return jsonify({
                "success": False,
                "error": "username or patient_id is required"
            }), 400

        if not s3_client:
            return jsonify({
                "success": False,
                "error": "S3 is not configured"
            }), 500

        representations = download_representations()
        before_count = len(representations)

        def belongs_to_user(record):
            username_lower = username.lower()

            # New dict format
            if isinstance(record, dict):
                identity = str(record.get("identity", "")).strip().lower()
                record_patient_id = record.get("patient_id")

                if username and identity == username_lower:
                    return True

                if patient_id is not None and record_patient_id is not None:
                    return str(record_patient_id) == str(patient_id)

                return False

            # Old list format: ['username', embedding]
            if isinstance(record, (list, tuple)) and len(record) > 0:
                identity = str(record[0]).strip().lower()
                return username and identity == username_lower

            return False

        user_records = [r for r in representations if belongs_to_user(r)]

        image_keys = set()

        for record in user_records:
            if isinstance(record, dict):
                key = record.get("image_key")
                if key:
                    image_keys.add(key)

        # Delete exact image keys from pkl if available
        deleted_images_from_pkl_keys = 0

        if image_keys:
            keys = list(image_keys)

            for i in range(0, len(keys), 1000):
                batch = keys[i:i + 1000]
                response = s3_client.delete_objects(
                    Bucket=bucket,
                    Delete={
                        "Objects": [{"Key": key} for key in batch],
                        "Quiet": True
                    }
                )
                deleted_images_from_pkl_keys += len(response.get("Deleted", []))

        # Delete S3 folder faces/<username>/
        deleted_s3_keys = []
        images_deleted_by_prefix = 0

        if username:
            candidate_prefixes = [
                f"{prefix}/{username}/",
                f"{prefix}/{username.lower()}/"
            ]

            for user_prefix in candidate_prefixes:
                paginator = s3_client.get_paginator("list_objects_v2")

                for page in paginator.paginate(Bucket=bucket, Prefix=user_prefix):
                    objects = page.get("Contents", [])

                    if not objects:
                        continue

                    keys = [obj["Key"] for obj in objects]
                    deleted_s3_keys.extend(keys)

                    for i in range(0, len(keys), 1000):
                        batch = keys[i:i + 1000]
                        response = s3_client.delete_objects(
                            Bucket=bucket,
                            Delete={
                                "Objects": [{"Key": key} for key in batch],
                                "Quiet": True
                            }
                        )
                        images_deleted_by_prefix += len(response.get("Deleted", []))

        # Remove from pkl
        remaining = [r for r in representations if not belongs_to_user(r)]
        upload_representations(remaining)

        # Update local pkl too
        local_pkl_path = os.getenv(
            "LOCAL_PKL_PATH",
            "/app/build_database/db_folder/representations.pkl"
        )

        local_pkl_updated = False

        try:
            os.makedirs(os.path.dirname(local_pkl_path), exist_ok=True)
            with open(local_pkl_path, "wb") as f:
                pickle.dump(remaining, f)
            local_pkl_updated = True
        except Exception as e:
            print(f"[PKL LOCAL UPDATE ERROR] {e}")

        return jsonify({
            "success": True,
            "username": username,
            "patient_id": patient_id,
            "representations_before": before_count,
            "representations_after": len(remaining),
            "representations_removed": before_count - len(remaining),
            "images_deleted_from_pkl_keys": deleted_images_from_pkl_keys,
            "images_deleted_by_prefix": images_deleted_by_prefix,
            "deleted_s3_keys": deleted_s3_keys,
            "local_pkl_updated": local_pkl_updated,
            "representations_key": REPRESENTATIONS_KEY
        })

    except Exception as e:
        print("[DELETE USER ERROR]")
        print(traceback.format_exc())

        return jsonify({
            "success": False,
            "error": str(e),
            "traceback": traceback.format_exc()
        }), 500
@app.route("/face/verify_face", methods=["POST"])
@app.route("/verify_face", methods=["POST"])
def verify_face():
    """
    Expected JSON:
    {
      "image": "data:image/jpeg;base64,..."
    }

    Optional:
    {
      "username": "specific_user"
    }
    """
    data = request.get_json(silent=True) or {}

    image_data = data.get("image")
    username_filter = data.get("username")

    if not image_data:
        return jsonify({"success": False, "error": "image is required"}), 400

    try:
        img = decode_base64_image(image_data)
        query_embedding = get_embedding_from_image(img)
    except Exception as e:
        return jsonify({"success": False, "error": str(e)}), 400

    representations = download_representations()

    if username_filter:
        username_filter = safe_username(username_filter)
        representations = [
            r for r in representations
            if r.get("identity") == username_filter
        ]

    if not representations:
        return jsonify({
            "success": False,
            "verified": False,
            "error": "No registered faces found"
        }), 404

    best_match = None
    best_distance = 999

    for record in representations:
        distance = cosine_distance(query_embedding, record.get("embedding", []))

        if distance < best_distance:
            best_distance = distance
            best_match = record

    verified = bool(best_distance <= THRESHOLD)

    return jsonify({
    "success": True,
    "verified": verified,
    "identity": str(best_match.get("identity")) if best_match else None,
    "patient_id": best_match.get("patient_id") if best_match else None,
    "distance": float(best_distance),
    "threshold": float(THRESHOLD),
    "image_key": str(best_match.get("image_key")) if best_match else None
})
sync_representations_from_s3()
if __name__ == "__main__":
    port = int(os.getenv("PORT", "5001"))
    app.run(host="0.0.0.0", port=port)