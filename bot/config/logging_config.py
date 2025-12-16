#!/usr/bin/env python3
"""
Configuración de logging para el Bot
"""

import logging
import os
from datetime import datetime
from config.settings import LOG_DIR, LOG_FILE

def setup_logging():
    """Configurar sistema de logging"""
    
    # Crear directorio de logs si no existe
    os.makedirs(LOG_DIR, exist_ok=True)
    
    # Configurar formato
    log_format = '%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    date_format = '%Y-%m-%d %H:%M:%S'
    
    # Configurar logging
    logging.basicConfig(
        level=logging.INFO,
        format=log_format,
        datefmt=date_format,
        handlers=[
            logging.FileHandler(LOG_FILE, encoding='utf-8'),
            logging.StreamHandler()
        ]
    )
    
    logger = logging.getLogger('bybot')
    logger.info("=" * 60)
    logger.info("🚀 Bot de Análisis ByBot iniciado")
    logger.info("=" * 60)
    
    return logger

