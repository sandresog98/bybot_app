#!/usr/bin/env python3
"""
Procesador para llenar pagarés con datos validados
"""

import logging
import os
import json
from typing import Optional, Dict, Any
from core.database import DatabaseManager
from core.file_downloader import FileDownloader
from core.file_uploader import FileUploader
from core.pagare_filler import PagareFiller

logger = logging.getLogger('bybot.pagare_filler')

class PagareFillerProcessor:
    """Procesador para llenar pagarés con datos validados"""
    
    def __init__(self):
        """Inicializar procesador"""
        self.db = DatabaseManager()
        self.downloader = FileDownloader()
        self.uploader = FileUploader()
        self.pagare_filler = PagareFiller()
    
    def obtener_proceso_pendiente(self) -> Optional[Dict[str, Any]]:
        """Obtener un proceso en estado 'informacion_ia_validada' para llenar pagaré"""
        try:
            query = """
                SELECT id, codigo, archivo_pagare_original
                FROM crear_coop_procesos
                WHERE estado = 'informacion_ia_validada'
                AND archivo_pagare_original IS NOT NULL
                ORDER BY fecha_actualizacion ASC
                LIMIT 1
            """
            proceso = self.db.execute_query(query, fetch_one=True)
            return proceso
        except Exception as e:
            logger.error(f"❌ Error obteniendo proceso pendiente: {e}")
            return None
    
    def obtener_datos_validados(self, proceso_id: int) -> Optional[Dict[str, Any]]:
        """Obtener datos validados de un proceso"""
        try:
            query = """
                SELECT datos_validados, datos_originales
                FROM crear_coop_datos_ia
                WHERE proceso_id = %s
                ORDER BY fecha_analisis DESC
                LIMIT 1
            """
            resultado = self.db.execute_query(query, (proceso_id,), fetch_one=True)
            
            if not resultado:
                return None
            
            # Usar datos validados si existen, sino usar originales
            datos_validados = resultado.get('datos_validados')
            datos_originales = resultado.get('datos_originales')
            
            if datos_validados:
                return json.loads(datos_validados)
            elif datos_originales:
                return json.loads(datos_originales)
            
            return None
        except Exception as e:
            logger.error(f"❌ Error obteniendo datos validados: {e}")
            return None
    
    def actualizar_estado(self, proceso_id: int, nuevo_estado: str):
        """Actualizar estado del proceso"""
        try:
            query = """
                UPDATE crear_coop_procesos 
                SET estado = %s, fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = %s
            """
            self.db.execute_update(query, (nuevo_estado, proceso_id))
            logger.info(f"✅ Estado actualizado a '{nuevo_estado}' para proceso {proceso_id}")
        except Exception as e:
            logger.error(f"❌ Error actualizando estado: {e}")
            raise
    
    def guardar_pagare_llenado(self, proceso_id: int, ruta_archivo: str):
        """Guardar ruta del pagaré llenado en el proceso"""
        try:
            query = """
                UPDATE crear_coop_procesos 
                SET archivo_pagare_llenado = %s,
                    fecha_actualizacion = CURRENT_TIMESTAMP
                WHERE id = %s
            """
            self.db.execute_update(query, (ruta_archivo, proceso_id))
            logger.info(f"✅ Pagaré llenado guardado para proceso {proceso_id}")
        except Exception as e:
            logger.error(f"❌ Error guardando pagaré llenado: {e}")
            raise
    
    def procesar_proceso(self, proceso: Dict[str, Any]) -> bool:
        """Procesar un proceso y llenar su pagaré"""
        proceso_id = proceso['id']
        codigo = proceso['codigo']
        
        try:
            logger.info(f"🔄 Iniciando llenado de pagaré del proceso {codigo} (ID: {proceso_id})")
            
            # Actualizar estado a "llenando_pagare"
            self.actualizar_estado(proceso_id, 'llenar_pagare')
            
            # Obtener datos validados
            datos = self.obtener_datos_validados(proceso_id)
            if not datos:
                raise ValueError("No se encontraron datos validados para el proceso")
            
            # Descargar pagaré original
            logger.info("📥 Descargando pagaré original del servidor...")
            pagare_path = self.downloader.download_file(proceso_id, 'pagare')
            if not pagare_path or not os.path.exists(pagare_path):
                raise FileNotFoundError("Pagaré original no encontrado o no se pudo descargar")
            
            # Preparar datos para llenar
            estado_cuenta = datos.get('estado_cuenta', {})
            if not isinstance(estado_cuenta, dict):
                estado_cuenta = {}
            
            deudor = datos.get('deudor', {})
            # Si es una lista (vacía o no), convertir a diccionario vacío
            if isinstance(deudor, list):
                deudor = {}
            elif not isinstance(deudor, dict):
                deudor = {}
            
            codeudor = datos.get('codeudor', {})
            # Si es una lista (vacía o no), convertir a diccionario vacío
            if isinstance(codeudor, list):
                codeudor = {}
            elif not isinstance(codeudor, dict):
                codeudor = {}
            
            datos_llenado = {
                'saldo_capital': estado_cuenta.get('saldo_capital') if isinstance(estado_cuenta, dict) else None,
                'saldo_interes': estado_cuenta.get('saldo_interes') if isinstance(estado_cuenta, dict) else None,
                'saldo_mora': estado_cuenta.get('saldo_mora') if isinstance(estado_cuenta, dict) else None,
                'tasa_interes_efectiva_anual': estado_cuenta.get('tasa_interes_efectiva_anual') if isinstance(estado_cuenta, dict) else None,
                'fecha_causacion': estado_cuenta.get('fecha_causacion') if isinstance(estado_cuenta, dict) else None,
                'deudor': deudor,
                'codeudor': codeudor
            }
            
            logger.debug(f"📊 Datos preparados para llenar pagaré: estado_cuenta={isinstance(estado_cuenta, dict)}, deudor={isinstance(deudor, dict)}, codeudor={isinstance(codeudor, dict)}")
            
            # Llenar pagaré
            logger.info("📝 Llenando pagaré con datos validados...")
            pagare_llenado_path = self.pagare_filler.llenar_pagare(pagare_path, datos_llenado)
            
            # Verificar que el archivo se creó correctamente
            if not pagare_llenado_path or not os.path.exists(pagare_llenado_path):
                raise FileNotFoundError(f"El pagaré llenado no se creó correctamente: {pagare_llenado_path}")
            
            file_size = os.path.getsize(pagare_llenado_path)
            if file_size == 0:
                raise ValueError(f"El pagaré llenado está vacío: {pagare_llenado_path}")
            
            logger.info(f"✅ Pagaré llenado creado: {pagare_llenado_path} ({file_size} bytes)")
            
            # Subir pagaré llenado al servidor
            logger.info("📤 Subiendo pagaré llenado al servidor...")
            if self.uploader.upload_file(proceso_id, pagare_llenado_path, 'pagare_llenado'):
                logger.info("✅ Pagaré llenado subido exitosamente")
                # Actualizar estado a "con_pagare" cuando la subida sea exitosa
                self.actualizar_estado(proceso_id, 'con_pagare')
                logger.info(f"✅ Proceso {codigo} - Pagaré llenado exitosamente, estado actualizado a 'con_pagare'")
                return True
            else:
                raise Exception("Error al subir pagaré llenado")
            
        except Exception as e:
            logger.error(f"❌ Error procesando proceso {codigo}: {e}")
            # Revertir estado a "informacion_ia_validada" para reintentar
            try:
                self.actualizar_estado(proceso_id, 'informacion_ia_validada')
            except:
                pass
            return False
            
        finally:
            # Limpiar archivos temporales
            if 'pagare_path' in locals() and pagare_path:
                self.downloader.cleanup_temp_file(pagare_path)
            if 'pagare_llenado_path' in locals() and pagare_llenado_path and os.path.exists(pagare_llenado_path):
                try:
                    os.remove(pagare_llenado_path)
                except:
                    pass
    
    def procesar_siguiente(self) -> bool:
        """Procesar el siguiente proceso pendiente"""
        proceso = self.obtener_proceso_pendiente()
        
        if not proceso:
            return False
        
        return self.procesar_proceso(proceso)

