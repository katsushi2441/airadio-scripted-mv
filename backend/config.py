from pathlib import Path
import os

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

OLLAMA_URL = os.environ.get("OLLAMA_URL", "http://192.168.0.14:11434")
OLLAMA_MODEL = os.environ.get("OLLAMA_MODEL", "gemma4:e4b")
ERNIE_URL = os.environ.get("ERNIE_URL", "http://192.168.0.3:8010/image/generate")
NVM_NODE = os.environ.get("NVM_NODE", "/home/kojima/.nvm/versions/node/v22.22.3/bin")
HYPERFRAMES_VERSION = "0.4.44"
