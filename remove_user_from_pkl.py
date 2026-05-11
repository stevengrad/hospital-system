import pickle
import shutil
from pathlib import Path

PKL_FILE = Path("representations.pkl")
USERNAME = "steve77"

BACKUP_FILE = Path(f"representations_backup_before_delete_{USERNAME}.pkl")

def belongs_to_user(item, username):
    """
    Detect if one pickle entry belongs to username.
    Works with common structures:
    - dict with identity/image/path/file keys
    - list/tuple containing path or username
    - string path containing username
    """
    username_lower = username.lower()

    if isinstance(item, str):
        return username_lower in item.lower()

    if isinstance(item, dict):
        for key, value in item.items():
            key_lower = str(key).lower()

            if key_lower in ["identity", "path", "image", "file", "filename", "name", "username"]:
                if isinstance(value, str) and username_lower in value.lower():
                    return True

            if isinstance(value, str) and username_lower in value.lower():
                return True

        return False

    if isinstance(item, (list, tuple)):
        return any(belongs_to_user(x, username) for x in item)

    return False


if not PKL_FILE.exists():
    raise FileNotFoundError(f"{PKL_FILE} not found")

shutil.copy2(PKL_FILE, BACKUP_FILE)
print(f"Backup created: {BACKUP_FILE}")

with open(PKL_FILE, "rb") as f:
    data = pickle.load(f)

before_count = None
after_count = None

if isinstance(data, list):
    before_count = len(data)
    data = [item for item in data if not belongs_to_user(item, USERNAME)]
    after_count = len(data)

elif isinstance(data, dict):
    before_count = len(data)

    keys_to_delete = []
    for key, value in data.items():
        if belongs_to_user(key, USERNAME) or belongs_to_user(value, USERNAME):
            keys_to_delete.append(key)

    for key in keys_to_delete:
        del data[key]

    after_count = len(data)

else:
    raise TypeError(f"Unsupported pickle structure: {type(data)}")

with open(PKL_FILE, "wb") as f:
    pickle.dump(data, f)

print("Done.")
print(f"Before: {before_count}")
print(f"After: {after_count}")
print(f"Removed: {before_count - after_count}")