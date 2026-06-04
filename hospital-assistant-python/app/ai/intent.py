def detect_intent(text: str):
    text = text.lower()

    # symptoms
    if any(word in text for word in ["ألم", "وجع", "صداع", "حرقة", "pain", "ache"]):
        return "symptoms"

    # booking
    if any(word in text for word in ["احجز", "موعد", "book", "appointment"]):
        return "booking"

    # medications
    if any(word in text for word in ["دواء", "علاج", "medication", "drug"]):
        return "medication"

    return "general"