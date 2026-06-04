# Hospital RAG Chatbot Updates

## Added features

1. **Bilingual Arabic/English chat responses**
   - RAG fallback now detects Arabic vs English and answers with a cleaner language-specific prefix.
   - Existing medication-risk and booking flows were kept.

2. **Voice interaction / Speech-to-Text**
   - `/chat/free` now accepts multipart form uploads with `audio`, `voice`, or `audio_file`.
   - Uses a lazy-loaded pretrained ASR model through Hugging Face Transformers.
   - Default model: `openai/whisper-small`.
   - You can change it in `.env`:
     ```env
     STT_MODEL=openai/whisper-small
     VOICE_MAX_MB=25
     ```

3. **Prescription file attachments**
   - `/chat/free` now accepts prescription uploads using `attachment`, `attachment_file`, or `prescription`.
   - Allowed files: PDF, PNG, JPG, JPEG, WEBP.
   - Files are saved under `uploads/prescriptions`.
   - Metadata is stored in a new MySQL table created automatically when possible:
     `patient_prescription_uploads`.
   - You can change limits/location in `.env`:
     ```env
     PRESCRIPTION_UPLOAD_DIR=../uploads/prescriptions
     PRESCRIPTION_MAX_MB=15
     ```

4. **Offer-based appointment support**
   - The chatbot can understand offer-related messages such as:
     - `عندكم عروض؟`
     - `show offers`
     - `book offer`
   - It checks for any of these DB tables:
     - `offers`
     - `appointment_offers`
     - `service_offers`
     - `clinic_offers`
   - If an offer row contains `DoctorID` and `BranchID`, the bot can show available slots and let the patient book by number.

5. **Frontend support**
   - `dashboard.php` chatbot now includes:
     - prescription upload button
     - voice record button
     - multipart sending to `chat_api.php`
   - `chatindex.php` was upgraded with the same basic upload/voice support.
   - `chat_api.php` now forwards text, files, and audio to FastAPI.

## Install / run

Inside `hospital-assistant-python`:

```bash
pip install -r requirements.txt
python -m uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
```

## Important notes

- First voice request may take longer because the STT model loads lazily.
- If your database user cannot create tables, manually create `patient_prescription_uploads` or give the user CREATE TABLE permission.
- Offer booking needs an offer table containing at least `DoctorID` and `BranchID` to show slots automatically.


## Prescription OCR update

Uploaded prescription PDFs/images are now processed with an Arabic/English OCR model using EasyOCR.

- Files are saved in `uploads/prescriptions`.
- Extracted text is saved in the `ExtractedText` column.
- The upload metadata table is `prescription_uploads`.
- PDF OCR reads the first 3 pages by default. Change this with `PRESCRIPTION_OCR_MAX_PDF_PAGES`.
- OCR runs lazily only when a prescription file is uploaded.

## Final robust fix notes

This package includes the final connection/session fix:

- `chat_api.php` now accepts a stable `chat_id` from the browser and always returns JSON errors instead of breaking the UI.
- `dashboard.php` and `chatindex.php` send the same `chat_id` on every message using `localStorage`.
- The booking flow state is now saved in `.chat_state/` so branch/slot selection does not get lost between messages during local testing.
- Bare numbers like `1` are no longer sent to RAG if the booking context is missing; the bot will ask to restart instead of giving unrelated hospital information.
- Voice transcription errors such as missing FFmpeg now return a friendly chatbot message instead of HTTP 400.
- Prescription upload OCR stores the actual OCR text in `prescription_uploads.ExtractedText`; it no longer stores the chat message as extracted text.

After replacing files, restart FastAPI and refresh the browser page. Type `ابدأ من جديد` once before testing the booking flow again.

## Chatbot feedback

Added patient feedback collection for chatbot answers.

- UI shows 👍 مفيد / 👎 غير مفيد under every bot response.
- Feedback is sent through `chat_api.php` to FastAPI endpoint `/chat/feedback`.
- Feedback is stored in MySQL table `chatbot_feedback`.
- If database write fails, feedback is saved as JSONL fallback in `.chat_feedback/chatbot_feedback.jsonl`.

Optional manual SQL table creation is available in:

```text
hospital-assistant-python/chatbot_feedback_table.sql
```
