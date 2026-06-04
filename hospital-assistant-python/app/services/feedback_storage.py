from __future__ import annotations

from datetime import datetime
from pathlib import Path
from typing import Optional
import json

from sqlalchemy import text

try:
    from app.db import engine
except Exception:  # pragma: no cover
    engine = None

FEEDBACK_FALLBACK_DIR = Path(__file__).resolve().parents[2] / ".chat_feedback"


def ensure_chatbot_feedback_table() -> None:
    """Create a small table to store patient feedback on chatbot answers."""
    if engine is None:
        FEEDBACK_FALLBACK_DIR.mkdir(parents=True, exist_ok=True)
        return

    ddl = """
    CREATE TABLE IF NOT EXISTS chatbot_feedback (
        FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
        ChatID VARCHAR(180) NULL,
        UserMessage MEDIUMTEXT NULL,
        BotReply MEDIUMTEXT NULL,
        Rating VARCHAR(20) NOT NULL,
        Comment MEDIUMTEXT NULL,
        Intent VARCHAR(100) NULL,
        Source VARCHAR(50) NOT NULL DEFAULT 'chatbot_ui',
        UserAgent VARCHAR(500) NULL,
        CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_chatbot_feedback_chatid (ChatID),
        INDEX idx_chatbot_feedback_rating (Rating),
        INDEX idx_chatbot_feedback_created (CreatedAt)
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    """
    with engine.begin() as conn:
        conn.execute(text(ddl))


def save_chatbot_feedback(
    *,
    chat_id: Optional[str],
    user_message: Optional[str],
    bot_reply: Optional[str],
    rating: str,
    comment: Optional[str] = None,
    intent: Optional[str] = None,
    source: str = "chatbot_ui",
    user_agent: Optional[str] = None,
) -> dict:
    """Save a feedback row. Falls back to a JSONL file if DB writing fails."""
    rating_clean = (rating or "").strip().lower()
    allowed = {"up", "down", "good", "bad", "positive", "negative", "1", "0"}
    if rating_clean not in allowed:
        raise ValueError("Invalid rating. Use up/down or positive/negative.")

    if rating_clean in {"good", "positive", "1"}:
        rating_clean = "up"
    elif rating_clean in {"bad", "negative", "0"}:
        rating_clean = "down"

    row = {
        "chat_id": chat_id,
        "user_message": user_message,
        "bot_reply": bot_reply,
        "rating": rating_clean,
        "comment": comment,
        "intent": intent,
        "source": source or "chatbot_ui",
        "user_agent": user_agent,
    }

    try:
        ensure_chatbot_feedback_table()
        if engine is None:
            raise RuntimeError("Database engine is not available")

        insert_sql = text("""
            INSERT INTO chatbot_feedback
                (ChatID, UserMessage, BotReply, Rating, Comment, Intent, Source, UserAgent)
            VALUES
                (:chat_id, :user_message, :bot_reply, :rating, :comment, :intent, :source, :user_agent)
        """)
        with engine.begin() as conn:
            result = conn.execute(insert_sql, row)
            feedback_id = getattr(result, "lastrowid", None)
        return {"saved": True, "storage": "database", "feedback_id": feedback_id, "rating": rating_clean}
    except Exception as exc:
        FEEDBACK_FALLBACK_DIR.mkdir(parents=True, exist_ok=True)
        fallback_path = FEEDBACK_FALLBACK_DIR / "chatbot_feedback.jsonl"
        row["created_at"] = datetime.utcnow().isoformat() + "Z"
        row["db_error"] = str(exc)
        with fallback_path.open("a", encoding="utf-8") as f:
            f.write(json.dumps(row, ensure_ascii=False) + "\n")
        return {"saved": True, "storage": "jsonl_fallback", "path": str(fallback_path), "rating": rating_clean}
