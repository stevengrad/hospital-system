# Feature 4 — Pharmacy Product Recommendation, Comparison, and Order Requests

This update adds a pharmacy product layer based on `app/data/pharmacy_products.csv`, generated from the uploaded Excel file after adding `Quantity` and stock status.

## What it supports

1. **Product recommendation** by meaning, not exact wording.
   - Arabic: `عايزة غسول للبشرة الدهنية`
   - Franco: `3awza 8asol lel bahsra el dohneya`
   - English: `recommend a cleanser for oily skin`

2. **Hybrid product search**
   - Vector search using the existing embedding model.
   - Keyword matching for exact brand/skin/product words.
   - Fuzzy matching for typos and Franco spellings.

3. **Product comparison**
   - `قارن بين La Roche-Posay Effaclar Gel و Bioderma Sebium Gel Moussant`
   - `compare Effaclar Gel vs Bioderma Sebium`

4. **Stock-aware responses**
   - In stock: shows current quantity and offers to take order request.
   - Low stock: tells user to confirm with pharmacy.
   - Out of stock: says out of stock and can save a follow-up request.

5. **Simple order request flow**
   - After recommendations, user can type `اطلب رقم 1` or `order 1`.
   - Bot asks for quantity + phone/note.
   - Order request is saved to `app/data/pharmacy_orders.jsonl`.

## Files added/updated

- `app/data/pharmacy_products.csv`
- `app/data/Comprehensive_Medications_Database_with_Quantity.xlsx`
- `app/tools/pharmacy_products.py`
- `app/tools/chat_free.py`
- `app/ai/text_normalizer.py`
- `app/main.py`

## Important note

This feature saves **order requests**, not final paid orders. The pharmacy team should confirm availability, price, delivery/pickup, and payment.
