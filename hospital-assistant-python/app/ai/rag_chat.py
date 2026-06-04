from __future__ import annotations

from app.ai.text_normalizer import normalize_for_chat
from app.ai.vector_store import format_results_for_prompt, hybrid_search


def rag_answer(question: str):
    norm = normalize_for_chat(question)
    search_query = norm.search_text or norm.chat_text or question

    results = hybrid_search(search_query, top_k=3, candidate_k=10, min_score=0.28)
    if not results:
        return None

    context = format_results_for_prompt(results)

    if norm.language == "ar":
        return (
            "حسب أقرب معلومات لقيتها في قاعدة معرفة المستشفى:\n\n"
            f"{context}\n\n"
            "لو محتاج/ة حجز، اكتبي الأعراض أو اسم الدكتور والفرع."
        )

    return (
        "According to the closest hospital information I found:\n\n"
        f"{context}\n\n"
        "If you need booking help, send your symptoms or the doctor name and branch."
    )
