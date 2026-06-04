from difflib import get_close_matches
from sqlalchemy import text
from app.db import engine


def get_medication_names():
    with engine.connect() as conn:
        rows = conn.execute(
            text("SELECT TradeName, GenericName FROM medications")
        ).fetchall()

    meds = set()

    for trade, generic in rows:
        if trade:
            meds.add(trade.lower().strip())
        if generic:
            meds.add(generic.lower().strip())

    return list(meds)


def normalize_medications_from_db(user_text: str) -> str:
    meds = get_medication_names()

    words = user_text.lower().split()
    corrected_words = []

    for word in words:
        clean_word = word.strip(".,!?؟،")

        matches = get_close_matches(
            clean_word,
            meds,
            n=1,
            cutoff=0.70
        )

        if matches:
            corrected_words.append(matches[0])
        else:
            corrected_words.append(word)

    return " ".join(corrected_words)