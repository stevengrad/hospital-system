# Feature 3 — Hybrid Search for RAG

This feature improves the RAG fallback search so the user does not need to write the exact words stored in `hospital_knowledge.txt`.

## What changed

Updated files:

```text
app/ai/vector_store.py
app/ai/rag_chat.py
```

## Search logic

The new `hybrid_search()` combines three signals:

1. **Vector search** using FAISS and multilingual embeddings.
   - Good for semantic meaning.
   - Example: `headache`, `صداع`, and `soda3` can retrieve similar hospital knowledge.

2. **Keyword overlap**.
   - Good for exact names like department names, branch names, doctor names, medication names, and service names.

3. **Fuzzy matching**.
   - Helps with small typos or approximate wording.

## Why this is useful

Before this feature, the RAG fallback mainly returned nearest vector chunks directly.
Now it is more forgiving when the user writes:

```text
3ndy soda3
I have head pain
محتاج عيادة مخ واعصاب
emrgency open?
```

The system can retrieve related knowledge even if the wording is not exactly the same as the knowledge base.

## Important note

This feature improves the knowledge-base RAG fallback only.
Booking, medications, patient data, and live appointment availability should still use SQL/database logic because these need live accurate data.

## Testing

Run:

```bash
uvicorn app.main:app --reload
```

Try `/chat/free` with questions like:

```text
I need emergency
emrgency available?
3ndy soda3
neurology available?
فين عيادة المخ والاعصاب
```

If the question is handled earlier by booking/symptom logic, that is normal. The RAG fallback runs only when no stronger intent handles the message.
