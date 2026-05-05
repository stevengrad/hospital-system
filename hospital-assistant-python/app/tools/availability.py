from __future__ import annotations

from datetime import datetime, date, time, timedelta
from sqlalchemy import text
from app.db import SessionLocal
from app.config import get_settings

settings = get_settings()

PY_WEEKDAY_TO_ENUM = {
    0: "Monday",
    1: "Tuesday",
    2: "Wednesday",
    3: "Thursday",
    4: "Friday",
    5: "Saturday",
    6: "Sunday",
}

def _to_time(v) -> time:
    """
    MySQL TIME sometimes comes back as:
    - datetime.time (ideal)
    - datetime.timedelta (common with some drivers)
    - 'HH:MM:SS' string
    Normalize all to datetime.time.
    """
    if v is None:
        raise ValueError("StartTime/EndTime is NULL")

    if isinstance(v, time):
        return v

    if isinstance(v, timedelta):
        total_seconds = int(v.total_seconds())
        if total_seconds < 0:
            total_seconds = total_seconds % (24 * 3600)
        hours = (total_seconds // 3600) % 24
        minutes = (total_seconds % 3600) // 60
        seconds = total_seconds % 60
        return time(hour=hours, minute=minutes, second=seconds)

    if isinstance(v, str):
        # expects 'HH:MM:SS' or 'HH:MM'
        parts = v.strip().split(":")
        if len(parts) < 2:
            raise ValueError(f"Invalid time string: {v}")
        h = int(parts[0])
        m = int(parts[1])
        s = int(parts[2]) if len(parts) >= 3 else 0
        return time(hour=h, minute=m, second=s)

    raise TypeError(f"Unsupported time value type: {type(v)} value={v!r}")

def _daterange(d1: date, d2: date):
    cur = d1
    while cur < d2:
        yield cur
        cur += timedelta(days=1)

def _generate_slots_for_day(day: date, start_t: time, end_t: time, slot_minutes: int):
    start_dt = datetime.combine(day, start_t)
    end_dt = datetime.combine(day, end_t)
    step = timedelta(minutes=slot_minutes)

    cur = start_dt
    while cur + step <= end_dt:
        yield (cur, cur + step)
        cur += step

def get_available_slots(doctor_id: int, branch_id: int, from_dt: datetime, to_dt: datetime):
    if to_dt <= from_dt:
        return []

    from_date = from_dt.date()
    to_date_exclusive = to_dt.date()
    if to_dt.time() != time(0, 0, 0):
        to_date_exclusive = to_date_exclusive + timedelta(days=1)

    schedule_sql = """
    SELECT DayOfWeek, StartTime, EndTime
    FROM doctor_branch_schedule
    WHERE DoctorID = :doctor_id AND BranchID = :branch_id
    """

    booked_sql = """
    SELECT AppointmentDateTime
    FROM appointments
    WHERE DoctorID = :doctor_id
      AND BranchID = :branch_id
      AND AppointmentDateTime >= :from_dt
      AND AppointmentDateTime <  :to_dt
      AND (Status IN ('booked','confirmed') OR Status IS NULL)
    """

    with SessionLocal() as db:
        schedule_rows = db.execute(
            text(schedule_sql),
            {"doctor_id": doctor_id, "branch_id": branch_id},
        ).mappings().all()

        booked_rows = db.execute(
            text(booked_sql),
            {"doctor_id": doctor_id, "branch_id": branch_id, "from_dt": from_dt, "to_dt": to_dt},
        ).mappings().all()

    booked_set = {r["AppointmentDateTime"] for r in booked_rows}

    schedule_by_day: dict[str, list[tuple[time, time]]] = {}
    for r in schedule_rows:
        day = r["DayOfWeek"]
        start_t = _to_time(r["StartTime"])
        end_t = _to_time(r["EndTime"])
        schedule_by_day.setdefault(day, []).append((start_t, end_t))

    slots = []
    for day in _daterange(from_date, to_date_exclusive):
        day_name = PY_WEEKDAY_TO_ENUM[day.weekday()]
        if day_name not in schedule_by_day:
            continue

        for (start_t, end_t) in schedule_by_day[day_name]:
            for (s, e) in _generate_slots_for_day(day, start_t, end_t, settings.slot_minutes):
                if s < from_dt or s >= to_dt:
                    continue
                if s in booked_set:
                    continue
                slots.append({"doctor_id": doctor_id, "branch_id": branch_id, "start": s, "end": e})

    slots.sort(key=lambda x: x["start"])
    return slots