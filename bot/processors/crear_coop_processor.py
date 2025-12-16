#!/usr/bin/env python3
"""
Procesador para análisis de procesos CoreCoop con Gemini
"""

import logging
import os
import json
from typing import Optional, Dict, Any
from core.database import DatabaseManager
from core.gemini_client import GeminiClient
from core.file_downloader import FileDownloader
from config.settings import UPLOADS_DIR, GEMINI_CONFIG

logger = logging.getLogger('bybot.processor')

class CrearCoopProcessor:
    """Procesador de análisis de procesos CoreCoop"""
    
    def __init__(self):
        """Inicializar procesador"""
        self.db = DatabaseManager()
        self.gemini = GeminiClient()
        self.downloader = FileDownloader()
    
    def obtener_proceso_pendiente(self) -> Optional[Dict[str, Any]]:
        """Obtener un proceso en estado 'creado' para analizar"""
        try:
            query = """
                SELECT id, codigo, archivo_pagare_original, archivo_estado_cuenta, 
                       archivo_anexos_original, intentos_analisis
                FROM crear_coop_procesos
                WHERE estado = 'creado'
                ORDER BY fecha_creacion ASC
                LIMIT 1
            """
            proceso = self.db.execute_query(query, fetch_one=True)
            return proceso
        except Exception as e:
            logger.error(f"❌ Error obteniendo proceso pendiente: {e}")
            return None
    
    def obtener_anexos(self, proceso_id: int) -> list:
        """Obtener todos los anexos de un proceso"""
        try:
            query = """
                SELECT id, ruta_archivo, nombre_archivo
                FROM crear_coop_anexos
                WHERE proceso_id = %s AND tipo = 'anexo_original'
                ORDER BY fecha_subida ASC
            """
            anexos = self.db.execute_query(query, (proceso_id,))
            return anexos
        except Exception as e:
            logger.error(f"❌ Error obteniendo anexos: {e}")
            return []
    
    def actualizar_estado(self, proceso_id: int, nuevo_estado: str, incrementar_intentos: bool = False):
        """Actualizar estado del proceso"""
        try:
            if incrementar_intentos:
                query = """
                    UPDATE crear_coop_procesos 
                    SET estado = %s, 
                        intentos_analisis = intentos_analisis + 1,
                        fecha_actualizacion = CURRENT_TIMESTAMP
                    WHERE id = %s
                """
            else:
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
    
    def obtener_intentos_analisis(self, proceso_id: int) -> int:
        """Obtener número de intentos de análisis de un proceso"""
        try:
            query = """
                SELECT intentos_analisis 
                FROM crear_coop_procesos
                WHERE id = %s
            """
            resultado = self.db.execute_query(query, (proceso_id,), fetch_one=True)
            return resultado['intentos_analisis'] if resultado else 0
        except Exception as e:
            logger.error(f"❌ Error obteniendo intentos de análisis: {e}")
            return 0
    
    def actualizar_datos_ia(self, proceso_id: int, datos: Dict[str, Any], metadata: Dict[str, Any]):
        """Guardar datos extraídos por IA en la tabla crear_coop_datos_ia"""
        try:
            # Convertir datos a JSON
            datos_json = json.dumps(datos, ensure_ascii=False)
            metadata_json = json.dumps(metadata, ensure_ascii=False)
            
            # Verificar si ya existe un registro para este proceso
            query_check = """
                SELECT id FROM crear_coop_datos_ia WHERE proceso_id = %s
            """
            existe = self.db.execute_query(query_check, (proceso_id,), fetch_one=True)
            
            if existe:
                # Actualizar registro existente
                query = """
                    UPDATE crear_coop_datos_ia 
                    SET datos_originales = %s,
                        metadata = %s,
                        fecha_analisis = CURRENT_TIMESTAMP
                    WHERE proceso_id = %s
                """
                self.db.execute_update(query, (datos_json, metadata_json, proceso_id))
                logger.info(f"✅ Datos de IA actualizados para proceso {proceso_id}")
            else:
                # Insertar nuevo registro
                query = """
                    INSERT INTO crear_coop_datos_ia 
                    (proceso_id, datos_originales, metadata, fecha_analisis)
                    VALUES (%s, %s, %s, CURRENT_TIMESTAMP)
                """
                self.db.execute_update(query, (proceso_id, datos_json, metadata_json))
                logger.info(f"✅ Datos de IA guardados para proceso {proceso_id}")
            
            # Actualizar estado del proceso y resetear intentos
            query_proceso = """
                UPDATE crear_coop_procesos 
                SET estado = 'analizado_con_ia',
                    intentos_analisis = 0
                WHERE id = %s
            """
            self.db.execute_update(query_proceso, (proceso_id,))
            
        except Exception as e:
            logger.error(f"❌ Error guardando datos de IA: {e}")
            raise
    
    def procesar_proceso(self, proceso: Dict[str, Any]) -> bool:
        """Procesar un proceso completo"""
        proceso_id = proceso['id']
        codigo = proceso['codigo']
        
        try:
            logger.info(f"🔄 Iniciando análisis del proceso {codigo} (ID: {proceso_id})")
            
            # Actualizar estado a "analizando_con_ia"
            self.actualizar_estado(proceso_id, 'analizando_con_ia')
            
            # Descargar archivos del servidor
            temp_files = []  # Lista para limpiar archivos temporales al final
            
            # Descargar pagaré (opcional, para referencia futura)
            logger.info("📥 Descargando pagaré del servidor...")
            pagare_path = self.downloader.download_file(proceso_id, 'pagare')
            if pagare_path and os.path.exists(pagare_path):
                temp_files.append(pagare_path)
                logger.info("✅ Pagaré descargado correctamente")
            else:
                logger.warning("⚠️ No se pudo descargar el pagaré (continuando sin él)")
            
            # Descargar estado de cuenta
            logger.info("📥 Descargando estado de cuenta del servidor...")
            estado_cuenta_path = self.downloader.download_file(proceso_id, 'estado_cuenta')
            if not estado_cuenta_path or not os.path.exists(estado_cuenta_path):
                raise FileNotFoundError(f"Estado de cuenta no encontrado o no se pudo descargar")
            temp_files.append(estado_cuenta_path)
            
            # Obtener anexos
            anexos = self.obtener_anexos(proceso_id)
            if not anexos:
                raise ValueError("No se encontraron anexos para el proceso")
            
            # Descargar anexos
            logger.info(f"📥 Descargando {len(anexos)} anexo(s) del servidor...")
            anexos_paths = []
            for anexo in anexos:
                anexo_path = self.downloader.download_file(proceso_id, 'anexo', anexo['id'])
                if anexo_path and os.path.exists(anexo_path):
                    anexos_paths.append(anexo_path)
                    temp_files.append(anexo_path)
                else:
                    logger.warning(f"⚠️ No se pudo descargar anexo ID {anexo['id']}")
            
            if not anexos_paths:
                raise FileNotFoundError("Ningún anexo válido encontrado o descargado")
            
            # Analizar estado de cuenta
            logger.info("📊 Analizando estado de cuenta...")
            datos_estado_cuenta, metadata_estado_cuenta = self.gemini.analyze_estado_cuenta(estado_cuenta_path)
            
            # Analizar anexos
            logger.info("📊 Analizando anexos...")
            datos_anexos, metadata_anexos = self.gemini.analyze_anexos(anexos_paths)
            
            # Combinar datos
            datos_completos = {
                'estado_cuenta': datos_estado_cuenta.copy(),
                'deudor': datos_anexos.get('deudor', {}),
                'codeudor': datos_anexos.get('codeudor', {})
            }
            
            # Manejar TEA: buscar en estado de cuenta primero, luego en anexos
            tea_estado_cuenta = datos_estado_cuenta.get('tasa_interes_efectiva_anual')
            tea_anexos = datos_anexos.get('tasa_interes_efectiva_anual')
            
            if tea_estado_cuenta:
                # TEA encontrada en estado de cuenta (prioridad)
                datos_completos['estado_cuenta']['tasa_interes_efectiva_anual'] = tea_estado_cuenta
                logger.info(f"✅ TEA encontrada en estado de cuenta: {tea_estado_cuenta}%")
            elif tea_anexos:
                # TEA no encontrada en estado de cuenta, pero sí en anexos
                datos_completos['estado_cuenta']['tasa_interes_efectiva_anual'] = tea_anexos
                logger.info(f"✅ TEA encontrada en anexos (no estaba en estado de cuenta): {tea_anexos}%")
            else:
                # TEA no encontrada en ninguno
                datos_completos['estado_cuenta']['tasa_interes_efectiva_anual'] = None
                logger.warning("⚠️ TEA no encontrada ni en estado de cuenta ni en anexos")
            
            # Combinar metadata (sumar tokens)
            metadata_completa = {
                'tokens_entrada': metadata_estado_cuenta.get('tokens_entrada', 0) + metadata_anexos.get('tokens_entrada', 0),
                'tokens_salida': metadata_estado_cuenta.get('tokens_salida', 0) + metadata_anexos.get('tokens_salida', 0),
                'tokens_total': metadata_estado_cuenta.get('tokens_total', 0) + metadata_anexos.get('tokens_total', 0),
                'modelo': GEMINI_CONFIG['model']
            }
            
            # Actualizar base de datos
            self.actualizar_datos_ia(proceso_id, datos_completos, metadata_completa)
            
            logger.info(f"✅ Proceso {codigo} analizado exitosamente")
            return True
            
        except Exception as e:
            logger.error(f"❌ Error procesando proceso {codigo}: {e}")
            
            # Obtener número de intentos actual
            intentos_actuales = self.obtener_intentos_analisis(proceso_id)
            intentos_nuevos = intentos_actuales + 1
            
            # Si ya se intentó 3 veces, marcar como error
            if intentos_nuevos >= 3:
                logger.warning(f"⚠️ Proceso {codigo} ha fallado {intentos_nuevos} veces. Marcando como error_analisis")
                try:
                    self.actualizar_estado(proceso_id, 'error_analisis', incrementar_intentos=True)
                except:
                    pass
            else:
                # Revertir estado a "creado" para reintentar
                logger.info(f"🔄 Reintentando proceso {codigo} (intento {intentos_nuevos}/3)")
                try:
                    self.actualizar_estado(proceso_id, 'creado', incrementar_intentos=True)
                except:
                    pass
            return False
            
        finally:
            # Limpiar archivos temporales
            if 'temp_files' in locals():
                for temp_file in temp_files:
                    self.downloader.cleanup_temp_file(temp_file)
    
    def procesar_siguiente(self) -> bool:
        """Procesar el siguiente proceso pendiente"""
        proceso = self.obtener_proceso_pendiente()
        
        if not proceso:
            return False
        
        return self.procesar_proceso(proceso)

