from __future__ import annotations

from datetime import datetime, timedelta
from sqlalchemy import text
from app.db import SessionLocal

# NOTE:
# - doctors.EmployeeID is the doctor id used in schedules (DoctorID)
# - employees.EmployeeID stores FirstName/LastName/Role
# - doctor_branch_schedule has: DoctorID, BranchID, DayOfWeek, StartTime, EndTime
#
# This module focuses on:
#   - Specialty info
#   - Which branches have doctors for a specialty
#   - Doctor list per specialty per branch
#   - Helper to get doctor names


def get_specialty_by_id(specialty_id: int) -> dict | None:
    sql = """
    SELECT SpecialtyID, Name, Description
    FROM specialties
    WHERE SpecialtyID = :sid
    LIMIT 1
    """
    with SessionLocal() as db:
        row = db.execute(text(sql), {"sid": specialty_id}).mappings().first()
    return dict(row) if row else None


def list_branches_for_specialty(specialty_id: int) -> list[dict]:
    """
    Returns branches that have at least one doctor of this specialty
    AND have at least one schedule row in doctor_branch_schedule.
    """
    sql = """
    SELECT DISTINCT b.BranchID, b.Name
    FROM branches b
    JOIN doctor_branch_schedule s ON s.BranchID = b.BranchID
    JOIN doctors d ON d.EmployeeID = s.DoctorID
    WHERE d.SpecialtyID = :sid
    ORDER BY b.Name
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"sid": specialty_id}).mappings().all()
    return [dict(r) for r in rows]


def list_doctors_for_specialty_in_branch(specialty_id: int, branch_id: int) -> list[dict]:
    sql = """
    SELECT d.EmployeeID AS DoctorID,
           d.SpecialtyID,
           d.ConsultationFee,
           d.LicenseNumber
    FROM doctors d
    JOIN doctor_branch_schedule s ON s.DoctorID = d.EmployeeID
    WHERE d.SpecialtyID = :sid AND s.BranchID = :bid
    GROUP BY d.EmployeeID, d.SpecialtyID, d.ConsultationFee, d.LicenseNumber
    ORDER BY d.EmployeeID
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"sid": specialty_id, "bid": branch_id}).mappings().all()
    return [dict(r) for r in rows]


def get_doctor_names(doctor_ids: list[int]) -> dict[int, str]:
    """
    Returns {EmployeeID: "FirstName LastName"}.
    """
    if not doctor_ids:
        return {}

    params = {f"id{i}": did for i, did in enumerate(doctor_ids)}
    in_clause = ", ".join([f":id{i}" for i in range(len(doctor_ids))])

    sql = f"""
    SELECT EmployeeID, FirstName, LastName
    FROM employees
    WHERE EmployeeID IN ({in_clause})
    """

    with SessionLocal() as db:
        rows = db.execute(text(sql), params).mappings().all()

    out: dict[int, str] = {}
    for r in rows:
        eid = int(r["EmployeeID"])
        out[eid] = f"{r['FirstName']} {r['LastName']}"
    return out


def get_specialty_slots(
    *,
    branch_id: int,
    specialty_id: int,
    get_available_slots_fn,
    days_ahead: int = 14,
    limit_total: int = 20,
) -> list[dict]:
    """
    Collect upcoming available slots for all doctors in a specialty in ONE branch.

    Parameters:
      - branch_id / specialty_id: filter
      - get_available_slots_fn: pass your existing function:
            from app.tools.availability import get_available_slots
        then call:
            get_specialty_slots(branch_id=..., specialty_id=..., get_available_slots_fn=get_available_slots)

      - days_ahead: window size for slots
      - limit_total: limit returned slots across ALL doctors

    Returns list of dict like:
      {
        "doctor_id": int,
        "doctor_name": str,
        "branch_id": int,
        "start": datetime (or whatever your get_available_slots returns),
        ... any extra keys from get_available_slots
      }
    """
    from_dt = datetime.now()
    to_dt = from_dt + timedelta(days=days_ahead)

    doctors = list_doctors_for_specialty_in_branch(specialty_id, branch_id)

    all_slots: list[dict] = []
    for d in doctors:
        did = int(d["DoctorID"])
        slots = get_available_slots_fn(doctor_id=did, branch_id=branch_id, from_dt=from_dt, to_dt=to_dt)
        for s in slots:
            # keep original slot keys + add doctor/branch context
            all_slots.append({"doctor_id": did, "branch_id": branch_id, **s})

    # attach names
    names = get_doctor_names(sorted({int(s["doctor_id"]) for s in all_slots}))
    for s in all_slots:
        s["doctor_name"] = names.get(int(s["doctor_id"]), f"Doctor {s['doctor_id']}")

    # sort by slot start
    def _key(x):
        # support a few likely keys
        return x.get("start") or x.get("AppointmentDateTime") or x.get("datetime") or ""

    all_slots.sort(key=_key)
    return all_slots[:limit_total]