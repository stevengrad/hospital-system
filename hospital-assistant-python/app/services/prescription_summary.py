from __future__ import annotations

from app.tools.medications import find_medications_in_text, get_medication_warnings
from app.tools.drug_interactions import reply_for_drug_interaction_question


def _clip(text: str, limit: int = 900) -> str:
    text = (text or '').strip()
    if len(text) <= limit:
        return text
    return text[:limit].rstrip() + '...'


def build_prescription_summary(extracted_text: str | None, lang: str = 'ar') -> dict:
    """Build a safe user-facing summary for OCR prescription uploads.

    This does not diagnose or prescribe. It only summarizes text that OCR found,
    detects medication names already present in the local medications table,
    and optionally reviews recorded warnings/interactions.
    """
    text = (extracted_text or '').strip()
    is_ar = lang == 'ar'

    if not text:
        reply = (
            'اتحفظت الروشتة ✅\nلكن الـOCR ماقدرش يقرأ كلام واضح من الملف. جربي صورة أوضح بإضاءة كويسة.'
            if is_ar else
            'Prescription saved ✅\nOCR could not read clear text from the file. Try a clearer image with good lighting.'
        )
        return {'reply': reply, 'medications': [], 'interaction_review': None}

    meds = find_medications_in_text(text, limit=8)

    if is_ar:
        lines = ['اتحفظت الروشتة ✅', '', 'ملخص الكلام المقروء من الروشتة:']
        lines.append(_clip(text, 700))
        if meds:
            lines.append('\nالأدوية اللي قدرت أتعرف عليها من قاعدة البيانات:')
            for med in meds:
                name = med.get('TradeName') or med.get('GenericName')
                generic = med.get('GenericName')
                category = med.get('Category')
                extra = []
                if generic and generic.lower() != str(name).lower():
                    extra.append(str(generic))
                if category:
                    extra.append(str(category))
                lines.append(f"- {name}" + (f" ({' | '.join(extra)})" if extra else ''))
        else:
            lines.append('\nماقدرتش أطابق أسماء أدوية واضحة مع جدول medications. ممكن تكون الصورة غير واضحة أو الاسم التجاري غير موجود في قاعدة البيانات.')
    else:
        lines = ['Prescription saved ✅', '', 'OCR summary from the prescription:']
        lines.append(_clip(text, 700))
        if meds:
            lines.append('\nMedications I recognized from the database:')
            for med in meds:
                name = med.get('TradeName') or med.get('GenericName')
                generic = med.get('GenericName')
                category = med.get('Category')
                extra = []
                if generic and generic.lower() != str(name).lower():
                    extra.append(str(generic))
                if category:
                    extra.append(str(category))
                lines.append(f"- {name}" + (f" ({' | '.join(extra)})" if extra else ''))
        else:
            lines.append('\nI could not match clear medication names against the medications table.')

    interaction_review = None
    if len(meds) >= 2:
        med_names = [str(m.get('TradeName') or m.get('GenericName')) for m in meds[:4]]
        query = ' and '.join(med_names)
        try:
            interaction_review = reply_for_drug_interaction_question(query, lang=lang)
            if is_ar:
                lines.append('\nمراجعة مبدئية للتداخلات بين الأدوية المقروءة:')
            else:
                lines.append('\nInitial interaction review between recognized medications:')
            lines.append(interaction_review.get('reply', ''))
        except Exception as exc:
            if is_ar:
                lines.append(f'\nتم التعرف على أكثر من دواء، لكن مراجعة التداخلات فشلت تقنيًا: {exc}')
            else:
                lines.append(f'\nMore than one medication was recognized, but interaction review failed technically: {exc}')
    elif len(meds) == 1:
        med = meds[0]
        warnings = []
        try:
            warnings = get_medication_warnings(int(med['MedicationID']))
        except Exception:
            warnings = []
        if warnings:
            if is_ar:
                lines.append('\nتحذيرات مسجلة للدواء المقروء:')
            else:
                lines.append('\nRecorded warnings for the recognized medication:')
            for w in warnings[:5]:
                lines.append(f"- [{w.get('WarningType')}] {w.get('Description')}")

    lines.append('\nتنبيه: قراءة الروشتة آلية وقد تخطئ، ولا تغني عن مراجعة الطبيب/الصيدلي.' if is_ar else '\nNote: OCR can make mistakes and does not replace medical/pharmacist review.')
    return {'reply': '\n'.join([x for x in lines if x is not None]), 'medications': meds, 'interaction_review': interaction_review}
