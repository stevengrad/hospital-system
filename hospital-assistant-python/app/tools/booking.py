from datetime import datetime
from sqlalchemy import text
from app.db import SessionLocal

def book_appointment(patient_id: int, doctor_id: int, branch_id: int, appt_dt: datetime):
    # حماية بسيطة ضد double-booking (لنفس doctor/branch/datetime)
    check_sql = """
    SELECT AppointmentID
    FROM appointments
    WHERE DoctorID = :doctor_id
      AND BranchID = :branch_id
      AND AppointmentDateTime = :appt_dt
      AND (Status IN ('booked','confirmed') OR Status IS NULL)
    LIMIT 1
    """

    insert_sql = """
    INSERT INTO appointments (PatientID, DoctorID, BranchID, AppointmentDateTime, Status)
    VALUES (:patient_id, :doctor_id, :branch_id, :appt_dt, 'booked')
    """

    with SessionLocal() as db:
        existing = db.execute(
            text(check_sql),
            {"doctor_id": doctor_id, "branch_id": branch_id, "appt_dt": appt_dt},
        ).mappings().first()

        if existing:
            return {"booked": False, "message": "المعاد ده اتحجز بالفعل. اختاري رقم تاني."}

        db.execute(
            text(insert_sql),
            {"patient_id": patient_id, "doctor_id": doctor_id, "branch_id": branch_id, "appt_dt": appt_dt},
        )
        db.commit()

    return {"booked": True, "message": "تم الحجز بنجاح."}
# Backward-compatible alias (old code still imports book_slot)
def book_slot(patient_id: int, doctor_id: int, branch_id: int, appt_dt: datetime):
    return book_appointment(
        patient_id=patient_id,
        doctor_id=doctor_id,
        branch_id=branch_id,
        appt_dt=appt_dt,
    )