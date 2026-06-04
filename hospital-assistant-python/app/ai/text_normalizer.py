from __future__ import annotations

import re
from dataclasses import dataclass
from difflib import get_close_matches


@dataclass(frozen=True)
class NormalizedText:
    original: str
    chat_text: str
    search_text: str
    language: str  # ar | en


# Common Arabic normalization. This keeps numbers because booking flow and
# PatientID depend on them.
_ARABIC_TRANSLATION = str.maketrans({
    "أ": "ا", "إ": "ا", "آ": "ا", "ٱ": "ا",
    "ة": "ه",
    "ى": "ي",
    "ؤ": "و",
    "ئ": "ي",
    "٠": "0", "١": "1", "٢": "2", "٣": "3", "٤": "4",
    "٥": "5", "٦": "6", "٧": "7", "٨": "8", "٩": "9",
    "۰": "0", "۱": "1", "۲": "2", "۳": "3", "۴": "4",
    "۵": "5", "۶": "6", "۷": "7", "۸": "8", "۹": "9",
    "\u200e": "", "\u200f": "", "\u061c": "",
})

_ARABIC_DIACRITICS_RE = re.compile(r"[ـًٌٍَُِّْ]")
_PUNCT_RE = re.compile(r"[^\w\s\u0600-\u06FF]", flags=re.UNICODE)
_SPACES_RE = re.compile(r"\s+")

# Phrase mappings are sorted by length before replacement, so multi-word phrases
# like "shortness of breath" are handled before smaller words like "breath".
ENGLISH_PHRASES = {
    # booking / navigation
    "i need to book": "عايز احجز",
    "i want to book": "عايز احجز",
    "book an appointment": "احجز ميعاد",
    "book appointment": "احجز ميعاد",
    "make an appointment": "احجز ميعاد",
    "schedule appointment": "احجز ميعاد",
    "appointment": "ميعاد",
    "booking": "حجز",
    "book": "احجز",
    "branch": "فرع",
    "doctor": "دكتور",
    "offers": "عروض",
    "offer": "عرض",
    "discount": "خصم",
    # symptoms
    "shortness of breath": "ضيق نفس",
    "chest pain": "الم صدر",
    "heart pain": "الم قلب",
    "back pain": "الم الظهر",
    "lower back pain": "الم اسفل الظهر",
    "neck pain": "الم رقبه",
    "shoulder pain": "الم كتف",
    "knee pain": "الم ركبه",
    "stomach pain": "الم معده",
    "abdominal pain": "الم بطن",
    "belly pain": "الم بطن",
    "tummy pain": "الم بطن",
    "head pain": "الم راس",
    "headache": "صداع",
    "migraine": "صداع نصفي",
    "dizziness": "دوخه",
    "fever": "حراره",
    "high fever": "حراره عاليه",
    "cough": "كحه",
    "cold": "برد",
    "flu": "انفلونزا",
    "allergy": "حساسيه",
    "nausea": "غثيان",
    "vomiting": "قيء",
    "diarrhea": "اسهال",
    "constipation": "امساك",
    "heartburn": "حرقان معده",
    "acid reflux": "ارتجاع",
    "numbness": "تنميل",
    "tingling": "وخز",
    "weakness": "ضعف",
    "fainting": "اغماء",
    "seizure": "تشنجات",
    "eye pain": "الم عين",
    "blurred vision": "زغلله",
    "ear pain": "الم اذن",
    "throat pain": "الم حلق",
    "sore throat": "الم حلق",
    "sinus": "جيوب انفيه",
    "pregnant": "حامل",
    "pregnancy": "حمل",
    "period pain": "الم الدوره",
    "menstrual pain": "الم الدوره",
    "pain": "الم",
    # pharmacy products / skin care
    "face cleanser": "غسول وجه",
    "facial cleanser": "غسول وجه",
    "cleanser": "غسول",
    "face wash": "غسول وجه",
    "skin wash": "غسول بشره",
    "oily skin": "بشره دهنيه",
    "dry skin": "بشره جافه",
    "sensitive skin": "بشره حساسه",
    "combination skin": "بشره مختلطه",
    "acne prone skin": "بشره معرضه للحبوب",
    "moisturizer": "مرطب",
    "sunscreen": "واقي شمس",
    "serum": "سيروم",
}

# Franco / Arabizi words that are likely in this hospital chatbot domain.
FRANCO_WORDS = {
    # common chat words
    "ana": "انا", "3ndi": "عندي", "3andy": "عندي", "3ndy": "عندي", "3ndk": "عندك",
    "3ayz": "عايز", "3ayza": "عايزه", "3awz": "عايز", "3awza": "عايزه",
    "m7tag": "محتاج", "m7taga": "محتاجه", "momkn": "ممكن", "law": "لو", "w": "و", "we": "و", "lel": "لل", "lil": "لل", "3shan": "عشان", "ashan": "عشان",
    "fe": "في", "fi": "في", "fy": "في", "mafish": "مفيش", "mfeesh": "مفيش",
    "la": "لا", "ah": "اه", "aywa": "ايوه", "tmam": "تمام",
    # booking
    "a7gez": "احجز", "ahgez": "احجز", "agz": "احجز", "hagz": "حجز", "7agz": "حجز",
    "me3ad": "ميعاد", "ma3ad": "ميعاد", "m3ad": "ميعاد", "maw3ed": "ميعاد",
    "doctor": "دكتور", "dr": "دكتور", "doktor": "دكتور", "dktor": "دكتور",
    "far3": "فرع", "fr3": "فرع", "branch": "فرع",
    # symptoms
    "soda3": "صداع", "sodaa": "صداع", "soda": "صداع", "migraine": "صداع نصفي",
    "wga3": "وجع", "wg3": "وجع", "waga3": "وجع", "waja3": "وجع", "alm": "الم",
    "ras": "راس", "rasy": "راسي", "demagh": "دماغ", "dma8": "دماغ",
    "batn": "بطن", "batny": "بطني", "maghas": "مغص", "mghas": "مغص", "ma3da": "معده", "m3da": "معده",
    "dahr": "ظهر", "dahry": "ظهري", "zahr": "ظهر", "zahy": "ظهري",
    "sadr": "صدر", "alb": "قلب", "2alb": "قلب", "nafas": "نفس", "nefs": "نفس", "de2": "ضيق",
    "harara": "حراره", "7arara": "حراره", "so5onia": "سخونيه", "sokhna": "سخونيه",
    "ko7a": "كحه", "koh7a": "كحه", "bard": "برد", "rash7": "رشح", "zokam": "زكام",
    "hasaseya": "حساسيه", "7asaseya": "حساسيه", "7saseya": "حساسيه",
    "do5a": "دوخه", "dokha": "دوخه", "dawkha": "دوخه",
    "tanmeel": "تنميل", "tnmeel": "تنميل", "tashanogat": "تشنجات",
    "3en": "عين", "3eny": "عيني", "wedn": "ودن", "wdn": "ودن", "zoor": "زور", "zour": "زور", "7ala2": "حلق",
    "regl": "رجل", "ragl": "رجل", "edra3": "ذراع", "deraa": "ذراع", "ketf": "كتف", "rokba": "ركبه",
    "haml": "حمل", "7aml": "حمل", "7amel": "حامل", "dawra": "دوره",
    # pharmacy products / skin care
    "8asol": "غسول", "ghasol": "غسول", "ghasool": "غسول", "gsol": "غسول",
    "bashara": "بشره", "bshra": "بشره", "bahsra": "بشره", "bashra": "بشره",
    "dohneya": "دهنيه", "dohnia": "دهنيه", "dohnya": "دهنيه", "oily": "دهنيه",
    "gafa": "جافه", "dry": "جافه", "sensitive": "حساسه", "7asasa": "حساسه",
    "mokhtaleta": "مختلطه", "7obob": "حبوب", "hobob": "حبوب", "acne": "حبوب",
    "blackheads": "رؤوس", "pores": "مسام", "tafteeh": "تفتيح", "brightening": "تفتيح",
    "serum": "سيروم", "moisturizer": "مرطب", "cream": "كريم", "sunscreen": "واقي شمس",
}

# Medication/product aliases. Keep canonical names in English because existing
# medication lookup already expects English trade names in many places.
ALIASES = {
    "بنادول": "panadol", "بندول": "panadol", "banadol": "panadol", "panadolll": "panadol",
    "بروفين": "brufen", "brufin": "brufen", "brofen": "brufen", "brufenn": "brufen",
    "اسبرين": "aspirin", "aspirine": "aspirin", "asprin": "aspirin",
    "كونجستال": "congestal", "congstal": "congestal",
    "كتافلام": "cataflam", "كاتافلام": "cataflam", "kataflam": "cataflam",
}

CANONICAL_PRODUCT_WORDS = sorted(set(ALIASES.values()) | {"panadol", "brufen", "aspirin", "congestal", "cataflam"})


def _basic_clean(text: str) -> str:
    text = (text or "").lower().translate(_ARABIC_TRANSLATION)
    text = _ARABIC_DIACRITICS_RE.sub("", text)
    text = _PUNCT_RE.sub(" ", text)
    text = _SPACES_RE.sub(" ", text).strip()
    return text


def _looks_franco(text: str) -> bool:
    x = (text or "").lower()
    tokens = set(re.findall(r"[a-z0-9]+", x))

    # Franco usually has numbers inside the same word, e.g. soda3, 3ndy, a7gez.
    # Do not classify normal English messages like "patient 200" as Franco.
    if any(re.search(r"[a-z]", tok) and re.search(r"[235789]", tok) for tok in tokens):
        return True

    common_english_tokens = {"doctor", "dr", "branch", "book", "appointment", "offer", "offers"}
    franco_detection_words = set(FRANCO_WORDS.keys()) - common_english_tokens
    return bool(tokens.intersection(franco_detection_words))


def detect_language(text: str) -> str:
    if re.search(r"[\u0600-\u06FF]", text or ""):
        return "ar"
    if _looks_franco(text):
        return "ar"
    return "en"


def _replace_phrases(text: str, mapping: dict[str, str]) -> str:
    out = f" {text} "
    for source, target in sorted(mapping.items(), key=lambda kv: len(kv[0]), reverse=True):
        pattern = r"(?<!\w)" + re.escape(source.lower()) + r"(?!\w)"
        out = re.sub(pattern, f" {target} ", out, flags=re.IGNORECASE)
    return _SPACES_RE.sub(" ", out).strip()


def _replace_words(text: str, mapping: dict[str, str]) -> str:
    words = text.split()
    return " ".join(mapping.get(w, w) for w in words)


def _correct_alias_typos(text: str) -> str:
    corrected = []
    for word in text.split():
        if word in ALIASES:
            corrected.append(ALIASES[word])
            continue
        if re.fullmatch(r"[a-z]{4,}", word):
            match = get_close_matches(word, CANONICAL_PRODUCT_WORDS, n=1, cutoff=0.86)
            corrected.append(match[0] if match else word)
        else:
            corrected.append(word)
    return " ".join(corrected)


def normalize_text(text: str) -> str:
    """Backward-compatible normalizer used by old imports.

    It preserves standalone digits and PatientID-like values, but converts common
    English/Franco symptom and booking words into Arabic-friendly terms.
    """
    return normalize_for_chat(text).chat_text


def normalize_for_chat(text: str) -> NormalizedText:
    original = text or ""
    language = detect_language(original)
    cleaned = _basic_clean(original)

    # English phrases first, then Franco words/aliases. This supports messages like
    # "book appointment for headache" and "3ayza a7gez 3shan soda3".
    normalized = _replace_phrases(cleaned, ENGLISH_PHRASES)
    normalized = _replace_words(normalized, FRANCO_WORDS)
    normalized = _replace_words(normalized, ALIASES)
    normalized = _correct_alias_typos(normalized)
    normalized = _SPACES_RE.sub(" ", normalized).strip()

    # For vector search, keep both the normalized and original forms. This improves
    # retrieval when the knowledge base contains English terms while the user used Arabic.
    search_text = f"{normalized} {cleaned}".strip() if cleaned and cleaned != normalized else normalized

    return NormalizedText(
        original=original,
        chat_text=normalized,
        search_text=search_text,
        language=language,
    )
