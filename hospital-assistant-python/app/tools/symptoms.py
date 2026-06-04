from __future__ import annotations
import re


def norm_ar(s: str) -> str:
    s = (s or "").strip().lower()
    s = s.replace("أ", "ا").replace("إ", "ا").replace("آ", "ا")
    s = s.replace("ة", "ه")
    s = re.sub(r"[ـًٌٍَُِّْ]", "", s)
    s = re.sub(r"[^\w\s]", " ", s, flags=re.UNICODE)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def norm_en(s: str) -> str:
    s = (s or "").strip().lower()
    s = re.sub(r"[^\w\s]", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def norm_text(s: str) -> str:
    return norm_ar(norm_en(s))


def _contains_any_phrase(text_norm: str, phrases: list[str]) -> int:
    score = 0
    padded = f" {text_norm} "
    for p in phrases:
        pn = norm_text(p)
        if not pn:
            continue
        # Substring phrase matching is useful for Arabic, but avoid generic tiny words.
        if len(pn) <= 2:
            continue
        if pn in padded or pn in text_norm:
            score += 1
    return score


# SpecialtyIDs from the user's database:
# 1 General Surgery, 2 Internal Medicine, 3 Pediatrics, 4 Cardiology,
# 5 Neurology, 6 Ophthalmology, 7 OB/GYN, 8 Physical Therapy, 12 ENT.
# IMPORTANT: do not use the generic word "pain/الم" alone; it sends many symptoms
# to the wrong specialty. Match the body area + symptom together.
SYMPTOM_RULES: list[tuple[int, list[str]]] = [
    # Internal Medicine / GI (2)
    (2, [
        "abdominal pain", "abdomen pain", "belly pain", "stomach pain", "tummy pain",
        "heartburn", "acid reflux", "reflux", "gerd", "indigestion", "gastritis",
        "nausea", "vomiting", "diarrhea", "constipation", "colon pain", "ibs",
        "pain in abdomen", "pain in belly", "pain in stomach", "burning stomach",
        "الم في البطن", "الم بالبطن", "الم بطن", "وجع البطن", "وجع بطن", "وجع بطني", "بطني بتوجعني",
        "البطن", "بطني", "مغص", "مغص بطني", "الم المعده", "وجع المعده", "وجع معده", "الم معده",
        "معده", "المعده", "حرقان في المعده", "حرقان المعده", "حموضه", "حموضة",
        "ارتجاع", "عسر هضم", "غثيان", "ترجيع", "قيء", "اسهال", "امساك", "قولون",
    ]),

    # Cardiology (4)
    (4, [
        "chest pain", "heart pain", "palpitations", "tachycardia", "shortness of breath",
        "pressure in chest", "tight chest", "الم صدر", "الم في الصدر", "وجع صدر",
        "وجع في الصدر", "خفقان", "القلب", "ضيق نفس", "نهجان", "ضغط في الصدر",
    ]),

    # Neurology (5)
    (5, [
        "headache", "migraine", "dizziness", "seizure", "numbness", "tingling", "fainting",
        "head pain", "صداع", "صداع نصفي", "دوخه", "دوخة", "دوار", "تشنجات", "تنميل", "وخز", "اغماء",
    ]),

    # Physical Therapy / musculoskeletal (8)
    (8, [
        "back pain", "lower back pain", "neck pain", "shoulder pain", "knee pain",
        "joint pain", "muscle pain", "sprain", "ankle pain", "wrist pain", "arm pain", "leg pain",
        "الم الظهر", "الم في الظهر", "وجع الظهر", "ظهري بيوجعني", "الام الظهر", "ظهر",
        "الم رقبه", "الم في الرقبه", "وجع الرقبه", "الم كتف", "الم في الكتف",
        "الم الركبه", "الم في الركبه", "الم مفاصل", "الم في المفاصل", "شد عضلي", "التواء",
        "الم رجل", "الم في الرجل", "الم ذراع", "الم في الذراع", "علاج طبيعي", "تاهيل",
    ]),

    # Pediatrics (3)
    (3, [
        "baby", "infant", "child", "newborn", "pediatric", "my child", "my baby",
        "طفل", "اطفال", "رضيع", "حديث الولاده", "ابني", "بنتي", "طفلي", "ابنتي",
    ]),

    # Ophthalmology (6)
    (6, [
        "eye pain", "eye", "vision", "blurred vision", "red eye", "conjunctivitis",
        "الم عين", "الم في العين", "عين", "النظر", "نظر", "زغلله", "زغللة", "احمرار العين", "التهاب ملتحمه",
    ]),

    # ENT (12)
    (12, [
        "ear pain", "throat pain", "tonsil", "sinus", "nose", "otitis", "sore throat",
        "الم اذن", "الم في الاذن", "وجع ودن", "ودن", "اذن", "حلق", "لوز", "جيوب", "انف", "رشح", "احتقان",
    ]),

    # OB/GYN (7)
    (7, [
        "pregnant", "pregnancy", "period pain", "menstrual pain", "obgyn", "vaginal bleeding",
        "حمل", "حامل", "دوره", "دورة", "الم الدوره", "الم الدورة", "طمث", "نسائيه", "نزيف مهبلي",
    ]),
]


def suggest_specialty_id(user_text: str) -> int | None:
    t = norm_text(user_text)
    if not t:
        return None

    best_sid: int | None = None
    best_score = 0
    for sid, phrases in SYMPTOM_RULES:
        score = _contains_any_phrase(t, phrases)
        if score > best_score:
            best_score = score
            best_sid = sid

    return best_sid if best_score > 0 else None
