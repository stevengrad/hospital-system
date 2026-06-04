from __future__ import annotations

import os
import re
import uuid
from datetime import datetime
from pathlib import Path
from typing import Any

from fastapi import UploadFile
from sqlalchemy import text

from app.db import engine
from app.services.prescription_ocr import extract_prescription_text_from_bytes

BASE_UPLOAD_DIR = Path(os.getenv("PRESCRIPTION_UPLOAD_DIR", "../uploads/prescriptions")).resolve()
ALLOWED_EXTENSIONS = {".pdf", ".png", ".jpg", ".jpeg", ".webp"}
MAX_FILE_SIZE_MB = int(os.getenv("PRESCRIPTION_MAX_MB", "15"))


def _safe_name(name: str) -> str:
    stem = Path(name or "file").stem
    stem = re.sub(r"[^a-zA-Z0-9_\-]+", "_", stem).strip("_")[:80]
    return stem or "file"


def ensure_prescription_upload_table() -> None:
    sql = """
    CREATE TABLE IF NOT EXISTS prescription_uploads (
        UploadID INT AUTO_INCREMENT PRIMARY KEY,
        PatientID INT NULL,
        Username VARCHAR(150) NULL,
        OriginalFileName VARCHAR(255) NOT NULL,
        StoredFileName VARCHAR(255) NOT NULL,
        FilePath VARCHAR(500) NOT NULL,
        MimeType VARCHAR(120) NULL,
        FileSizeBytes BIGINT NULL,
        Source VARCHAR(50) NOT NULL DEFAULT 'chatbot',
        ExtractedText MEDIUMTEXT NULL,
        CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_patient_uploads (PatientID),
        INDEX idx_username_uploads (Username)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    """
    with engine.begin() as conn:
        conn.execute(text(sql))


async def save_prescription_upload(
    upload: UploadFile,
    *,
    patient_id: int | None = None,
    username: str | None = None,
    extracted_text: str | None = None,
) -> dict[str, Any]:
    if not upload or not upload.filename:
        raise ValueError("No attachment file was received.")

    ext = Path(upload.filename).suffix.lower()
    if ext not in ALLOWED_EXTENSIONS:
        raise ValueError("Only PDF or image prescription files are allowed: PDF, PNG, JPG, JPEG, WEBP.")

    raw = await upload.read()
    max_bytes = MAX_FILE_SIZE_MB * 1024 * 1024
    if len(raw) > max_bytes:
        raise ValueError(f"Attachment is too large. Maximum allowed size is {MAX_FILE_SIZE_MB} MB.")

    if extracted_text is None:
        extracted_text = extract_prescription_text_from_bytes(raw, upload.filename) or None

    ensure_prescription_upload_table()
    BASE_UPLOAD_DIR.mkdir(parents=True, exist_ok=True)

    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    stored_name = f"prescription_{patient_id or 'unknown'}_{stamp}_{uuid.uuid4().hex[:10]}_{_safe_name(upload.filename)}{ext}"
    path = BASE_UPLOAD_DIR / stored_name
    path.write_bytes(raw)

    rel_path = os.path.relpath(path, Path.cwd())
    sql = """
    INSERT INTO prescription_uploads
        (PatientID, Username, OriginalFileName, StoredFileName, FilePath, MimeType, FileSizeBytes, Source, ExtractedText)
    VALUES
        (:patient_id, :username, :original, :stored, :file_path, :mime, :size_bytes, 'chatbot', :extracted_text)
    """
    with engine.begin() as conn:
        result = conn.execute(
            text(sql),
            {
                "patient_id": patient_id,
                "username": username,
                "original": upload.filename,
                "stored": stored_name,
                "file_path": rel_path,
                "mime": upload.content_type,
                "size_bytes": len(raw),
                "extracted_text": extracted_text,
            },
        )
        upload_id = int(result.lastrowid or 0)

    return {
        "upload_id": upload_id,
        "patient_id": patient_id,
        "username": username,
        "original_file_name": upload.filename,
        "stored_file_name": stored_name,
        "file_path": rel_path,
        "mime_type": upload.content_type,
        "file_size_bytes": len(raw),
        "extracted_text": extracted_text,
    }
