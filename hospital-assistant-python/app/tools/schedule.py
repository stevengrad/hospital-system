from sqlalchemy import text
from app.db import SessionLocal

def get_weekly_schedule(doctor_id: int, branch_id: int):
    sql = """
    SELECT DayOfWeek, StartTime, EndTime
    FROM doctor_branch_schedule
    WHERE DoctorID = :doctor_id AND BranchID = :branch_id
    ORDER BY FIELD(DayOfWeek,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'),
             StartTime
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"doctor_id": doctor_id, "branch_id": branch_id}).mappings().all()
    return [dict(r) for r in rows]