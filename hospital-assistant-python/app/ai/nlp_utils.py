import re


def normalize_arabizi(text: str) -> str:
    text = (text or "").lower()

    replacements = {
        "3": "ع",
        "7": "ح",
        "5": "خ",
        "8": "ق",
        "2": "ء",
        "9": "ص",
    }

    for k, v in replacements.items():
        text = text.replace(k, v)

    arabizi_words = {
        "alm": "الم",
        "wga3": "وجع",
        "wg3": "وجع",
        "zahr": "ظهر",
        "dahr": "ظهر",
        "batn": "بطن",
        "ras": "راس",
        "soda3": "صداع",
        "harara": "حرارة",
        "fever": "حرارة",
        "cough": "كحة",
        "ko7a": "كحة",
        "kحة": "كحة",
        "sadr": "صدر",
        "nafas": "نفس",
        "doctor": "دكتور",
        "book": "احجز",
        "appointment": "ميعاد",
    }

    words = text.split()
    words = [arabizi_words.get(w, w) for w in words]

    return " ".join(words)


def normalize_text(text: str) -> str:
    text = normalize_arabizi(text)
    text = re.sub(r"[؟?!.،,;:()\[\]{}\"']", " ", text)
    text = re.sub(r"\s+", " ", text).strip()
    return text