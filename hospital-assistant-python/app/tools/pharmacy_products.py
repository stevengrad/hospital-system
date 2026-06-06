from __future__ import annotations

import csv
import json
import math
import re
from dataclasses import dataclass
from datetime import datetime
from difflib import SequenceMatcher
from pathlib import Path
from typing import Any

import faiss
import numpy as np

from app.ai.embeddings import get_embedding
from app.ai.text_normalizer import normalize_for_chat


DATA_PATH = Path(__file__).resolve().parents[1] / "data" / "pharmacy_products.csv"
ORDERS_PATH = Path(__file__).resolve().parents[1] / "data" / "pharmacy_orders.jsonl"

_TOKEN_RE = re.compile(r"[\w\u0600-\u06FF]+", flags=re.UNICODE)

_STOPWORDS = {
    "a", "an", "the", "is", "are", "am", "i", "you", "do", "does", "did", "have", "has",
    "for", "to", "of", "in", "on", "at", "and", "or", "with", "about", "what", "when", "where",
    "how", "can", "could", "please", "need", "want", "me", "my", "your", "it", "this", "that",
    "انا", "انت", "انتي", "هو", "هي", "في", "من", "عن", "علي", "على", "الى", "الي", "و", "او",
    "ده", "دي", "دا", "ايه", "ازاي", "امتي", "فين", "لو", "ممكن", "عايز", "عايزه", "محتاج", "محتاجه",
    "عندي", "عندك", "هل", "ما", "لا", "اه", "ايوه", "طب", "طيب", "واحد", "حاجه", "شيء",
}

PRODUCT_AR_EN_TERMS = {
    # product types
    "غسول": "cleanser wash gel facial cleanser foaming gel",
    "غسول_وجه": "face cleanser facial wash",
    "كريم": "cream moisturizer topical cream",
    "مرطب": "moisturizer hydrating cream",
    "سيروم": "serum",
    "تونر": "toner",
    "واقي": "sunscreen sunblock",
    "حبوب": "tablets capsules medication",
    # skin types / needs
    "بشره": "skin",
    "البشره": "skin",
    "دهنيه": "oily sebum acne prone",
    "جافه": "dry dehydrated xerosis",
    "حساسه": "sensitive redness prone",
    "مختلطه": "combination t-zone",
    "حبوب": "acne pimples blemish",
    "رؤوس": "blackheads pores",
    "تفتيح": "brightening dullness vitamin c",
    "مسام": "pores enlarged pores",
    "قشره": "flaky dandruff scaling",
    # common symptoms/needs mapped to pharmacy rows
    "صداع": "headache pain analgesic migraine",
    "حراره": "fever antipyretic",
    "كحه": "cough respiratory",
    "برد": "cold flu congestion",
    "حرقان": "heartburn acid reflux gerd",
    "مغص": "spasms cramps abdominal pain",
    "اسهال": "diarrhea",
    "امساك": "constipation",
    "حساسيه": "allergy allergic rhinitis antihistamine",
}

FRANCO_PRODUCT_TERMS = {
    "8asol": "غسول", "ghasol": "غسول", "ghasool": "غسول", "gsol": "غسول", "wash": "غسول", "cleanser": "غسول",
    "bashara": "بشره", "bshra": "بشره", "bahsra": "بشره", "bashra": "بشره", "beshera": "بشره",
    "dohneya": "دهنيه", "dohnia": "دهنيه", "dohnya": "دهنيه", "dehneyya": "دهنيه", "oily": "دهنيه",
    "gafa": "جافه", "dry": "جافه", "sensitive": "حساسه", "7asasa": "حساسه", "mokhtaleta": "مختلطه",
    "7obob": "حبوب", "hobob": "حبوب", "acne": "حبوب", "blackheads": "رؤوس", "pores": "مسام",
    "tafteeh": "تفتيح", "brightening": "تفتيح", "serum": "سيروم", "moisturizer": "مرطب", "cream": "كريم",
}

COMPARE_WORDS = [
    "compare", "comparison", "difference", "better", "vs", "versus", "قارن", "مقارنه", "مقارنة", "الفرق", "افضل", "احسن", "ولا", "بين", "maben", "mabyn",
]

RECOMMEND_WORDS = [
    "recommend", "suggest", "suitable", "best", "for", "need", "want", "medicine", "medication", "treatment", "product",
    "عايز", "عايزه", "عايزة", "عاوز", "عاوزه", "عاوزة", "محتاج", "محتاجه", "محتاجة",
    "حاجة", "حاجه", "دواء", "علاج", "منتج", "رشح", "تنصح", "ينفع", "مناسب", "مناسبه", "مناسبة",
    "غسول", "بشره", "بشرة", "دهنيه", "دهنية", "جافه", "جافة", "حساسه", "حساسة",
]

ORDER_WORDS = [
    "order", "buy", "purchase", "اطلب", "اطلبي", "اوردر", "اشتري", "عايزه اطلب", "عايز اطلب", "yes", "اه", "ايوه", "تمام",
]


def _basic_product_normalize(text: str) -> str:
    norm = normalize_for_chat(text or "")
    x = f"{norm.search_text} {text or ''}".lower()
    for src, target in FRANCO_PRODUCT_TERMS.items():
        x = re.sub(r"(?<!\w)" + re.escape(src) + r"(?!\w)", f" {target} ", x, flags=re.IGNORECASE)
    for src, target in PRODUCT_AR_EN_TERMS.items():
        x = re.sub(r"(?<!\w)" + re.escape(src) + r"(?!\w)", f" {src} {target} ", x, flags=re.IGNORECASE)
    return re.sub(r"\s+", " ", x).strip()


def _tokens(text: str) -> set[str]:
    x = _basic_product_normalize(text)
    toks = set(_TOKEN_RE.findall(x))
    return {t for t in toks if len(t) > 1 and t not in _STOPWORDS}


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


def _keyword_score(query_tokens: set[str], doc_tokens: set[str]) -> float:
    if not query_tokens or not doc_tokens:
        return 0.0
    overlap = query_tokens.intersection(doc_tokens)
    return len(overlap) / max(len(query_tokens), 1)


def _fuzzy_score(query: str, doc: str, query_tokens: set[str], doc_tokens: set[str]) -> float:
    phrase_score = SequenceMatcher(None, query.lower(), doc.lower()).ratio() if query and doc else 0.0
    best = 0.0
    for q in query_tokens:
        if len(q) < 3:
            continue
        for d in doc_tokens:
            if len(d) < 3:
                continue
            best = max(best, SequenceMatcher(None, q, d).ratio())
    return max(phrase_score, best)


def _quantity_int(value: Any) -> int:
    try:
        return int(float(str(value).strip()))
    except Exception:
        return 0


@dataclass
class PharmacyProduct:
    category: str
    disease_skin_type: str
    brand_name: str
    active_ingredient: str
    main_indication: str
    quantity: int
    stock_status: str

    @property
    def searchable_text(self) -> str:
        extra = _basic_product_normalize(
            f"{self.category} {self.disease_skin_type} {self.brand_name} {self.active_ingredient} {self.main_indication}"
        )
        return (
            f"Category: {self.category}. Disease/Skin Type: {self.disease_skin_type}. "
            f"Brand: {self.brand_name}. Active Ingredient: {self.active_ingredient}. "
            f"Indication: {self.main_indication}. Quantity: {self.quantity}. Status: {self.stock_status}. {extra}"
        )

    def to_dict(self) -> dict[str, Any]:
        return {
            "category": self.category,
            "disease_skin_type": self.disease_skin_type,
            "brand_name": self.brand_name,
            "active_ingredient": self.active_ingredient,
            "main_indication": self.main_indication,
            "quantity": self.quantity,
            "stock_status": self.stock_status,
        }


_products: list[PharmacyProduct] = []
_product_docs: list[str] = []
_product_tokens: list[set[str]] = []
_index = None


def load_pharmacy_products(force: bool = False) -> None:
    global _products, _product_docs, _product_tokens, _index
    if _products and _index is not None and not force:
        return
    _products = []
    _product_docs = []
    _product_tokens = []
    _index = None
    if not DATA_PATH.exists():
        return

    with DATA_PATH.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            q = _quantity_int(row.get("Quantity") or row.get("Quantaty") or row.get("quantity"))
            stock = (row.get("Stock Status") or "").strip()
            if not stock:
                stock = "Out of stock" if q <= 0 else ("Low stock" if q <= 5 else "In stock")
            item = PharmacyProduct(
                category=(row.get("Category") or "").strip(),
                disease_skin_type=(row.get("Disease / Skin Type") or row.get("Disease/Skin Type") or "").strip(),
                brand_name=(row.get("Brand Name") or "").strip(),
                active_ingredient=(row.get("Active Ingredient") or "").strip(),
                main_indication=(row.get("Main Indication") or "").strip(),
                quantity=q,
                stock_status=stock,
            )
            if item.brand_name:
                _products.append(item)

    if not _products:
        return

    _product_docs = [p.searchable_text for p in _products]
    _product_tokens = [_tokens(doc) for doc in _product_docs]
    embeddings = np.array([_safe_embedding(doc) for doc in _product_docs], dtype="float32")
    embeddings = _normalize_matrix(embeddings).astype("float32")
    _index = faiss.IndexFlatIP(embeddings.shape[1])
    _index.add(embeddings)


def _search_products(query: str, top_k: int = 5, candidate_k: int = 30, min_score: float = 0.25) -> list[dict[str, Any]]:
    load_pharmacy_products()
    if _index is None or not _products:
        return []

    search_query = _basic_product_normalize(query)
    q_tokens = _tokens(search_query)
    q_emb = _normalize_vector(_safe_embedding(search_query)).reshape(1, -1).astype("float32")
    k = min(max(candidate_k, top_k), len(_products))
    vector_scores, vector_indices = _index.search(q_emb, k)

    candidate_scores: dict[int, float] = {}
    for raw_score, idx in zip(vector_scores[0], vector_indices[0]):
        if 0 <= idx < len(_products):
            candidate_scores[int(idx)] = max(0.0, min(1.0, (float(raw_score) + 1.0) / 2.0))

    # Add exact/fuzzy candidates, useful for brand names and typos.
    for i, toks in enumerate(_product_tokens):
        kw = _keyword_score(q_tokens, toks)
        fz = _fuzzy_score(search_query, _product_docs[i], q_tokens, toks)
        if kw >= 0.15 or fz >= 0.83:
            candidate_scores.setdefault(i, 0.0)

    results: list[dict[str, Any]] = []
    for idx, vector_score in candidate_scores.items():
        kw = _keyword_score(q_tokens, _product_tokens[idx])
        fz = _fuzzy_score(search_query, _product_docs[idx], q_tokens, _product_tokens[idx])
        score = (0.60 * vector_score) + (0.30 * kw) + (0.10 * fz)
        item = _products[idx]

        # Recommendation should prefer available products, but still show out-of-stock when relevant.
        availability_bonus = 0.05 if item.quantity > 0 else -0.08
        score += availability_bonus

        if score >= min_score:
            row = item.to_dict()
            row.update({
                "score": round(score, 4),
                "vector_score": round(vector_score, 4),
                "keyword_score": round(kw, 4),
                "fuzzy_score": round(fz, 4),
            })
            results.append(row)

    results.sort(key=lambda r: (r["quantity"] > 0, r["score"]), reverse=True)
    return results[:top_k]


def is_product_comparison_question(text: str) -> bool:
    x = _basic_product_normalize(text)
    return any(w in x for w in COMPARE_WORDS)


def is_product_recommendation_question(text: str) -> bool:
    x = _basic_product_normalize(text)
    # Avoid catching medication safety questions.
    if any(k in x for k in ["مخاطر", "risk", "warning", "interaction", "تعارض", "تداخل"]):
        return False
    product_domain = any(k in x for k in [
        # skincare/products
        "غسول", "cleanser", "wash", "بشره", "بشرة", "skin", "دهنيه", "دهنية", "oily",
        "حبوب", "acne", "مرطب", "كريم", "cream", "serum", "سيروم", "واقي", "sunscreen",
        # common pharmacy needs/symptoms that can be answered from pharmacy file
        "مغص", "spasm", "cramp", "stomach", "abdominal", "صداع", "headache",
        "حراره", "حرارة", "fever", "كحه", "كحة", "cough", "برد", "cold", "flu",
        "حساسيه", "حساسية", "allergy", "حرقان", "heartburn", "اسهال", "diarrhea", "امساك", "constipation",
        "الم", "ألم", "pain"
    ])
    recommendation_domain = any(k in x for k in RECOMMEND_WORDS)
    return product_domain and recommendation_domain


def is_product_order_request(text: str) -> bool:
    x = _basic_product_normalize(text)
    return any(w in x for w in ORDER_WORDS)


def recommend_products(text: str, limit: int = 5) -> list[dict[str, Any]]:
    return _search_products(text, top_k=limit, candidate_k=40, min_score=0.25)


def find_products_by_name(text: str, limit: int = 3) -> list[dict[str, Any]]:
    return _search_products(text, top_k=limit, candidate_k=30, min_score=0.22)



def _simplify_product_text(text: str) -> str:
    """Normalize a brand/query to comparable lightweight tokens."""
    x = _basic_product_normalize(text or "").lower()
    # keep letters/numbers/Arabic only
    return " ".join(_TOKEN_RE.findall(x))


def _meaningful_brand_tokens(brand: str) -> set[str]:
    toks = set(_TOKEN_RE.findall(_simplify_product_text(brand)))
    generic = {
        "advance", "extra", "plus", "protect", "forte", "gel", "cream", "tablet", "tablets",
        "capsule", "capsules", "syrup", "mg", "ml", "100", "500", "1000"
    }
    return {t for t in toks if len(t) >= 3 and t not in generic}


def _mentioned_products_in_query(text: str) -> list[dict[str, Any]]:
    """Return products explicitly mentioned by brand, e.g. Panadol and Brufen.

    This prevents comparison questions from comparing the top recommendation
    results instead of the two products the user actually named.
    """
    load_pharmacy_products()
    if not _products:
        return []

    q = _simplify_product_text(text)
    q_tokens = set(_TOKEN_RE.findall(q))
    selected: list[dict[str, Any]] = []
    seen = set()

    # 1) Strong token containment: "Panadol" matches "Panadol Advance".
    for item in _products:
        brand_key = item.brand_name.lower()
        if brand_key in seen:
            continue
        brand_tokens = _meaningful_brand_tokens(item.brand_name)
        if brand_tokens and brand_tokens.intersection(q_tokens):
            row = item.to_dict()
            row.update({"score": 1.0, "explicit_brand_match": True})
            selected.append(row)
            seen.add(brand_key)

    # 2) Fuzzy fallback for typos in brand names.
    if len(selected) < 2:
        for item in _products:
            brand_key = item.brand_name.lower()
            if brand_key in seen:
                continue
            brand_tokens = _meaningful_brand_tokens(item.brand_name)
            if not brand_tokens:
                continue
            best = 0.0
            for bt in brand_tokens:
                for qt in q_tokens:
                    if len(qt) >= 3:
                        best = max(best, SequenceMatcher(None, bt, qt).ratio())
            if best >= 0.88:
                row = item.to_dict()
                row.update({"score": round(best, 4), "explicit_brand_match": True})
                selected.append(row)
                seen.add(brand_key)
            if len(selected) >= 3:
                break

    # Keep order based on first occurrence in user text when possible.
    def pos(row: dict[str, Any]) -> int:
        positions = []
        for token in _meaningful_brand_tokens(row.get("brand_name", "")):
            idx = q.find(token)
            if idx >= 0:
                positions.append(idx)
        return min(positions) if positions else 10**9

    selected.sort(key=pos)
    return selected[:3]


def _need_hint(text: str) -> str:
    x = _basic_product_normalize(text)
    if any(k in x for k in ["صداع", "headache", "migraine", "soda3"]):
        return "headache"
    if any(k in x for k in ["حراره", "fever", "temperature"]):
        return "fever"
    if any(k in x for k in ["التهاب", "inflammation", "swelling"]):
        return "inflammation"
    if any(k in x for k in ["وجع", "pain", "ache"]):
        return "pain"
    return ""


def _best_for_need(items: list[dict[str, Any]], text: str) -> dict[str, Any] | None:
    need = _need_hint(text)
    if not need or not items:
        return None

    def suitability(item: dict[str, Any]) -> float:
        blob = _basic_product_normalize(
            f"{item.get('brand_name','')} {item.get('disease_skin_type','')} {item.get('active_ingredient','')} {item.get('main_indication','')}"
        ).lower()
        score = 0.0
        if need == "headache":
            for k in ["headache", "migraine", "صداع", "pain relief", "analgesic", "paracetamol", "acetaminophen"]:
                if k in blob:
                    score += 2.0
            for k in ["toothache", "dental", "joint", "swelling", "inflammation"]:
                if k in blob:
                    score -= 0.7
        elif need == "fever":
            for k in ["fever", "antipyretic", "حراره", "paracetamol", "acetaminophen"]:
                if k in blob:
                    score += 2.0
        elif need == "inflammation":
            for k in ["inflammation", "swelling", "nsaid", "ibuprofen"]:
                if k in blob:
                    score += 2.0
        elif need == "pain":
            for k in ["pain", "analgesic", "paracetamol", "ibuprofen", "acetaminophen"]:
                if k in blob:
                    score += 1.5
        if int(item.get("quantity") or 0) <= 0:
            score -= 1.0
        return score

    ranked = sorted(items, key=suitability, reverse=True)
    return ranked[0] if suitability(ranked[0]) > 0 else None

def compare_products(text: str) -> list[dict[str, Any]]:
    # First: respect products explicitly named by the user.
    # Example: "which is better for headache Panadol or Brufen" must compare
    # Panadol with Brufen, not Panadol with another headache recommendation.
    selected: list[dict[str, Any]] = _mentioned_products_in_query(text)
    seen = {i["brand_name"].lower() for i in selected}

    if len(selected) >= 2:
        return selected[:3]

    # Extract product-like pieces around common separators; fallback to search the whole query.
    clean = re.sub(r"\b(vs|versus|and|or|with|between|maben|mabyn)\b", "|", text, flags=re.IGNORECASE)
    clean = re.sub(r"(قارن|مقارنه|مقارنة|الفرق|بين|و|ولا|مع)", "|", clean)
    pieces = [p.strip(" ?:-،,") for p in clean.split("|") if len(p.strip()) >= 2]

    for piece in pieces:
        if len(_tokens(piece)) == 0:
            continue
        results = find_products_by_name(piece, limit=2)
        for item in results:
            key = item["brand_name"].lower()
            if key not in seen:
                selected.append(item)
                seen.add(key)
                break
        if len(selected) >= 2:
            break

    if len(selected) < 2:
        results = recommend_products(text, limit=4)
        for item in results:
            key = item["brand_name"].lower()
            if key not in seen:
                selected.append(item)
                seen.add(key)
            if len(selected) >= 2:
                break
    return selected[:3]


def format_stock_line(item: dict[str, Any], lang: str = "ar") -> str:
    q = int(item.get("quantity") or 0)
    if lang == "en":
        if q <= 0:
            return "Out of stock right now."
        if q <= 5:
            return f"Low stock: only {q} available. Please call our pharmacy to confirm before ordering."
        return f"In stock: {q} available. You can call our pharmacy to order it, or I can take the order request from you."
    if q <= 0:
        return "غير متوفر حاليًا (Out of stock)."
    if q <= 5:
        return f"متاح لكن الكمية قليلة: {q} فقط. يفضّل الاتصال بالصيدلية للتأكيد قبل الطلب."
    return f"متوفر في الصيدلية: الكمية الحالية {q}. ممكن تتصلي بالصيدلية لطلبه أو أقدر آخد منك طلب أوردر."


def format_recommendations(items: list[dict[str, Any]], lang: str = "ar") -> str:
    if not items:
        return "مش لاقية منتج مناسب في ملف الصيدلية الحالي." if lang == "ar" else "I could not find a suitable product in the current pharmacy file."
    lines = []
    if lang == "en":
        lines.append("Closest pharmacy recommendations:")
        for i, item in enumerate(items, start=1):
            lines.append(
                f"{i}) {item['brand_name']} — {item['disease_skin_type']}\n"
                f"   Active: {item['active_ingredient']}\n"
                f"   Why: {item['main_indication']}\n"
                f"   Stock: {format_stock_line(item, 'en')}"
            )
        lines.append("\nTo order, type: order <number> or order <product name>.")
        return "\n".join(lines)

    lines.append("أقرب ترشيحات من ملف صيدلية المستشفى:")
    for i, item in enumerate(items, start=1):
        lines.append(
            f"{i}) {item['brand_name']} — {item['disease_skin_type']}\n"
            f"   المادة الفعالة: {item['active_ingredient']}\n"
            f"   السبب: {item['main_indication']}\n"
            f"   الحالة: {format_stock_line(item, 'ar')}"
        )
    lines.append("\nلو عايزة أطلبهولك اكتبي: اطلب رقم <الرقم> أو اطلب <اسم المنتج>.")
    return "\n".join(lines)


def format_comparison(items: list[dict[str, Any]], lang: str = "ar", question: str = "") -> str:
    if len(items) < 2:
        return "محتاج اسم منتجين على الأقل عشان أعمل مقارنة." if lang == "ar" else "I need at least two product names to compare."
    if lang == "en":
        lines = ["Product comparison:"]
        for item in items:
            lines.append(
                f"- {item['brand_name']}\n"
                f"  Use/Skin type: {item['disease_skin_type']}\n"
                f"  Active ingredient: {item['active_ingredient']}\n"
                f"  Main indication: {item['main_indication']}\n"
                f"  Stock: {format_stock_line(item, 'en')}"
            )
        best_need = _best_for_need(items, question) if question else None
        if best_need:
            lines.append(f"\nFor your need, the better match is: {best_need['brand_name']}.")
        else:
            available = [i for i in items if int(i.get("quantity") or 0) > 0]
            if available:
                lines.append(f"\nBest available option now: {available[0]['brand_name']}.")
        return "\n".join(lines)

    lines = ["مقارنة المنتجات:"]
    for item in items:
        lines.append(
            f"- {item['brand_name']}\n"
            f"  الاستخدام/نوع البشرة: {item['disease_skin_type']}\n"
            f"  المادة الفعالة: {item['active_ingredient']}\n"
            f"  المؤشر الأساسي: {item['main_indication']}\n"
            f"  المخزون: {format_stock_line(item, 'ar')}"
        )
    best_need = _best_for_need(items, question) if question else None
    if best_need:
        lines.append(f"\nبالنسبة لاحتياجك، الأنسب هو: {best_need['brand_name']}.")
    else:
        available = [i for i in items if int(i.get("quantity") or 0) > 0]
        if available:
            lines.append(f"\nالأفضل المتاح حاليًا من ناحية المخزون: {available[0]['brand_name']}.")
    return "\n".join(lines)


def create_pharmacy_order(product: dict[str, Any], quantity: int, customer_note: str, chat_id: str | None = None) -> dict[str, Any]:
    available_qty = int(product.get("quantity") or 0)
    requested_qty = max(1, int(quantity or 1))
    order = {
        "created_at": datetime.utcnow().isoformat() + "Z",
        "chat_id": chat_id or "default",
        "brand_name": product.get("brand_name"),
        "active_ingredient": product.get("active_ingredient"),
        "requested_quantity": requested_qty,
        "available_quantity_at_request": available_qty,
        "stock_status_at_request": product.get("stock_status"),
        "customer_note": customer_note,
        "status": "pending_pharmacy_confirmation" if available_qty >= requested_qty else "pending_out_of_stock_followup",
    }
    ORDERS_PATH.parent.mkdir(parents=True, exist_ok=True)
    with ORDERS_PATH.open("a", encoding="utf-8") as f:
        f.write(json.dumps(order, ensure_ascii=False) + "\n")
    return order
