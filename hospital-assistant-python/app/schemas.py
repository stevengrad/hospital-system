from pydantic import BaseModel
from datetime import datetime
from typing import List

class Slot(BaseModel):
    doctor_id: int
    branch_id: int
    start: datetime
    end: datetime

class AvailabilityResponse(BaseModel):
    slots: List[Slot]

class BookRequest(BaseModel):
    patient_id: int
    doctor_id: int
    branch_id: int
    start: datetime  # AppointmentDateTime

class BookResponse(BaseModel):
    booked: bool
    message: str