from datetime import datetime
from typing import Optional

from fastapi import FastAPI, HTTPException, Query, Request
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

app = FastAPI(title="Hospital Availability & Booking (MVP)")

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


# ---------- Chat (Free / rule-based) ----------
# ---------- Chat (Free / rule-based) ----------
class FreeChatRequest(BaseModel):
    text: str
    username: Optional[str] = None
    display_name: Optional[str] = None
    patient_id: Optional[int] = None
    chat_id: Optional[str] = "default"


@app.post("/chat/free")
def chat_free(req: FreeChatRequest):
    """
    Make /chat/free use the same router logic as /chat.
    This fixes the issue where the UI calls /chat/free and never reaches route_chat().
    """
    payload = {
        "text": req.text,
        "username": req.username,
        "display_name": req.display_name,
        "patient_id": req.patient_id,
        "chat_id": req.chat_id or "default",
    }

    # 1) First: try the main router (supports medication risks, history, booking, etc.)
    result = route_chat(payload)
    if result and result.get("intent") != "unknown":
        return result

    # 2) Fallback: original free chat behavior (menu / generic replies)
    answer = maybe_answer_patient_data(req.text, req.username, req.display_name)
    if answer:
        return answer

    return handle_chat(
        req.text,
        chat_id=req.chat_id or "default",
        patient_id=req.patient_id,
    )


# ---------- Chat (router) ----------
@app.post("/chat", response_model=ChatResponse)
def chat(req: ChatRequest):
    result = route_chat(req.model_dump())
    return ChatResponse(**result)


# ---------- Health ----------
@app.get("/health/db")
def health_db():
    return {"ok": True, "db": db_ping()}