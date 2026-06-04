from __future__ import annotations

import re
from difflib import SequenceMatcher
from sqlalchemy import text
from app.db import SessionLocal


def _cleanup(s: str) -> str:
    return (s or "").strip()


def _norm_med_name(s: str) -> str:
    s = (s or "").strip().lower()
    s = re.sub(r"[?؟!.,\(\)\[\]\"'“”‘’،:;_\-\\/]+", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    s = re.sub(r"^\s*ال", "", s).strip()
    return s


def _fix_common_med_typos(q: str) -> str:
    """Normalize common medication spelling mistakes before DB search.

    This is intentionally small and safe. It fixes common brand/generic typos
    without changing unrelated words. Fuzzy DB matching below handles the rest.
    """
    aliases = {
        # Aspirin
        "aspirine": "aspirin",
        "asprin": "aspirin",
        "asp": "aspirin",
        "acetylsalicylic": "acetylsalicylic acid",
        "acetylsalicylicacid": "acetylsalicylic acid",

        # Brufen / Ibuprofen common misspellings
        "burfen": "brufen",
        "brufin": "brufen",
        "brofen": "brufen",
        "bروفين": "brufen",
        "بروفين": "brufen",
        "ايبوبروفين": "ibuprofen",
        "iboprofen": "ibuprofen",
        "ibuprophen": "ibuprofen",
        "ibuprufen": "ibuprofen",
    }

    q = (q or "").strip().lower()
    return aliases.get(q, q)


def split_med_names(text_in: str) -> list[str]:
    t = " " + (text_in or "").strip() + " "

    t = t.replace("،", ",")
    t = t.replace("+", ",")
    t = t.replace("&", ",")
    t = t.replace("/", ",")
    t = re.sub(r"\s+و\s+", ",", t)
    t = re.sub(r"\s+and\s+", ",", t, flags=re.IGNORECASE)
    t = re.sub(r"\s+or\s+", ",", t, flags=re.IGNORECASE)
    t = t.replace(" مع ", ",")
    t = t.replace(" أو ", ",")

    parts = [p.strip() for p in t.split(",")]
    parts = [p for p in parts if p]

    stop_words = {
        "ماهي", "ما", "ماهو", "ما هو", "ايه", "اي", "what", "what is",
        "هي", "هو", "ده", "دي", "دا", "هذا", "هذه",
        "is", "are", "it", "this", "that",
        # Arabic risk/benefit/medicine words
        "مخاطر", "خطر", "خطورة", "تحذيرات", "تحذير", "أضرار", "اضرار", "ضرر", "فوائد",
        "اعراض", "أعراض", "جانبية", "اعراض جانبية", "أعراض جانبية",
        "استخدام", "استخدامات", "استعمال", "استعمالات",
        "دواء", "ادوية", "أدوية", "علاج",
        # English risk/benefit/medicine words + common typos
        "medicine", "drug", "medication", "medications",
        "risk", "risks", "danger", "dangers", "dangerous", "harm", "harms",
        "side", "effect", "effects", "warning", "warnings", "contraindication",
        "denger", "dengerace", "dangar", "dangr", "risc", "riks",
        # Common filler words
        "لي", "ل", "لـ", "بالنسبة", "بالنسبه", "عن", "من", "على", "في", "الى", "إلى", "مع", "ينفع",
        "the", "a", "an", "of", "for", "to", "with", "regarding",
        "tell", "me", "about", "can", "you", "please", "what", "what's", "whats", "is", "are"
    }

    cleaned: list[str] = []

    for p in parts:
        p2 = _norm_med_name(p)
        words = [w for w in p2.split() if w not in stop_words]
        p2 = " ".join(words).strip()
        p2 = _fix_common_med_typos(p2)

        if p2:
            cleaned.append(p2)

    return cleaned


def _search_exact(db, q: str):
    sql_exact = """
    SELECT MedicationID, TradeName, GenericName, Category
    FROM medications
    WHERE LOWER(TRIM(TradeName)) = LOWER(TRIM(:q))
       OR LOWER(TRIM(GenericName)) = LOWER(TRIM(:q))
    LIMIT 1
    """

    return db.execute(text(sql_exact), {"q": q}).mappings().first()


def _search_partial(db, q: str, limit: int = 5):
    sql_partial = """
    SELECT MedicationID, TradeName, GenericName, Category
    FROM medications
    WHERE LOWER(TradeName) LIKE LOWER(:likeq)
       OR LOWER(GenericName) LIKE LOWER(:likeq)
    ORDER BY
      CASE
        WHEN LOWER(TRIM(TradeName)) = LOWER(TRIM(:q))
          OR LOWER(TRIM(GenericName)) = LOWER(TRIM(:q)) THEN 0
        WHEN LOWER(TradeName) LIKE LOWER(:starts)
          OR LOWER(GenericName) LIKE LOWER(:starts) THEN 1
        ELSE 2
      END,
      TradeName
    LIMIT :limit_n
    """

    return db.execute(
        text(sql_partial),
        {
            "q": q,
            "starts": f"{q}%",
            "likeq": f"%{q}%",
            "limit_n": limit,
        },
    ).mappings().all()


def _similarity(a: str, b: str) -> float:
    return SequenceMatcher(None, _norm_med_name(a), _norm_med_name(b)).ratio()


def _search_fuzzy(db, q: str, min_score: float = 0.80):
    """Best-effort typo correction against the medication table.

    Example: 'burfen' can match 'Brufen'. We only fuzzy-match reasonably
    long medication-like words to avoid accidental matches from Arabic prose.
    """
    qn = _norm_med_name(q)
    if len(qn) < 4:
        return None

    rows = db.execute(text("""
        SELECT MedicationID, TradeName, GenericName, Category
        FROM medications
    """)).mappings().all()

    best = None
    best_score = 0.0
    for row in rows:
        candidates = [row.get("TradeName") or "", row.get("GenericName") or ""]
        for cand in candidates:
            if not cand:
                continue
            # Also compare generic parts such as Amoxicillin/Clavulanate.
            for part in re.split(r"[/,+&\s]+", str(cand)):
                part = part.strip()
                if not part:
                    continue
                score = max(_similarity(qn, cand), _similarity(qn, part))
                if score > best_score:
                    best_score = score
                    best = row

    if best is not None and best_score >= min_score:
        return best
    return None


def find_medication(query: str):
    parts = split_med_names(query)

    if parts:
        q = parts[0]
    else:
        q = _norm_med_name(_cleanup(query))
        q = _fix_common_med_typos(q)

    if not q:
        return {"found": False, "suggestions": []}

    with SessionLocal() as db:
        med = _search_exact(db, q)

        if med:
            return {
                "found": True,
                "med": dict(med),
                "suggestions": []
            }

        partials = _search_partial(db, q, limit=5)

        if partials:
            return {
                "found": True,
                "med": dict(partials[0]),
                "suggestions": [dict(r) for r in partials]
            }

        fuzzy = _search_fuzzy(db, q)
        if fuzzy:
            return {
                "found": True,
                "med": dict(fuzzy),
                "suggestions": [dict(fuzzy)],
            }

    return {
        "found": False,
        "suggestions": []
    }


def find_multiple_medications(query: str):
    parts = split_med_names(query)

    if not parts:
        q = _norm_med_name(_cleanup(query))
        q = _fix_common_med_typos(q)
        parts = [q] if q else []

    found = []
    not_found = []
    seen_ids = set()
    seen_queries = set()

    with SessionLocal() as db:
        for part in parts:
            part = _fix_common_med_typos(part)

            if not part or part in seen_queries:
                continue

            seen_queries.add(part)

            med = _search_exact(db, part)

            if not med:
                partials = _search_partial(db, part, limit=1)
                med = partials[0] if partials else None

            if not med:
                med = _search_fuzzy(db, part)

            if med:
                med_dict = dict(med)
                mid = med_dict["MedicationID"]

                if mid not in seen_ids:
                    found.append(med_dict)
                    seen_ids.add(mid)
            else:
                not_found.append(part)

    return {
        "found": found,
        "not_found": not_found,
    }


def get_medication_warnings(medication_id: int):
    sql = """
    SELECT WarningType, Description
    FROM drug_interactions
    WHERE MedicationID = :mid
    ORDER BY WarningType
    """

    with SessionLocal() as db:
        rows = db.execute(text(sql), {"mid": medication_id}).mappings().all()

    return [dict(r) for r in rows]


def get_medication_by_name(name: str):
    q = _norm_med_name(name)
    q = _fix_common_med_typos(q)

    if not q:
        return None

    with SessionLocal() as db:
        med = _search_exact(db, q)

        if med:
            return dict(med)

        partials = _search_partial(db, q, limit=1)

        if partials:
            return dict(partials[0])

    return None


def _norm_text_for_scan(s: str) -> str:
    s = (s or "").lower()
    s = s.replace("أ", "ا").replace("إ", "ا").replace("آ", "ا")
    s = re.sub(r"[^a-z0-9\u0600-\u06ff\s]+", " ", s)
    return re.sub(r"\s+", " ", s).strip()


def find_medications_in_text(text_in: str, limit: int = 10) -> list[dict]:
    """Find medication names mentioned inside free text/OCR output.

    It scans TradeName, GenericName, and generic name parts from the local
    medications table. This is safer for prescriptions than trying to split the
    whole OCR text as one medication query.
    """
    haystack = " " + _norm_text_for_scan(text_in) + " "
    if not haystack.strip():
        return []

    with SessionLocal() as db:
        rows = db.execute(text("""
            SELECT MedicationID, TradeName, GenericName, Category
            FROM medications
        """)).mappings().all()

    matches: list[tuple[int, dict]] = []
    seen_ids = set()

    for row in rows:
        candidates: list[str] = []
        for key in ["TradeName", "GenericName"]:
            val = row.get(key)
            if val:
                candidates.append(str(val))
                for part in re.split(r"[/,+&]", str(val)):
                    if part.strip():
                        candidates.append(part.strip())

        best_len = 0
        for cand in candidates:
            cn = _norm_text_for_scan(cand)
            if len(cn) < 3:
                continue
            # Word-boundary style check that works for English and most Arabic OCR text.
            if re.search(r"(?<![a-z0-9\u0600-\u06ff])" + re.escape(cn) + r"(?![a-z0-9\u0600-\u06ff])", haystack):
                best_len = max(best_len, len(cn))

        if best_len and row["MedicationID"] not in seen_ids:
            seen_ids.add(row["MedicationID"])
            matches.append((best_len, dict(row)))

    matches.sort(key=lambda x: (-x[0], str(x[1].get("TradeName") or "")))
    return [m for _, m in matches[:limit]]
