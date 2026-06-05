from flask import Flask, request, jsonify
from flask_cors import CORS
import cv2
import numpy as np
import easyocr
import re
import io
from PIL import Image, ImageOps

try:
    from pillow_heif import register_heif_opener
    register_heif_opener()
except Exception:
    pass
app = Flask(__name__)
CORS(app)

reader_ar = easyocr.Reader(["ar", "en"], gpu=False)
reader_en = easyocr.Reader(["en"], gpu=False)
ALLOWED_IMAGE_EXTENSIONS = {
    "jpg", "jpeg", "png", "webp", "bmp", "tif", "tiff", "gif", "heic", "heif"
}

def convert_arabic_digits_to_english(text):
    arabic_digits = "٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹"
    english_digits = "01234567890123456789"
    table = str.maketrans(arabic_digits, english_digits)
    return text.translate(table)

def get_file_extension(filename):
    if not filename or "." not in filename:
        return ""
    return filename.rsplit(".", 1)[1].lower().strip()


def uploaded_image_to_jpg_cv2(file_storage):
    """
    Accept different image formats, convert them to JPG in memory,
    then return an OpenCV BGR image for the existing OCR pipeline.
    """
    original_filename = file_storage.filename or "uploaded_image"
    ext = get_file_extension(original_filename)

    if ext and ext not in ALLOWED_IMAGE_EXTENSIONS:
        return None, None, f"Unsupported image type: .{ext}"

    file_bytes = file_storage.read()
    if not file_bytes:
        return None, None, "Empty image file"

    try:
        pil_img = Image.open(io.BytesIO(file_bytes))
        pil_img = ImageOps.exif_transpose(pil_img)

        try:
            pil_img.seek(0)
        except Exception:
            pass

        if pil_img.mode in ("RGBA", "LA") or (pil_img.mode == "P" and "transparency" in pil_img.info):
            rgba = pil_img.convert("RGBA")
            background = Image.new("RGBA", rgba.size, (255, 255, 255, 255))
            background.alpha_composite(rgba)
            pil_img = background.convert("RGB")
        else:
            pil_img = pil_img.convert("RGB")

        jpg_buffer = io.BytesIO()
        pil_img.save(jpg_buffer, format="JPEG", quality=95)
        jpg_bytes = jpg_buffer.getvalue()

        jpg_array = np.frombuffer(jpg_bytes, np.uint8)
        img = cv2.imdecode(jpg_array, cv2.IMREAD_COLOR)

        if img is None:
            return None, None, "Image was converted to JPG but OpenCV could not read it"

        return img, jpg_bytes, None

    except Exception as pil_error:
        try:
            file_array = np.frombuffer(file_bytes, np.uint8)
            img = cv2.imdecode(file_array, cv2.IMREAD_COLOR)

            if img is None:
                return None, None, f"Invalid or unsupported image. Details: {pil_error}"

            success, jpg_array = cv2.imencode(".jpg", img, [int(cv2.IMWRITE_JPEG_QUALITY), 95])

            if not success:
                return None, None, "Image was decoded but could not be converted to JPG"

            return img, jpg_array.tobytes(), None

        except Exception as cv_error:
            return None, None, f"Invalid image. Details: {cv_error}"
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
        "service": "ocr",
        "accepted_image_types": sorted(ALLOWED_IMAGE_EXTENSIONS),
        "conversion": "uploaded image is converted to JPG before OCR"
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
    original_filename = file.filename or "uploaded_image"

    img, jpg_bytes, conversion_error = uploaded_image_to_jpg_cv2(file)

    if img is None:
     return jsonify({
        "success": False,
        "message": conversion_error or "Invalid image",
        "original_filename": original_filename
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