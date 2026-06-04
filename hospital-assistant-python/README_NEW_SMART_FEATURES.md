# New Smart Chatbot Features

This update adds three safe Gen-AI style features without changing the old database design:

## 1) Symptom triage before booking
When the patient sends symptoms such as back pain, stomach burning, headache, etc., the chatbot now asks a short safety triage question before showing branches and slots.

It checks for red flags such as:
- severe/unbearable pain
- numbness or weakness
- fever
- trauma/fall/injury
- loss of bladder/bowel control
- chest pain or shortness of breath

If red flags are detected, the bot advises urgent medical assessment instead of directly booking.
If the patient replies with a reassuring answer like `لا مفيش` or `no`, the bot continues to branch selection and booking.

## 2) Prescription OCR summary + medication review
When the patient uploads a prescription image/PDF:
- the file is saved in `uploads/prescriptions`
- OCR text is stored in `prescription_uploads.ExtractedText`
- the bot summarizes the OCR text to the user
- the bot tries to recognize medication names from the local `medications` table
- if multiple medications are detected, it runs an initial interaction review using `drug_interactions`

New file:
`hospital-assistant-python/app/services/prescription_summary.py`

## 3) Better drug interaction between two medicines
The interaction feature now handles both Arabic and English responses better.

Examples:
- `ما مخاطر Brufen مع Aspirin؟`
- `Can I take Brufen with Aspirin?`
- `is Warfarin safe with Aspirin?`
- `what is the danger of brufen`

The bot searches the local `medications` and `drug_interactions` tables and returns direct warnings if found. If no direct pair warning is found, it returns recorded warnings for each medication.

## Files changed/added
- `chat_api.php`
- `dashboard.php`
- `chatindex.php`
- `hospital-assistant-python/app/main.py`
- `hospital-assistant-python/app/tools/chat_free.py`
- `hospital-assistant-python/app/tools/drug_interactions.py`
- `hospital-assistant-python/app/tools/medications.py`
- `hospital-assistant-python/app/services/prescription_summary.py`
- existing feedback/OCR/support files from previous update are included to keep the system complete.

## After extracting
Restart FastAPI:

```bash
cd C:\xampp\htdocs\hospital\hospital-assistant-python
python -m uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
```

Then refresh the website page.
