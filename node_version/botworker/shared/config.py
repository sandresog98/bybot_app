"""
Configuración compartida para botworker (analizador + daemon).
Lee variables del .env único en la raíz del proyecto (node_version/.env).
"""
import os
from pathlib import Path
from dotenv import load_dotenv

# Raíz del monorepo: botworker/ → subir 1 nivel
ROOT = Path(__file__).resolve().parent.parent.parent
load_dotenv(ROOT / ".env")

# ===== Gemini =====
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "gemini-1.5-flash")
GEMINI_TEMPERATURE = float(os.getenv("GEMINI_TEMPERATURE", "0.1"))
GEMINI_MAX_TOKENS = int(os.getenv("GEMINI_MAX_TOKENS", "4000"))

# ===== Base de datos =====
DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("DB_PORT", "3306"))
DB_NAME = os.getenv("DB_NAME", "bybot_consolidado")
DB_USER = os.getenv("DB_USER", "root")
DB_PASS = os.getenv("DB_PASS", "")

# ===== Rutas =====
UPLOADS_DIR = ROOT / os.getenv("STORAGE_LOCAL_DIR", "uploads")

# ===== Colas =====
COLA_POLL_INTERVAL = int(os.getenv("COLA_POLL_INTERVAL_SEG", "5"))
WORKER_TIMEOUT = int(os.getenv("WORKER_TIMEOUT_SEG", "120"))