import os
import io
import re
import pickle
import base64
from datetime import datetime

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


def safe_username(username: str) -> str:
    username = username.strip().lower()
    username = re.sub(r"[^a-zA-Z0-9_.-]", "_", username)
    return username or "unknown"


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
        enforce_detection=True
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


@app.route("/face/verify_face", methods=["POST"])
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

    verified = best_distance <= THRESHOLD

    return jsonify({
        "success": True,
        "verified": verified,
        "identity": best_match.get("identity") if best_match else None,
        "patient_id": best_match.get("patient_id") if best_match else None,
        "distance": float(best_distance),
        "threshold": THRESHOLD,
        "image_key": best_match.get("image_key") if best_match else None
    })


if __name__ == "__main__":
    port = int(os.getenv("PORT", "5001"))
    app.run(host="0.0.0.0", port=port)