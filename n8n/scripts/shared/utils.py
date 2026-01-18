"""
Utilidades compartidas para scripts de ByBot
"""

import os
import json
import logging
import requests
from pathlib import Path
from datetime import datetime
from typing import Optional, Dict, Any

from .config import (
    BYBOT_API_URL, 
    BYBOT_ACCESS_TOKEN, 
    TEMP_DIR, 
    LOG_LEVEL,
    LOG_FORMAT
)

# Configurar logging
logging.basicConfig(level=LOG_LEVEL, format=LOG_FORMAT)
logger = logging.getLogger(__name__)


def download_file(url: str, token: str = None, output_path: Path = None) -> Path:
    """
    Descarga un archivo desde una URL.
    
    Args:
        url: URL del archivo a descargar
        token: Token de acceso (incluido en la URL normalmente)
        output_path: Ruta de salida (opcional)
    
    Returns:
        Path del archivo descargado
    """
    try:
        headers = {}
        if token:
            headers["Authorization"] = f"Bearer {token}"
        
        response = requests.get(url, headers=headers, timeout=60, stream=True)
        response.raise_for_status()
        
        # Determinar nombre del archivo
        if output_path is None:
            # Intentar obtener nombre del header Content-Disposition
            content_disp = response.headers.get("Content-Disposition", "")
            if "filename=" in content_disp:
                filename = content_disp.split("filename=")[1].strip('"')
            else:
                # Usar timestamp como nombre
                filename = f"file_{datetime.now().strftime('%Y%m%d_%H%M%S')}.pdf"
            output_path = TEMP_DIR / filename
        
        # Guardar archivo
        with open(output_path, "wb") as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)
        
        logger.info(f"Archivo descargado: {output_path}")
        return output_path
        
    except Exception as e:
        logger.error(f"Error descargando archivo: {e}")
        raise


def upload_file(file_path: Path, proceso_id: int, tipo: str = "pagare_llenado") -> Dict[str, Any]:
    """
    Sube un archivo a la API de ByBot.
    
    Args:
        file_path: Ruta del archivo a subir
        proceso_id: ID del proceso
        tipo: Tipo de archivo
    
    Returns:
        Respuesta de la API
    """
    try:
        url = f"{BYBOT_API_URL}/archivos/subir-externo"
        
        headers = {
            "X-N8N-Access-Token": BYBOT_ACCESS_TOKEN
        }
        
        with open(file_path, "rb") as f:
            files = {"archivo": (file_path.name, f, "application/pdf")}
            data = {
                "proceso_id": proceso_id,
                "tipo": tipo
            }
            
            response = requests.post(url, headers=headers, files=files, data=data, timeout=60)
            response.raise_for_status()
        
        result = response.json()
        logger.info(f"Archivo subido: {result}")
        return result
        
    except Exception as e:
        logger.error(f"Error subiendo archivo: {e}")
        raise


def send_callback(proceso_id: int, action: str, data: Dict[str, Any]) -> Dict[str, Any]:
    """
    Envía un callback a la API de ByBot.
    
    Args:
        proceso_id: ID del proceso
        action: Acción del callback (analysis_complete, analysis_error, etc.)
        data: Datos adicionales
    
    Returns:
        Respuesta de la API
    """
    try:
        url = f"{BYBOT_API_URL}/webhook/n8n"
        
        payload = {
            "action": action,
            "proceso_id": proceso_id,
            **data
        }
        
        headers = {
            "Content-Type": "application/json",
            "X-N8N-Access-Token": BYBOT_ACCESS_TOKEN
        }
        
        response = requests.post(url, json=payload, headers=headers, timeout=30)
        response.raise_for_status()
        
        result = response.json()
        logger.info(f"Callback enviado: {action} para proceso {proceso_id}")
        return result
        
    except Exception as e:
        logger.error(f"Error enviando callback: {e}")
        raise


def cleanup_temp_files(*files: Path) -> None:
    """
    Limpia archivos temporales.
    """
    for file_path in files:
        try:
            if file_path and file_path.exists():
                file_path.unlink()
                logger.debug(f"Archivo temporal eliminado: {file_path}")
        except Exception as e:
            logger.warning(f"Error eliminando archivo temporal {file_path}: {e}")


def format_currency(amount: float) -> str:
    """
    Formatea un número como moneda colombiana.
    """
    if amount is None:
        return ""
    return f"${amount:,.0f}".replace(",", ".")


def number_to_words(number: float) -> str:
    """
    Convierte un número a palabras en español.
    Implementación básica - considerar usar librería num2words
    """
    try:
        # Para valores grandes, usar librería externa
        # pip install num2words
        from num2words import num2words
        return num2words(int(number), lang='es').upper() + " PESOS M/CTE"
    except ImportError:
        # Fallback básico
        return f"{int(number)} PESOS"


def parse_date(date_str: str) -> Optional[datetime]:
    """
    Parsea una fecha en varios formatos comunes.
    """
    formats = [
        "%Y-%m-%d",
        "%d/%m/%Y",
        "%d-%m-%Y",
        "%Y/%m/%d"
    ]
    
    for fmt in formats:
        try:
            return datetime.strptime(date_str, fmt)
        except ValueError:
            continue
    
    return None


def format_date(date: datetime, format_str: str = "%d de %B de %Y") -> str:
    """
    Formatea una fecha en español.
    """
    import locale
    try:
        locale.setlocale(locale.LC_TIME, 'es_ES.UTF-8')
    except:
        try:
            locale.setlocale(locale.LC_TIME, 'es_CO.UTF-8')
        except:
            pass
    
    return date.strftime(format_str)

