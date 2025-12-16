#!/usr/bin/env python3
"""
Cliente HTTP para subir archivos al servidor PHP
"""

import logging
import os
import requests
from typing import Optional
from config.settings import SERVER_CONFIG

logger = logging.getLogger('bybot.uploader')

class FileUploader:
    """Cliente para subir archivos al servidor PHP"""
    
    def __init__(self):
        """Inicializar uploader"""
        base_url = SERVER_CONFIG['base_url'].rstrip('/')
        # Asegurar que la URL termine con /admin si no está presente
        if not base_url.endswith('/admin'):
            base_url = base_url + '/admin'
        self.base_url = base_url
        # Limpiar token (eliminar espacios y saltos de línea)
        self.api_token = SERVER_CONFIG['api_token'].strip() if SERVER_CONFIG['api_token'] else ''
        self.timeout = SERVER_CONFIG['timeout']
        
        if not self.api_token:
            logger.warning("⚠️ BOT_API_TOKEN no configurada. Las subidas pueden fallar.")
        else:
            logger.debug(f"🔑 Token configurado (longitud: {len(self.api_token)})")
    
    def upload_file(self, proceso_id: int, file_path: str, tipo: str, nombre_archivo: Optional[str] = None) -> bool:
        """
        Subir archivo al servidor
        
        Args:
            proceso_id: ID del proceso
            file_path: Ruta local del archivo a subir
            tipo: Tipo de archivo ('solicitud_vinculacion_deudor', 'solicitud_vinculacion_codeudor', 'pagare_llenado')
            nombre_archivo: Nombre del archivo (opcional, se usa el nombre del archivo local si no se proporciona)
        
        Returns:
            True si la subida fue exitosa, False en caso contrario
        """
        file_handle = None
        try:
            if not os.path.exists(file_path):
                logger.error(f"❌ Archivo no encontrado: {file_path}")
                return False
            
            file_size = os.path.getsize(file_path)
            if file_size == 0:
                logger.error(f"❌ El archivo está vacío: {file_path}")
                return False
            
            # Construir URL
            url = f"{self.base_url}/modules/crear_coop/api/upload_file_from_bot.php"
            
            # Headers con token de autenticación
            clean_token = self.api_token.strip()
            if not clean_token:
                logger.error("❌ Token de API no configurado")
                return False
            
            headers = {
                'X-API-Token': clean_token
            }
            
            # Preparar archivo
            if nombre_archivo is None:
                nombre_archivo = os.path.basename(file_path)
            
            # Abrir archivo con contexto para asegurar que se cierre
            file_handle = open(file_path, 'rb')
            
            # Datos del formulario
            files = {
                'archivo': (nombre_archivo, file_handle, 'application/pdf')
            }
            
            data = {
                'proceso_id': proceso_id,
                'tipo': tipo
            }
            
            logger.info(f"📤 Subiendo {tipo} del proceso {proceso_id}...")
            logger.debug(f"   URL: {url}")
            logger.debug(f"   Archivo: {file_path} ({file_size} bytes)")
            logger.debug(f"   Tipo: {tipo}")
            
            # Realizar petición
            response = requests.post(
                url,
                headers=headers,
                files=files,
                data=data,
                timeout=self.timeout
            )
            
            # Verificar respuesta
            if response.status_code == 401:
                logger.error("❌ Error de autenticación: Token de API inválido o faltante")
                logger.debug(f"   Respuesta: {response.text[:200]}")
                return False
            elif response.status_code == 403:
                logger.error("❌ Error de autorización: Token de API no autorizado")
                logger.debug(f"   Respuesta: {response.text[:200]}")
                return False
            elif response.status_code != 200:
                logger.error(f"❌ Error HTTP {response.status_code}: {response.text[:200]}")
                try:
                    error_data = response.json()
                    logger.error(f"   Mensaje: {error_data.get('error', 'N/A')}")
                    if 'message' in error_data:
                        logger.error(f"   Detalle: {error_data.get('message', 'N/A')}")
                except:
                    pass
                return False
            
            # Verificar respuesta JSON
            try:
                result = response.json()
                if result.get('success'):
                    logger.info(f"✅ Archivo subido exitosamente: {result.get('ruta_archivo', 'N/A')}")
                    return True
                else:
                    error_msg = result.get('message', result.get('error', 'Error desconocido'))
                    logger.error(f"❌ Error al subir archivo: {error_msg}")
                    return False
            except Exception as e:
                logger.error(f"❌ Error parseando respuesta del servidor: {e}")
                logger.debug(f"   Respuesta raw: {response.text[:500]}")
                return False
            
        except requests.exceptions.Timeout:
            logger.error(f"❌ Timeout al subir archivo del proceso {proceso_id}")
            return False
        except requests.exceptions.ConnectionError as e:
            logger.error(f"❌ Error de conexión al servidor: {e}")
            logger.debug(f"   URL intentada: {url if 'url' in locals() else 'N/A'}")
            return False
        except Exception as e:
            logger.error(f"❌ Error inesperado al subir archivo: {e}", exc_info=True)
            return False
        finally:
            # Asegurar que el archivo se cierre
            if file_handle is not None:
                try:
                    file_handle.close()
                except:
                    pass

