#!/usr/bin/env python3
"""
Script principal de análisis de documentos para ByBot
Ejecutado por n8n para procesar documentos con Gemini AI

Uso:
    python main.py --proceso_id 123 --archivos '[{"url": "...", "tipo": "estado_cuenta"}, ...]'
    
    O con archivos locales:
    python main.py --proceso_id 123 --archivos_locales '/path/to/file1.pdf,/path/to/file2.pdf'
"""

import os
import sys
import json
import argparse
import logging
from pathlib import Path
from datetime import datetime
from typing import Dict, Any, List

# Agregar path del proyecto
sys.path.insert(0, str(Path(__file__).parent.parent))

from shared.config import TEMP_DIR, LOG_LEVEL
from shared.utils import download_file, send_callback, cleanup_temp_files, logger
from gemini_client import GeminiClient

# Configurar logging para este script
logging.basicConfig(
    level=LOG_LEVEL,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)


def parse_arguments():
    """Parsea argumentos de línea de comandos."""
    parser = argparse.ArgumentParser(description='Analiza documentos con Gemini AI')
    
    parser.add_argument(
        '--proceso_id',
        type=int,
        required=True,
        help='ID del proceso en ByBot'
    )
    
    parser.add_argument(
        '--archivos',
        type=str,
        help='JSON con lista de archivos a analizar [{url, tipo, nombre}]'
    )
    
    parser.add_argument(
        '--archivos_locales',
        type=str,
        help='Rutas locales de archivos separadas por coma'
    )
    
    parser.add_argument(
        '--callback_url',
        type=str,
        help='URL para enviar callback (opcional, usa default de config)'
    )
    
    parser.add_argument(
        '--no_callback',
        action='store_true',
        help='No enviar callback, solo imprimir resultado'
    )
    
    return parser.parse_args()


def download_archivos(archivos_json: str) -> Dict[str, List[Path]]:
    """
    Descarga archivos desde las URLs proporcionadas.
    
    Returns:
        Diccionario con archivos por tipo
    """
    archivos = json.loads(archivos_json)
    resultado = {
        'estado_cuenta': [],
        'anexos': [],
        'pagare_original': []
    }
    
    for archivo in archivos:
        url = archivo.get('url')
        tipo = archivo.get('tipo', 'anexo')
        nombre = archivo.get('nombre', 'documento.pdf')
        
        if not url:
            continue
        
        try:
            output_path = TEMP_DIR / f"{tipo}_{nombre}"
            downloaded = download_file(url, output_path=output_path)
            
            if tipo == 'estado_cuenta':
                resultado['estado_cuenta'].append(downloaded)
            elif tipo == 'pagare_original':
                resultado['pagare_original'].append(downloaded)
            else:
                resultado['anexos'].append(downloaded)
                
        except Exception as e:
            logger.error(f"Error descargando {nombre}: {e}")
    
    return resultado


def load_archivos_locales(paths_str: str) -> Dict[str, List[Path]]:
    """
    Carga archivos desde rutas locales.
    """
    resultado = {
        'estado_cuenta': [],
        'anexos': [],
        'pagare_original': []
    }
    
    paths = [p.strip() for p in paths_str.split(',') if p.strip()]
    
    for path_str in paths:
        path = Path(path_str)
        if path.exists():
            # Determinar tipo por nombre
            nombre_lower = path.name.lower()
            if 'estado' in nombre_lower or 'cuenta' in nombre_lower:
                resultado['estado_cuenta'].append(path)
            elif 'pagare' in nombre_lower:
                resultado['pagare_original'].append(path)
            else:
                resultado['anexos'].append(path)
    
    return resultado


def analyze_documents(archivos: Dict[str, List[Path]]) -> Dict[str, Any]:
    """
    Ejecuta el análisis de todos los documentos.
    
    Returns:
        Diccionario con todos los datos extraídos
    """
    client = GeminiClient()
    resultado = {
        'estado_cuenta': None,
        'deudor': None,
        'codeudor': None,
        'referencias': [],
        'metadata': {
            'fecha_analisis': datetime.now().isoformat(),
            'archivos_procesados': 0,
            'tokens_totales': 0
        }
    }
    
    # Analizar estado de cuenta
    if archivos['estado_cuenta']:
        logger.info("Analizando estado de cuenta...")
        estado_cuenta_path = archivos['estado_cuenta'][0]
        
        response = client.analyze_estado_cuenta(estado_cuenta_path)
        
        if response['success']:
            resultado['estado_cuenta'] = response['data'].get('estado_cuenta')
            resultado['metadata']['archivos_procesados'] += 1
            resultado['metadata']['tokens_totales'] += response.get('tokens', {}).get('total', 0)
        else:
            logger.error(f"Error en estado de cuenta: {response.get('error')}")
    
    # Analizar anexos
    todos_anexos = archivos['anexos'] + archivos['estado_cuenta'][1:] if len(archivos['estado_cuenta']) > 1 else archivos['anexos']
    
    if todos_anexos:
        logger.info(f"Analizando {len(todos_anexos)} anexos...")
        
        response = client.analyze_anexos(todos_anexos)
        
        if response['success']:
            data = response['data']
            resultado['deudor'] = data.get('deudor')
            resultado['codeudor'] = data.get('codeudor')
            resultado['referencias'] = data.get('referencias', [])
            resultado['solicitudes_vinculacion'] = data.get('solicitudes_vinculacion')
            resultado['metadata']['archivos_procesados'] += len(todos_anexos)
            resultado['metadata']['tokens_totales'] += response.get('tokens', {}).get('total', 0)
        else:
            logger.error(f"Error en anexos: {response.get('error')}")
    
    return resultado


def main():
    """Función principal."""
    args = parse_arguments()
    proceso_id = args.proceso_id
    
    logger.info(f"=== Iniciando análisis para proceso {proceso_id} ===")
    
    downloaded_files = []
    
    try:
        # Cargar archivos
        if args.archivos:
            archivos = download_archivos(args.archivos)
            # Guardar referencia para cleanup
            for tipo_archivos in archivos.values():
                downloaded_files.extend(tipo_archivos)
        elif args.archivos_locales:
            archivos = load_archivos_locales(args.archivos_locales)
        else:
            raise ValueError("Debe proporcionar --archivos o --archivos_locales")
        
        # Verificar que hay archivos
        total_archivos = sum(len(v) for v in archivos.values())
        if total_archivos == 0:
            raise ValueError("No se encontraron archivos para analizar")
        
        logger.info(f"Archivos cargados: {total_archivos}")
        
        # Ejecutar análisis
        resultado = analyze_documents(archivos)
        
        # Preparar respuesta
        output = {
            'success': True,
            'proceso_id': proceso_id,
            'datos': resultado,
            'timestamp': datetime.now().isoformat()
        }
        
        # Enviar callback o imprimir
        if args.no_callback:
            print(json.dumps(output, indent=2, ensure_ascii=False))
        else:
            send_callback(
                proceso_id=proceso_id,
                action='analysis_complete',
                data={
                    'datos': resultado,
                    'modelo': 'gemini-1.5-flash',
                    'tokens': resultado['metadata']['tokens_totales']
                }
            )
            print(json.dumps({'success': True, 'message': 'Callback enviado'}))
        
        logger.info(f"=== Análisis completado para proceso {proceso_id} ===")
        return 0
        
    except Exception as e:
        logger.error(f"Error en análisis: {e}")
        
        error_output = {
            'success': False,
            'proceso_id': proceso_id,
            'error': str(e),
            'timestamp': datetime.now().isoformat()
        }
        
        if args.no_callback:
            print(json.dumps(error_output, indent=2))
        else:
            try:
                send_callback(
                    proceso_id=proceso_id,
                    action='analysis_error',
                    data={'error': str(e)}
                )
            except:
                pass
        
        return 1
        
    finally:
        # Limpiar archivos temporales descargados
        if downloaded_files:
            cleanup_temp_files(*downloaded_files)


if __name__ == '__main__':
    sys.exit(main())

