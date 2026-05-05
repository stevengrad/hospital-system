from __future__ import annotations

from dataclasses import dataclass, field
from datetime import datetime
from typing import Any


@dataclass
class ChatState:
    doctor_id: int | None = None
    branch_id: int | None = None
    slots: list[dict[str, Any]] = field(default_factory=list)   # each has start/end/doctor_id/branch_id
    patient_id: int | None = None
    pending_step: str | None = None
    pending_booking_slot: dict[str, Any] | None = None
    pending_specialty_id: int | None = None
    pending_branches: list[dict[str, Any]] | None = None
    updated_at: datetime = field(default_factory=datetime.utcnow)


# key: chat_id
CHAT_STATE: dict[str, ChatState] = {}