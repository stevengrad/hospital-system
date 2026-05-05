import json
import re
import faiss
import pandas as pd

from fastapi import FastAPI
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer

app = FastAPI()

BASE = r"C:\xampp\htdocs\hospital\ai_data"

# Load files once
index = faiss.read_index(f"{BASE}/faiss.index")

with open(f"{BASE}/docstore.json", "r", encoding="utf-8") as f:
    docs = json.load(f)

medications_df = pd.read_csv(f"{BASE}/medications.csv")


embedder = SentenceTransformer("BAAI/bge-m3")


class ChatRequest(BaseModel):
    message: str


def detect_lang(text: str) -> str:
    return "ar" if re.search(r"[\u0600-\u06FF]", text) else "en"


def find_medication_id(query):
    q = query.lower()
    candidates = []

    for _, r in medications_df.iterrows():
        trade = str(r.get("TradeName", "")).strip()
        generic = str(r.get("GenericName", "")).strip()
        mid = int(r["MedicationID"])

        if trade and trade.lower() in q:
            candidates.append((mid, trade, generic, "TradeName"))
        elif generic and generic.lower() in q:
            candidates.append((mid, trade, generic, "GenericName"))

    return candidates[:5]


def retrieve_full(query, top_k=6):
    q = embedder.encode([query], normalize_embeddings=True).astype("float32")
    scores, idx = index.search(q, top_k)

    hits = []
    for rank, i in enumerate(idx[0]):
        d = docs[int(i)]
        hits.append({
            "rank": rank + 1,
            "score": float(scores[0][rank]),
            "id": d["id"],
            "table": d["metadata"].get("table"),
            "text": d["text"],
        })
    return hits


def retrieve_filtered(query, top_k=10):
    meds = find_medication_id(query)
    hits = retrieve_full(query, top_k=top_k)

    if not meds:
        return [], meds

    target_mids = {m[0] for m in meds}
    filtered = []

    for h in hits:
        doc = next((d for d in docs if d["id"] == h["id"]), None)
        if not doc:
            continue

        mid = doc["metadata"].get("MedicationID")
        if doc["metadata"].get("table") == "drug_interactions" and mid in target_mids:
            filtered.append({**h, "MedicationID": mid})

    return filtered, meds


def extract_description(text):
    for line in text.splitlines():
        if line.lower().startswith("description:"):
            return line.split(":", 1)[1].strip()
    return ""


def split_bullets(desc: str, lang: str):
    pattern = r"[\.!\؟\?؛;\n]+" if lang == "ar" else r"[\.!\?;\n]+"
    return [p.strip() for p in re.split(pattern, desc) if p.strip()]


def chat_sources_only(query, top_k=12, use_k_sources=4, min_score=0.50):
    lang = detect_lang(query)
    unknown = "لا أعرف بناءً على البيانات المتاحة." if lang == "ar" else "I don't know based on the provided data."

    hits, meds = retrieve_filtered(query, top_k=top_k)

    if not meds:
        return {
            "answer": unknown,
            "lang": lang,
            "sources": [],
            "detected_meds": []
        }

    target_mid = meds[0][0]

    strict = []
    for h in hits:
        doc = next((d for d in docs if d["id"] == h["id"]), None)
        if not doc:
            continue
        if doc["metadata"].get("table") == "drug_interactions" and doc["metadata"].get("MedicationID") == target_mid:
            strict.append(h)

    if not strict:
        return {
            "answer": unknown,
            "lang": lang,
            "sources": [],
            "detected_meds": meds
        }

    best_score = max(h["score"] for h in strict)
    if best_score < min_score:
        return {
            "answer": unknown,
            "lang": lang,
            "sources": [],
            "detected_meds": meds
        }

    chosen = strict[:use_k_sources]

    bullets = []
    for h in chosen:
        desc = extract_description(h["text"])
        if not desc:
            continue
        for p in split_bullets(desc, lang)[:2]:
            bullets.append(f"- {p}")

    answer = unknown if not bullets else "\n".join(bullets)

    return {
        "answer": answer,
        "lang": lang,
        "sources": [h["id"] for h in chosen],
        "detected_meds": meds
    }


@app.get("/")
def root():
    return {"message": "Hospital chatbot API is running"}


@app.post("/chat")
def chat(req: ChatRequest):
    return chat_sources_only(req.message)