#!/usr/bin/env python3
"""
Script para probar el procesador de pagarés
"""

import sys
import os
import logging
import tempfile

# Agregar el directorio del bot al path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from config.logging_config import setup_logging
from processors.pagare_filler_processor import PagareFillerProcessor
from core.database import DatabaseManager

# Configurar logging
logger = setup_logging()

def test_import():
    """Probar que se puede importar el procesador"""
    print("=" * 60)
    print("🧪 PRUEBA 1: Importación del procesador")
    print("=" * 60)
    try:
        processor = PagareFillerProcessor()
        print("✅ Procesador importado correctamente")
        return processor
    except Exception as e:
        print(f"❌ Error importando procesador: {e}")
        import traceback
        traceback.print_exc()
        return None

def test_database_connection(processor):
    """Probar conexión a la base de datos"""
    print("\n" + "=" * 60)
    print("🧪 PRUEBA 2: Conexión a base de datos")
    print("=" * 60)
    try:
        # Intentar obtener un proceso pendiente
        proceso = processor.obtener_proceso_pendiente()
        if proceso:
            print(f"✅ Conexión a BD exitosa")
            print(f"   Proceso encontrado: ID={proceso['id']}, Código={proceso['codigo']}")
            print(f"   Archivo pagaré: {proceso.get('archivo_pagare_original', 'N/A')}")
            return proceso
        else:
            print("⚠️  Conexión a BD exitosa, pero no hay procesos pendientes")
            print("   (Esto es normal si no hay procesos en estado 'informacion_ia_validada')")
            return None
    except Exception as e:
        print(f"❌ Error conectando a BD: {e}")
        import traceback
        traceback.print_exc()
        return None

def test_file_uploader(processor):
    """Probar el uploader de archivos"""
    print("\n" + "=" * 60)
    print("🧪 PRUEBA 3: Configuración del uploader")
    print("=" * 60)
    try:
        uploader = processor.uploader
        print(f"✅ Uploader configurado")
        print(f"   URL Base: {uploader.base_url}")
        print(f"   Token configurado: {'Sí' if uploader.api_token else 'No'}")
        if uploader.api_token:
            print(f"   Longitud del token: {len(uploader.api_token)}")
        return True
    except Exception as e:
        print(f"❌ Error verificando uploader: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_pagare_filler(processor):
    """Probar el llenador de pagarés"""
    print("\n" + "=" * 60)
    print("🧪 PRUEBA 4: Llenador de pagarés")
    print("=" * 60)
    try:
        pagare_filler = processor.pagare_filler
        print("✅ Llenador de pagarés inicializado correctamente")
        return True
    except Exception as e:
        print(f"❌ Error verificando llenador: {e}")
        import traceback
        traceback.print_exc()
        return False

def test_proceso_completo(processor, proceso):
    """Probar el procesamiento completo de un proceso"""
    print("\n" + "=" * 60)
    print("🧪 PRUEBA 5: Procesamiento completo")
    print("=" * 60)
    if not proceso:
        print("⚠️  No se puede probar sin un proceso pendiente")
        return False
    
    try:
        print(f"📋 Procesando proceso ID={proceso['id']}, Código={proceso['codigo']}")
        resultado = processor.procesar_proceso(proceso)
        if resultado:
            print("✅ Procesamiento completado exitosamente")
        else:
            print("❌ El procesamiento falló (revisa los logs para más detalles)")
        return resultado
    except Exception as e:
        print(f"❌ Error durante el procesamiento: {e}")
        import traceback
        traceback.print_exc()
        return False

def main():
    """Función principal de prueba"""
    print("\n" + "=" * 60)
    print("🤖 PRUEBA DEL PROCESADOR DE PAGARÉS")
    print("=" * 60)
    print()
    
    # Prueba 1: Importación
    processor = test_import()
    if not processor:
        print("\n❌ No se puede continuar sin el procesador")
        return
    
    # Prueba 2: Conexión a BD
    proceso = test_database_connection(processor)
    
    # Prueba 3: Uploader
    test_file_uploader(processor)
    
    # Prueba 4: Llenador
    test_pagare_filler(processor)
    
    # Prueba 5: Procesamiento completo (solo si hay proceso)
    if proceso:
        # En modo no interactivo, procesar automáticamente
        import sys
        if sys.stdin.isatty():
            respuesta = input("\n¿Deseas procesar el proceso encontrado? (s/n): ")
            if respuesta.lower() == 's':
                test_proceso_completo(processor, proceso)
            else:
                print("⚠️  Procesamiento cancelado por el usuario")
        else:
            print("\n🔄 Modo no interactivo: procesando automáticamente...")
            test_proceso_completo(processor, proceso)
    else:
        print("\n💡 Para probar el procesamiento completo, necesitas un proceso en estado 'informacion_ia_validada'")
    
    print("\n" + "=" * 60)
    print("✅ Pruebas completadas")
    print("=" * 60)

if __name__ == '__main__':
    main()
