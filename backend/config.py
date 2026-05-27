from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
STORAGE_DIR = BASE_DIR / "storage" / "lyrics"
JOBS_DIR = STORAGE_DIR / "jobs"
UPLOADS_DIR = STORAGE_DIR / "uploads"
OUTPUTS_DIR = STORAGE_DIR / "outputs"
WORK_DIR = STORAGE_DIR / "work"

HOST = "0.0.0.0"
PORT = 18201
DEFAULT_MODEL = "small"
DEFAULT_LANGUAGE = "ja"

