# Hospital Assistant Python (MVP)

## Run (Windows CMD)
1) python -m venv .venv
2) .venv\Scripts\activate
3) pip install -r requirements.txt
4) copy .env.example .env
5) edit .env with real MYSQL_URL
6) uvicorn app.main:app --reload --port 8000

Open:
http://127.0.0.1:8000/docs