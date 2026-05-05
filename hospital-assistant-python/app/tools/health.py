from sqlalchemy import text
from app.db import SessionLocal

def db_ping():
    with SessionLocal() as db:
        return db.execute(text("SELECT 1")).scalar()