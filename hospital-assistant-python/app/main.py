from datetime import datetime
from typing import Optional

from fastapi import FastAPI, HTTPException, Query, Request, UploadFile
from fastapi.responses import HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.templating import Jinja2Templates
from pydantic import BaseModel

from app.schemas import AvailabilityResponse, Slot, BookRequest, BookResponse
from app.schemas_chat import ChatRequest, ChatResponse

from app.tools.availability import get_available_slots
from app.tools.booking import book_appointment
from app.tools.chat_router import route_chat
from app.tools.chat_free import handle_chat, maybe_answer_patient_data
from app.tools.health import db_ping

from app.ai.vector_store import load_knowledge_base
from app.ai.rag_chat import rag_answer
from app.ai.text_normalizer import normalize_text, normalize_for_chat
from app.ai.conversation_memory import (
    enrich_question_with_memory,
    remember_chat_turn,
    reset_conversation_memory,
    get_memory_summary,
    is_memory_reset_request,
)
from app.ai.intent import detect_intent
from app.ai.med_normalizer import normalize_medications_from_db
from app.services.file_storage import save_prescription_upload, ensure_prescription_upload_table
from app.services.speech_to_text import transcribe_upload
from app.services.feedback_storage import ensure_chatbot_feedback_table, save_chatbot_feedback
from app.services.prescription_summary import build_prescription_summary


app = FastAPI(title="Hospital Availability & Booking (MVP)")


@app.on_event("startup")
def startup_event():
    load_knowledge_base()
    try:
        ensure_prescription_upload_table()
    except Exception:
        # The chatbot should still start even if DB permissions do not allow DDL.
        pass
    try:
        ensure_chatbot_feedback_table()
    except Exception:
        # Feedback has a JSONL fallback if DB permissions do not allow DDL.
        pass


# ---------- UI ----------
templates = Jinja2Templates(directory="app/templates")
app.mount("/static", StaticFiles(directory="app/static"), name="static")


@app.get("/", response_class=HTMLResponse)
def home(request: Request):
    return templates.TemplateResponse("index.html", {"request": request})


# ---------- Availability ----------
@app.get("/availability", response_model=AvailabilityResponse)
def availability(
    doctor_id: int = Query(...),
    branch_id: int = Query(...),
    from_dt: datetime = Query(...),
    to_dt: datetime = Query(...),
):
    slots = get_available_slots(
        doctor_id=doctor_id,
        branch_id=branch_id,
        from_dt=from_dt,
        to_dt=to_dt,
    )
    return AvailabilityResponse(slots=[Slot(**s) for s in slots])


# ---------- Booking ----------
@app.post("/book", response_model=BookResponse)
def book(req: BookRequest):
    result = book_appointment(
        patient_id=req.patient_id,
        doctor_id=req.doctor_id,
        branch_id=req.branch_id,
        appt_dt=req.start,
    )
    if not result["booked"]:
        raise HTTPException(status_code=409, detail=result["message"])
    return BookResponse(booked=True, message="Appointment booked successfully.")


# ---------- Chat Free ----------
class FreeChatRequest(BaseModel):
    text: str
    username: Optional[str] = None
    display_name: Optional[str] = None
    patient_id: Optional[int] = None
    chat_id: Optional[str] = "default"


def _normalize_chat_result(result):
    if result is None:
        return None
    if isinstance(result, dict):
        if "reply" not in result and "answer" in result:
            result["reply"] = result["answer"]
        if "answer" not in result and "reply" in result:
            result["answer"] = result["reply"]
    return result


def _effective_chat_id(req: FreeChatRequest) -> str:
    """Use a stable conversation id so memory works across follow-up messages.

    If the frontend sends chat_id, we use it. Otherwise we fall back to username
    or patient_id before using the shared local "default" id.
    """
    if req.chat_id and req.chat_id != "default":
        return str(req.chat_id)
    if req.username:
        return f"user:{req.username}"
    if req.patient_id is not None:
        return f"patient:{req.patient_id}"
    return req.chat_id or "default"


def _run_chat_logic(req: FreeChatRequest):
    """
    Run chatbot logic in a safe order.

    IMPORTANT ROOT FIX:
    app.ai.nlp.normalize_text() removes digits and converts some franco digits.
    Booking choices like "1", "فرع 1", "احجز 2", and PatientID values must NOT
    be sent to the booking state machine after that normalization.

    Therefore handle_chat() receives the RAW user text first. Only the RAG/general
    answer path uses normalized/corrected text.
    """
    original_text = req.text or ""
    effective_chat_id = _effective_chat_id(req)

    if is_memory_reset_request(original_text):
        reset_conversation_memory(effective_chat_id)

    # If the user asks a contextual follow-up like "بكام؟" or "what about it?",
    # enrich it with the last remembered topic. Numeric booking commands stay unchanged.
    raw_text = enrich_question_with_memory(original_text, effective_chat_id)

    # 1) Stateful chatbot flow FIRST using RAW text.
    # This preserves numbers for branch choice, slot choice, and PatientID.
    stateful_answer = handle_chat(
        raw_text,
        chat_id=effective_chat_id,
        patient_id=req.patient_id,
    )
    if stateful_answer and stateful_answer.get("intent") not in ["unknown", "empty", "help"]:
        return _normalize_chat_result(stateful_answer)

    # 2) Patient data questions also use RAW text so words/numbers are not damaged.
    patient_answer = maybe_answer_patient_data(
        raw_text,
        req.username,
        req.display_name,
    )
    if patient_answer:
        return _normalize_chat_result(patient_answer)

    # 3) Legacy router gets RAW text as well. It may need numeric PatientID.
    payload = {
        "text": raw_text,
        "username": req.username,
        "display_name": req.display_name,
        "patient_id": req.patient_id,
        "chat_id": effective_chat_id,
    }
    result = route_chat(payload)
    if result and result.get("intent") not in ["unknown", "need_patient_id", "patient_id_set"]:
        return _normalize_chat_result(result)

    # 4) Normalize only for medication-name correction and RAG fallback.
    try:
        normalized_info = normalize_for_chat(raw_text)
        normalized = normalized_info.search_text or normalized_info.chat_text
    except Exception:
        normalized = raw_text

    try:
        corrected = normalize_medications_from_db(normalized)
    except Exception:
        corrected = normalized

    # 5) If the stateful flow returned help, use it before RAG.
    if stateful_answer and stateful_answer.get("intent") == "help":
        return _normalize_chat_result(stateful_answer)

    # 6) RAG fallback for general hospital information.
    ai_answer = rag_answer(corrected or raw_text)
    if ai_answer:
        return {
            "intent": "ai_rag",
            "reply": ai_answer,
            "answer": ai_answer,
        }

    return _normalize_chat_result(stateful_answer)


async def _free_chat_request_from_http(request: Request) -> tuple[FreeChatRequest, UploadFile | None, UploadFile | None, dict]:
    content_type = request.headers.get("content-type", "").lower()
    meta = {"transcription": None, "attachment": None}

    if "multipart/form-data" in content_type or "application/x-www-form-urlencoded" in content_type:
        form = await request.form()
        audio_file = form.get("audio_file") or form.get("voice") or form.get("audio")
        attachment_file = form.get("attachment_file") or form.get("attachment") or form.get("prescription")

        text_value = str(form.get("text") or form.get("message") or "").strip()
        if hasattr(audio_file, "filename") and hasattr(audio_file, "read"):
            try:
                transcription = await transcribe_upload(audio_file)
                meta["transcription"] = transcription
                transcribed_text = (transcription or {}).get("text", "") if isinstance(transcription, dict) else ""
                if not text_value:
                    text_value = transcribed_text
                else:
                    text_value = f"{text_value} {transcribed_text}".strip()
            except Exception as exc:
                # Do not break the whole chatbot when ffmpeg or the STT model has an issue.
                meta["transcription_error"] = str(exc)

        pid_raw = form.get("patient_id")
        try:
            patient_id = int(pid_raw) if pid_raw not in (None, "") else None
        except Exception:
            patient_id = None

        req = FreeChatRequest(
            text=text_value,
            username=form.get("username") or None,
            display_name=form.get("display_name") or None,
            patient_id=patient_id,
            chat_id=form.get("chat_id") or "default",
        )
        return req, attachment_file if (hasattr(attachment_file, "filename") and hasattr(attachment_file, "read")) else None, audio_file if (hasattr(audio_file, "filename") and hasattr(audio_file, "read")) else None, meta

    body = await request.json()
    return FreeChatRequest(**body), None, None, meta


@app.post("/chat/free")
async def chat_free(request: Request):
    try:
        req, attachment_file, audio_file, meta = await _free_chat_request_from_http(request)
    except Exception as exc:
        raise HTTPException(status_code=400, detail=f"Invalid chat request: {exc}")

    attachment_saved = None
    if attachment_file is not None:
        try:
            attachment_saved = await save_prescription_upload(
                attachment_file,
                patient_id=req.patient_id,
                username=req.username,
                # Let OCR read the prescription file itself. Do not store the chat message as ExtractedText.
                extracted_text=None,
            )
            meta["attachment"] = attachment_saved
        except ValueError as exc:
            raise HTTPException(status_code=400, detail=str(exc))

    if not (req.text or "").strip() and attachment_saved:
        lang = "ar"
        summary = build_prescription_summary(attachment_saved.get("extracted_text"), lang=lang)
        reply = summary.get("reply") or "تم رفع وحفظ الروشتة بنجاح داخل قاعدة البيانات ✅"
        return {
            "intent": "prescription_upload_summary",
            "reply": reply,
            "answer": reply,
            "data": {
                "attachment": attachment_saved,
                "transcription": meta.get("transcription"),
                "prescription_summary": summary,
            },
        }

    if not (req.text or "").strip():
        if audio_file is not None:
            err = meta.get("transcription_error")
            if err:
                reply = "الصوت اتسجل، لكن تحويله لكلام فشل. التفاصيل التقنية: " + str(err)
            else:
                reply = "الصوت اتسجل، لكن النظام مقدرش يحوله لكلام واضح. جربي تسجلي بصوت أوضح أو اكتبي الرسالة بدل الصوت."
            return {"intent": "voice_transcription_failed", "reply": reply, "answer": reply, "data": meta}
        raise HTTPException(status_code=400, detail="Message text or voice transcription is required.")

    result = _run_chat_logic(req) or {"intent": "empty", "reply": "اكتب رسالتك.", "data": None}

    if attachment_saved:
        lang = "ar" if any("\u0600" <= ch <= "\u06FF" for ch in (req.text or "")) else "en"
        summary = build_prescription_summary(attachment_saved.get("extracted_text"), lang=lang)
        extra = "\n\n" + (summary.get("reply") or "تم حفظ ملف الروشتة المرفق في قاعدة البيانات ✅")
        result["reply"] = str(result.get("reply", "")) + extra
        result["answer"] = result["reply"]
        result.setdefault("data", {})
        if isinstance(result["data"], dict):
            result["data"]["attachment"] = attachment_saved
            result["data"]["prescription_summary"] = summary

    if meta.get("transcription"):
        result.setdefault("data", {})
        if isinstance(result["data"], dict):
            result["data"]["transcription"] = meta["transcription"]
            result["data"]["transcribed_text"] = meta["transcription"].get("text")

    result = _normalize_chat_result(result)
    try:
        remember_chat_turn(_effective_chat_id(req), req.text or "", result)
    except Exception:
        # Memory must never break the chatbot response.
        pass

    return result


# ---------- Conversation Memory Debug / Reset ----------
class ChatMemoryResetRequest(BaseModel):
    chat_id: Optional[str] = None
    username: Optional[str] = None
    patient_id: Optional[int] = None


@app.get("/chat/memory")
def chat_memory(chat_id: Optional[str] = Query("default")):
    return get_memory_summary(chat_id or "default")


@app.post("/chat/memory/reset")
def chat_memory_reset(req: ChatMemoryResetRequest):
    cid = req.chat_id or (f"user:{req.username}" if req.username else None) or (f"patient:{req.patient_id}" if req.patient_id is not None else None) or "default"
    reset_conversation_memory(cid)
    return {"ok": True, "chat_id": cid, "message": "Conversation memory cleared."}


# ---------- Chatbot Feedback ----------
class ChatFeedbackRequest(BaseModel):
    chat_id: Optional[str] = None
    user_message: Optional[str] = None
    bot_reply: Optional[str] = None
    rating: str
    comment: Optional[str] = None
    intent: Optional[str] = None
    source: Optional[str] = "chatbot_ui"


@app.post("/chat/feedback")
async def chat_feedback(req: ChatFeedbackRequest, request: Request):
    try:
        result = save_chatbot_feedback(
            chat_id=req.chat_id,
            user_message=req.user_message,
            bot_reply=req.bot_reply,
            rating=req.rating,
            comment=req.comment,
            intent=req.intent,
            source=req.source or "chatbot_ui",
            user_agent=request.headers.get("user-agent"),
        )
        return {"ok": True, **result}
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc))
    except Exception as exc:
        raise HTTPException(status_code=500, detail=f"Feedback save failed: {exc}")


# ---------- Chat Router ----------
@app.post("/chat", response_model=ChatResponse)
def chat(req: ChatRequest):
    result = route_chat(req.model_dump())

    if result and result.get("intent") != "unknown":
        return ChatResponse(**result)

    ai_answer = rag_answer(req.text)
    if ai_answer:
        return ChatResponse(
            intent="ai_rag",
            reply=ai_answer,
        )

    return ChatResponse(**result)


# ---------- Health ----------
@app.get("/health/db")
def health_db():
    return {"ok": True, "db": db_ping()}


@app.get("/health/ai")
def health_ai():
    return {
        "ai_loaded": True,
        "rag_available": True,
    }
