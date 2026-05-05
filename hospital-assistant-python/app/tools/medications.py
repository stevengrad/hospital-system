from __future__ import annotations

import re
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


def split_med_names(text_in: str) -> list[str]:
    """
    Extract possible medication names from a user utterance.
    Handles Arabic and English connectors and removes common filler words.
    """
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
        "مخاطر", "تحذيرات", "أضرار", "اضرار", "فوائد", "استخدام", "استخدامات",
        "دواء", "ادوية", "أدوية", "علاج", "medicine", "drug", "medication",
        "لي", "عن", "من", "على", "في", "الى", "إلى", "the", "a", "an",
        "of", "for", "to", "with", "tell", "me", "about"
    }

    cleaned: list[str] = []

    for p in parts:
        p2 = _norm_med_name(p)
        words = [w for w in p2.split() if w not in stop_words]
        p2 = " ".join(words).strip()
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
    sql_suggest = """
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
        text(sql_suggest),
        {
            "q": q,
            "starts": f"{q}%",
            "likeq": f"%{q}%",
            "limit_n": limit,
        },
    ).mappings().all()


def find_medication(query: str):
    parts = split_med_names(query)

    if parts:
        q = parts[0]
    else:
        q = _norm_med_name(_cleanup(query))

    if not q:
        return {"found": False, "suggestions": []}

    with SessionLocal() as db:
        med = _search_exact(db, q)
        if med:
            return {"found": True, "med": dict(med)}

        sugg = _search_partial(db, q, limit=5)

    return {"found": False, "suggestions": [dict(r) for r in sugg]}


def find_multiple_medications(query: str):
    parts = split_med_names(query)

    if not parts:
        q = _norm_med_name(_cleanup(query))
        parts = [q] if q else []

    found = []
    not_found = []
    seen_ids = set()
    seen_queries = set()

    with SessionLocal() as db:
        for part in parts:
            if not part or part in seen_queries:
                continue
            seen_queries.add(part)

            med = _search_exact(db, part)

            if not med:
                partials = _search_partial(db, part, limit=1)
                med = partials[0] if partials else None

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