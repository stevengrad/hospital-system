from __future__ import annotations

import re
from datetime import datetime
from typing import Any

from app.tools.lookup import find_branches_by_name, find_doctors_by_name
from app.tools.availability import get_available_slots
from app.tools.booking import book_appointment

from app.tools.medications import (
    split_med_names,
    find_medication,
    find_multiple_medications,
    get_medication_warnings,
)
from app.tools.symptoms import suggest_specialty_id
from app.tools.specialty_booking import (
    get_specialty_by_id,
    list_branches_for_specialty,
    get_specialty_slots,
)
from app.tools.patient_context import (
    resolve_patient_context,
    get_patient_history_by_username,
    get_recent_medical_records,
    get_prescriptions_for_patient,
    get_lab_results_for_patient,
)

# In-memory chat state (for demo / single-process).
# If you run multiple workers, move this to Redis/DB.
CHAT_STATE: dict[str, dict] = {}


def _now_str(dt: datetime) -> str:
    return dt.strftime("%Y-%m-%d %H:%M")


def _extract_after(text: str, key: str) -> str:
    if key not in text:
        return ""
    return text.split(key, 1)[1].strip()


def _parse_int(s: str) -> int | None:
    try:
        return int(s)
    except Exception:
        return None


def _detect_branch_choice(t: str) -> int | None:
    """
    Accept:
      - "فرع 1"
      - "branch 1"
      - "1" (only if in choose-branch step)
    """
    m = re.search(r"(?:\bفرع\b|\bbranch\b)\s*(\d+)", t, flags=re.IGNORECASE)
    if m:
        return _parse_int(m.group(1))

    m2 = re.fullmatch(r"\s*(\d+)\s*", t)
    if m2:
        return _parse_int(m2.group(1))

    return None


def _detect_book_choice(t: str) -> int | None:
    """
    Accept:
      - احجز 3
      - book 3
      - 3
    """
    msg = (t or "").strip()

    m = re.search(r"(?:احجز|book)\s*(\d+)", msg, flags=re.IGNORECASE)
    if m:
        return _parse_int(m.group(1))

    if re.fullmatch(r"\d+", msg):
        return _parse_int(msg)

    return None


def _detect_lang(text: str) -> str:
    return "ar" if re.search(r"[\u0600-\u06FF]", text or "") else "en"


def _is_booking_start(t: str) -> bool:
    x = (t or "").lower().strip()
    return any(
        p in x
        for p in [
            "i need to book",
            "i want to book",
            "book appointment",
            "book an appointment",
            "make an appointment",
            "schedule",
            "appointment",
            "عايز احجز",
            "عايزة احجز",
            "عاوز احجز",
            "محتاج احجز",
            "محتاجة احجز",
            "احجزلي",
            "حجز",
        ]
    )


def _reply_medication_single(name: str, person: str):
    found = find_medication(name)
    if not found.get("found"):
        sugg = found.get("suggestions", [])
        if sugg:
            opts = ", ".join([f"{s['TradeName']} ({s['GenericName']})" for s in sugg])
            return {
                "intent": "medication_risks",
                "reply": f"الدواء: {name}\n- غير موجود بالاسم ده عندي. هل تقصدي: {opts} ؟",
                "data": {"query": name, "found": False, "suggestions": sugg},
            }

        return {
            "intent": "medication_risks",
            "reply": f"الدواء: {name}\n- غير موجود في جدول medications عندي.",
            "data": {"query": name, "found": False, "suggestions": []},
        }

    med = found["med"]
    warnings = get_medication_warnings(int(med["MedicationID"]))

    head = f"يا {person}، تحذيرات {med['TradeName']}"
    if med.get("GenericName"):
        head += f" ({med['GenericName']})"
    if med.get("Category"):
        head += f" | التصنيف: {med['Category']}"

    if not warnings:
        return {
            "intent": "medication_risks",
            "reply": head + "\n- لا توجد تحذيرات مسجلة في قاعدة البيانات لهذا الدواء.",
            "data": {"medication": med, "warnings": []},
        }

    lines = [head]
    for w in warnings[:10]:
        lines.append(f"- [{w['WarningType']}] {w['Description']}")

    lines.append("\nتنبيه: المعلومات للتوعية ولا تغني عن استشارة الطبيب/الصيدلي.")
    return {
        "intent": "medication_risks",
        "reply": "\n".join(lines),
        "data": {"medication": med, "warnings": warnings},
    }


def _reply_medication_multi(text: str):
    multi = find_multiple_medications(text)
    found_items = multi.get("found", [])
    not_found = multi.get("not_found", [])

    if not found_items and not_found:
        return {
            "intent": "medication_risks",
            "reply": "مش لاقية الأدوية دي في جدول medications:\n" + "\n".join([f"- {x}" for x in not_found]),
            "data": {"medications": [], "not_found": not_found},
        }

    blocks = []
    out_items = []

    for med in found_items:
        warnings = get_medication_warnings(int(med["MedicationID"]))
        head = f"{med['TradeName']}"
        if med.get("GenericName"):
            head += f" ({med['GenericName']})"
        if med.get("Category"):
            head += f" | {med['Category']}"

        if warnings:
            body = "\n".join([f"- [{w['WarningType']}] {w['Description']}" for w in warnings[:5]])
        else:
            body = "- لا توجد تحذيرات/مخاطر مسجلة في drug_interactions."

        blocks.append(head + "\n" + body)
        out_items.append({"medication": med, "warnings": warnings})

    if not_found:
        blocks.append("الأدوية دي ما اتلاقتش بالاسم المكتوب:\n" + "\n".join([f"- {x}" for x in not_found]))

    blocks.append("تنبيه: المعلومات للتوعية ولا تغني عن استشارة الطبيب/الصيدلي.")
    return {
        "intent": "medication_risks",
        "reply": "\n\n".join(blocks),
        "data": {"items": out_items, "not_found": not_found},
    }


def handle_chat(text: str, chat_id: str = "web", patient_id: int | None = None) -> dict:
    t = (text or "").strip()
    lang = _detect_lang(t)

    if not t:
        return {
            "reply": "اكتب رسالتك." if lang == "ar" else "Type your message.",
            "data": None,
            "intent": "empty",
        }

    st = CHAT_STATE.setdefault(chat_id, {})

    # sync patient_id into state if provided
    if patient_id is not None:
        st["patient_id"] = int(patient_id)

    # ------------------------------------------------------------
    # 0) If we are waiting for PatientID to complete a booking
    # ------------------------------------------------------------
    if st.get("pending_step") == "need_patient_id":
        pid = patient_id
        if pid is None:
            pid = _parse_int(t)

        if pid is None:
            return {
                "intent": "need_patient_id",
                "reply": (
                    "لازم تدخلي PatientID عشان أعمل الحجز. (مثال: 200)"
                    if lang == "ar"
                    else "You need to enter PatientID to make the booking. (Example: 200)"
                ),
                "data": None,
            }

        st["patient_id"] = pid
        st["pending_step"] = None

        pending = st.get("pending_booking_slot")
        if not pending:
            return {
                "intent": "ok",
                "reply": "تمام. قوليلي (احجز رقم) تاني." if lang == "ar" else "Okay. Tell me (book number) again.",
                "data": None,
            }

        st["pending_booking_slot"] = None
        return _book_slot_with_patient(pid, pending)

    # ------------------------------------------------------------
    # 1) Medication risks
    # ------------------------------------------------------------
    if any(k in t.lower() for k in ["مخاطر", "تحذيرات", "أضرار", "اضرار", "warnings", "risks", "risk"]):
        meds = split_med_names(t)
        if not meds:
            return {
                "intent": "med_need_names",
                "reply": "اكتبي أسماء الأدوية. مثال: مخاطر Aspirin و Ibuprofen",
                "data": None,
            }

        if len(meds) > 1:
            return _reply_medication_multi(t)

        return _reply_medication_single(meds[0], "حضرتك")

    # ------------------------------------------------------------
    # 2) Booking by number
    # ------------------------------------------------------------
    book_idx = _detect_book_choice(t)
    if book_idx is not None:
        slots = st.get("last_slots") or []
        if not slots:
            return {
                "intent": "no_slots",
                "reply": (
                    "مفيش قائمة مواعيد مرقمة دلوقتي. اطلب مواعيد الأول."
                    if lang == "ar"
                    else "No numbered appointment list available right now. Request appointments first."
                ),
                "data": None,
            }

        if book_idx < 1 or book_idx > len(slots):
            return {
                "intent": "bad_index",
                "reply": f"اختاري رقم بين 1 و {len(slots)}." if lang == "ar" else f"Choose a number between 1 and {len(slots)}.",
                "data": None,
            }

        chosen = slots[book_idx - 1]
        pid = patient_id or st.get("patient_id")

        if pid is None:
            st["pending_step"] = "need_patient_id"
            st["pending_booking_slot"] = chosen
            return {
                "intent": "need_patient_id",
                "reply": "لازم تدخلي PatientID عشان أعمل الحجز." if lang == "ar" else "You need to enter PatientID to make the booking.",
                "data": {"need": "patient_id"},
            }

        return _book_slot_with_patient(int(pid), chosen)

    # ------------------------------------------------------------
    # 3) Symptom -> Specialty -> choose branch -> show slots -> book
    # ------------------------------------------------------------
    if st.get("pending_step") == "choose_branch":
        choice = _detect_branch_choice(t)
        branches = st.get("pending_branches") or []
        sid = st.get("pending_specialty_id")

        if choice is None or choice < 1 or choice > len(branches):
            return {
                "intent": "choose_branch",
                "reply": f"اختاري رقم فرع صحيح (1 - {len(branches)}). اكتبي: فرع 1",
                "data": {"branches": branches},
            }

        branch = branches[choice - 1]
        branch_id = int(branch["BranchID"])

        slots = get_specialty_slots(
            branch_id=branch_id,
            specialty_id=int(sid),
            get_available_slots_fn=get_available_slots,
            days_ahead=14,
            limit_total=15,
        )

        if not slots:
            return {
                "intent": "no_slots",
                "reply": f"مفيش مواعيد متاحة قريبًا في {branch['Name']} للتخصص ده. جرّبي فرع تاني.",
                "data": {"branch": branch, "specialty_id": sid},
            }

        st["last_slots"] = [
            {
                "doctor_id": s["doctor_id"],
                "doctor_name": s.get("doctor_name"),
                "branch_id": s["branch_id"],
                "start": s.get("start").isoformat() if isinstance(s.get("start"), datetime) else s.get("start"),
                "end": s.get("end").isoformat() if isinstance(s.get("end"), datetime) else s.get("end"),
            }
            for s in slots
        ]

        lines = [f"أقرب المواعيد المتاحة في {branch['Name']}:"]

        for i, s in enumerate(slots, start=1):
            start = s.get("start")
            start_str = _now_str(start) if isinstance(start, datetime) else str(start)
            doc_name = s.get("doctor_name") or f"{s['doctor_id']}"
            lines.append(f"{i}) {start_str} - دكتور {doc_name}")

        lines.append("\nاكتبي: احجز <رقم> (مثال: احجز 2)")
        st["pending_step"] = None
        st["pending_branches"] = None

        return {
            "intent": "specialty_slots",
            "reply": "\n".join(lines),
            "data": {"branch": branch, "slots": st["last_slots"]},
        }

    sid = suggest_specialty_id(t)
    if sid is not None:
        spec = get_specialty_by_id(int(sid))
        branches = list_branches_for_specialty(int(sid))

        if not branches:
            return {
                "intent": "no_branches_for_specialty",
                "reply": "فهمت الأعراض، بس مش لاقي فروع فيها دكاترة للتخصص ده حاليًا.",
                "data": {"specialty_id": sid, "specialty": spec},
            }

        st["pending_specialty_id"] = int(sid)
        st["pending_branches"] = branches
        st["pending_step"] = "choose_branch"

        spec_name = spec["Name"] if spec else f"Specialty {sid}"
        lines = [
            f"ده غالبًا يخص تخصص: {spec_name}.",
            "اختاري الفرع اللي تحبي تحجزي فيه:",
        ]
        for i, b in enumerate(branches, start=1):
            lines.append(f"{i}) {b['Name']}")
        lines.append("\nاكتبي: فرع <رقم> (مثال: فرع 1)")

        return {
            "intent": "choose_branch",
            "reply": "\n".join(lines),
            "data": {"specialty": spec, "branches": branches},
        }

    # ------------------------------------------------------------
    # 4) Doctor schedule query
    # ------------------------------------------------------------
    if "مواعيد" in t and ("دكتور" in t or "doctor" in t.lower()) and "فرع" in t:
        doc_name = _extract_after(t, "دكتور").split("فرع", 1)[0].strip()
        branch_part = _extract_after(t, "فرع").strip()

        doctors = find_doctors_by_name(doc_name)
        if not doctors:
            return {
                "intent": "no_doctor",
                "reply": f"مش لاقي دكتور باسم قريب من: {doc_name}",
                "data": {"query": doc_name},
            }

        branches = find_branches_by_name(branch_part)
        if not branches:
            btoks = branch_part.replace("-", " ").split()
            if len(btoks) >= 2 and btoks[0].lower() == "branch":
                branches = find_branches_by_name(" ".join(btoks[:2]))

        if not branches:
            return {
                "intent": "no_branch",
                "reply": f"مش لاقي فرع باسم قريب من: {branch_part}",
                "data": {"query": branch_part},
            }

        doctor = doctors[0]
        branch = branches[0]

        doctor_id = int(doctor["EmployeeID"]) if "EmployeeID" in doctor else int(doctor["DoctorID"])
        branch_id = int(branch["BranchID"])

        slots = get_available_slots(doctor_id=doctor_id, branch_id=branch_id)
        if not slots:
            return {
                "intent": "no_slots",
                "reply": "مفيش مواعيد متاحة قريبًا.",
                "data": {"doctor": doctor, "branch": branch},
            }

        st["last_slots"] = [
            {
                "doctor_id": doctor_id,
                "doctor_name": doctor.get("Name") or doctor.get("DoctorName") or doc_name,
                "branch_id": branch_id,
                "start": s.get("start").isoformat() if isinstance(s.get("start"), datetime) else s.get("start"),
                "end": s.get("end").isoformat() if isinstance(s.get("end"), datetime) else s.get("end"),
            }
            for s in slots[:15]
        ]

        lines = [f"أقرب المواعيد المتاحة للدكتور {doc_name} في {branch['Name']}:"]

        for i, s in enumerate(slots[:15], start=1):
            start = s.get("start")
            start_str = _now_str(start) if isinstance(start, datetime) else str(start)
            lines.append(f"{i}) {start_str}")

        lines.append("\nاكتبي: احجز <رقم> (مثال: احجز 1)")
        return {
            "intent": "doctor_slots",
            "reply": "\n".join(lines),
            "data": {"doctor": doctor, "branch": branch, "slots": st["last_slots"]},
        }

    # ------------------------------------------------------------
    # 5) Fallback help
    # ------------------------------------------------------------
    if lang == "en":
        return {
            "intent": "help",
            "reply": (
                "I can help with:\n"
                "1) Medication risks: e.g. Risks of Aspirin and Ibuprofen\n"
                "2) Symptom-based booking: e.g. stomach burning\n"
                "3) Doctor slots: e.g. Doctor Ahmed slots branch Branch A - Main Hospital\n"
                "After I show numbered slots, type: book 3"
            ),
            "data": None,
        }

    return {
        "intent": "help",
        "reply": (
            "أقدر أساعدك في:\n"
            "1) مخاطر الأدوية: (مثال) مخاطر Aspirin و Ibuprofen\n"
            "2) حجز حسب الأعراض: (مثال) حرقان في المعدة\n"
            "3) مواعيد دكتور: (مثال) مواعيد دكتور Ahmed فرع Branch A - Main Hospital\n"
            "وبعد ما يعرض أرقام: اكتبي احجز 3"
        ),
        "data": None,
    }


def _book_slot_with_patient(patient_id: int, chosen_slot: dict) -> dict:
    doctor_id = int(chosen_slot["doctor_id"])
    branch_id = int(chosen_slot["branch_id"])
    appt_dt = chosen_slot.get("start")

    if not isinstance(appt_dt, datetime):
        try:
            appt_dt = datetime.fromisoformat(str(appt_dt))
        except Exception:
            return {
                "intent": "bad_slot",
                "reply": "الوقت المختار غير صالح. اطلب المواعيد تاني.",
                "data": {"slot": chosen_slot},
            }

    result = book_appointment(
        patient_id=int(patient_id),
        doctor_id=doctor_id,
        branch_id=branch_id,
        appt_dt=appt_dt,
    )
    return {
        "intent": "book_result",
        "reply": result.get("message", "تم."),
        "data": {"result": result, "slot": chosen_slot},
    }


def _fmt_dt(v):
    return str(v) if v is not None else "-"


def _who(display_name: str | None, username: str | None) -> str:
    return display_name or username or "حضرتك"


def _ctx_to_dict(ctx):
    if ctx is None:
        return None
    if hasattr(ctx, "model_dump") and callable(getattr(ctx, "model_dump")):
        return ctx.model_dump()
    if hasattr(ctx, "dict") and callable(getattr(ctx, "dict")):
        return ctx.dict()
    if hasattr(ctx, "__dict__"):
        return dict(ctx.__dict__)
    return str(ctx)


def maybe_answer_patient_data(text: str, username: str | None, display_name: str | None) -> dict | None:
    t = (text or "").lower().strip()
    if not t:
        return None

    ask_history = any(k in t for k in ["تاريخ", "history", "patient history", "زيارات", "زيارة"])
    ask_records = any(k in t for k in ["medical record", "medical records", "سجلي الطبي", "آخر كشف", "اخر كشف", "تشخيص", "diagnosis"])
    ask_rx = any(k in t for k in ["روشت", "prescription", "prescriptions", "medication", "medications", "ادوية", "أدوية"])
    ask_labs = any(k in t for k in ["تحاليل", "تحاليلك", "lab", "labs", "lab results", "نتايج", "نتائج"])

    if not (ask_history or ask_records or ask_rx or ask_labs):
        return None

    if not username:
        return {
            "reply": "لازم أعرف الـusername من تسجيل الدخول عشان أقدر أجيب بياناتك الطبية. افتحي الشات من داخل الموقع بعد الـLogin.",
            "intent": "need_username",
            "data": {},
        }

    ctx = resolve_patient_context(username)
    person = _who(display_name or (ctx.display_name if ctx else None), username)

    if ask_history:
        hist = get_patient_history_by_username(username, limit=5)
        if not hist:
            return {
                "reply": f"يا {person}، مش لاقي زيارات في patient history عندك.",
                "intent": "patient_history",
                "data": {"context": _ctx_to_dict(ctx), "history": []},
            }
        lines = [f"يا {person}، آخر زيارات في الـPatient History:"]
        for h in hist:
            lines.append(
                f"- {_fmt_dt(h.get('visit_date'))}: {h.get('doctor_name')} | التشخيص: {h.get('diagnosis') or '-'} | العلاج: {h.get('treatment') or '-'}"
            )
        return {
            "reply": "\n".join(lines),
            "intent": "patient_history",
            "data": {"context": _ctx_to_dict(ctx), "history": hist},
        }

    if not ctx or not getattr(ctx, "patient_id", None):
        return {
            "reply": f"يا {person}، مش قادر أحدد PatientID من بيانات الحساب (username/national_id). تأكدي إن الرقم القومي في registration/login مطابق لجدول patients.",
            "intent": "missing_patient_id",
            "data": {"context": _ctx_to_dict(ctx)},
        }

    pid = ctx.patient_id

    if ask_records:
        recs = get_recent_medical_records(pid, limit=3)
        if not recs:
            return {
                "reply": f"يا {person}، مش لاقي Medical Records مسجلة ليكي.",
                "intent": "medical_records",
                "data": {"context": _ctx_to_dict(ctx), "records": []},
            }
        lines = [f"يا {person}، آخر Medical Records:"]
        for r in recs:
            lines.append(
                f"- {_fmt_dt(r.get('VisitDate'))}: التشخيص: {r.get('Diagnosis')} | الشكوى: {r.get('ChiefComplaint') or '-'}"
            )
        return {
            "reply": "\n".join(lines),
            "intent": "medical_records",
            "data": {"context": _ctx_to_dict(ctx), "records": recs},
        }

    if ask_rx:
        rx = get_prescriptions_for_patient(pid, limit=10)
        if not rx:
            return {
                "reply": f"يا {person}، مش لاقي روشتات (Prescriptions) مسجلة ليكي.",
                "intent": "prescriptions",
                "data": {"context": _ctx_to_dict(ctx), "prescriptions": []},
            }
        lines = [f"يا {person}، آخر Prescriptions:"]
        for p in rx[:10]:
            lines.append(
                f"- {_fmt_dt(p.get('VisitDate'))}: MedicationID={p.get('MedicationID')} | {p.get('Dosage') or '-'} | {p.get('Duration') or '-'} | {p.get('Instructions') or '-'}"
            )
        return {
            "reply": "\n".join(lines),
            "intent": "prescriptions",
            "data": {"context": _ctx_to_dict(ctx), "prescriptions": rx},
        }

    if ask_labs:
        labs = get_lab_results_for_patient(pid, limit=10)
        if not labs:
            return {
                "reply": f"يا {person}، مش لاقي نتائج تحاليل (Lab Results) مسجلة ليكي.",
                "intent": "lab_results",
                "data": {"context": _ctx_to_dict(ctx), "lab_results": []},
            }
        lines = [f"يا {person}، آخر Lab Results:"]
        for l in labs[:10]:
            val = l.get("ResultValue")
            unit = l.get("Units") or ""
            rr = l.get("ReferenceRange") or "-"
            lines.append(
                f"- {_fmt_dt(l.get('ResultDate'))}: {l.get('TestName')} = {val} {unit} (Reference: {rr})"
            )
        return {
            "reply": "\n".join(lines),
            "intent": "lab_results",
            "data": {"context": _ctx_to_dict(ctx), "lab_results": labs},
        }

    return None