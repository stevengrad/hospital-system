from __future__ import annotations

import re
from datetime import datetime, timedelta
from typing import Any

from sqlalchemy import inspect, text

from app.db import engine
from app.tools.availability import get_available_slots

OFFER_TABLE_CANDIDATES = ["offers", "appointment_offers", "service_offers", "clinic_offers"]


def _first_existing_offer_table() -> str | None:
    inspector = inspect(engine)
    existing = set(inspector.get_table_names())
    for table in OFFER_TABLE_CANDIDATES:
        if table in existing:
            return table
    return None


def _columns(table: str) -> set[str]:
    inspector = inspect(engine)
    return {c["name"] for c in inspector.get_columns(table)}


def _row_label(row: dict[str, Any]) -> str:
    return str(
        row.get("Title")
        or row.get("Name")
        or row.get("OfferName")
        or row.get("ServiceName")
        or row.get("Description")
        or row.get("offer_title")
        or row.get("name")
        or "Offer"
    )


def list_active_offers(limit: int = 5) -> dict[str, Any]:
    table = _first_existing_offer_table()
    if not table:
        return {"configured": False, "table": None, "offers": []}

    cols = _columns(table)
    where = []
    if "IsActive" in cols:
        where.append("IsActive = 1")
    if "Active" in cols:
        where.append("Active = 1")
    if "Status" in cols:
        where.append("LOWER(Status) IN ('active','enabled','available')")
    if "EndDate" in cols:
        where.append("(EndDate IS NULL OR EndDate >= CURDATE())")
    if "ValidTo" in cols:
        where.append("(ValidTo IS NULL OR ValidTo >= CURDATE())")

    where_sql = " WHERE " + " AND ".join(where) if where else ""
    order_col = "CreatedAt" if "CreatedAt" in cols else next(iter(cols))
    sql = f"SELECT * FROM {table}{where_sql} ORDER BY {order_col} DESC LIMIT :lim"
    with engine.connect() as conn:
        rows = conn.execute(text(sql), {"lim": int(limit)}).mappings().all()
    return {"configured": True, "table": table, "offers": [dict(r) for r in rows]}


def find_offer_by_text(user_text: str) -> dict[str, Any] | None:
    data = list_active_offers(limit=20)
    if not data.get("configured"):
        return None

    q = (user_text or "").lower()
    best = None
    best_score = 0
    for offer in data.get("offers", []):
        label = _row_label(offer).lower()
        words = [w for w in re.split(r"\W+", label) if len(w) >= 3]
        score = sum(1 for w in words if w in q)
        if label and label in q:
            score += 3
        if score > best_score:
            best = offer
            best_score = score

    return best or (data.get("offers") or [None])[0]


def _get_int(row: dict[str, Any], *names: str) -> int | None:
    for n in names:
        if n in row and row[n] not in (None, ""):
            try:
                return int(row[n])
            except Exception:
                pass
    return None


def get_offer_slots(offer: dict[str, Any], *, days_ahead: int = 14, limit: int = 10) -> list[dict[str, Any]]:
    """
    Supports offers that already contain DoctorID and BranchID columns.
    If the current DB uses another offer schema, the chatbot will still list offers and explain what columns are needed.
    """
    doctor_id = _get_int(offer, "DoctorID", "doctor_id", "EmployeeID")
    branch_id = _get_int(offer, "BranchID", "branch_id")
    if not doctor_id or not branch_id:
        return []

    start = datetime.now().replace(hour=0, minute=0, second=0, microsecond=0)
    end = start + timedelta(days=days_ahead)
    slots = get_available_slots(doctor_id=doctor_id, branch_id=branch_id, from_dt=start, to_dt=end)
    out = []
    for s in slots[:limit]:
        out.append(
            {
                "doctor_id": doctor_id,
                "branch_id": branch_id,
                "start": s.get("start").isoformat() if hasattr(s.get("start"), "isoformat") else s.get("start"),
                "end": s.get("end").isoformat() if hasattr(s.get("end"), "isoformat") else s.get("end"),
                "offer": offer,
            }
        )
    return out


def reply_for_offers(user_text: str, chat_state: dict[str, Any], lang: str = "ar") -> dict[str, Any]:
    data = list_active_offers(limit=8)
    if not data.get("configured"):
        msg_ar = (
            "ميزة العروض جاهزة في الكود، لكن قاعدة البيانات لا تحتوي جدول offers/appointment_offers/service_offers حتى الآن.\n"
            "اعملي جدول للعروض يحتوي مثلًا: Title, Description, DoctorID, BranchID, IsActive, StartDate, EndDate."
        )
        msg_en = (
            "Offers support is ready in code, but the database does not currently have an offers/appointment_offers/service_offers table.\n"
            "Create an offers table with columns like: Title, Description, DoctorID, BranchID, IsActive, StartDate, EndDate."
        )
        return {"intent": "offers_not_configured", "reply": msg_ar if lang == "ar" else msg_en, "data": data}

    offer = find_offer_by_text(user_text)
    if not offer:
        return {"intent": "offers", "reply": "لا توجد عروض نشطة حاليًا." if lang == "ar" else "No active offers are available now.", "data": data}

    slots = get_offer_slots(offer)
    label = _row_label(offer)
    chat_state["last_offer"] = offer
    chat_state["last_slots"] = slots

    lines = [f"العرض المناسب: {label}" if lang == "ar" else f"Selected offer: {label}"]
    desc = offer.get("Description") or offer.get("Details") or offer.get("description")
    if desc:
        lines.append(str(desc))

    if slots:
        lines.append("\nالمواعيد المتاحة للعرض:" if lang == "ar" else "\nAvailable slots for this offer:")
        for i, s in enumerate(slots, start=1):
            lines.append(f"{i}) {s.get('start')}")
        lines.append("\nاكتبي: احجز <رقم>" if lang == "ar" else "\nType: book <number>")
    else:
        lines.append(
            "\nالعرض موجود، لكن لا يحتوي DoctorID و BranchID أو لا توجد مواعيد متاحة." if lang == "ar"
            else "\nThe offer exists, but it has no DoctorID/BranchID columns or no available slots."
        )

    return {"intent": "offer_slots" if slots else "offers", "reply": "\n".join(lines), "data": {"offer": offer, "slots": slots}}
