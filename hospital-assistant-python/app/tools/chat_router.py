from __future__ import annotations

from datetime import datetime, timedelta
import re

from sqlalchemy import text
from app.db import SessionLocal

from app.tools.availability import get_available_slots
from app.tools.booking import book_slot

from app.tools.patient_data import (
    resolve_patient_id_from_username,
    get_patient_history,
    get_recent_medical_records,
    get_recent_prescriptions,
    get_recent_lab_results,
)

from app.tools.medications import (
    split_med_names,
    find_medication,
    find_multiple_medications,
    get_medication_warnings,
)


# --- Simple in-memory chat session (per chat_id) ---
# NOTE: This resets when you restart uvicorn.
CHAT_STATE: dict[str, dict] = {}


def _parse_dt(s: str) -> datetime:
    return datetime.fromisoformat(s)


def _who(payload: dict) -> str:
    return payload.get("display_name") or payload.get("username") or "حضرتك"


def _default_date_range(days: int = 7):
    start = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    end = start + timedelta(days=days)
    return start, end


def _extract_doctor_name(text_raw: str) -> str | None:
    """
    Examples:
      - "مواعيد دكتور Mona Shawky"
      - "مواعيد الدكتور أحمد زكي"
    """
    t = (text_raw or "").strip()
    if not t:
        return None

    t = re.sub(r"^\s*مواعيد\s+", "", t).strip()
    t = re.sub(r"^\s*(دكتور|الدكتور)\s+", "", t).strip()

    return t if len(t) >= 2 else None


def resolve_doctor_from_employees(name: str):
    """
    doctor_id = employees.EmployeeID
    branch_id = employees.BranchID
    """
    q = (name or "").strip()
    if not q:
        return {"found": False, "doctor": None, "suggestions": []}

    parts = [p for p in re.split(r"\s+", q) if p]
    first = parts[0] if parts else q
    last = parts[-1] if len(parts) > 1 else ""

    sql_exact_full = """
    SELECT EmployeeID, BranchID, FirstName, LastName
    FROM employees
    WHERE Role='Doctor'
      AND LOWER(CONCAT(FirstName, ' ', LastName)) = LOWER(:full)
    LIMIT 1
    """

    sql_exact_parts = """
    SELECT EmployeeID, BranchID, FirstName, LastName
    FROM employees
    WHERE Role='Doctor'
      AND LOWER(FirstName) = LOWER(:first)
      AND (:last = '' OR LOWER(LastName) = LOWER(:last))
    LIMIT 1
    """

    sql_suggest = """
    SELECT EmployeeID, BranchID, FirstName, LastName
    FROM employees
    WHERE Role='Doctor'
      AND (
        LOWER(FirstName) LIKE LOWER(:likeq)
        OR LOWER(LastName) LIKE LOWER(:likeq)
        OR LOWER(CONCAT(FirstName, ' ', LastName)) LIKE LOWER(:likeq)
      )
    ORDER BY FirstName, LastName
    LIMIT 5
    """

    with SessionLocal() as db:
        row = db.execute(text(sql_exact_full), {"full": q}).mappings().first()
        if row:
            return {"found": True, "doctor": dict(row), "suggestions": []}

        row = db.execute(
            text(sql_exact_parts),
            {"first": first, "last": last},
        ).mappings().first()
        if row:
            return {"found": True, "doctor": dict(row), "suggestions": []}

        sugg = db.execute(text(sql_suggest), {"likeq": f"%{q}%"}).mappings().all()
        return {"found": False, "doctor": None, "suggestions": [dict(s) for s in sugg]}


def _is_reset_command(text_raw: str) -> bool:
    return text_raw.strip().lower() in ["ابدأ من جديد", "ابدأ", "ريست", "reset", "restart"]


def _detect_medication_risk_intent(text_raw: str, text: str) -> bool:
    risk_keywords = [
        "مخاطر",
        "أضرار",
        "اضرار",
        "تحذيرات",
        "warnings",
        "risks",
        "risk",
        "side effects",
        "adverse",
        "adverse effects",
    ]
    return any(k in text_raw for k in risk_keywords) or any(k in text for k in risk_keywords)


def _reply_for_single_medication(person: str, med: dict):
    warnings = get_medication_warnings(int(med["MedicationID"]))

    if not warnings:
        return {
            "intent": "medication_risks",
            "reply": (
                f"لقيت الدواء: {med.get('TradeName')} ({med.get('GenericName')})\n"
                "بس مفيش تحذيرات/مخاطر مسجلة له في جدول drug_interactions."
            ),
            "data": {"medication": med, "warnings": []},
        }

    lines = [f"يا {person}، تحذيرات {med.get('TradeName')} ({med.get('GenericName')}):"]
    for w in warnings[:10]:
        lines.append(f"- {w.get('WarningType')}: {w.get('Description')}")

    return {
        "intent": "medication_risks",
        "reply": "\n".join(lines),
        "data": {"medication": med, "warnings": warnings},
    }


def _reply_for_multiple_medications(person: str, found_meds: list[dict], not_found: list[str]):
    lines = []

    meds_data = []
    for med in found_meds:
        warnings = get_medication_warnings(int(med["MedicationID"]))
        meds_data.append({"medication": med, "warnings": warnings})

        lines.append(f"{med.get('TradeName')} ({med.get('GenericName')}):")
        if warnings:
            for w in warnings[:5]:
                lines.append(f"- {w.get('WarningType')}: {w.get('Description')}")
        else:
            lines.append("- لا توجد تحذيرات/مخاطر مسجلة في drug_interactions.")
        lines.append("")

    if not_found:
        lines.append("الأدوية دي ما اتلاقتش بالاسم المكتوب:")
        for nf in not_found:
            lines.append(f"- {nf}")

    prefix = f"يا {person}، " if person else ""
    reply = prefix + "\n".join(lines).strip()

    return {
        "intent": "medication_risks",
        "reply": reply,
        "data": {
            "medications": meds_data,
            "not_found": not_found,
        },
    }


def route_chat(payload: dict):
    text_raw = (payload.get("text") or "").strip()
    text = text_raw.lower()

    username = payload.get("username")
    person = _who(payload)

    chat_id = payload.get("chat_id") or "default"
    state = CHAT_STATE.setdefault(chat_id, {"step": "need_patient_id", "patient_id": None})

    # -------------------------
    # Reset command
    # -------------------------
    if _is_reset_command(text_raw):
        CHAT_STATE[chat_id] = {"step": "need_patient_id", "patient_id": None}
        return {
            "intent": "reset",
            "reply": f"تمام يا {person}. اكتب/ي PatientID (رقم فقط).",
            "data": None,
        }

    # -------------------------
    # Medication risks intent
    # IMPORTANT: handle BEFORE requiring patient_id
    # -------------------------
    wants_med_risks = _detect_medication_risk_intent(text_raw, text)

    if wants_med_risks:
        meds = split_med_names(text_raw)

        if not meds:
            return {
                "intent": "medication_risks",
                "reply": "اكتبي اسم الدواء بعد كلمة (مخاطر). مثال: مخاطر Brufen",
                "data": {"medications": []},
            }

        # لو أكثر من دواء
        if len(meds) > 1:
            multi = find_multiple_medications(text_raw)
            found_meds = multi.get("found", [])
            not_found = multi.get("not_found", [])

            if not found_meds and not_found:
                return {
                    "intent": "medication_risks",
                    "reply": (
                        "مش لاقية الأدوية دي في جدول medications:\n"
                        + "\n".join([f"- {x}" for x in not_found])
                    ),
                    "data": {"medications": [], "not_found": not_found},
                }

            return _reply_for_multiple_medications(person, found_meds, not_found)

        # لو دواء واحد
        med_query = meds[0]
        found = find_medication(med_query)

        if not found.get("found"):
            suggestions = found.get("suggestions", [])
            if suggestions:
                sug_txt = "\n".join(
                    [f"- {s['TradeName']} ({s['GenericName']})" for s in suggestions]
                )
                return {
                    "intent": "medication_risks",
                    "reply": (
                        f"مش لاقياه بالاسم ده: {med_query}\n"
                        f"هل تقصدي واحد من دول؟\n{sug_txt}"
                    ),
                    "data": {"medications": [], "suggestions": suggestions},
                }

            return {
                "intent": "medication_risks",
                "reply": (
                    f"الدواء ({med_query}) مش موجود في جدول medications عندي. "
                    "جربي اسم موجود عندك زي: Brufen أو Ibuprofen."
                ),
                "data": {"medications": [], "suggestions": []},
            }

        med = found["med"]
        return _reply_for_single_medication(person, med)

    # -------------------------
    # Step-by-step session (PatientID first) for patient-specific tasks only
    # -------------------------
    if payload.get("patient_id"):
        state["patient_id"] = int(payload["patient_id"])
        state["step"] = "ready"

    if not state.get("patient_id"):
        m = re.search(r"\b(\d{1,10})\b", text_raw)
        if m:
            pid = int(m.group(1))
            state["patient_id"] = pid
            state["step"] = "ready"
            payload["patient_id"] = pid
            return {
                "intent": "patient_id_set",
                "reply": (
                    f"تمام يا {person} ✅ سجلت PatientID = {pid}.\n"
                    "دلوقتي اكتبي: (مواعيد دكتور <الاسم>) أو (احجز) أو "
                    "(تاريخي/سجلي/روشتاتي/تحاليل) أو (مخاطر Brufen)."
                ),
                "data": {"patient_id": pid},
            }

        return {
            "intent": "need_patient_id",
            "reply": f"يا {person}، من فضلك اكتبي PatientID الأول (رقم فقط) علشان أقدر أحجز وأجيب بياناتك.",
            "data": {"expected": "patient_id"},
        }

    payload["patient_id"] = int(state["patient_id"])

    # -------------------------
    # Patient data intents
    # -------------------------
    wants_history = any(
        k in text for k in ["history", "patient history", "تاريخي", "تاريخ مرضي", "زيارات", "زيارة"]
    )
    wants_records = any(
        k in text
        for k in [
            "medical record",
            "medical records",
            "سجلي",
            "سجلي الطبي",
            "آخر كشف",
            "اخر كشف",
            "records",
            "تشخيص",
        ]
    )
    wants_rx = any(
        k in text
        for k in ["prescription", "prescriptions", "روشت", "روشتتي", "ادوية", "أدوية", "علاجي", "علاج"]
    )
    wants_labs = any(
        k in text for k in ["lab", "labs", "lab results", "تحاليل", "نتائج", "نتايج", "analysis", "تحاليلك"]
    )

    if wants_history:
        if not payload.get("patient_id") and username:
            pid = resolve_patient_id_from_username(username)
            if pid:
                payload["patient_id"] = pid

        if not username:
            return {
                "intent": "need_username",
                "reply": "لازم أعرف الـusername من تسجيل الدخول عشان أجيب تاريخك المرضي.",
                "data": None,
            }

        hist = get_patient_history(username, limit=5)
        if not hist:
            return {
                "intent": "patient_history",
                "reply": f"يا {person}، مفيش زيارات مسجلة في الـPatient History.",
                "data": {"history": []},
            }

        lines = [f"يا {person}، آخر زيارات في الـPatient History:"]
        for h in hist:
            lines.append(
                f"- {h.get('visit_date')}: {h.get('doctor_name')} | التشخيص: {h.get('diagnosis') or '-'} | العلاج: {h.get('treatment') or '-'}"
            )

        return {
            "intent": "patient_history",
            "reply": "\n".join(lines),
            "data": {"history": hist},
        }

    if wants_records:
        recs = get_recent_medical_records(int(payload["patient_id"]), limit=3)
        if not recs:
            return {
                "intent": "medical_records",
                "reply": f"يا {person}، مفيش Medical Records مسجلة.",
                "data": {"records": []},
            }

        lines = [f"يا {person}، آخر Medical Records:"]
        for r in recs:
            lines.append(
                f"- {r.get('VisitDate')}: التشخيص: {r.get('Diagnosis')} | الشكوى: {r.get('ChiefComplaint') or '-'}"
            )

        return {
            "intent": "medical_records",
            "reply": "\n".join(lines),
            "data": {"records": recs},
        }

    if wants_rx:
        rx = get_recent_prescriptions(int(payload["patient_id"]), limit=10)
        if not rx:
            return {
                "intent": "prescriptions",
                "reply": f"يا {person}، مفيش Prescriptions مسجلة.",
                "data": {"prescriptions": []},
            }

        lines = [f"يا {person}، آخر Prescriptions:"]
        for p in rx[:10]:
            trade = (p.get("TradeName") or "").strip()
            generic = (p.get("GenericName") or "").strip()
            name = trade or generic or f"MedicationID={p.get('MedicationID')}"
            if trade and generic and trade.lower() != generic.lower():
                name = f"{trade} ({generic})"

            lines.append(f"- {p.get('VisitDate')}: {name} | {p.get('Dosage') or '-'} | {p.get('Duration') or '-'}")

        return {
            "intent": "prescriptions",
            "reply": "\n".join(lines),
            "data": {"prescriptions": rx},
        }

    if wants_labs:
        labs = get_recent_lab_results(int(payload["patient_id"]), limit=10)
        if not labs:
            return {
                "intent": "lab_results",
                "reply": f"يا {person}، مفيش Lab Results مسجلة.",
                "data": {"lab_results": []},
            }

        lines = [f"يا {person}، آخر Lab Results:"]
        for l in labs[:10]:
            unit = l.get("Units") or ""
            rr = l.get("ReferenceRange") or "-"
            lines.append(
                f"- {l.get('ResultDate')}: {l.get('TestName')} = {l.get('ResultValue')} {unit} (Ref: {rr})"
            )

        return {
            "intent": "lab_results",
            "reply": "\n".join(lines),
            "data": {"lab_results": labs},
        }

    # -------------------------
    # Availability / booking intents
    # -------------------------
    wants_availability = any(
        k in text for k in ["available", "availability", "slots", "مواعيد", "متاح"]
    )
    wants_booking = any(k in text for k in ["book", "reserve", "احجز", "حجز", "confirm", "ثبت"])

    doctor_id = payload.get("doctor_id")
    branch_id = payload.get("branch_id")

    if wants_availability and not doctor_id:
        doc_name = _extract_doctor_name(text_raw)
        if doc_name:
            res = resolve_doctor_from_employees(doc_name)
            if res["found"]:
                doctor = res["doctor"]
                payload["doctor_id"] = int(doctor["EmployeeID"])
                if not branch_id and doctor.get("BranchID") is not None:
                    payload["branch_id"] = int(doctor["BranchID"])
            else:
                sugg = res["suggestions"]
                if sugg:
                    lines = ["مش لاقي الدكتور بالاسم ده. هل تقصدي:"]
                    for s in sugg:
                        lines.append(
                            f"- {s.get('FirstName')} {s.get('LastName')} (doctor_id={s.get('EmployeeID')}, branch_id={s.get('BranchID')})"
                        )
                    return {
                        "intent": "need_doctor_name",
                        "reply": "\n".join(lines),
                        "data": {"suggestions": sugg},
                    }
                return {
                    "intent": "need_doctor_name",
                    "reply": "مش لاقي الدكتور ده في جدول employees. اكتبي الاسم زي الموجود.",
                    "data": None,
                }

    if wants_availability and payload.get("doctor_id") and payload.get("branch_id") and (
        not payload.get("from_dt") or not payload.get("to_dt")
    ):
        f, t2 = _default_date_range(7)
        payload["from_dt"] = f.isoformat()
        payload["to_dt"] = t2.isoformat()

    doctor_id = payload.get("doctor_id")
    branch_id = payload.get("branch_id")

    if wants_availability:
        if not doctor_id or not branch_id or not payload.get("from_dt") or not payload.get("to_dt"):
            return {
                "intent": "availability",
                "reply": "عايز/ة أعرف (doctor_id, branch_id, from_dt, to_dt). مثال: from_dt=2026-03-16T00:00:00",
                "data": None,
            }

        from_dt = _parse_dt(payload["from_dt"])
        to_dt = _parse_dt(payload["to_dt"])
        slots = get_available_slots(int(doctor_id), int(branch_id), from_dt, to_dt)

        slots_ui = []
        for s in slots:
            slots_ui.append(
                {
                    "doctor_id": s["doctor_id"],
                    "branch_id": s["branch_id"],
                    "start": s["start"].isoformat(),
                    "end": s["end"].isoformat(),
                }
            )

        return {
            "intent": "availability",
            "reply": f"لقيت {len(slots_ui)} ميعاد متاح. اختاري من الأزرار تحت.",
            "data": {"slots": slots_ui},
        }

    if wants_booking:
        if not payload.get("doctor_id") or not payload.get("branch_id") or not payload.get("start"):
            return {
                "intent": "booking",
                "reply": "عشان أحجز لازم (doctor_id, branch_id, start). مثال start=2026-03-16T10:00:00",
                "data": None,
            }

        start_dt = _parse_dt(payload["start"])
        ok, msg = book_slot(
            int(payload["patient_id"]),
            int(payload["doctor_id"]),
            int(payload["branch_id"]),
            start_dt,
        )
        return {
            "intent": "booking",
            "reply": msg if ok else f"فشل الحجز: {msg}",
            "data": {"booked": ok},
        }

    return {
        "intent": "unknown",
        "reply": "قولي عايز/ة: (مواعيد دكتور <الاسم>) أو (احجز) أو (تاريخي/سجلي/روشتاتي/تحاليل) أو (مخاطر Brufen).",
        "data": None,
    }