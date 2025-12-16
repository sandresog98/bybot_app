#!/usr/bin/env python3
"""
Cliente para interactuar con Gemini API
"""

import logging
import base64
import json
from typing import Dict, Any, Optional, Tuple
import google.generativeai as genai
from config.settings import GEMINI_CONFIG

logger = logging.getLogger('bybot.gemini')

class GeminiClient:
    """Cliente para análisis de documentos con Gemini"""
    
    def __init__(self):
        """Inicializar cliente Gemini"""
        api_key = GEMINI_CONFIG['api_key']
        if not api_key:
            raise ValueError("GEMINI_API_KEY no está configurada en .env")
        
        genai.configure(api_key=api_key)
        
        # Inicializar con el modelo configurado
        model_name = GEMINI_CONFIG['model']
        self.model = genai.GenerativeModel(
            model_name,
            generation_config={
                'temperature': GEMINI_CONFIG['temperature'],
                'max_output_tokens': GEMINI_CONFIG['max_tokens']
            }
        )
        logger.info(f"✅ Cliente Gemini inicializado con modelo: {model_name}")
    
    def analyze_estado_cuenta(self, pdf_path: str) -> Tuple[Dict[str, Any], Dict[str, Any]]:
        """Analizar estado de cuenta y extraer información
        Retorna: (datos_extraidos, metadata_con_tokens)
        """
        try:
            logger.info(f"📄 Analizando estado de cuenta: {pdf_path}")
            
            # Cargar PDF
            with open(pdf_path, 'rb') as f:
                pdf_data = f.read()
            
            # Crear prompt para extracción
            prompt = """
Analiza este documento de estado de cuenta bancario y extrae la siguiente información en formato JSON:

{
    "fecha_causacion": "YYYY-MM-DD o null si no se encuentra",
    "saldo_capital": número decimal o null,
    "saldo_interes": número decimal o null,
    "saldo_mora": número decimal o null,
    "tasa_interes_efectiva_anual": número decimal (porcentaje) o null
}

INSTRUCCIONES ESPECÍFICAS:
1. fecha_causacion: Busca la ÚLTIMA fecha en la que la persona realizó un pago y toma la fecha del movimiento siguiente. Revisa movimientos, pagos, abonos o transacciones recientes.
    - Es de suma importancia analizar el valor Capital-Abono e Intereses-Abono, ya que el ultimo movimiento que tenga alguna o las dos con un valor mayor a cero es la ultima fecha de pago.
    - La fecha de causación es el movimiento siguiente a ese ultimo pago.
    - En resumen, la fecha de causación es el movimiento siguiente a ese ultimo pago. Por ejemplo si el ultimo pago fue el 10/12/2025, y el siguiente movimiento es el 11/12/2025, la fecha de causación es el 11/12/2025.
    - No es mandatorio pero como guía: La fecha de causación suele tener el valor "CAUSACION DE MORA Y REINTEGROS" en el campo Descripción Movimiento.

2. saldo_capital: Busca el saldo de capital, capital pendiente, saldo principal o monto del crédito. Puede aparecer como "Capital", "Principal", "Saldo Capital".

3. saldo_interes: Busca intereses pendientes, intereses causados, intereses a pagar. Puede aparecer como "Intereses", "Interés Causado", "Interés Pendiente".

4. saldo_mora: Busca mora, intereses de mora, recargos por mora, intereses moratorios. Puede aparecer como "Mora", "Interés de Mora", "Recargo por Mora".

5. tasa_interes_efectiva_anual (TEA):
   - Busca términos como: "TEA", "T.E.A.", "Tasa Efectiva Anual", "Tasa de Interés Efectiva Anual", "Tasa E.A.", "Tasa Efectiva"
   - Busca porcentajes que puedan ser tasas de interés (números seguidos de %)
   - Revisa tablas, encabezados, pies de página, condiciones del crédito
   - Busca en secciones como "Condiciones", "Términos", "Información del Crédito", "Detalles del Préstamo"
   - Si encuentras una tasa, verifica que sea anual (no mensual ni diaria)
   - El valor debe ser un número decimal (ejemplo: 15.5 para 15.5% anual)
   - Si encuentras una tasa mensual, multiplícala por 12 para obtener la anual
   - Si encuentras una tasa nominal, busca si hay conversión a efectiva anual

IMPORTANTE:
- Revisa TODO el documento, no solo la primera página
- La TEA es CRÍTICA, busca en todas las secciones posibles
- Si no encuentras algún dato después de revisar exhaustivamente, usa null
- Responde SOLO con el JSON válido, sin texto adicional, sin explicaciones
"""
            
            # Log del prompt enviado
            logger.info("📝 Prompt enviado para análisis de estado de cuenta:")
            logger.info(f"   {prompt[:200]}...")  # Primeros 200 caracteres
            
            # Enviar a Gemini
            pdf_file = genai.upload_file(path=pdf_path)
            response = self.model.generate_content([prompt, pdf_file])
            
            # Registrar tokens utilizados
            metadata = {}
            if hasattr(response, 'usage_metadata'):
                tokens_input = getattr(response.usage_metadata, 'prompt_token_count', 0)
                tokens_output = getattr(response.usage_metadata, 'candidates_token_count', 0)
                tokens_total = getattr(response.usage_metadata, 'total_token_count', 0)
                metadata = {
                    'tokens_entrada': tokens_input,
                    'tokens_salida': tokens_output,
                    'tokens_total': tokens_total
                }
                logger.info(f"🔢 Tokens utilizados - Entrada: {tokens_input}, Salida: {tokens_output}, Total: {tokens_total}")
            else:
                logger.warning("⚠️ No se pudo obtener información de tokens de la respuesta")
                metadata = {
                    'tokens_entrada': 0,
                    'tokens_salida': 0,
                    'tokens_total': 0
                }
            
            # Log de la respuesta completa
            logger.debug(f"📥 Respuesta completa de Gemini: {response.text}")
            
            # Parsear respuesta JSON
            result_text = response.text.strip()
            # Limpiar markdown si viene envuelto
            if result_text.startswith('```json'):
                result_text = result_text[7:]
            if result_text.startswith('```'):
                result_text = result_text[3:]
            if result_text.endswith('```'):
                result_text = result_text[:-3]
            result_text = result_text.strip()
            
            result = json.loads(result_text)
            logger.info(f"✅ Estado de cuenta analizado: {result}")
            
            # Limpiar archivo subido
            try:
                genai.delete_file(pdf_file)
            except Exception as e:
                logger.warning(f"⚠️ No se pudo eliminar archivo subido: {e}")
            
            return result, metadata
            
        except Exception as e:
            logger.error(f"❌ Error analizando estado de cuenta: {e}")
            raise
    
    def analyze_anexos(self, pdf_paths: list) -> Tuple[Dict[str, Any], Dict[str, Any]]:
        """Analizar anexos y extraer información de deudor y codeudor
        Retorna: (datos_extraidos, metadata_con_tokens)
        """
        try:
            logger.info(f"📄 Analizando {len(pdf_paths)} anexos")
            
            # Cargar todos los PDFs
            pdf_files = []
            for pdf_path in pdf_paths:
                pdf_file = genai.upload_file(path=pdf_path)
                pdf_files.append(pdf_file)
            
            # Crear prompt para extracción
            prompt = """
Analiza estos documentos anexos y extrae la siguiente información en formato JSON:

{
    "deudor": {
        "tipo_identificacion": "CC, CE, NIT, etc. o null",
        "numero_identificacion": "string o null",
        "nombres": "string o null",
        "apellidos": "string o null",
        "fecha_expedicion_cedula": "YYYY-MM-DD o null",
        "fecha_nacimiento": "YYYY-MM-DD o null",
        "telefono": "string o null",
        "direccion": "string o null",
        "correo": "string o null"
    },
    "codeudor": {
        "tipo_identificacion": "CC, CE, NIT, etc. o null",
        "numero_identificacion": "string o null",
        "nombres": "string o null",
        "apellidos": "string o null",
        "fecha_expedicion_cedula": "YYYY-MM-DD o null",
        "fecha_nacimiento": "YYYY-MM-DD o null",
        "telefono": "string o null",
        "direccion": "string o null",
        "correo": "string o null"
    },
    "tasa_interes_efectiva_anual": número decimal (porcentaje) o null
}

INSTRUCCIONES ESPECÍFICAS:
- El deudor/solicitante es la persona principal del crédito
- El codeudor es la persona que garantiza el crédito (puede no existir)
- Si no encuentras algún dato, usa null
- Las fechas deben estar en formato YYYY-MM-DD

IMPORTANTE - Tasa Interés Efectiva Anual (TEA):
- Busca EXHAUSTIVAMENTE en TODOS los documentos anexos:
  - Busca términos como: "TEA", "T.E.A.", "Tasa Efectiva Anual", "Tasa de Interés Efectiva Anual", "Tasa E.A.", "Tasa Efectiva"
  - Busca porcentajes que puedan ser tasas de interés (números seguidos de %)
  - Revisa tablas, encabezados, pies de página, condiciones del crédito
  - Busca en secciones como "Condiciones", "Términos", "Información del Crédito", "Detalles del Préstamo", "Contrato"
  - Si encuentras una tasa, verifica que sea anual (no mensual ni diaria)
  - El valor debe ser un número decimal (ejemplo: 15.5 para 15.5% anual)
  - Si encuentras una tasa mensual, multiplícala por 12 para obtener la anual
  - Si encuentras una tasa nominal, busca si hay conversión a efectiva anual
- Revisa TODO el documento, no solo la primera página
- La TEA es CRÍTICA, busca en todas las secciones posibles

- Responde SOLO con el JSON válido, sin texto adicional, sin explicaciones
"""
            
            # Log del prompt enviado
            logger.info("📝 Prompt enviado para análisis de anexos:")
            logger.info(f"   {prompt[:200]}...")  # Primeros 200 caracteres
            
            # Enviar a Gemini
            response = self.model.generate_content([prompt] + pdf_files)
            
            # Registrar tokens utilizados
            metadata = {}
            if hasattr(response, 'usage_metadata'):
                tokens_input = getattr(response.usage_metadata, 'prompt_token_count', 0)
                tokens_output = getattr(response.usage_metadata, 'candidates_token_count', 0)
                tokens_total = getattr(response.usage_metadata, 'total_token_count', 0)
                metadata = {
                    'tokens_entrada': tokens_input,
                    'tokens_salida': tokens_output,
                    'tokens_total': tokens_total
                }
                logger.info(f"🔢 Tokens utilizados - Entrada: {tokens_input}, Salida: {tokens_output}, Total: {tokens_total}")
            else:
                logger.warning("⚠️ No se pudo obtener información de tokens de la respuesta")
                metadata = {
                    'tokens_entrada': 0,
                    'tokens_salida': 0,
                    'tokens_total': 0
                }
            
            # Log de la respuesta completa
            logger.debug(f"📥 Respuesta completa de Gemini: {response.text}")
            
            # Parsear respuesta JSON
            result_text = response.text.strip()
            # Limpiar markdown si viene envuelto
            if result_text.startswith('```json'):
                result_text = result_text[7:]
            if result_text.startswith('```'):
                result_text = result_text[3:]
            if result_text.endswith('```'):
                result_text = result_text[:-3]
            result_text = result_text.strip()
            
            result = json.loads(result_text)
            logger.info(f"✅ Anexos analizados: deudor y codeudor encontrados")
            
            # Limpiar archivos subidos
            for pdf_file in pdf_files:
                try:
                    genai.delete_file(pdf_file)
                except Exception as e:
                    logger.warning(f"⚠️ No se pudo eliminar archivo subido: {e}")
            
            return result, metadata
            
        except Exception as e:
            logger.error(f"❌ Error analizando anexos: {e}")
            raise
    
    def identificar_solicitudes_vinculacion(self, pdf_paths: list) -> Tuple[Dict[str, Any], Dict[str, Any]]:
        """Identificar qué páginas contienen las solicitudes de vinculación del deudor y codeudor
        Retorna: (resultado_identificacion, metadata_con_tokens)
        """
        try:
            logger.info(f"🔍 Identificando solicitudes de vinculación en {len(pdf_paths)} anexos")
            
            # Cargar todos los PDFs
            pdf_files = []
            for pdf_path in pdf_paths:
                pdf_file = genai.upload_file(path=pdf_path)
                pdf_files.append(pdf_file)
            
            # Crear prompt para identificación con información clara sobre los archivos
            num_archivos = len(pdf_paths)
            prompt = f"""
Analiza estos {num_archivos} documento(s) anexo(s) y identifica las páginas que contienen las solicitudes de vinculación.

IMPORTANTE: Hay {num_archivos} archivo(s) PDF. El primer archivo tiene índice 0, el segundo tiene índice 1, etc.
Si solo hay 1 archivo, usa archivo_index = 0 para todas las solicitudes que encuentres en ese archivo.

Una solicitud de vinculación típicamente:
- Tiene un título como "SOLICITUD DE VINCULACIÓN", "FORMULARIO DE VINCULACIÓN", "SOLICITUD DE ASOCIACIÓN"
- Contiene datos personales del solicitante (nombres, apellidos, identificación, etc.)
- Suele ser 2 páginas consecutivas para cada persona
- La solicitud del DEUDOR/SOLICITANTE es la persona principal del crédito, hay un campo que suele estar marcado con una X o un Check junto a la palabra solicitante.
- La solicitud del CODEUDOR es la persona que garantiza el crédito (puede no existir), hay un campo que suele estar marcado con una X o un Check junto a la palabra codeudor.

Responde SOLO con un JSON válido en este formato:

{{
    "deudor": {{
        "archivo_index": número del índice del archivo (0-based, donde 0 es el primer archivo, 1 es el segundo, etc.),
        "paginas": [número_pagina_1, número_pagina_2] (números de página, 1-based, ej: [1, 2] para páginas 1 y 2)
    }},
    "codeudor": {{
        "archivo_index": número del índice del archivo (0-based),
        "paginas": [número_pagina_1, número_pagina_2]
    }} o null si no hay codeudor
}}

INSTRUCCIONES CRÍTICAS:
- Los números de página son 1-based (la primera página es 1, no 0)
- archivo_index es 0-based: 0 = primer archivo, 1 = segundo archivo, etc.
- Si solo hay 1 archivo, TODOS los archivo_index deben ser 0
- Si no encuentras la solicitud del deudor, usa null para deudor
- Si no hay codeudor o no encuentras su solicitud, usa null para codeudor
- Las páginas deben ser consecutivas (ej: [3, 4] o [5, 6])
- Responde SOLO con el JSON válido, sin texto adicional, sin explicaciones
"""
            
            logger.info("📝 Prompt enviado para identificar solicitudes de vinculación")
            
            # Enviar a Gemini
            response = self.model.generate_content([prompt] + pdf_files)
            
            # Registrar tokens utilizados
            metadata = {}
            if hasattr(response, 'usage_metadata'):
                tokens_input = getattr(response.usage_metadata, 'prompt_token_count', 0)
                tokens_output = getattr(response.usage_metadata, 'candidates_token_count', 0)
                tokens_total = getattr(response.usage_metadata, 'total_token_count', 0)
                metadata = {
                    'tokens_entrada': tokens_input,
                    'tokens_salida': tokens_output,
                    'tokens_total': tokens_total
                }
                logger.info(f"🔢 Tokens utilizados (identificación) - Entrada: {tokens_input}, Salida: {tokens_output}, Total: {tokens_total}")
            else:
                logger.warning("⚠️ No se pudo obtener información de tokens de la respuesta")
                metadata = {
                    'tokens_entrada': 0,
                    'tokens_salida': 0,
                    'tokens_total': 0
                }
            
            # Parsear respuesta JSON
            result_text = response.text.strip()
            # Limpiar markdown si viene envuelto
            if result_text.startswith('```json'):
                result_text = result_text[7:]
            if result_text.startswith('```'):
                result_text = result_text[3:]
            if result_text.endswith('```'):
                result_text = result_text[:-3]
            result_text = result_text.strip()
            
            result = json.loads(result_text)
            logger.info(f"✅ Solicitudes de vinculación identificadas: {result}")
            
            # Validar y corregir archivo_index si es necesario
            if result.get('deudor') and result['deudor'].get('archivo_index') is not None:
                archivo_index = result['deudor']['archivo_index']
                if archivo_index >= num_archivos:
                    logger.warning(f"⚠️ archivo_index del deudor ({archivo_index}) fuera de rango. Corrigiendo a 0")
                    result['deudor']['archivo_index'] = 0
            
            if result.get('codeudor') and result['codeudor'].get('archivo_index') is not None:
                archivo_index = result['codeudor']['archivo_index']
                if archivo_index >= num_archivos:
                    logger.warning(f"⚠️ archivo_index del codeudor ({archivo_index}) fuera de rango. Corrigiendo a 0")
                    result['codeudor']['archivo_index'] = 0
            
            # Limpiar archivos subidos
            for pdf_file in pdf_files:
                try:
                    genai.delete_file(pdf_file)
                except Exception as e:
                    logger.warning(f"⚠️ No se pudo eliminar archivo subido: {e}")
            
            return result, metadata
            
        except Exception as e:
            logger.error(f"❌ Error identificando solicitudes de vinculación: {e}")
            raise

