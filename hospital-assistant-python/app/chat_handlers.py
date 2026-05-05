from __future__ import annotations

from app.medications import split_med_names, find_medication, get_medication_warnings


RISK_KEYWORDS = ("مخاطر", "أضرار", "اضرار", "تحذيرات")


def handle_message(user_text: str) -> dict:
    t = (user_text or "").strip()

    # 1) Medication risks / warnings intent
    if any(k in t for k in RISK_KEYWORDS):
        meds = split_med_names(t)
        if not meds:
            return {
                "reply": "اكتبي اسم الدواء بعد كلمة (مخاطر) مثال: مخاطر Brufen"
            }

        # حاليا هنشتغل على أول دواء فقط
        med_query = meds[0]
        found = find_medication(med_query)

        if not found["found"]:
            suggestions = found.get("suggestions", [])
            if suggestions:
                sug_txt = "\n".join(
                    [f"- {s['TradeName']} ({s['GenericName']})" for s in suggestions]
                )
                return {
                    "reply": f"مش لاقيه في قاعدة البيانات بالاسم ده: {med_query}\nهل تقصدي واحد من دول؟\n{sug_txt}"
                }
            return {
                "reply": f"الدواء ({med_query}) مش موجود عندي في قاعدة البيانات. اكتبي الاسم زي اللي عندك في جدول medications (TradeName أو GenericName)."
            }

        med = found["med"]
        warnings = get_medication_warnings(med["MedicationID"])

        if not warnings:
            return {
                "reply": f"لقيت الدواء: {med['TradeName']} ({med['GenericName']})\nبس مفيش تحذيرات/مخاطر مسجلة له في جدول drug_interactions."
            }

        lines = [f"تحذيرات {med['TradeName']} ({med['GenericName']}):"]
        for w in warnings[:10]:
            lines.append(f"- {w['WarningType']}: {w['Description']}")
        return {"reply": "\n".join(lines)}

    # 2) otherwise show your main menu / other intents
    return {"reply": "أقدر أساعدك في:\n1) مخاطر الأدوية...\n2) حجز...\n3) مواعيد دكتور...\nاكتبي رقم الاختيار."}