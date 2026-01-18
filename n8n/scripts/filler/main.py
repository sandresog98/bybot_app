#!/usr/bin/env python3
"""
Script principal de llenado de PDF para ByBot
Ejecutado por n8n para llenar pagarés con datos validados

Uso:
    python main.py --proceso_id 123 --pagare_url "..." --datos '{"deudor": {...}, ...}'
    
    O con archivos locales:
    python main.py --proceso_id 123 --pagare_local '/path/to/pagare.pdf' --datos_file '/path/to/datos.json'
"""

import os
import sys
import json
import base64
import argparse
import logging
from pathlib import Path
from datetime import datetime
from typing import Dict, Any

# Agregar path del proyecto
sys.path.insert(0, str(Path(__file__).parent.parent))

from shared.config import TEMP_DIR, LOG_LEVEL
from shared.utils import download_file, upload_file, send_callback, cleanup_temp_files, logger
from pdf_filler import PDFFiller

# Configurar logging
logging.basicConfig(
    level=LOG_LEVEL,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(sys.stdout)
    ]
)


def parse_arguments():
    """Parsea argumentos de línea de comandos."""
    parser = argparse.ArgumentParser(description='Llena pagarés PDF con datos validados')
    
    parser.add_argument(
        '--proceso_id',
        type=int,
        required=True,
        help='ID del proceso en ByBot'
    )
    
    parser.add_argument(
        '--pagare_url',
        type=str,
        help='URL del pagaré original para descargar'
    )
    
    parser.add_argument(
        '--pagare_local',
        type=str,
        help='Ruta local del pagaré original'
    )
    
    parser.add_argument(
        '--datos',
        type=str,
        help='JSON con datos para llenar el pagaré'
    )
    
    parser.add_argument(
        '--datos_file',
        type=str,
        help='Ruta a archivo JSON con datos'
    )
    
    parser.add_argument(
        '--config',
        type=str,
        help='Ruta a archivo de configuración de posiciones'
    )
    
    parser.add_argument(
        '--output',
        type=str,
        help='Ruta de salida (opcional)'
    )
    
    parser.add_argument(
        '--no_callback',
        action='store_true',
        help='No enviar callback, solo procesar localmente'
    )
    
    parser.add_argument(
        '--no_upload',
        action='store_true',
        help='No subir el archivo, retornar en base64'
    )
    
    return parser.parse_args()


def load_datos(args) -> Dict[str, Any]:
    """Carga datos desde argumentos o archivo."""
    if args.datos:
        return json.loads(args.datos)
    elif args.datos_file:
        with open(args.datos_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    else:
        raise ValueError("Debe proporcionar --datos o --datos_file")


def get_pagare_path(args) -> Path:
    """Obtiene la ruta al pagaré (descargando si es necesario)."""
    if args.pagare_local:
        path = Path(args.pagare_local)
        if not path.exists():
            raise FileNotFoundError(f"Pagaré no encontrado: {path}")
        return path
    elif args.pagare_url:
        output_path = TEMP_DIR / f"pagare_original_{args.proceso_id}.pdf"
        return download_file(args.pagare_url, output_path=output_path)
    else:
        raise ValueError("Debe proporcionar --pagare_url o --pagare_local")


def main():
    """Función principal."""
    args = parse_arguments()
    proceso_id = args.proceso_id
    
    logger.info(f"=== Iniciando llenado de pagaré para proceso {proceso_id} ===")
    
    temp_files = []
    
    try:
        # Cargar datos
        datos = load_datos(args)
        logger.info(f"Datos cargados: {list(datos.keys())}")
        
        # Obtener pagaré
        pagare_path = get_pagare_path(args)
        if args.pagare_url:
            temp_files.append(pagare_path)
        logger.info(f"Pagaré: {pagare_path}")
        
        # Cargar configuración de posiciones
        if args.config:
            filler = PDFFiller.load_config(Path(args.config))
            logger.info(f"Configuración cargada: {args.config}")
        else:
            filler = PDFFiller()
            logger.info("Usando configuración por defecto")
        
        # Determinar ruta de salida
        if args.output:
            output_path = Path(args.output)
        else:
            output_path = TEMP_DIR / f"pagare_llenado_{proceso_id}_{datetime.now().strftime('%Y%m%d%H%M%S')}.pdf"
        
        # Llenar pagaré
        filled_path = filler.fill_pagare(
            pdf_path=pagare_path,
            datos=datos,
            output_path=output_path
        )
        temp_files.append(filled_path)
        
        logger.info(f"Pagaré llenado: {filled_path}")
        
        # Preparar respuesta
        if args.no_callback:
            # Modo local - retornar resultado
            result = {
                'success': True,
                'proceso_id': proceso_id,
                'output_path': str(filled_path),
                'timestamp': datetime.now().isoformat()
            }
            
            if args.no_upload:
                # Incluir contenido en base64
                with open(filled_path, 'rb') as f:
                    result['archivo_base64'] = base64.b64encode(f.read()).decode('utf-8')
            
            print(json.dumps(result, indent=2))
            
        else:
            # Modo n8n - subir y enviar callback
            if args.no_upload:
                # Solo enviar callback con base64
                with open(filled_path, 'rb') as f:
                    archivo_base64 = base64.b64encode(f.read()).decode('utf-8')
                
                send_callback(
                    proceso_id=proceso_id,
                    action='fill_complete',
                    data={
                        'archivo_contenido_base64': archivo_base64,
                        'archivo_nombre': filled_path.name
                    }
                )
            else:
                # Subir archivo a ByBot
                upload_result = upload_file(
                    file_path=filled_path,
                    proceso_id=proceso_id,
                    tipo='pagare_llenado'
                )
                
                # Enviar callback
                send_callback(
                    proceso_id=proceso_id,
                    action='fill_complete',
                    data={
                        'archivo_ruta': upload_result.get('ruta_archivo'),
                        'archivo_id': upload_result.get('id')
                    }
                )
            
            print(json.dumps({'success': True, 'message': 'Proceso completado'}))
        
        logger.info(f"=== Llenado completado para proceso {proceso_id} ===")
        return 0
        
    except Exception as e:
        logger.error(f"Error en llenado: {e}")
        
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
                    action='fill_error',
                    data={'error': str(e)}
                )
            except:
                pass
        
        return 1
        
    finally:
        # Limpiar archivos temporales
        if temp_files and not args.output:
            # Solo limpiar si no se especificó output personalizado
            pass  # Dejamos los archivos para debug, n8n puede limpiar después


if __name__ == '__main__':
    sys.exit(main())

