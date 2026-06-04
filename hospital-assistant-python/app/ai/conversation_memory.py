from __future__ import annotations

import json
import os
import re
from datetime import datetime
from pathlib import Path
from typing import Any

from app.ai.text_normalizer import detect_language, normalize_for_chat

_MEMORY_DIR = Path(os.getenv("CHATBOT_MEMORY_DIR", ".chat_memory"))
_MAX_HISTORY = int(os.getenv("CHATBOT_MEMORY_MAX_HISTORY", "12"))

_MEMORY_CACHE: dict[str, dict[str, Any]] = {}

_RESET_WORDS = {
    "reset", "restart", "start over", "new chat", "clear memory", "forget", "forget everything",
    "ابدأ من جديد", "ابدا من جديد", "محادثة جديدة", "امسح الذاكرة", "انسى", "انسي", "انس كل حاجة",
}

# Phrases that usually mean the user is referring to the previous topic.
_AR_FOLLOW_UP_HINTS = [
    "ده", "دا", "دي", "دول", "هو", "هي", "عنها", "عنه", "منه", "منها",
    "بكام", "سعره", "سعرها", "متاح", "موجود", "موجوده", "ينفع", "مخاطره", "اضراره", "تحذيراته",
    "احجزه", "احجزها", "احجزلي ده", "طب", "طيب", "وكمان", "والتاني", "نفس", "اقرب", "فين", "امتى",
]
_EN_FOLLOW_UP_HINTS = [
    "it", "this", "that", "them", "those", "one", "same", "also", "and", "what about",
    "price", "cost", "available", "availability", "book it", "risks", "warnings", "side effects",
    "where", "when", "how much", "tell me more",
]

# Commands that depend on raw numbers/state should not be rewritten.
_STATEFUL_COMMAND_RE = re.compile(
    r"^\s*(?:\d+|فرع\s*\d+|branch\s*\d+|احجز\s*\d+|book\s*\d+|patient\s*\d+|patientid\s*\d+)\s*$",
    flags=re.IGNORECASE,
)


def _safe_chat_id(chat_id: str | None) -> str:
    return re.sub(r"[^a-zA-Z0-9_\-:.]", "_", chat_id or "default")[:120]


def _memory_path(chat_id: str | None) -> Path:
    return _MEMORY_DIR / f"{_safe_chat_id(chat_id)}.json"


def _json_safe(obj: Any) -> Any:
    if isinstance(obj, datetime):
        return obj.isoformat()
    if isinstance(obj, dict):
        return {str(k): _json_safe(v) for k, v in obj.items()}
    if isinstance(obj, list):
        return [_json_safe(v) for v in obj]
    return obj


def load_conversation_memory(chat_id: str | None) -> dict[str, Any]:
    cid = _safe_chat_id(chat_id)
    if cid in _MEMORY_CACHE:
        return _MEMORY_CACHE[cid]

    path = _memory_path(cid)
    if path.exists():
        try:
            mem = json.loads(path.read_text(encoding="utf-8"))
            _MEMORY_CACHE[cid] = mem
            return mem
        except Exception:
            pass

    mem = {
        "chat_id": cid,
        "history": [],
        "last_intent": None,
        "last_topic": None,
        "last_entities": {},
        "updated_at": None,
    }
    _MEMORY_CACHE[cid] = mem
    return mem


def save_conversation_memory(chat_id: str | None, memory: dict[str, Any]) -> None:
    cid = _safe_chat_id(chat_id)
    memory["chat_id"] = cid
    memory["updated_at"] = datetime.utcnow().isoformat()
    _MEMORY_CACHE[cid] = memory
    try:
        _MEMORY_DIR.mkdir(parents=True, exist_ok=True)
        _memory_path(cid).write_text(json.dumps(_json_safe(memory), ensure_ascii=False, indent=2), encoding="utf-8")
    except Exception:
        pass


def reset_conversation_memory(chat_id: str | None) -> None:
    cid = _safe_chat_id(chat_id)
    _MEMORY_CACHE[cid] = {
        "chat_id": cid,
        "history": [],
        "last_intent": None,
        "last_topic": None,
        "last_entities": {},
        "updated_at": datetime.utcnow().isoformat(),
    }
    try:
        _memory_path(cid).unlink(missing_ok=True)
    except Exception:
        pass


def is_memory_reset_request(text: str | None) -> bool:
    normalized = normalize_for_chat(text or "").chat_text.lower().strip()
    raw = (text or "").lower().strip()
    return normalized in _RESET_WORDS or raw in _RESET_WORDS


def _is_probably_follow_up(text: str) -> bool:
    if not text or _STATEFUL_COMMAND_RE.match(text):
        return False

    norm = normalize_for_chat(text)
    x = (norm.chat_text or text).lower().strip()
    raw = (text or "").lower().strip()

    if len(x.split()) <= 4:
        # Very short messages are often contextual: "بكام؟", "and brufen?", "فين؟"
        if any(h in x for h in _AR_FOLLOW_UP_HINTS) or any(h in raw for h in _EN_FOLLOW_UP_HINTS):
            return True

    return any(h in x for h in _AR_FOLLOW_UP_HINTS) or any(h in raw for h in _EN_FOLLOW_UP_HINTS)


def _entity_label(entities: dict[str, Any]) -> str | None:
    meds = entities.get("medications") or []
    if meds:
        return ", ".join(str(m) for m in meds[:3])

    for key in ["doctor", "specialty", "branch", "offer", "rag_topic"]:
        val = entities.get(key)
        if isinstance(val, dict):
            name = val.get("Name") or val.get("name") or val.get("DoctorName") or val.get("title")
            if name:
                return str(name)
        elif val:
            return str(val)

    return None


def enrich_question_with_memory(text: str, chat_id: str | None) -> str:
    """Rewrite only ambiguous follow-up questions by adding the last known topic.

    Example:
      previous: "مخاطر aspirin"
      new: "ومع brufen ينفع؟"
      enriched: "بالنسبة لـ aspirin، ومع brufen ينفع؟"

    Numeric booking commands and clear standalone questions are returned unchanged.
    """
    raw = text or ""
    if not raw.strip() or is_memory_reset_request(raw) or _STATEFUL_COMMAND_RE.match(raw):
        return raw

    mem = load_conversation_memory(chat_id)
    entities = mem.get("last_entities") or {}
    topic = _entity_label(entities) or mem.get("last_topic")
    if not topic:
        return raw

    if not _is_probably_follow_up(raw):
        return raw

    lang = detect_language(raw)
    if lang == "ar":
        return f"بالنسبة لـ {topic}: {raw}"
    return f"Regarding {topic}: {raw}"


def _first_name(obj: Any) -> str | None:
    if not isinstance(obj, dict):
        return None
    for k in ["TradeName", "GenericName", "Name", "DoctorName", "name", "title"]:
        if obj.get(k):
            return str(obj[k])
    return None


def _extract_entities(result: dict[str, Any] | None, user_text: str) -> dict[str, Any]:
    entities: dict[str, Any] = {}
    if not isinstance(result, dict):
        return entities

    data = result.get("data") if isinstance(result.get("data"), dict) else {}
    intent = result.get("intent")

    # Medication single/multiple responses
    meds: list[str] = []
    med = data.get("medication")
    name = _first_name(med)
    if name:
        meds.append(name)

    for item in data.get("items") or []:
        if isinstance(item, dict):
            name = _first_name(item.get("medication"))
            if name:
                meds.append(name)

    for med_obj in data.get("medications") or []:
        name = _first_name(med_obj)
        if name:
            meds.append(name)

    # Also catch obvious medication names from text when the DB response did not return data.
    normalized = normalize_for_chat(user_text).chat_text.lower()
    for candidate in ["panadol", "brufen", "aspirin", "congestal", "cataflam", "ibuprofen"]:
        if candidate in normalized and candidate not in [m.lower() for m in meds]:
            meds.append(candidate)

    if meds:
        # Preserve order and remove duplicates case-insensitively.
        seen = set()
        clean = []
        for m in meds:
            key = m.lower()
            if key not in seen:
                seen.add(key)
                clean.append(m)
        entities["medications"] = clean

    # Specialty / branch / doctor entities from booking flow
    if isinstance(data.get("specialty"), dict):
        entities["specialty"] = data["specialty"]
    if isinstance(data.get("branch"), dict):
        entities["branch"] = data["branch"]
    if isinstance(data.get("doctor"), dict):
        entities["doctor"] = data["doctor"]

    if data.get("specialty_id") and "specialty" not in entities:
        entities["specialty"] = {"id": data.get("specialty_id")}

    if intent and "offer" in str(intent):
        entities["offer"] = "hospital offers"

    if intent == "ai_rag":
        entities["rag_topic"] = user_text.strip()[:160]

    return entities


def remember_chat_turn(chat_id: str | None, user_text: str, result: dict[str, Any] | None) -> None:
    if is_memory_reset_request(user_text):
        reset_conversation_memory(chat_id)
        return

    mem = load_conversation_memory(chat_id)
    history = mem.get("history") or []

    reply = ""
    intent = None
    if isinstance(result, dict):
        reply = str(result.get("reply") or result.get("answer") or "")
        intent = result.get("intent")

    history.append({
        "time": datetime.utcnow().isoformat(),
        "user": user_text,
        "reply": reply[:1200],
        "intent": intent,
    })
    mem["history"] = history[-_MAX_HISTORY:]
    mem["last_intent"] = intent

    entities = _extract_entities(result, user_text)
    if entities:
        previous = mem.get("last_entities") or {}
        previous.update(entities)
        mem["last_entities"] = previous
        label = _entity_label(previous)
        if label:
            mem["last_topic"] = label
    elif intent not in {"help", "empty", "unknown"} and user_text.strip():
        # Keep a useful topic for general RAG / information questions.
        mem["last_topic"] = user_text.strip()[:160]

    save_conversation_memory(chat_id, mem)


def get_memory_summary(chat_id: str | None) -> dict[str, Any]:
    mem = load_conversation_memory(chat_id)
    return {
        "chat_id": mem.get("chat_id"),
        "last_intent": mem.get("last_intent"),
        "last_topic": mem.get("last_topic"),
        "last_entities": mem.get("last_entities") or {},
        "history_count": len(mem.get("history") or []),
        "updated_at": mem.get("updated_at"),
    }
