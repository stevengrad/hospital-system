from sqlalchemy import text
from app.db import SessionLocal

def find_branches_by_name(name: str, limit: int = 10):
    sql = """
    SELECT BranchID, Name
    FROM branches
    WHERE Name LIKE :q
    ORDER BY Name
    LIMIT :limit
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"q": f"%{name}%", "limit": limit}).mappings().all()
    return [dict(r) for r in rows]

def find_doctors_by_name(name: str, limit: int = 10):
    # DoctorID == EmployeeID
    sql = """
    SELECT
      d.EmployeeID AS DoctorID,
      e.FirstName,
      e.LastName
    FROM doctors d
    JOIN employees e ON e.EmployeeID = d.EmployeeID
    WHERE e.Role = 'Doctor'
      AND CONCAT(e.FirstName, ' ', e.LastName) LIKE :q
    ORDER BY e.FirstName, e.LastName
    LIMIT :limit
    """
    with SessionLocal() as db:
        rows = db.execute(text(sql), {"q": f"%{name}%", "limit": limit}).mappings().all()

    out = []
    for r in rows:
        rr = dict(r)
        rr["FullName"] = f"{rr['FirstName']} {rr['LastName']}".strip()
        out.append(rr)
    return out
def find_branches_by_name(q: str):
    q = (q or "").strip()
    if not q:
        return []

    sql = """
    SELECT BranchID, Name
    FROM branches
    WHERE Name LIKE :q
    LIMIT 10
    """

    def _run(pattern: str):
        with SessionLocal() as db:
            rows = db.execute(text(sql), {"q": f"%{pattern}%"}).mappings().all()
        return [dict(r) for r in rows]

    rows = _run(q)
    if rows:
        return rows

    # fallback: normalize (remove hyphens + extra spaces)
    q2 = q.replace("-", " ")
    while "  " in q2:
        q2 = q2.replace("  ", " ")
    return _run(q2)