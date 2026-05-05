from __future__ import annotations

from sqlalchemy import text

from app.db import SessionLocal


def resolve_patient_id_from_username(username: str) -> int | None:
    """
    Resolve:
      username -> national_id (registration/login) -> PatientID (patients)

    NOTE:
    - patient_history uses username directly.
    - medical_records/prescriptions/lab_results use PatientID/RecordID.
    """
    if not username:
        return None

    sql_nid = """
    SELECT COALESCE(r.national_id, l.national_id) AS national_id
    FROM registration r
    LEFT JOIN login l ON l.username = r.username
    WHERE r.username = :u
    LIMIT 1
    """

    sql_nid_login_only = """
    SELECT l.national_id AS national_id
    FROM login l
    WHERE l.username = :u
    LIMIT 1
    """

    with SessionLocal() as db:
        row = db.execute(text(sql_nid), {"u": username}).mappings().first()
        if not row:
            row = db.execute(text(sql_nid_login_only), {"u": username}).mappings().first()
        if not row:
            return None

        nid = row.get("national_id")
        if not nid:
            return None

        prow = db.execute(
            text("SELECT PatientID FROM patients WHERE NationalID = :nid LIMIT 1"),
            {"nid": nid},
        ).mappings().first()

        return int(prow["PatientID"]) if prow and prow.get("PatientID") is not None else None


def get_patient_history(username: str, limit: int = 5) -> list[dict]:
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


def get_recent_medical_records(patient_id: int, limit: int = 3) -> list[dict]:
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


def get_recent_prescriptions(patient_id: int, limit: int = 10) -> list[dict]:
    """
    prescriptions + medications names (TradeName/GenericName)
    """
    sql = """
    SELECT
        p.PrescriptionID,
        p.RecordID,
        mr.VisitDate,
        p.MedicationID,

        m.TradeName,
        m.GenericName,
        m.Category,

        p.Dosage,
        p.Duration,
        p.Instructions
    FROM prescriptions p
    JOIN medical_records mr ON mr.RecordID = p.RecordID
    LEFT JOIN medications m ON m.MedicationID = p.MedicationID
    WHERE mr.PatientID = :pid
    ORDER BY mr.VisitDate DESC, p.PrescriptionID DESC
    LIMIT :lim
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"pid": int(patient_id), "lim": int(limit)}).mappings().all()
    return [dict(r) for r in rows]


def get_recent_lab_results(patient_id: int, limit: int = 10) -> list[dict]:
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