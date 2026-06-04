from __future__ import annotations

import os
import tempfile
from pathlib import Path
from typing import Any

from fastapi import UploadFile

_ASR_PIPELINE = None


def _get_asr_pipeline():
    """
    Lazy-load a pretrained Speech-to-Text model only when a voice message is sent.
    Default model is Whisper small; override with STT_MODEL in .env.
    """
    global _ASR_PIPELINE
    if _ASR_PIPELINE is None:
        from transformers import pipeline

        model_name = os.getenv("STT_MODEL", "openai/whisper-small")
        _ASR_PIPELINE = pipeline(
            "automatic-speech-recognition",
            model=model_name,
        )
    return _ASR_PIPELINE


async def transcribe_upload(upload: UploadFile) -> dict[str, Any]:
    if not upload or not upload.filename:
        raise ValueError("No audio file was received.")

    suffix = Path(upload.filename).suffix or ".webm"
    raw = await upload.read()
    max_mb = int(os.getenv("VOICE_MAX_MB", "25"))
    if len(raw) > max_mb * 1024 * 1024:
        raise ValueError(f"Audio file is too large. Maximum allowed size is {max_mb} MB.")

    with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
        tmp.write(raw)
        tmp_path = tmp.name

    try:
        pipe = _get_asr_pipeline()
        result = pipe(tmp_path)
        text = (result.get("text") if isinstance(result, dict) else str(result)).strip()
        return {
            "text": text,
            "file_name": upload.filename,
            "mime_type": upload.content_type,
            "size_bytes": len(raw),
        }
    finally:
        try:
            os.remove(tmp_path)
        except OSError:
            pass
