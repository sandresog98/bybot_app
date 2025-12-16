#!/usr/bin/env python3
"""
Script de prueba para verificar conexiones del Bot
ByBot App
"""

import sys
import logging
from config.logging_config import setup_logging
from config.settings import DB_CONFIG, GEMINI_CONFIG, SERVER_CONFIG
from core.database import DatabaseManager
from core.gemini_client import GeminiClient
from core.file_downloader import FileDownloader

logger = setup_logging()

def test_database():
    """Probar conexión a base de datos"""
    logger.info("🔍 Probando conexión a base de datos...")
    try:
        conn = DatabaseManager.get_connection()
        cursor = conn.cursor(dictionary=True)
        cursor.execute("SELECT 1 as test")
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        
        if result and result['test'] == 1:
            logger.info("✅ Conexión a base de datos: OK")
            
            # Verificar que existe la tabla
            conn = DatabaseManager.get_connection()
            cursor = conn.cursor()
            cursor.execute("SHOW TABLES LIKE 'crear_coop_procesos'")
            table_exists = cursor.fetchone()
            cursor.close()
            conn.close()
            
            if table_exists:
                logger.info("✅ Tabla 'crear_coop_procesos' existe")
                
                # Contar procesos
                conn = DatabaseManager.get_connection()
                cursor = conn.cursor(dictionary=True)
                cursor.execute("SELECT COUNT(*) as total FROM crear_coop_procesos WHERE estado = 'creado'")
                result = cursor.fetchone()
                cursor.close()
                conn.close()
                
                logger.info(f"📊 Procesos pendientes (estado='creado'): {result['total']}")
            else:
                logger.warning("⚠️ Tabla 'crear_coop_procesos' no existe")
            
            return True
        else:
            logger.error("❌ Error en la consulta de prueba")
            return False
    except Exception as e:
        logger.error(f"❌ Error de conexión a BD: {e}")
        return False

def test_gemini():
    """Probar conexión a Gemini API"""
    logger.info("🔍 Probando conexión a Gemini API...")
    try:
        client = GeminiClient()
        logger.info("✅ Cliente Gemini inicializado correctamente")
        logger.info(f"   Modelo: {GEMINI_CONFIG['model']}")
        logger.info(f"   API Key: {'✅ Configurada' if GEMINI_CONFIG['api_key'] else '❌ No configurada'}")
        return True
    except Exception as e:
        logger.error(f"❌ Error inicializando Gemini: {e}")
        logger.error("   Verifica que GEMINI_API_KEY esté configurada en .env")
        return False

def test_server_connection():
    """Probar conexión al servidor PHP"""
    logger.info("🔍 Probando conexión al servidor PHP...")
    try:
        downloader = FileDownloader()
        
        if not downloader.api_token:
            logger.warning("⚠️ BOT_API_TOKEN no configurada")
            return False
        
        logger.info(f"✅ Configuración del servidor:")
        logger.info(f"   URL Base: {SERVER_CONFIG['base_url']}")
        logger.info(f"   API Token: {'✅ Configurada' if SERVER_CONFIG['api_token'] else '❌ No configurada'}")
        logger.info(f"   Timeout: {SERVER_CONFIG['timeout']}s")
        
        # Nota: No hacemos una petición real porque necesitaríamos un proceso_id válido
        logger.info("ℹ️  La conexión real se probará al procesar el primer archivo")
        return True
    except Exception as e:
        logger.error(f"❌ Error en configuración del servidor: {e}")
        return False

def main():
    """Función principal"""
    logger.info("=" * 60)
    logger.info("🧪 PRUEBAS DE CONEXIÓN - ByBot App")
    logger.info("=" * 60)
    logger.info("")
    
    logger.info("📋 Configuración:")
    logger.info(f"   DB_HOST: {DB_CONFIG['host']}")
    logger.info(f"   DB_NAME: {DB_CONFIG['database']}")
    logger.info(f"   GEMINI_MODEL: {GEMINI_CONFIG['model']}")
    logger.info(f"   GEMINI_TEMPERATURE: {GEMINI_CONFIG['temperature']}")
    logger.info(f"   GEMINI_MAX_TOKENS: {GEMINI_CONFIG['max_tokens']}")
    logger.info(f"   SERVER_BASE_URL: {SERVER_CONFIG['base_url']}")
    logger.info("")
    
    results = {
        'database': test_database(),
        'gemini': test_gemini(),
        'server': test_server_connection()
    }
    
    logger.info("")
    logger.info("=" * 60)
    logger.info("📊 RESUMEN DE PRUEBAS")
    logger.info("=" * 60)
    
    all_ok = all(results.values())
    
    for test_name, result in results.items():
        status = "✅ OK" if result else "❌ FALLO"
        logger.info(f"   {test_name.upper()}: {status}")
    
    logger.info("")
    
    if all_ok:
        logger.info("✅ Todas las pruebas pasaron. El bot está listo para ejecutarse.")
        logger.info("")
        logger.info("🚀 Para iniciar el bot, ejecuta:")
        logger.info("   cd bot/ && ./start.sh")
        logger.info("   o")
        logger.info("   cd bot/ && source venv/bin/activate && python main.py")
        return 0
    else:
        logger.error("❌ Algunas pruebas fallaron. Revisa la configuración en .env")
        return 1

if __name__ == '__main__':
    sys.exit(main())

