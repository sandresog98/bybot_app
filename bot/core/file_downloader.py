#!/usr/bin/env python3
"""
Cliente HTTP para descargar archivos del servidor PHP
"""

import logging
import os
import tempfile
import requests
from typing import Optional
from config.settings import SERVER_CONFIG

logger = logging.getLogger('bybot.downloader')

class FileDownloader:
    """Cliente para descargar archivos desde el servidor PHP"""
    
    def __init__(self):
        """Inicializar descargador"""
        self.base_url = SERVER_CONFIG['base_url']
        self.api_token = SERVER_CONFIG['api_token']
        self.timeout = SERVER_CONFIG['timeout']
        
        if not self.api_token:
            logger.warning("⚠️ BOT_API_TOKEN no configurada. Las descargas pueden fallar.")
    
    def download_file(self, proceso_id: int, tipo: str, anexo_id: Optional[int] = None) -> Optional[str]:
        """
        Descargar archivo del servidor y guardarlo temporalmente
        
        Args:
            proceso_id: ID del proceso
            tipo: Tipo de archivo ('pagare', 'estado_cuenta', 'anexo')
            anexo_id: ID del anexo (solo si tipo es 'anexo')
        
        Returns:
            Ruta del archivo temporal descargado, o None si falla
        """
        try:
            # Construir URL
            url = f"{self.base_url}/modules/crear_coop/api/serve_file_for_bot.php"
            params = {
                'proceso_id': proceso_id,
                'tipo': tipo
            }
            
            if tipo == 'anexo' and anexo_id:
                params['anexo_id'] = anexo_id
            
            # Headers con token de autenticación
            headers = {
                'X-API-Token': self.api_token
            }
            
            logger.info(f"📥 Descargando {tipo} del proceso {proceso_id}...")
            
            # Realizar petición
            response = requests.get(
                url,
                params=params,
                headers=headers,
                timeout=self.timeout,
                stream=True
            )
            
            # Verificar respuesta
            if response.status_code == 401:
                logger.error("❌ Error de autenticación: Token de API inválido o faltante")
                return None
            elif response.status_code == 403:
                logger.error("❌ Error de autorización: Token de API no autorizado")
                return None
            elif response.status_code == 404:
                logger.error(f"❌ Archivo no encontrado: {tipo} del proceso {proceso_id}")
                return None
            elif response.status_code != 200:
                logger.error(f"❌ Error HTTP {response.status_code}: {response.text[:200]}")
                return None
            
            # Obtener nombre del archivo desde headers
            file_name = response.headers.get('X-File-Name', f"{tipo}_{proceso_id}.pdf")
            
            # Crear archivo temporal
            temp_dir = tempfile.gettempdir()
            temp_file = os.path.join(temp_dir, f"bybot_{proceso_id}_{tipo}_{os.getpid()}_{file_name}")
            
            # Descargar archivo
            total_size = 0
            content_length = response.headers.get('Content-Length')
            if content_length:
                total_size = int(content_length)
                logger.info(f"   Tamaño: {self._format_bytes(total_size)}")
            
            with open(temp_file, 'wb') as f:
                for chunk in response.iter_content(chunk_size=8192):
                    if chunk:
                        f.write(chunk)
            
            if not os.path.exists(temp_file) or os.path.getsize(temp_file) == 0:
                logger.error("❌ Archivo descargado está vacío o no existe")
                return None
            
            actual_size = os.path.getsize(temp_file)
            logger.info(f"✅ Archivo descargado: {file_name} ({self._format_bytes(actual_size)})")
            
            return temp_file
            
        except requests.exceptions.Timeout:
            logger.error(f"❌ Timeout al descargar {tipo} del proceso {proceso_id}")
            return None
        except requests.exceptions.ConnectionError as e:
            logger.error(f"❌ Error de conexión al servidor: {e}")
            return None
        except Exception as e:
            logger.error(f"❌ Error inesperado al descargar archivo: {e}")
            return None
    
    def cleanup_temp_file(self, file_path: str):
        """Eliminar archivo temporal"""
        try:
            if file_path and os.path.exists(file_path):
                os.remove(file_path)
                logger.debug(f"🗑️ Archivo temporal eliminado: {file_path}")
        except Exception as e:
            logger.warning(f"⚠️ No se pudo eliminar archivo temporal {file_path}: {e}")
    
    @staticmethod
    def _format_bytes(bytes_size: int) -> str:
        """Formatear bytes a formato legible"""
        for unit in ['B', 'KB', 'MB', 'GB']:
            if bytes_size < 1024.0:
                return f"{bytes_size:.2f} {unit}"
            bytes_size /= 1024.0
        return f"{bytes_size:.2f} TB"

