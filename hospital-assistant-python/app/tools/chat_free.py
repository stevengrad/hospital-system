from __future__ import annotations

import json
import os
import re
from pathlib import Path
from datetime import datetime
from typing import Any

from app.tools.lookup import find_branches_by_name, find_doctors_by_name
from app.tools.availability import get_available_slots
from app.tools.booking import book_appointment
from app.ai.text_normalizer import normalize_for_chat, detect_language

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
    list_doctors_for_specialty_in_branch,
    get_doctor_names,
    get_specialty_slots,
)
from app.tools.offers import reply_for_offers
from app.tools.drug_interactions import is_drug_interaction_question, reply_for_drug_interaction_question
from app.tools.pharmacy_products import (
    is_product_comparison_question,
    is_product_recommendation_question,
    is_product_order_request,
    recommend_products,
    compare_products,
    find_products_by_name,
    format_recommendations,
    format_comparison,
    create_pharmacy_order,
)
from app.tools.patient_context import (
    resolve_patient_context,
    get_patient_history_by_username,
    get_recent_medical_records,
    get_prescriptions_for_patient,
    get_lab_results_for_patient,
)

# In-memory + tiny file cache chat state. The file cache protects the booking flow
# if PHP/Python session cookies change or uvicorn reloads while testing locally.
CHAT_STATE: dict[str, dict] = {}
_STATE_DIR = Path(os.getenv("CHATBOT_STATE_DIR", ".chat_state"))


def _safe_chat_id(chat_id: str) -> str:
    return re.sub(r"[^a-zA-Z0-9_\-:.]", "_", chat_id or "default")[:120]


def _state_path(chat_id: str) -> Path:
    return _STATE_DIR / f"{_safe_chat_id(chat_id)}.json"


def _json_safe(obj):
    if isinstance(obj, datetime):
        return obj.isoformat()
    if isinstance(obj, dict):
        return {str(k): _json_safe(v) for k, v in obj.items()}
    if isinstance(obj, list):
        return [_json_safe(v) for v in obj]
    return obj


def _load_chat_state(chat_id: str) -> dict:
    cid = _safe_chat_id(chat_id)
    if cid in CHAT_STATE:
        return CHAT_STATE[cid]
    path = _state_path(cid)
    if path.exists():
        try:
            CHAT_STATE[cid] = json.loads(path.read_text(encoding="utf-8"))
            return CHAT_STATE[cid]
        except Exception:
            pass
    CHAT_STATE[cid] = {}
    return CHAT_STATE[cid]


def _save_chat_state(chat_id: str, st: dict) -> None:
    cid = _safe_chat_id(chat_id)
    CHAT_STATE[cid] = st
    try:
        _STATE_DIR.mkdir(parents=True, exist_ok=True)
        _state_path(cid).write_text(json.dumps(_json_safe(st), ensure_ascii=False), encoding="utf-8")
    except Exception:
        pass


def _clear_chat_state(chat_id: str) -> None:
    cid = _safe_chat_id(chat_id)
    CHAT_STATE[cid] = {}
    try:
        _state_path(cid).unlink(missing_ok=True)
    except Exception:
        pass


def _now_str(dt: datetime) -> str:
    return dt.strftime("%Y-%m-%d %H:%M")


def _extract_after(text: str, key: str) -> str:
    if key not in text:
        return ""
    return text.split(key, 1)[1].strip()


def _normalize_digits(s: str) -> str:
    """Convert Arabic/Persian digits to English digits and remove common invisible RTL marks."""
    if s is None:
        return ""
    trans = str.maketrans({
        "٠": "0", "١": "1", "٢": "2", "٣": "3", "٤": "4",
        "٥": "5", "٦": "6", "٧": "7", "٨": "8", "٩": "9",
        "۰": "0", "۱": "1", "۲": "2", "۳": "3", "۴": "4",
        "۵": "5", "۶": "6", "۷": "7", "۸": "8", "۹": "9",
        "\u200e": "", "\u200f": "", "\u061c": "",
    })
    return str(s).translate(trans).strip()


def _parse_int(s: str) -> int | None:
    try:
        return int(_normalize_digits(s))
    except Exception:
        return None


def _detect_branch_choice(t: str) -> int | None:
    """
    Accept robust Arabic/English branch choices:
      - "فرع 1" / "فرع١" / "فرع رقم 1"
      - "branch 1" / "branch #1"
      - "1" when the chat is already waiting for a branch choice
    """
    msg = _normalize_digits(t)

    # Direct numeric answer in the choose-branch step
    if re.fullmatch(r"\s*\d+\s*", msg):
        return _parse_int(msg)

    # Arabic/English branch phrase with optional words/symbols between branch and number
    m = re.search(r"(?:فرع|branch)\s*(?:رقم|number|no\.?|#)?\s*(\d+)", msg, flags=re.IGNORECASE)
    if m:
        return _parse_int(m.group(1))

    # Last fallback: if the message contains exactly one number, use it as the branch choice
    nums = re.findall(r"\d+", msg)
    if len(nums) == 1:
        return _parse_int(nums[0])

    return None


def _detect_book_choice(t: str) -> int | None:
    """
    Accept:
      - احجز 3 / احجز رقم 3
      - book 3 / book #3
      - 3
    """
    msg = _normalize_digits(t)

    m = re.search(r"(?:احجز|book)\s*(?:رقم|number|no\.?|#)?\s*(\d+)", msg, flags=re.IGNORECASE)
    if m:
        return _parse_int(m.group(1))

    if re.fullmatch(r"\d+", msg):
        return _parse_int(msg)

    return None


def _detect_doctor_choice(t: str) -> int | None:
    """
    Accept doctor selection after the bot lists doctors:
      - دكتور 1 / دكتور رقم 1
      - doctor 1 / dr 1
      - 1 when the chat is already waiting for doctor choice
    """
    msg = _normalize_digits(t)

    if re.fullmatch(r"\s*\d+\s*", msg):
        return _parse_int(msg)

    m = re.search(r"(?:دكتور|طبيب|doctor|dr\.?)\s*(?:رقم|number|no\.?|#)?\s*(\d+)", msg, flags=re.IGNORECASE)
    if m:
        return _parse_int(m.group(1))

    nums = re.findall(r"\d+", msg)
    if len(nums) == 1:
        return _parse_int(nums[0])

    return None


def _doctor_display_name(d: dict) -> str:
    return (
        d.get("doctor_name")
        or d.get("FullName")
        or d.get("Name")
        or d.get("DoctorName")
        or f"Doctor {d.get('DoctorID') or d.get('EmployeeID') or ''}"
    ).strip()


def _prepare_doctors_for_display(doctors: list[dict]) -> list[dict]:
    """Attach readable doctor names and normalize ids for frontend/state."""
    if not doctors:
        return []
    ids = [int(d.get("DoctorID") or d.get("EmployeeID")) for d in doctors if d.get("DoctorID") or d.get("EmployeeID")]
    names = get_doctor_names(ids)
    prepared = []
    for d in doctors:
        dd = dict(d)
        did = int(dd.get("DoctorID") or dd.get("EmployeeID"))
        dd["DoctorID"] = did
        dd["doctor_id"] = did
        dd["doctor_name"] = names.get(did, _doctor_display_name(dd))
        prepared.append(dd)
    return prepared


def _detect_lang(text: str) -> str:
    return detect_language(text)


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



def _norm_ar_en(text: str) -> str:
    x = _normalize_digits(text or "").lower()
    x = x.replace("أ", "ا").replace("إ", "ا").replace("آ", "ا")
    return re.sub(r"\s+", " ", x).strip()


def _triage_has_red_flags(text: str) -> bool:
    x = _norm_ar_en(text)

    # Common negative answers should not be treated as red flags just because
    # they mention the word itself, e.g. "مفيش تنميل" / "no numbness".
    negated_red_flags = [
        "مفيش تنميل", "مافيش تنميل", "لا يوجد تنميل", "لا تنميل",
        "مفيش ضعف", "مافيش ضعف", "لا يوجد ضعف", "لا ضعف",
        "مفيش حرارة", "مافيش حرارة", "لا يوجد حرارة", "لا حرارة", "مفيش سخونية", "مافيش سخونية",
        "مفيش حادث", "مافيش حادث", "مفيش اصابة", "مافيش اصابة", "لا يوجد اصابة",
        "مفيش فقدان تحكم", "مافيش فقدان تحكم", "لا يوجد فقدان تحكم",
        "no numbness", "no weakness", "no fever", "no trauma", "no injury",
        "no accident", "no loss of bladder", "no loss of bowel", "not severe", "not bad",
    ]
    strong_positive_red_flags = [
        "عندي تنميل", "في تنميل", "يوجد تنميل", "عندي ضعف", "في ضعف", "يوجد ضعف",
        "عندي حرارة", "في حرارة", "عندي سخونية", "في سخونية", "حصل حادث", "وقعت", "اصابة",
        "i have numbness", "i have weakness", "i have fever", "after trauma", "after injury", "after accident",
    ]
    if any(p in x for p in negated_red_flags) and not any(p in x for p in strong_positive_red_flags):
        return False

    # Important: Arabic users may write "ألم بطن شديد" / "الم بطن شديد".
    # The old check only matched the exact phrase "الم شديد", so it missed
    # "الم بطن شديد" because "بطن" is between them.
    pain_words = [
        "الم", "الام", "وجع", "واجع", "يوجع", "بيوجع", "مغص", "تقلص", "تقلصات",
        "صداع", "صداع نصفي", "حرقان", "حرقه", "حارق", "نغز", "نغزة", "نغزات",
        "شد", "تشنج", "تشنجات", "وجعان", "مؤلم", "مؤلمة",
        "pain", "painful", "ache", "aching", "sore", "soreness", "cramp", "cramps",
        "cramping", "burning", "stinging", "sharp pain", "throbbing", "migraine",
        "headache", "spasm", "spasms",
    ]
    severe_words = [
        "شديد", "شديدة", "شده", "جامد", "جامدة", "جدا", "جداً", "اوي", "قوي",
        "قوية", "فظيع", "فظيعة", "رهيب", "رهيبة", "قاتل", "مش قادر استحمل",
        "مش قادرة استحمل", "مش محتمل", "مش محتملة", "لا يحتمل", "لا تحتمل",
        "غير محتمل", "غير محتملة", "مستمر", "مستمرة", "مفاجئ", "مفاجئة",
        "severe", "very severe", "extreme", "intense", "bad", "very bad",
        "unbearable", "intolerable", "worst", "constant", "persistent", "sudden",
    ]
    if any(p in x for p in pain_words) and any(sv in x for sv in severe_words):
        return True

    red_flags = [
        "تنميل", "خدر", "ضعف", "مش قادر امشي", "مش قادرة امشي", "شلل",
        "فقدان تحكم", "عدم تحكم", "بول", "براز", "سلس بول", "سلس براز",
        "سخونية", "حرارة", "حراره", "حمى", "حمي",
        "حرارة عالية", "حراره عاليه", "سخونية عالية",
        "حادث", "وقعت", "وقوع", "اصابة", "نزيف",
        "الم صدر", "وجع صدر", "ضيق نفس", "نهجان شديد", "عرق شديد",
        "الم شديد", "شديد جدا", "صداع شديد", "صداع مفاجئ",
        "قيء دم", "ترجيع دم", "استفراغ دم", "اغماء", "فقدت الوعي",
        "دوخة شديدة", "زغللة شديدة", "تشنجات", "براز اسود", "دم في البراز",

        "numbness", "weakness", "cannot walk", "can not walk", "paralysis",
        "loss of bladder control", "loss of bowel control", "urine leakage", "stool leakage",
        "fever", "high fever", "heat", "hot", "after trauma", "after injury", "after accident",
        "bleeding", "severe chest pain", "chest pain", "shortness of breath", "heavy sweating",
        "severe pain", "very severe", "unbearable pain", "sudden headache", "severe headache",
        "vomiting blood", "bloody vomit", "fainting", "passed out", "severe dizziness",
        "seizure", "seizures", "black stool", "blood in stool",
    ]
    return any(k in x for k in red_flags)


def _triage_is_reassuring(text: str) -> bool:
    x = _norm_ar_en(text)
    reassuring = [
        "لا", "لأ", "لا يوجد", "مفيش", "مافيش", "مش موجود", "لا مفيش", "لا لا",
        "no", "nope", "none", "not severe", "no fever", "no numbness", "no weakness",
        "خفيف", "خفيفه", "خفيفة", "بسيط", "بسيطه", "بسيطة", "حاجة بسيطة", "حاجه بسيطه", "متوسط", "محتمل", "normal", "mild", "moderate", "simple"
    ]
    # If the same message contains a red flag, red flag wins.
    return any(k in x for k in reassuring) and not _triage_has_red_flags(text)


def _triage_questions_for_specialty(specialty_name: str | None, lang: str) -> str:
    spec = (specialty_name or "").lower()

    if lang == "ar":
        if "internal" in spec or "medicine" in spec:
            return (
                f"قبل ما أعرض مواعيد {specialty_name or 'الباطنة'}، محتاجة أتأكد من شدة الحالة.\n"
                "هل عندك أي علامة من دول؟\n"
                "- ألم بطن شديد جدًا أو مستمر\n"
                "- قيء مستمر، قيء دم، أو براز أسود/دم\n"
                "- حرارة عالية، دوخة شديدة، أو إغماء\n\n"
                "اكتبي: لا مفيش، أو اكتبي العلامة الموجودة."
            )
        if "cardio" in spec:
            return (
                f"قبل ما أعرض مواعيد {specialty_name or 'القلب'}، محتاجة أتأكد من شدة الحالة.\n"
                "هل في ألم صدر شديد، ضيق نفس، عرق شديد، أو إغماء؟\n\n"
                "اكتبي: لا مفيش، أو اكتبي العلامة الموجودة."
            )
        if "neuro" in spec:
            return (
                f"قبل ما أعرض مواعيد {specialty_name or 'المخ والأعصاب'}، محتاجة أتأكد من شدة الحالة.\n"
                "هل في صداع شديد مفاجئ، تشنجات، إغماء، ضعف/تنميل في طرف، أو زغللة شديدة؟\n\n"
                "اكتبي: لا مفيش، أو اكتبي العلامة الموجودة."
            )
        return (
            f"قبل ما أعرض مواعيد {specialty_name or 'التخصص المناسب'}، محتاجة أتأكد من شدة الحالة.\n"
            "هل عندك أي علامة من دول؟\n"
            "- ألم شديد جدًا أو بعد حادث/وقعة\n"
            "- تنميل أو ضعف في الرجل/الذراع\n"
            "- حرارة عالية أو أعراض شديدة مفاجئة\n\n"
            "اكتبي: لا مفيش، أو اكتبي العلامة الموجودة."
        )

    if "internal" in spec or "medicine" in spec:
        return (
            f"Before I show {specialty_name or 'Internal Medicine'} slots, I need to check severity.\n"
            "Do you have any of these red flags?\n"
            "- very severe or persistent abdominal pain\n"
            "- repeated vomiting, vomiting blood, or black/bloody stool\n"
            "- high fever, severe dizziness, or fainting\n\n"
            "Reply: no, or describe the red flag."
        )
    if "cardio" in spec:
        return (
            f"Before I show {specialty_name or 'Cardiology'} slots, I need to check severity.\n"
            "Do you have severe chest pain, shortness of breath, heavy sweating, or fainting?\n\n"
            "Reply: no, or describe the red flag."
        )
    if "neuro" in spec:
        return (
            f"Before I show {specialty_name or 'Neurology'} slots, I need to check severity.\n"
            "Do you have sudden severe headache, seizures, fainting, weakness/numbness, or severe vision changes?\n\n"
            "Reply: no, or describe the red flag."
        )
    return (
        f"Before I show {specialty_name or 'the suitable specialty'} slots, I need to check severity.\n"
        "Do you have any of these red flags?\n"
        "- very severe pain or pain after trauma/fall\n"
        "- numbness or weakness in a leg/arm\n"
        "- high fever or sudden severe symptoms\n\n"
        "Reply: no, or describe the red flag."
    )



def _pharmacy_symptom_triage_question(specialty_name: str | None, lang: str) -> str:
    """Ask safety triage before recommending OTC/pharmacy products for symptom-like requests.

    Example: "عايزة حاجة للمغص" should not immediately recommend medicine if the
    user has red flags. We first ask the same safety options; then:
    - dangerous/red flag -> doctor booking flow
    - simple/no red flags -> pharmacy recommendations
    """
    base = _triage_questions_for_specialty(specialty_name, lang)
    if lang == "ar":
        return (
            base
            + "\n\nلو الحالة بسيطة ومفيش أي علامة من دول، اكتبي: بسيطة أو لا مفيش، "
              "وساعتها أرشحلك أسماء أدوية/منتجات من صيدلية المستشفى."
            + "\nلو عايزة تحجزي دكتور مباشرة اكتبي: احجز دكتور."
        )
    return (
        base
        + "\n\nIf it is mild/simple and none of these red flags exist, type: simple or no, "
          "then I will recommend products from the hospital pharmacy."
        + "\nIf you want to book a doctor directly, type: book doctor."
    )


def _start_pharmacy_symptom_triage(st: dict, chat_id: str, sid: int, query: str, lang: str) -> dict:
    spec = get_specialty_by_id(int(sid))
    st["pending_step"] = "pharmacy_symptom_triage"
    st["pending_specialty_id"] = int(sid)
    st["pending_pharmacy_query"] = query
    _save_chat_state(chat_id, st)
    return {
        "intent": "pharmacy_symptom_triage",
        "reply": _pharmacy_symptom_triage_question(spec.get("Name") if spec else None, lang),
        "data": {"specialty_id": sid, "specialty": spec, "pharmacy_query": query},
    }


def _recommend_after_simple_triage(st: dict, chat_id: str, query: str, lang: str) -> dict:
    items = recommend_products(query, limit=5)
    st["pending_step"] = None
    st.pop("pending_pharmacy_query", None)
    if items:
        st["last_product_recommendations"] = items
        _save_chat_state(chat_id, st)
        return {
            "intent": "product_recommendation",
            "reply": format_recommendations(items, lang=lang),
            "data": {"products": items},
        }
    _save_chat_state(chat_id, st)
    return {
        "intent": "product_recommendation_not_found",
        "reply": (
            "الحالة بسيطة حسب كلامك، لكن مش لاقية منتج مناسب في ملف الصيدلية الحالي. ممكن تسألي الصيدلي أو تحجزي دكتور لو الأعراض مستمرة."
            if lang == "ar" else
            "Based on your answer it sounds mild, but I could not find a suitable product in the current pharmacy file. Please ask the pharmacist or book a doctor if symptoms continue."
        ),
        "data": {"products": []},
    }


def _begin_booking_after_red_flag(st: dict, chat_id: str, sid: int | None, lang: str) -> dict:
    if sid is None:
        return {
            "intent": "symptom_triage_urgent",
            "reply": (
                "في علامة محتاجة تقييم طبي. الأفضل تحجزي دكتور أو تتواصلي مع الطوارئ لو الحالة شديدة."
                if lang == "ar" else
                "There is a red flag that needs medical assessment. Please book a doctor or contact emergency care if severe."
            ),
            "data": {"red_flags": True},
        }
    result = _begin_specialty_branch_choice(st, chat_id, int(sid), lang)
    prefix = (
        "طالما في علامة خطورة أو أعراض شديدة، الأفضل نحجز دكتور بدل ما أرشح دواء فقط.\n\n"
        if lang == "ar" else
        "Because there is a red flag or severe symptom, it is safer to book a doctor instead of only recommending medicine.\n\n"
    )
    result["intent"] = "symptom_triage_red_flag_booking"
    result["reply"] = prefix + result.get("reply", "")
    result.setdefault("data", {})["red_flags"] = True
    return result


def _begin_specialty_branch_choice(st: dict, chat_id: str, sid: int, lang: str) -> dict:
    spec = get_specialty_by_id(int(sid))
    branches = list_branches_for_specialty(int(sid))

    if not branches:
        return {
            "intent": "no_branches_for_specialty",
            "reply": "فهمت الأعراض، بس مش لاقي فروع فيها دكاترة للتخصص ده حاليًا." if lang == "ar" else "I understood the symptoms, but I cannot find branches with doctors for this specialty now.",
            "data": {"specialty_id": sid, "specialty": spec},
        }

    st["pending_specialty_id"] = int(sid)
    st["pending_branches"] = branches
    st["pending_step"] = "choose_branch"
    _save_chat_state(chat_id, st)

    spec_name = spec["Name"] if spec else f"Specialty {sid}"
    if lang == "ar":
        lines = [
            f"ده غالبًا يخص تخصص: {spec_name}.",
            "اختاري الفرع اللي تحبي تحجزي فيه:",
        ]
        for i, b in enumerate(branches, start=1):
            lines.append(f"{i}) {b['Name']}")
        lines.append("\nاكتبي: فرع <رقم> (مثال: فرع 1)")
    else:
        lines = [
            f"This most likely matches: {spec_name}.",
            "Choose the branch you want to book in:",
        ]
        for i, b in enumerate(branches, start=1):
            lines.append(f"{i}) {b['Name']}")
        lines.append("\nType: branch <number> (example: branch 1)")

    return {
        "intent": "choose_branch",
        "reply": "\n".join(lines),
        "data": {"specialty": spec, "branches": branches},
    }


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




def _parse_quantity_from_text(text: str) -> int | None:
    msg = _normalize_digits(text or "")
    m = re.search(r"(?:كمية|quantity|qty|عدد)\s*(\d+)", msg, flags=re.IGNORECASE)
    if m:
        return _parse_int(m.group(1))
    nums = re.findall(r"\d+", msg)
    if nums:
        # Use the first small number as quantity. Long numbers are probably phone numbers.
        for n in nums:
            try:
                v = int(n)
                if 1 <= v <= 50:
                    return v
            except Exception:
                pass
    return None


def _select_product_from_message(text: str, products: list[dict]) -> dict | None:
    msg = _normalize_digits(text or "").strip()
    if products:
        m = re.search(r"(?:اطلب|order|اوردر|اشتري)?\s*(?:رقم|number|#)?\s*(\d+)", msg, flags=re.IGNORECASE)
        if m:
            idx = _parse_int(m.group(1))
            if idx is not None and 1 <= idx <= len(products):
                return products[idx - 1]

    # If user says yes/order it, choose the first available item, or first item if all out of stock.
    if products and is_product_order_request(msg):
        available = [p for p in products if int(p.get("quantity") or 0) > 0]
        return available[0] if available else products[0]

    found = find_products_by_name(msg, limit=3)
    if found:
        return found[0]
    return None


def _ask_for_product_order_details(product: dict, lang: str) -> dict:
    q = int(product.get("quantity") or 0)
    if lang == "en":
        if q <= 0:
            reply = (
                f"{product['brand_name']} is out of stock right now. I can still save your request so the pharmacy follows up when available.\n"
                "Please send quantity and phone/note, example: quantity 1 phone 010xxxxxxxx"
            )
        else:
            reply = (
                f"Okay, I can take an order request for {product['brand_name']}. Current stock: {q}.\n"
                "Please send quantity and phone/note, example: quantity 1 phone 010xxxxxxxx"
            )
    else:
        if q <= 0:
            reply = (
                f"{product['brand_name']} غير متوفر حاليًا. أقدر أسجل طلب متابعة للصيدلية لو حبيتي.\n"
                "اكتبي الكمية ورقم التليفون/ملاحظة، مثال: الكمية 1 تليفون 010xxxxxxxx"
            )
        else:
            reply = (
                f"تمام، هسجل طلب أوردر لـ {product['brand_name']}. الكمية المتاحة حاليًا: {q}.\n"
                "اكتبي الكمية ورقم التليفون/ملاحظة، مثال: الكمية 1 تليفون 010xxxxxxxx"
            )
    return {"intent": "pharmacy_order_details_needed", "reply": reply, "data": {"product": product}}


def _complete_product_order(st: dict, chat_id: str, text: str, lang: str) -> dict:
    product = st.get("pending_product_order")
    if not product:
        st["pending_step"] = None
        _save_chat_state(chat_id, st)
        return {
            "intent": "pharmacy_order_missing_product",
            "reply": "مش لاقية المنتج المختار. ابعتي الترشيح أو اسم المنتج تاني." if lang == "ar" else "I lost the selected product. Please send the product name again.",
            "data": None,
        }

    qty = _parse_quantity_from_text(text) or 1
    order = create_pharmacy_order(product, qty, customer_note=text, chat_id=chat_id)
    st["pending_step"] = None
    st["pending_product_order"] = None
    st["last_pharmacy_order"] = order
    _save_chat_state(chat_id, st)

    if lang == "en":
        reply = (
            f"Done. I saved your pharmacy order request for {order['brand_name']} x {order['requested_quantity']}.\n"
            "Status: pending pharmacy confirmation. Please wait for the pharmacy team to confirm availability and delivery/pickup details."
        )
    else:
        reply = (
            f"تمام، سجلت طلب الصيدلية: {order['brand_name']} × {order['requested_quantity']}.\n"
            "الحالة: في انتظار تأكيد الصيدلية للكمية وطريقة الاستلام/التوصيل."
        )
    return {"intent": "pharmacy_order_created", "reply": reply, "data": {"order": order}}

def handle_chat(text: str, chat_id: str = "web", patient_id: int | None = None) -> dict:
    raw_t = (text or "").strip()
    norm = normalize_for_chat(raw_t)
    t = norm.chat_text or raw_t
    lang = norm.language

    if not raw_t:
        return {
            "reply": "اكتب رسالتك." if lang == "ar" else "Type your message.",
            "data": None,
            "intent": "empty",
        }

    chat_id = _safe_chat_id(chat_id or "web")
    if t.lower() in {"ابدأ من جديد", "ابدا من جديد", "reset", "restart", "start over", "new chat"}:
        _clear_chat_state(chat_id)
        return {
            "intent": "reset",
            "reply": "تمام، بدأت محادثة جديدة. اكتبي الأعراض أو طلب الحجز من الأول." if lang == "ar" else "Done, I started a new chat. Send your symptoms or booking request again.",
            "data": None,
        }

    st = _load_chat_state(chat_id)

    # sync patient_id into state if provided
    if patient_id is not None:
        st["patient_id"] = int(patient_id)
        _save_chat_state(chat_id, st)

    # ------------------------------------------------------------
    # 0) Branch choice must be handled before numeric booking / PatientID.
    #    Otherwise a plain "1" while choosing a branch can be mistaken for
    #    PatientID or appointment slot number.
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

        spec = get_specialty_by_id(int(sid))
        doctors = _prepare_doctors_for_display(list_doctors_for_specialty_in_branch(int(sid), branch_id))

        if not doctors:
            return {
                "intent": "no_doctors_for_specialty_branch",
                "reply": f"مفيش دكاترة متاحين حاليًا في {branch['Name']} للتخصص ده. جرّبي فرع تاني.",
                "data": {"branch": branch, "specialty_id": sid, "specialty": spec},
            }

        st["pending_step"] = "choose_doctor"
        st["pending_branch"] = branch
        st["pending_doctors"] = doctors
        st["last_slots"] = []
        st["pending_branches"] = None
        _save_chat_state(chat_id, st)

        spec_name = spec.get("Name") if spec else "التخصص المطلوب"
        if lang == "en":
            lines = [
                f"In {branch['Name']}, the {spec_name} department has these doctors:",
            ]
            for i, d in enumerate(doctors, start=1):
                fee = d.get("ConsultationFee")
                extra = f" - fee: {fee}" if fee not in (None, "") else ""
                lines.append(f"{i}) Dr. {_doctor_display_name(d)}{extra}")
            lines.append("\nChoose a doctor number, for example: doctor 1")
        else:
            lines = [
                f"في {branch['Name']}، قسم {spec_name} عندنا فيه الدكاترة دول:",
            ]
            for i, d in enumerate(doctors, start=1):
                fee = d.get("ConsultationFee")
                extra = f" - كشف: {fee}" if fee not in (None, "") else ""
                lines.append(f"{i}) دكتور {_doctor_display_name(d)}{extra}")
            lines.append("\nاختاري دكتور برقم. مثال: دكتور 1")

        return {
            "intent": "choose_doctor",
            "reply": "\n".join(lines),
            "data": {"specialty": spec, "branch": branch, "doctors": doctors},
        }


    # ------------------------------------------------------------
    # 0.25) Doctor choice after branch selection.
    #      New flow: symptoms -> specialty -> branch -> doctor -> slots -> book.
    # ------------------------------------------------------------
    if st.get("pending_step") == "choose_doctor":
        choice = _detect_doctor_choice(t)
        doctors = st.get("pending_doctors") or []
        branch = st.get("pending_branch") or {}

        if choice is None or choice < 1 or choice > len(doctors):
            return {
                "intent": "choose_doctor",
                "reply": f"اختاري رقم دكتور صحيح (1 - {len(doctors)}). مثال: دكتور 1" if lang == "ar" else f"Choose a valid doctor number (1 - {len(doctors)}). Example: doctor 1",
                "data": {"branch": branch, "doctors": doctors},
            }

        doctor = doctors[choice - 1]
        doctor_id = int(doctor.get("DoctorID") or doctor.get("doctor_id") or doctor.get("EmployeeID"))
        branch_id = int(branch.get("BranchID"))
        doctor_name = _doctor_display_name(doctor)

        from datetime import timedelta
        slots = get_available_slots(
            doctor_id=doctor_id,
            branch_id=branch_id,
            from_dt=datetime.now(),
            to_dt=datetime.now() + timedelta(days=14),
        )

        if not slots:
            return {
                "intent": "no_slots",
                "reply": f"مفيش مواعيد متاحة قريبًا للدكتور {doctor_name}. اختاري دكتور تاني من القائمة." if lang == "ar" else f"No near slots are available for Dr. {doctor_name}. Choose another doctor from the list.",
                "data": {"doctor": doctor, "branch": branch, "doctors": doctors},
            }

        st["last_slots"] = [
            {
                "doctor_id": doctor_id,
                "doctor_name": doctor_name,
                "branch_id": branch_id,
                "start": s.get("start").isoformat() if isinstance(s.get("start"), datetime) else s.get("start"),
                "end": s.get("end").isoformat() if isinstance(s.get("end"), datetime) else s.get("end"),
            }
            for s in slots[:15]
        ]
        st["pending_step"] = None
        st["pending_doctors"] = None
        st["pending_branch"] = None
        _save_chat_state(chat_id, st)

        if lang == "en":
            lines = [f"Available slots for Dr. {doctor_name} in {branch.get('Name', 'this branch')}:"]
            for i, s in enumerate(slots[:15], start=1):
                start = s.get("start")
                start_str = _now_str(start) if isinstance(start, datetime) else str(start)
                lines.append(f"{i}) {start_str}")
            lines.append("\nType: book <number> (example: book 2)")
        else:
            lines = [f"المواعيد المتاحة للدكتور {doctor_name} في {branch.get('Name', 'الفرع')}:" ]
            for i, s in enumerate(slots[:15], start=1):
                start = s.get("start")
                start_str = _now_str(start) if isinstance(start, datetime) else str(start)
                lines.append(f"{i}) {start_str}")
            lines.append("\nاكتبي: احجز <رقم> (مثال: احجز 2)")

        return {
            "intent": "doctor_slots",
            "reply": "\n".join(lines),
            "data": {"doctor": doctor, "branch": branch, "slots": st["last_slots"]},
        }


    # ------------------------------------------------------------
    # 0.5) If we are waiting for PatientID to complete a booking
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
        _save_chat_state(chat_id, st)

        pending = st.get("pending_booking_slot")
        if not pending:
            return {
                "intent": "ok",
                "reply": "تمام. قوليلي (احجز رقم) تاني." if lang == "ar" else "Okay. Tell me (book number) again.",
                "data": None,
            }

        st["pending_booking_slot"] = None
        _save_chat_state(chat_id, st)
        return _book_slot_with_patient(pid, pending)

    # ------------------------------------------------------------
    # 0.70) If user asks for pharmacy product for a symptom, do NOT
    # immediately recommend medicine. Ask the same safety options first:
    # dangerous/red flag -> doctor booking, simple/no red flags -> products.
    # ------------------------------------------------------------
    if st.get("pending_step") == "symptom_triage" and is_product_recommendation_question(t):
        sid_for_query = suggest_specialty_id(t) or st.get("pending_specialty_id")
        if sid_for_query is not None:
            return _start_pharmacy_symptom_triage(st, chat_id, int(sid_for_query), t, lang)

    # ------------------------------------------------------------
    # 0.72) Pharmacy symptom triage: after the bot asks safety options for
    # requests like "عايزة حاجة للمغص".
    # ------------------------------------------------------------
    if st.get("pending_step") == "pharmacy_symptom_triage":
       sid = st.get("pending_specialty_id")
       original_query = st.get("pending_pharmacy_query") or t

       if _triage_has_red_flags(t):
          st.pop("pending_pharmacy_query", None)
          st["pending_step"] = None
          _save_chat_state(chat_id, st)
          return _begin_booking_after_red_flag(st, chat_id, int(sid) if sid is not None else None, lang)

       if _is_booking_start(t):
          st.pop("pending_pharmacy_query", None)
          st["pending_step"] = None
          _save_chat_state(chat_id, st)
          if sid is not None:
             return _begin_specialty_branch_choice(st, chat_id, int(sid), lang)

       if _triage_is_reassuring(t) or any(k in _norm_ar_en(t) for k in ["بسيط", "بسيطه", "بسيطة", "حاجه بسيطه", "حاجة بسيطة", "simple", "mild"]):
          return _recommend_after_simple_triage(st, chat_id, original_query, lang)

       st.pop("pending_pharmacy_query", None)
       st["pending_step"] = None
       _save_chat_state(chat_id, st)
       return handle_chat(raw_t, chat_id=chat_id, patient_id=patient_id)

    # ------------------------------------------------------------
    # 0.75) Symptom severity triage before booking slots
    # ------------------------------------------------------------
    if st.get("pending_step") == "symptom_triage":
        sid = st.get("pending_specialty_id")
        if _triage_has_red_flags(t):
            # User already said a dangerous/severe symptom after the safety question.
            # Do not repeat the same question; move directly to doctor booking options.
            st["pending_step"] = None
            _save_chat_state(chat_id, st)
            return _begin_booking_after_red_flag(st, chat_id, int(sid) if sid is not None else None, lang)

        if _triage_is_reassuring(t) or any(k in _norm_ar_en(t) for k in ["متابعة الحجز", "كمل", "continue booking", "continue"]):
            st["pending_step"] = None
            _save_chat_state(chat_id, st)
            if sid is not None:
                return _begin_specialty_branch_choice(st, chat_id, int(sid), lang)

        spec = get_specialty_by_id(int(sid)) if sid is not None else None
        return {
            "intent": "symptom_triage",
            "reply": _triage_questions_for_specialty(spec.get("Name") if spec else None, lang),
            "data": {"specialty_id": sid},
        }

    # ------------------------------------------------------------
    # 0.9) Pharmacy product recommendation / comparison / order flow
    # ------------------------------------------------------------
    if st.get("pending_step") == "pharmacy_choose_product":
        products = st.get("last_product_recommendations") or []
        selected = _select_product_from_message(t, products)
        if not selected:
            return {
                "intent": "pharmacy_choose_product",
                "reply": "اختاري رقم منتج من الترشيحات أو اكتبي اسم المنتج. مثال: اطلب رقم 1" if lang == "ar" else "Choose a product number from the recommendations or type the product name. Example: order 1",
                "data": {"products": products},
            }
        st["pending_product_order"] = selected
        st["pending_step"] = "pharmacy_order_details"
        _save_chat_state(chat_id, st)
        return _ask_for_product_order_details(selected, lang)

    if st.get("pending_step") == "pharmacy_order_details":
        return _complete_product_order(st, chat_id, t, lang)

    # If the previous bot reply recommended products and user now says "order it".
    if is_product_order_request(t) and st.get("last_product_recommendations"):
        selected = _select_product_from_message(t, st.get("last_product_recommendations") or [])
        if selected:
            st["pending_product_order"] = selected
            st["pending_step"] = "pharmacy_order_details"
            _save_chat_state(chat_id, st)
            return _ask_for_product_order_details(selected, lang)
        st["pending_step"] = "pharmacy_choose_product"
        _save_chat_state(chat_id, st)
        return {
            "intent": "pharmacy_choose_product",
            "reply": "اختاري رقم المنتج اللي عايزة تطلبيه من آخر ترشيحات." if lang == "ar" else "Choose the product number you want to order from the last recommendations.",
            "data": {"products": st.get("last_product_recommendations") or []},
        }

    if is_product_comparison_question(t):
        items = compare_products(t)
        if len(items) >= 2:
            st["last_product_recommendations"] = items
            _save_chat_state(chat_id, st)
            return {
                "intent": "product_comparison",
                "reply": format_comparison(items, lang=lang, question=raw_t),
                "data": {"products": items},
            }

    if is_product_recommendation_question(t):
        # If the request is symptom-like (e.g. "عايزة حاجة للمغص"),
        # ask safety triage before recommending pharmacy products.
        # Non-symptom product needs like skincare continue directly.
        sid_for_query = suggest_specialty_id(t)
        if sid_for_query is not None:
            return _start_pharmacy_symptom_triage(st, chat_id, int(sid_for_query), t, lang)

        items = recommend_products(t, limit=5)
        if items:
            st["last_product_recommendations"] = items
            _save_chat_state(chat_id, st)
            return {
                "intent": "product_recommendation",
                "reply": format_recommendations(items, lang=lang),
                "data": {"products": items},
            }

    # ------------------------------------------------------------
    # 1) Appointment offers
    # ------------------------------------------------------------
    if any(k in t.lower() for k in ["offer", "offers", "discount", "package", "عرض", "عروض", "خصم", "باكدج", "باقة"]):
        return reply_for_offers(t, st, lang=lang)

    # ------------------------------------------------------------
    # 1) Drug interaction / medication risks
    # ------------------------------------------------------------
    if is_drug_interaction_question(t):
        meds = split_med_names(t)
        if len(meds) >= 2 or any(k in t.lower() for k in ["تعارض", "تداخل", "interaction", "interact", "safe with", "ينفع", "مع"]):
            return reply_for_drug_interaction_question(t, lang=lang)

        if not meds:
            return {
                "intent": "med_need_names",
                "reply": "اكتبي أسماء الأدوية. مثال: مخاطر Aspirin و Ibuprofen",
                "data": None,
            }

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
            _save_chat_state(chat_id, st)
            return {
                "intent": "need_patient_id",
                "reply": "لازم تدخلي PatientID عشان أعمل الحجز." if lang == "ar" else "You need to enter PatientID to make the booking.",
                "data": {"need": "patient_id"},
            }

        return _book_slot_with_patient(int(pid), chosen)

    # ------------------------------------------------------------
    # 3) Symptom -> Specialty -> choose branch -> show slots -> book
    # ------------------------------------------------------------
    sid = suggest_specialty_id(t)
    if sid is not None:
        spec = get_specialty_by_id(int(sid))
        # Triage before showing slots. If the original symptom message already
        # contains red flags, warn immediately. Otherwise ask a short safety check.
        if _triage_has_red_flags(t):
            # Direct severe symptom like "ألم بطن شديد" should not ask the triage
            # question again. It should advise doctor booking and show branch options.
            return _begin_booking_after_red_flag(st, chat_id, int(sid), lang)

        st["pending_specialty_id"] = int(sid)
        st["pending_step"] = "symptom_triage"
        _save_chat_state(chat_id, st)
        spec_name = spec.get("Name") if spec else None
        return {
            "intent": "symptom_triage",
            "reply": _triage_questions_for_specialty(spec_name, lang),
            "data": {"specialty_id": sid, "specialty": spec},
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

        _save_chat_state(chat_id, st)

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
    # 5) Do not send bare numbers/branch choices to RAG if state was lost.
    # ------------------------------------------------------------
    if _detect_branch_choice(t) is not None or _detect_book_choice(t) is not None:
        return {
            "intent": "lost_context",
            "reply": "الرقم وصل، لكن حالة الحجز مش موجودة. اكتبي الأعراض من الأول أو اكتبي: ابدأ من جديد" if lang == "ar" else "I received the number, but the booking context is missing. Send your symptoms again or type: reset",
            "data": None,
        }

    # ------------------------------------------------------------
    # 6) Fallback help
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
