from __future__ import annotations
import re

def norm_ar(s: str) -> str:
    s = (s or "").strip().lower()

    # unify Arabic alef forms: أ إ آ -> ا
    s = s.replace("أ", "ا").replace("إ", "ا").replace("آ", "ا")

    # remove tatweel and common harakat (optional)
    s = re.sub(r"[ـًٌٍَُِّْ]", "", s)

    # normalize punctuation to spaces
    s = re.sub(r"[^\w\s]", " ", s, flags=re.UNICODE)

    # normalize Arabic definite article spacing issues
    s = s.replace("ال", "ال")

    # collapse spaces
    s = re.sub(r"\s+", " ", s).strip()
    return s

def norm_en(s: str) -> str:
    s = (s or "").strip().lower()
    s = re.sub(r"[^\w\s]", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    return s

def norm_text(s: str) -> str:
    # apply both; Arabic rules won't harm English much
    return norm_ar(norm_en(s))

def _contains_any_phrase(text_norm: str, phrases: list[str]) -> int:
    """
    returns score: number of phrase hits
    Uses substring match after normalization.
    """
    score = 0
    for p in phrases:
        pn = norm_text(p)
        if pn and pn in text_norm:
            score += 1
    return score

# SpecialtyIDs based on your specialties.csv
SYMPTOM_RULES = [
    # Physical Therapy (8) - back/knee/muscle pain
    (8, [
        "back pain", "lower back pain", "neck pain", "shoulder pain",
        "knee pain", "joint pain", "muscle pain", "sprain",
        "الم ظهر", "آلام الظهر", "الام الظهر", "وجع الظهر", "ظهر",
        "الم رقبه", "الم الرقبه", "الم كتف", "الم الركبه", "الم مفاصل", "شد عضلي", "التواء",
        "علاج طبيعي", "تاهيل",
    ]),

    # Internal Medicine (2) - GI / general
    (2, [
        "heartburn", "acid reflux", "reflux", "gerd", "indigestion", "gastritis", "stomach pain",
        "حرقان", "حموضه", "حموضة", "ارتجاع", "معده", "المعده", "الم معده", "مغص", "عسر هضم",
    ]),

    # Cardiology (4)
    (4, [
        "chest pain", "palpitations", "tachycardia", "shortness of breath",
        "الم صدر", "الام الصدر", "وجع صدر", "خفقان", "القلب", "ضيق نفس", "ضغط",
    ]),

    # Neurology (5)
    (5, [
        "headache", "migraine", "dizziness", "seizure", "numbness", "tingling",
        "صداع", "صداع نصفي", "دوخه", "دوخة", "تشنجات", "تنميل", "وخز",
    ]),

    # Pediatrics (3)
    (3, [
        "baby", "infant", "child", "newborn", "pediatric",
        "طفل", "اطفال", "أطفال", "رضيع", "حديث الولاده", "حديث الولادة",
    ]),

    # Ophthalmology (6)
    (6, [
        "eye", "vision", "blurred vision", "conjunctivitis", "red eye",
        "عين", "النظر", "نظر", "زغلله", "زغللة", "احمرار العين", "التهاب ملتحمه", "التهاب ملتحمة",
    ]),

    # ENT (12)
    (12, [
        "ear pain", "throat", "tonsil", "sinus", "nose", "otitis",
        "الم اذن", "الام الاذن", "ودن", "اذن", "حلق", "لوز", "جيوب", "انف", "رشح",
    ]),

    # OB/GYN (7)
    (7, [
        "pregnant", "pregnancy", "period", "menstrual", "obgyn",
        "حمل", "حامل", "دوره", "دورة", "طمث", "نسائيه", "نسائية",
    ]),
]

def suggest_specialty_id(user_text: str) -> int | None:
    t = norm_text(user_text)
    if not t:
        return None

    best_sid = None
    best_score = 0

    for sid, phrases in SYMPTOM_RULES:
        score = _contains_any_phrase(t, phrases)
        if score > best_score:
            best_score = score
            best_sid = sid

    return best_sid if best_score > 0 else None