from __future__ import annotations

import math
import re
from dataclasses import dataclass
from difflib import SequenceMatcher
from pathlib import Path
from typing import Iterable

import faiss
import numpy as np

from app.ai.embeddings import get_embedding
from app.ai.text_normalizer import normalize_for_chat


@dataclass(frozen=True)
class SearchResult:
    text: str
    score: float
    vector_score: float
    keyword_score: float
    fuzzy_score: float
    source: str = "hospital_knowledge.txt"


chunks: list[str] = []
chunk_sources: list[str] = []
index = None

_TOKEN_RE = re.compile(r"[\w\u0600-\u06FF]+", flags=re.UNICODE)

# Small stop-word list so words like "في / من / do / have" do not dominate search.
_STOPWORDS = {
    "a", "an", "the", "is", "are", "am", "i", "you", "do", "does", "did", "have", "has", "had",
    "for", "to", "of", "in", "on", "at", "and", "or", "with", "about", "what", "when", "where",
    "how", "can", "could", "please", "need", "want",
    "انا", "انت", "انتي", "هو", "هي", "في", "من", "عن", "علي", "على", "الى", "الي", "و", "او",
    "ده", "دي", "دا", "ايه", "ازاي", "امتي", "فين", "لو", "ممكن", "عايز", "عايزه", "محتاج", "محتاجه",
    "عندي", "عندك", "هل", "ما", "لا", "اه", "ايوه", "طب", "طيب",
}


def _knowledge_path() -> Path:
    return Path(__file__).resolve().parent / "hospital_knowledge.txt"


def _safe_embedding(text: str) -> np.ndarray:
    emb = np.array(get_embedding(text), dtype="float32")
    if emb.ndim != 1:
        emb = emb.reshape(-1)
    return emb


def _normalize_matrix(embeddings: np.ndarray) -> np.ndarray:
    norms = np.linalg.norm(embeddings, axis=1, keepdims=True)
    norms[norms == 0] = 1.0
    return embeddings / norms


def _normalize_vector(embedding: np.ndarray) -> np.ndarray:
    norm = float(np.linalg.norm(embedding))
    if norm == 0 or math.isnan(norm):
        return embedding
    return embedding / norm


def _tokens(text: str) -> set[str]:
    normalized = normalize_for_chat(text).search_text.lower()
    tokens = set(_TOKEN_RE.findall(normalized))
    return {tok for tok in tokens if len(tok) > 1 and tok not in _STOPWORDS}


def _keyword_score(query_tokens: set[str], chunk_tokens: set[str]) -> float:
    if not query_tokens or not chunk_tokens:
        return 0.0
    overlap = query_tokens.intersection(chunk_tokens)
    # Recall-friendly score: reward how many query words were found, not only Jaccard.
    return len(overlap) / max(len(query_tokens), 1)


def _fuzzy_score(query: str, chunk: str, query_tokens: set[str], chunk_tokens: set[str]) -> float:
    if not query or not chunk:
        return 0.0

    phrase_score = SequenceMatcher(None, query.lower(), chunk.lower()).ratio()

    best_token_score = 0.0
    for qtok in query_tokens:
        for ctok in chunk_tokens:
            # Avoid comparing very tiny tokens because they create false positives.
            if len(qtok) < 3 or len(ctok) < 3:
                continue
            best_token_score = max(best_token_score, SequenceMatcher(None, qtok, ctok).ratio())

    return max(phrase_score, best_token_score)


def _combine_scores(vector_score: float, keyword_score: float, fuzzy_score: float) -> float:
    # Vector is the main semantic search. Keyword/fuzzy help exact names, typos, and Arabic/Franco variants.
    return (0.65 * vector_score) + (0.25 * keyword_score) + (0.10 * fuzzy_score)


def load_knowledge_base():
    """Load hospital knowledge into an in-memory FAISS vector database.

    This is still lightweight and local. It rebuilds the index when the app starts.
    The search itself is hybrid: semantic vector search + keyword matching + fuzzy matching.
    """
    global chunks, chunk_sources, index

    kb_path = _knowledge_path()
    if not kb_path.exists():
        chunks = []
        chunk_sources = []
        index = None
        return

    text = kb_path.read_text(encoding="utf-8")
    chunks = [c.strip() for c in text.split("\n\n") if c.strip()]
    chunk_sources = [kb_path.name for _ in chunks]

    if not chunks:
        index = None
        return

    embeddings = np.array([_safe_embedding(c) for c in chunks], dtype="float32")
    embeddings = _normalize_matrix(embeddings).astype("float32")

    # Inner product on normalized vectors = cosine similarity.
    index = faiss.IndexFlatIP(embeddings.shape[1])
    index.add(embeddings)


def hybrid_search(
    question: str,
    top_k: int = 3,
    candidate_k: int = 10,
    min_score: float = 0.28,
) -> list[SearchResult]:
    """Approximate/hybrid search for RAG.

    It does not require a 100% exact match. It combines:
    1. Vector similarity for semantic meaning.
    2. Keyword overlap for exact medical/product/hospital words.
    3. Fuzzy matching for small typos.
    """
    global chunks, chunk_sources, index

    if index is None or not chunks:
        return []

    norm = normalize_for_chat(question)
    search_text = norm.search_text or norm.chat_text or question
    query_tokens = _tokens(search_text)

    # 1) Vector candidate retrieval.
    q_emb = _normalize_vector(_safe_embedding(search_text)).reshape(1, -1).astype("float32")
    k = min(max(candidate_k, top_k), len(chunks))
    vector_scores, vector_indices = index.search(q_emb, k)

    candidate_map: dict[int, float] = {}
    for raw_score, idx in zip(vector_scores[0], vector_indices[0]):
        if 0 <= idx < len(chunks):
            # cosine usually [-1, 1]. Map to [0, 1] for easier mixing.
            candidate_map[int(idx)] = max(0.0, min(1.0, (float(raw_score) + 1.0) / 2.0))

    # 2) Add keyword/fuzzy candidates too, in case FAISS misses exact names.
    for i, chunk in enumerate(chunks):
        chunk_tokens = _tokens(chunk)
        kw = _keyword_score(query_tokens, chunk_tokens)
        fuzzy = _fuzzy_score(search_text, chunk, query_tokens, chunk_tokens)
        if kw >= 0.20 or fuzzy >= 0.82:
            candidate_map.setdefault(i, 0.0)

    results: list[SearchResult] = []
    for idx, vector_score in candidate_map.items():
        chunk = chunks[idx]
        chunk_tokens = _tokens(chunk)
        kw_score = _keyword_score(query_tokens, chunk_tokens)
        fz_score = _fuzzy_score(search_text, chunk, query_tokens, chunk_tokens)
        score = _combine_scores(vector_score, kw_score, fz_score)

        if score >= min_score:
            results.append(
                SearchResult(
                    text=chunk,
                    score=round(score, 4),
                    vector_score=round(vector_score, 4),
                    keyword_score=round(kw_score, 4),
                    fuzzy_score=round(fz_score, 4),
                    source=chunk_sources[idx] if idx < len(chunk_sources) else "hospital_knowledge.txt",
                )
            )

    results.sort(key=lambda item: item.score, reverse=True)
    return results[:top_k]


def search_context(question: str, top_k: int = 3):
    """Backward-compatible helper used by rag_chat.py.

    Returns a plain context string, but internally uses the new hybrid search.
    """
    results = hybrid_search(question, top_k=top_k)
    return "\n\n".join(result.text for result in results)


def format_results_for_prompt(results: Iterable[SearchResult]) -> str:
    lines = []
    for idx, result in enumerate(results, start=1):
        lines.append(
            f"[Source {idx}: {result.source}, relevance={result.score}]\n{result.text}"
        )
    return "\n\n".join(lines)
