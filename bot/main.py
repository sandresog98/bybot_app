#!/usr/bin/env python3
"""
Bot principal de análisis con Gemini - ByBot App
Procesa documentos de procesos CoreCoop usando IA
"""

import time
import signal
import sys
import logging
from config.logging_config import setup_logging
from config.settings import PROCESSING_CONFIG
from processors.crear_coop_processor import CrearCoopProcessor

# Configurar logging
logger = setup_logging()

# Variable global para controlar el loop
running = True

def signal_handler(sig, frame):
    """Manejar señales de terminación"""
    global running
    logger.info("\n🛑 Señal de terminación recibida. Cerrando bot...")
    running = False
    sys.exit(0)

def main():
    """Función principal del bot"""
    global running
    
    # Registrar manejadores de señales
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)
    
    logger.info("🤖 Bot de Análisis ByBot iniciado")
    logger.info(f"⏱️  Intervalo de consulta: {PROCESSING_CONFIG['poll_interval']} segundos")
    
    processor = CrearCoopProcessor()
    poll_interval = PROCESSING_CONFIG['poll_interval']
    
    try:
        procesos_sin_procesar = 0
        while running:
            try:
                # Intentar procesar siguiente proceso
                procesado = processor.procesar_siguiente()
                
                if procesado:
                    logger.info("✅ Proceso procesado exitosamente")
                    procesos_sin_procesar = 0  # Resetear contador si procesó algo
                else:
                    # No hay procesos pendientes
                    procesos_sin_procesar += 1
                    
                    # Si no hay procesos pendientes y ya esperamos varias veces, terminar
                    if procesos_sin_procesar >= 3:
                        logger.info("✅ No hay más procesos pendientes. Bot finalizado.")
                        break
                    
                    logger.info(f"⏳ No hay procesos pendientes. Esperando {poll_interval}s... (intento {procesos_sin_procesar}/3)")
                    time.sleep(poll_interval)
                    
            except KeyboardInterrupt:
                raise
            except Exception as e:
                logger.error(f"❌ Error en el loop principal: {e}")
                logger.info(f"⏳ Esperando {poll_interval}s antes de reintentar...")
                time.sleep(poll_interval)
                
    except KeyboardInterrupt:
        logger.info("\n🛑 Bot detenido por el usuario")
    except Exception as e:
        logger.error(f"❌ Error fatal: {e}")
        sys.exit(1)
    finally:
        logger.info("👋 Bot finalizado")

if __name__ == '__main__':
    main()

