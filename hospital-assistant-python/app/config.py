import os
from dataclasses import dataclass

@dataclass(frozen=True)
class Settings:
    mysql_url: str
    slot_minutes: int = 30  # مدة الكشف/الـslot

def get_settings() -> Settings:
    mysql_url = os.getenv("MYSQL_URL")
    if not mysql_url:
        raise RuntimeError("Missing MYSQL_URL. Set it like: mysql+pymysql://root@host:3308/hospital_system_gp")
    slot_minutes = int(os.getenv("SLOT_MINUTES", "30"))
    return Settings(mysql_url=mysql_url, slot_minutes=slot_minutes)