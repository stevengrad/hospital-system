from __future__ import annotations

import os
import re
from io import BytesIO
from pathlib import Path
from typing import Iterable


_OCR_READER = None


def _clean_text(lines: Iterable[str]) -> str:
    joined = "\n".join(str(line).strip() for line in lines if str(line).strip())
    joined = re.sub(r"[ \t]+", " ", joined)
    joined = re.sub(r"\n{3,}", "\n\n", joined)
    return joined.strip()


def _get_reader():
    """Lazy-load EasyOCR only when a prescription is uploaded."""
    global _OCR_READER
    if _OCR_READER is None:
        import easyocr

        gpu_value = os.getenv("PRESCRIPTION_OCR_GPU", "false").lower() in {"1", "true", "yes"}
        _OCR_READER = easyocr.Reader(["ar", "en"], gpu=gpu_value)
    return _OCR_READER


def _ocr_image_bytes(image_bytes: bytes) -> str:
    from PIL import Image
    import numpy as np

    reader = _get_reader()
    image = Image.open(BytesIO(image_bytes)).convert("RGB")
    result = reader.readtext(np.array(image), detail=0, paragraph=True)
    return _clean_text(result)


def _pdf_pages_to_images(pdf_bytes: bytes):
    import fitz  # PyMuPDF

    max_pages = int(os.getenv("PRESCRIPTION_OCR_MAX_PDF_PAGES", "3"))
    zoom = float(os.getenv("PRESCRIPTION_OCR_PDF_ZOOM", "2"))

    doc = fitz.open(stream=pdf_bytes, filetype="pdf")
    matrix = fitz.Matrix(zoom, zoom)
    for page_index in range(min(len(doc), max_pages)):
        page = doc.load_page(page_index)
        pix = page.get_pixmap(matrix=matrix, alpha=False)
        yield pix.tobytes("png")


def extract_prescription_text_from_bytes(file_bytes: bytes, filename: str | None = None) -> str:
    """
    Extract Arabic/English text from uploaded prescription images or PDFs.
    Returns an empty string if OCR fails so uploading still works.
    """
    if not file_bytes:
        return ""

    ext = Path(filename or "").suffix.lower()

    try:
        if ext == ".pdf":
            page_texts = []
            for image_bytes in _pdf_pages_to_images(file_bytes):
                text = _ocr_image_bytes(image_bytes)
                if text:
                    page_texts.append(text)
            return _clean_text(page_texts)

        if ext in {".png", ".jpg", ".jpeg", ".webp"}:
            return _ocr_image_bytes(file_bytes)

        return ""
    except Exception:
        return ""
