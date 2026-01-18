"""
Configuración compartida para scripts de ByBot
"""

import os
from pathlib import Path

# =============================================
# RUTAS
# =============================================
SCRIPT_DIR = Path(__file__).parent.parent
TEMP_DIR = SCRIPT_DIR / "temp"
LOGS_DIR = SCRIPT_DIR / "logs"

# Crear directorios si no existen
TEMP_DIR.mkdir(exist_ok=True)
LOGS_DIR.mkdir(exist_ok=True)

# =============================================
# CONFIGURACIÓN DE API (Hostinger)
# =============================================
BYBOT_API_URL = os.getenv("BYBOT_API_URL", "https://bybjuridicos.andapps.cloud/web/api/v1")
BYBOT_ACCESS_TOKEN = os.getenv("BYBOT_ACCESS_TOKEN", "")  # Mismo valor que WORKER_API_TOKEN en PHP

# =============================================
# CONFIGURACIÓN DE GEMINI
# =============================================
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "gemini-1.5-flash")
GEMINI_TEMPERATURE = float(os.getenv("GEMINI_TEMPERATURE", "0.1"))
GEMINI_MAX_TOKENS = int(os.getenv("GEMINI_MAX_TOKENS", "4000"))

# =============================================
# CONFIGURACIÓN DE PROCESAMIENTO
# =============================================
MAX_FILE_SIZE_MB = int(os.getenv("MAX_FILE_SIZE_MB", "10"))
TIMEOUT_SECONDS = int(os.getenv("TIMEOUT_SECONDS", "120"))

# =============================================
# LOGGING
# =============================================
LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO")
LOG_FORMAT = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"

