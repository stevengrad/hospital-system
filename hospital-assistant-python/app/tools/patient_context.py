from __future__ import annotations

from dataclasses import dataclass
from typing import Optional

from sqlalchemy import text

from app.db import SessionLocal


@dataclass(frozen=True)
class PatientContext:
    username: str
    display_name: Optional[str]
    national_id: Optional[str]
    patient_id: Optional[int]


def resolve_patient_context(username: str) -> Optional[PatientContext]:
    """
    Resolve a unified patient context from:
      registration/login -> national_id + names
      patients -> PatientID
    """
    if not username:
        return None

    sql_user = """
    SELECT
        r.username,
        r.first_name,
        r.last_name,
        COALESCE(r.national_id, l.national_id) AS national_id
    FROM registration r
    LEFT JOIN login l ON l.username = r.username
    WHERE r.username = :u
    LIMIT 1
    """

    sql_login_only = """
    SELECT
        l.username,
        NULL AS first_name,
        NULL AS last_name,
        l.national_id AS national_id
    FROM login l
    WHERE l.username = :u
    LIMIT 1
    """

    with SessionLocal() as db:
        row = db.execute(text(sql_user), {"u": username}).mappings().first()
        if not row:
            row = db.execute(text(sql_login_only), {"u": username}).mappings().first()
            if not row:
                return PatientContext(
                    username=username,
                    display_name=None,
                    national_id=None,
                    patient_id=None,
                )

    first_name = (row.get("first_name") or "").strip()
    last_name = (row.get("last_name") or "").strip()
    display_name = f"{first_name} {last_name}".strip() or None
    national_id = (row.get("national_id") or None)

    patient_id = None
    if national_id:
        sql_patient = """
        SELECT PatientID
        FROM patients
        WHERE NationalID = :nid
        LIMIT 1
        """
        with SessionLocal() as db:
            prow = db.execute(text(sql_patient), {"nid": national_id}).mappings().first()
            if prow and prow.get("PatientID") is not None:
                patient_id = int(prow["PatientID"])

    return PatientContext(
        username=username,
        display_name=display_name,
        national_id=national_id,
        patient_id=patient_id,
    )


def get_patient_history_by_username(username: str, limit: int = 10) -> list[dict]:
    sql = """
    SELECT id, patient_username, visit_date, doctor_name, diagnosis, treatment
    FROM patient_history
    WHERE patient_username = :u
    ORDER BY visit_date DESC
    LIMIT :lim
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"u": username, "lim": int(limit)}).mappings().all()
    return [dict(r) for r in rows]


def get_recent_medical_records(patient_id: int, limit: int = 5) -> list[dict]:
    sql = """
    SELECT
        RecordID, PatientID, DoctorID, VisitDate, BranchID,
        ChiefComplaint, Diagnosis, TreatmentNotes
    FROM medical_records
    WHERE PatientID = :pid
    ORDER BY VisitDate DESC
    LIMIT :lim
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"pid": int(patient_id), "lim": int(limit)}).mappings().all()
    return [dict(r) for r in rows]


def get_prescriptions_for_patient(patient_id: int, limit: int = 20) -> list[dict]:
    """
    Pull prescriptions by joining:
      medical_records (RecordID) -> prescriptions
    Note: If you have a medications table, we can join it to show names.
    """
    sql = """
    SELECT
        p.PrescriptionID,
        p.RecordID,
        mr.VisitDate,
        p.MedicationID,
        p.Dosage,
        p.Duration,
        p.Instructions
    FROM prescriptions p
    JOIN medical_records mr ON mr.RecordID = p.RecordID
    WHERE mr.PatientID = :pid
    ORDER BY mr.VisitDate DESC, p.PrescriptionID DESC
    LIMIT :lim
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"pid": int(patient_id), "lim": int(limit)}).mappings().all()
    return [dict(r) for r in rows] 
def get_lab_results_for_patient(patient_id: int, limit: int = 20) -> list[dict]:
    sql = """
    SELECT
        lr.ResultID,
        lr.RecordID,
        mr.VisitDate,
        lr.TestName,
        lr.ResultValue,
        lr.ReferenceRange,
        lr.Units,
        lr.ResultDate
    FROM lab_results lr
    JOIN medical_records mr ON mr.RecordID = lr.RecordID
    WHERE mr.PatientID = :pid
    ORDER BY lr.ResultDate DESC, lr.ResultID DESC
    LIMIT :lim
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"pid": int(patient_id), "lim": int(limit)}).mappings().all()
    return [dict(r) for r in rows]