import cv2
import re
import easyocr

reader = easyocr.Reader(["ar", "en"], gpu=False)

image_path = "test_id_me.jpg"

img = cv2.imread(image_path)

if img is None:
    raise FileNotFoundError("Image not found")

h, w, _ = img.shape

# Best crop
x1 = int(w * 0.40)
x2 = int(w * 0.99)
y1 = int(h * 0.65)
y2 = int(h * 0.99)

crop = img[y1:y2, x1:x2]
cv2.imwrite("national_id_crop.jpg", crop)

allowlist = "٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹0123456789"

results = reader.readtext(
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

def convert_arabic_digits_to_english(text):
    arabic_digits = "٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹"
    english_digits = "01234567890123456789"
    table = str.maketrans(arabic_digits, english_digits)
    return text.translate(table)

all_text = ""

for bbox, text, score in results:
    print(f"{text} | score: {score:.2f}")
    all_text += text

all_text = convert_arabic_digits_to_english(all_text)
digits_only = re.sub(r"\D", "", all_text)

match = re.search(r"[23]\d{13}", digits_only)

if match:
    print("Egyptian National ID:", match.group(0))
else:
    print("Egyptian National ID not found")