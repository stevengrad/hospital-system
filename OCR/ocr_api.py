from flask import Flask, request, jsonify
from flask_cors import CORS
import cv2
import numpy as np
import easyocr
import re

app = Flask(__name__)
CORS(app)

reader_ar = easyocr.Reader(["ar", "en"], gpu=False)
reader_en = easyocr.Reader(["en"], gpu=False)


def convert_arabic_digits_to_english(text):
    arabic_digits = "٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹"
    english_digits = "01234567890123456789"
    table = str.maketrans(arabic_digits, english_digits)
    return text.translate(table)


def extract_egyptian_id(img):
    h, w, _ = img.shape

    x1 = int(w * 0.40)
    x2 = int(w * 0.99)
    y1 = int(h * 0.65)
    y2 = int(h * 0.99)

    crop = img[y1:y2, x1:x2]

    allowlist = "٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹0123456789"

    results = reader_ar.readtext(
        crop,
        detail=1,
        paragraph=False,
        allowlist=allowlist,
        decoder="beamsearch",
        width_ths=2.0,
        mag_ratio=2.0,
        contrast_ths=0.05,
        adjust_contrast=0.7,
        text_threshold=0.3,
        low_text=0.2
    )

    all_text = ""

    for bbox, text, score in results:
        if score >= 0.20:
            all_text += text

    all_text = convert_arabic_digits_to_english(all_text)
    digits_only = re.sub(r"\D", "", all_text)

    match = re.search(r"[23]\d{13}", digits_only)

    if match:
        return match.group(0), all_text

    return None, all_text


def extract_passport_id(img):
    results = reader_en.readtext(img, detail=1, paragraph=False)

    blacklist = {
        "PASSPORT", "REPUBLIC", "EGYPT", "ARAB", "NAME",
        "SURNAME", "NATIONALITY", "DATE", "BIRTH",
        "EXPIRY", "ISSUE", "SEX", "TYPE", "CODE",
        "AUTHORITY", "PLACE", "SIGNATURE", "COUNTRY"
    }

    candidates = []

    for bbox, text, score in results:
        if score < 0.30:
            continue

        upper = text.upper().strip()

        if any(word in upper for word in blacklist):
            continue

        clean = re.sub(r"[^A-Z0-9]", "", upper)

        if 6 <= len(clean) <= 12 and re.search(r"\d", clean):
            candidates.append(clean)

    mixed = [
        c for c in candidates
        if re.search(r"[A-Z]", c) and re.search(r"\d", c)
    ]

    if mixed:
        return mixed[0], candidates

    if candidates:
        return candidates[0], candidates

    return None, candidates


@app.route("/ocr/health", methods=["GET"])
def health():
    return jsonify({
        "status": "ok",
        "service": "ocr"
    })


@app.route("/ocr/extract-id", methods=["POST"])
def extract_id():
    person_type = request.form.get("person_type", "egyptian")

    if "image" not in request.files:
        return jsonify({
            "success": False,
            "message": "No image uploaded"
        }), 400

    file = request.files["image"]

    file_bytes = np.frombuffer(file.read(), np.uint8)
    img = cv2.imdecode(file_bytes, cv2.IMREAD_COLOR)

    if img is None:
        return jsonify({
            "success": False,
            "message": "Invalid image"
        }), 400

    if person_type == "egyptian":
        national_id, raw_text = extract_egyptian_id(img)

        if national_id:
            return jsonify({
                "success": True,
                "person_type": "egyptian",
                "id_type": "national_id",
                "id_number": national_id,
                "raw_text": raw_text
            })

        return jsonify({
            "success": False,
            "person_type": "egyptian",
            "message": "Could not detect Egyptian National ID. Please type it manually.",
            "raw_text": raw_text
        })

    if person_type == "visitor":
        passport_id, candidates = extract_passport_id(img)

        if passport_id:
            return jsonify({
                "success": True,
                "person_type": "visitor",
                "id_type": "passport_id",
                "id_number": passport_id,
                "candidates": candidates
            })

        return jsonify({
            "success": False,
            "person_type": "visitor",
            "message": "Could not detect Passport ID. Please type it manually.",
            "candidates": candidates
        })

    return jsonify({
        "success": False,
        "message": "Invalid person_type"
    }), 400


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5050)