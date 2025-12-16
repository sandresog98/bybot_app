#!/usr/bin/env python3
"""
Configuración centralizada para el Bot de análisis con Gemini
"""

import os
from dotenv import load_dotenv

# Cargar variables de entorno
load_dotenv(dotenv_path=os.path.join(os.path.dirname(__file__), '../../.env'))

# Configuración de base de datos
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASS', ''),
    'database': os.getenv('DB_NAME', 'by_bot_app'),
    'charset': 'utf8mb4',
    'autocommit': False
}

# Configuración de Gemini API
GEMINI_CONFIG = {
    'api_key': os.getenv('GEMINI_API_KEY', ''),
    'model': os.getenv('GEMINI_MODEL', 'gemini-1.5-flash'),  # Modelo configurable desde .env
    'temperature': float(os.getenv('GEMINI_TEMPERATURE', '0.1')),  # Baja temperatura para respuestas más precisas
    'max_tokens': int(os.getenv('GEMINI_MAX_TOKENS', '4000'))
}

# Configuración de procesamiento
PROCESSING_CONFIG = {
    'poll_interval': 30,  # Segundos entre consultas de nuevos procesos
    'max_retries': 3,  # Intentos máximos si falla el análisis
    'timeout': 300,  # Timeout para análisis de documentos (5 minutos)
    'batch_size': 1  # Procesar un proceso a la vez
}

# Rutas de archivos
BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UPLOADS_DIR = os.path.join(BASE_DIR, 'uploads', 'crear_coop')

# Configuración del servidor PHP (para descargar archivos)
SERVER_CONFIG = {
    'base_url': os.getenv('SERVER_BASE_URL', 'http://localhost/bybot_app/admin'),
    'api_token': os.getenv('BOT_API_TOKEN', ''),
    'timeout': 300  # Timeout para descargas (5 minutos)
}

# Logging
LOG_DIR = os.path.join(BASE_DIR, 'bot', 'logs')
LOG_FILE = os.path.join(LOG_DIR, 'bot.log')

