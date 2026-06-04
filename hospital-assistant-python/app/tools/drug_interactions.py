from __future__ import annotations

import re
from app.tools.medications import find_multiple_medications, get_medication_warnings, find_medications_in_text


def _norm(s: str) -> str:
    s = (s or "").lower()
    s = s.replace("أ", "ا").replace("إ", "ا").replace("آ", "ا")
    s = re.sub(r"[^a-z0-9\u0600-\u06ff\s]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def _names_for_med(med: dict) -> list[str]:
    names = []
    for key in ["TradeName", "GenericName", "Category"]:
        val = med.get(key)
        if val:
            names.append(str(val))
            # Split combined generic names like Amoxicillin/Clavulanate.
            for part in re.split(r"[/,+&]", str(val)):
                if part.strip():
                    names.append(part.strip())
    return list(dict.fromkeys(names))


def is_drug_interaction_question(text: str) -> bool:
    t = _norm(text)
    triggers = [
        # English interaction/risk wording
        "drug interaction", "interactions", "interaction", "interact",
        "safe with", "risk with", "warning with",
        "risk", "risks", "danger", "dangers", "dangerous",
        "harm", "harms", "side effect", "side effects", "adverse effect",
        "warning", "warnings", "contraindication",
        # Common user typos for danger/risk words
        "denger", "dengerace", "dangar", "dangr", "risc", "riks",
        # Arabic wording
        "تعارض", "تداخل", "يتعارض", "ينفع", "امان", "آمن", "امن", "مع",
        "مخاطر", "خطر", "خطورة", "تحذيرات", "تحذير", "اضرار", "أضرار", "ضرر", "اعراض جانبية", "أعراض جانبية",
    ]
    return any(x in t for x in triggers)


def _merge_medication_lists(primary: list[dict], scanned: list[dict]) -> list[dict]:
    """Merge medication rows while preserving order and removing duplicates.

    This is important for follow-up questions enriched by memory, e.g.
    "بالنسبة لـ Aspirin: ومع Brufen ينفع؟". The old splitter could treat
    long text fragments as one medication name, while scanning the full text
    can still find Aspirin and Brufen inside the sentence.
    """
    merged: list[dict] = []
    seen: set[str] = set()
    for med in (primary or []) + (scanned or []):
        if not isinstance(med, dict):
            continue
        key = str(med.get("MedicationID") or med.get("TradeName") or med.get("GenericName") or "").lower()
        if not key or key in seen:
            continue
        seen.add(key)
        merged.append(med)
    return merged


def reply_for_drug_interaction_question(text: str, lang: str = "ar") -> dict:
    multi = find_multiple_medications(text)
    meds = multi.get("found", [])
    not_found = multi.get("not_found", [])

    # Robust fallback for contextual follow-ups and mixed Arabic/English sentences.
    # Example: previous memory adds "Aspirin" then user says "ومع Brufen ينفع؟".
    scanned = find_medications_in_text(text, limit=6)
    if scanned:
        meds = _merge_medication_lists(meds, scanned)
        found_names = {str(m.get("TradeName") or m.get("GenericName") or "").lower() for m in meds}
        # If the splitter produced noisy not_found fragments that actually contain
        # a scanned medication, do not show them as missing to the user.
        cleaned_not_found = []
        for nf in not_found:
            nf_l = str(nf).lower()
            if any(name and name in nf_l for name in found_names):
                continue
            cleaned_not_found.append(nf)
        # If we now have enough medications, suppress noisy sentence-fragment misses.
        not_found = cleaned_not_found if len(meds) < 2 else []

    is_ar = lang == "ar"

    if len(meds) < 2:
        if not_found:
            nf = "، ".join(not_found) if is_ar else ", ".join(not_found)
            return {
                "intent": "drug_interaction",
                "reply": (
                    f"مش لاقية الدواء/الأدوية دي في جدول medications: {nf}. اكتبي اسمين أدوية واضحين، مثال: ما مخاطر Warfarin مع Aspirin؟"
                    if is_ar else
                    f"I could not find these medication names in the medications table: {nf}. Send two clear medication names, e.g. Warfarin with Aspirin."
                ),
                "data": {"found": meds, "not_found": not_found},
            }
        return {
            "intent": "drug_interaction_need_names",
            "reply": "اكتبي اسمين أدوية عشان أراجع التعارض بينهم. مثال: ما مخاطر Warfarin مع Aspirin؟" if is_ar else "Send two medication names so I can check the interaction. Example: Warfarin with Aspirin.",
            "data": {"found": meds, "not_found": not_found},
        }

    # Keep the first two distinct medications for pair answer.
    med_a, med_b = meds[0], meds[1]
    warnings_a = get_medication_warnings(int(med_a["MedicationID"]))
    warnings_b = get_medication_warnings(int(med_b["MedicationID"]))

    b_terms = [_norm(x) for x in _names_for_med(med_b) if _norm(x)]
    a_terms = [_norm(x) for x in _names_for_med(med_a) if _norm(x)]

    direct = []
    for w in warnings_a:
        desc = _norm(w.get("Description", ""))
        if any(term and term in desc for term in b_terms):
            direct.append((med_a, w))
    for w in warnings_b:
        desc = _norm(w.get("Description", ""))
        if any(term and term in desc for term in a_terms):
            direct.append((med_b, w))

    name_a = med_a.get("TradeName") or med_a.get("GenericName")
    name_b = med_b.get("TradeName") or med_b.get("GenericName")

    lines = [
        f"نتيجة مراجعة التعارض بين {name_a} و {name_b}:"
        if is_ar else
        f"Interaction check between {name_a} and {name_b}:"
    ]

    if direct:
        lines.append("يوجد تحذير مباشر مسجل في قاعدة البيانات:" if is_ar else "A direct warning is recorded in the database:")
        for med, w in direct[:8]:
            lines.append(f"- {med.get('TradeName')}: [{w.get('WarningType')}] {w.get('Description')}")
    else:
        lines.append(
            "لا يوجد تعارض مباشر مكتوب بالاسمين معًا في جدول drug_interactions."
            if is_ar else
            "No direct interaction mentioning both names together is recorded in drug_interactions."
        )
        all_warnings = []
        for med, warnings in [(med_a, warnings_a), (med_b, warnings_b)]:
            for w in warnings[:4]:
                all_warnings.append((med, w))
        if all_warnings:
            lines.append(
                "لكن دي التحذيرات المسجلة لكل دواء في قاعدة البيانات:"
                if is_ar else
                "However, these warnings are recorded for each medication:"
            )
            for med, w in all_warnings:
                lines.append(f"- {med.get('TradeName')}: [{w.get('WarningType')}] {w.get('Description')}")
        else:
            lines.append(
                "وكمان مفيش تحذيرات مسجلة لأي واحد منهم في قاعدة البيانات الحالية."
                if is_ar else
                "There are also no recorded warnings for either medication in the current database."
            )

    if not_found:
        lines.append(("\nأدوية لم أجدها بالاسم المكتوب: " if is_ar else "\nMedication names I could not find: ") + ("، ".join(not_found) if is_ar else ", ".join(not_found)))

    lines.append("\nتنبيه: لا تبدأي أو توقفي أي دواء بدون الرجوع للطبيب أو الصيدلي." if is_ar else "\nNote: Do not start or stop any medication without checking with a doctor or pharmacist.")
    return {
        "intent": "drug_interaction",
        "reply": "\n".join(lines),
        "data": {"medications": meds, "not_found": not_found, "direct_matches": len(direct)},
    }
