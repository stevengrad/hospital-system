import os
import io
import cv2
import base64
import pickle
import numpy as np
import torch
from flask import Flask, request, jsonify
from ultralytics import YOLO
from facenet_pytorch import InceptionResnetV1
from PIL import Image

app = Flask(__name__)

# =========================
# PATHS
# =========================
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

DB_FOLDER = os.path.join(BASE_DIR, "build_database", "db_folder")
YOLO_WEIGHTS = os.path.join(BASE_DIR, "yolov8l_100e.pt")
FACENET_WEIGHTS = os.path.join(BASE_DIR, "20180402-114759-vggface2.pt")
REPRESENTATIONS_FILE = os.path.join(DB_FOLDER, "representations.pkl")

RECOGNITION_THRESHOLD = 1.4
DETECTION_CONFIDENCE = 0.3

# =========================
# CORS
# =========================
@app.after_request
def add_cors_headers(response):
    response.headers["Access-Control-Allow-Origin"] = "*"
    response.headers["Access-Control-Allow-Headers"] = "Content-Type"
    response.headers["Access-Control-Allow-Methods"] = "POST, GET, OPTIONS"
    return response

# =========================
# LOAD MODELS
# =========================
print("Loading YOLO model...")
face_detector = YOLO(YOLO_WEIGHTS)

print("Loading FaceNet model...")
face_recognizer = InceptionResnetV1(classify=False, pretrained=None)
state_dict = torch.load(FACENET_WEIGHTS, map_location="cpu")

# remove classification layer weights if present
state_dict.pop("logits.weight", None)
state_dict.pop("logits.bias", None)

face_recognizer.load_state_dict(state_dict, strict=False)
face_recognizer.eval()
print("Models loaded successfully!")

# =========================
# HELPERS
# =========================
def preprocess_face(face_img):
    face_rgb = cv2.cvtColor(face_img, cv2.COLOR_BGR2RGB)
    face_resized = cv2.resize(face_rgb, (160, 160))
    face_pixels = np.asarray(face_resized).astype("float32")

    mean, std = face_pixels.mean(), face_pixels.std()
    if std == 0:
        std = 1.0
    face_pixels = (face_pixels - mean) / std

    face_tensor = torch.from_numpy(face_pixels.transpose((2, 0, 1))).float()
    face_tensor = face_tensor.unsqueeze(0)
    return face_tensor


def extract_embedding(face_tensor):
    with torch.no_grad():
        embedding = face_recognizer(face_tensor)
    embedding = embedding.detach().cpu().numpy()
    embedding = embedding / np.sqrt(np.sum(np.multiply(embedding, embedding)))
    return embedding


def calculate_distance(embedding1, embedding2):
    return np.linalg.norm(embedding1 - embedding2)


def detect_faces(frame):
    results = face_detector.predict(frame, conf=DETECTION_CONFIDENCE, verbose=False)

    bboxes = []
    if len(results) > 0 and results[0].boxes is not None:
        boxes = results[0].boxes.xyxy.cpu().numpy()
        for box in boxes:
            x1, y1, x2, y2 = map(int, box)
            bboxes.append([x1, y1, x2, y2])
    return bboxes


def load_or_create_database():
    if not os.path.exists(DB_FOLDER):
        os.makedirs(DB_FOLDER, exist_ok=True)

    if os.path.exists(REPRESENTATIONS_FILE):
        print(f"Loading existing database from {REPRESENTATIONS_FILE}")
        with open(REPRESENTATIONS_FILE, "rb") as f:
            database = pickle.load(f)
        print(f"Loaded {len(database)} embeddings")
        return database

    print(f"Creating new database from {DB_FOLDER}")
    database = []

    person_folders = [
        f for f in os.listdir(DB_FOLDER)
        if os.path.isdir(os.path.join(DB_FOLDER, f))
    ]

    for person_name in person_folders:
        person_path = os.path.join(DB_FOLDER, person_name)
        image_files = [
            f for f in os.listdir(person_path)
            if f.lower().endswith((".jpg", ".jpeg", ".png", ".bmp"))
        ]

        for img_file in image_files:
            img_path = os.path.join(person_path, img_file)
            img = cv2.imread(img_path)
            if img is None:
                continue

            try:
                face_tensor = preprocess_face(img)
                embedding = extract_embedding(face_tensor)
                database.append([person_name, embedding])
                print(f"Added {img_file}")
            except Exception as e:
                print(f"Error processing {img_file}: {e}")

    with open(REPRESENTATIONS_FILE, "wb") as f:
        pickle.dump(database, f)

    print(f"Database saved with {len(database)} embeddings")
    return database

database = load_or_create_database()


def decode_base64_image(data_url):
    if "," in data_url:
        data_url = data_url.split(",", 1)[1]

    image_bytes = base64.b64decode(data_url)
    image = Image.open(io.BytesIO(image_bytes)).convert("RGB")
    image_np = np.array(image)
    image_bgr = cv2.cvtColor(image_np, cv2.COLOR_RGB2BGR)
    return image_bgr


def recognize_face(face_img):
    global database

    face_tensor = preprocess_face(face_img)
    face_embedding = extract_embedding(face_tensor)

    min_distance = float("inf")
    identity = "unknown"

    for person_name, db_embedding in database:
        distance = calculate_distance(face_embedding, db_embedding)
        if distance < min_distance:
            min_distance = distance
            identity = person_name

    if min_distance > RECOGNITION_THRESHOLD:
        identity = "unknown"

    return identity, float(min_distance)


# =========================
# ROUTES
# =========================
@app.route("/health", methods=["GET"])
@app.route("/face/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "db_folder": DB_FOLDER,
        "database_size": len(database)
    })


@app.route("/reload_db", methods=["POST"])
@app.route("/face/reload_db", methods=["POST"])
def reload_db():
    global database
    database = load_or_create_database()
    return jsonify({
        "success": True,
        "database_size": len(database)
    })


@app.route("/verify_face", methods=["POST", "OPTIONS"])
@app.route("/face/verify_face", methods=["POST", "OPTIONS"])
def verify_face():
    if request.method == "OPTIONS":
        return ("", 204)

    data = request.get_json(silent=True)
    if not data or "image" not in data:
        return jsonify({"success": False, "message": "No image received"}), 400

    try:
        frame = decode_base64_image(data["image"])
    except Exception as e:
        return jsonify({"success": False, "message": f"Invalid image: {str(e)}"}), 400

    bboxes = detect_faces(frame)
    if not bboxes:
        return jsonify({"success": False, "message": "No face detected"})

    # أكبر وجه
    bboxes.sort(key=lambda b: (b[2] - b[0]) * (b[3] - b[1]), reverse=True)
    x1, y1, x2, y2 = bboxes[0]

    x1 = max(0, x1)
    y1 = max(0, y1)
    x2 = min(frame.shape[1], x2)
    y2 = min(frame.shape[0], y2)

    face_img = frame[y1:y2, x1:x2]
    if face_img.size == 0:
        return jsonify({"success": False, "message": "Invalid face crop"})

    identity, distance = recognize_face(face_img)
    print(f"Matched identity: {identity}, distance: {distance}")

    return jsonify({
    "success": True,
    "matched": identity != "unknown",
    "identity": identity,
    "distance": float(distance)
})


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=False)