from pydantic import BaseModel
from typing import Optional, Any, Dict


class ChatRequest(BaseModel):
    text: str

    # booking/availability context (existing)
    patient_id: Optional[int] = None
    doctor_id: Optional[int] = None
    branch_id: Optional[int] = None
    from_dt: Optional[str] = None  # ISO string
    to_dt: Optional[str] = None    # ISO string
    start: Optional[str] = None    # ISO string for booking start time

    # NEW: website session context
    username: Optional[str] = None
    display_name: Optional[str] = None


class ChatResponse(BaseModel):
    intent: str
    reply: str
    data: Optional[Dict[str, Any]] = None