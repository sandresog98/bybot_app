"""
Cliente de Gemini AI para análisis de documentos
"""

import json
import logging
import google.generativeai as genai
from pathlib import Path
from typing import Dict, Any, List, Optional

import sys
sys.path.append(str(Path(__file__).parent.parent))

from shared.config import (
    GEMINI_API_KEY,
    GEMINI_MODEL,
    GEMINI_TEMPERATURE,
    GEMINI_MAX_TOKENS
)

logger = logging.getLogger(__name__)


class GeminiClient:
    """
    Cliente para interactuar con Gemini AI
    """
    
    def __init__(self):
        if not GEMINI_API_KEY:
            raise ValueError("GEMINI_API_KEY no está configurada")
        
        genai.configure(api_key=GEMINI_API_KEY)
        
        self.model = genai.GenerativeModel(
            model_name=GEMINI_MODEL,
            generation_config={
                "temperature": GEMINI_TEMPERATURE,
                "max_output_tokens": GEMINI_MAX_TOKENS,
                "response_mime_type": "application/json"
            }
        )
        
        logger.info(f"GeminiClient inicializado con modelo: {GEMINI_MODEL}")
    
    def analyze_estado_cuenta(self, pdf_path: Path) -> Dict[str, Any]:
        """
        Analiza un estado de cuenta y extrae datos financieros.
        
        Args:
            pdf_path: Ruta al archivo PDF
        
        Returns:
            Diccionario con datos extraídos
        """
        prompt = self._get_prompt_estado_cuenta()
        
        # Subir archivo a Gemini
        uploaded_file = genai.upload_file(str(pdf_path))
        logger.info(f"Archivo subido a Gemini: {uploaded_file.name}")
        
        try:
            # Generar respuesta
            response = self.model.generate_content([prompt, uploaded_file])
            
            # Parsear respuesta JSON
            result = json.loads(response.text)
            
            logger.info("Estado de cuenta analizado exitosamente")
            return {
                "success": True,
                "data": result,
                "tokens": {
                    "prompt": response.usage_metadata.prompt_token_count,
                    "completion": response.usage_metadata.candidates_token_count,
                    "total": response.usage_metadata.total_token_count
                }
            }
            
        except json.JSONDecodeError as e:
            logger.error(f"Error parseando respuesta JSON: {e}")
            logger.debug(f"Respuesta raw: {response.text}")
            return {
                "success": False,
                "error": "Error parseando respuesta de IA",
                "raw_response": response.text[:500]
            }
        except Exception as e:
            logger.error(f"Error en análisis: {e}")
            return {
                "success": False,
                "error": str(e)
            }
        finally:
            # Limpiar archivo subido
            try:
                genai.delete_file(uploaded_file.name)
            except:
                pass
    
    def analyze_anexos(self, pdf_paths: List[Path]) -> Dict[str, Any]:
        """
        Analiza anexos y extrae datos de deudor/codeudor.
        
        Args:
            pdf_paths: Lista de rutas a archivos PDF
        
        Returns:
            Diccionario con datos extraídos
        """
        prompt = self._get_prompt_anexos()
        
        # Subir archivos
        uploaded_files = []
        for pdf_path in pdf_paths:
            uploaded = genai.upload_file(str(pdf_path))
            uploaded_files.append(uploaded)
            logger.info(f"Archivo subido: {uploaded.name}")
        
        try:
            # Generar respuesta
            content = [prompt] + uploaded_files
            response = self.model.generate_content(content)
            
            result = json.loads(response.text)
            
            logger.info("Anexos analizados exitosamente")
            return {
                "success": True,
                "data": result,
                "tokens": {
                    "prompt": response.usage_metadata.prompt_token_count,
                    "completion": response.usage_metadata.candidates_token_count,
                    "total": response.usage_metadata.total_token_count
                }
            }
            
        except Exception as e:
            logger.error(f"Error en análisis de anexos: {e}")
            return {
                "success": False,
                "error": str(e)
            }
        finally:
            for uploaded in uploaded_files:
                try:
                    genai.delete_file(uploaded.name)
                except:
                    pass
    
    def _get_prompt_estado_cuenta(self) -> str:
        """
        Retorna el prompt para análisis de estado de cuenta.
        """
        return """Analiza este estado de cuenta bancario/financiero y extrae la siguiente información en formato JSON.

IMPORTANTE: 
- Responde SOLO con el JSON, sin texto adicional
- Usa null para campos que no encuentres
- Los valores monetarios deben ser números (sin símbolos de moneda)
- Las tasas de interés deben ser números decimales (ej: 24.5 para 24.5%)

Estructura JSON requerida:
{
    "estado_cuenta": {
        "numero_credito": "string o null",
        "fecha_corte": "YYYY-MM-DD o null",
        "capital": number o null,
        "intereses_corrientes": number o null,
        "intereses_mora": number o null,
        "honorarios": number o null,
        "gastos": number o null,
        "seguros": number o null,
        "otros_cobros": number o null,
        "total_deuda": number o null,
        "tasa_interes_corriente": number o null,
        "tasa_interes_mora": number o null,
        "dias_mora": number o null,
        "fecha_ultimo_pago": "YYYY-MM-DD o null",
        "valor_ultimo_pago": number o null
    },
    "entidad": {
        "nombre": "string o null",
        "nit": "string o null"
    },
    "observaciones": "string con notas adicionales relevantes"
}

Analiza el documento y extrae toda la información disponible:"""
    
    def _get_prompt_anexos(self) -> str:
        """
        Retorna el prompt para análisis de anexos (datos personales).
        """
        return """Analiza estos documentos anexos y extrae la información del deudor y codeudor (si existe).

IMPORTANTE:
- Responde SOLO con el JSON, sin texto adicional
- Usa null para campos que no encuentres
- Identifica si hay información de codeudor/garante

Estructura JSON requerida:
{
    "deudor": {
        "nombre_completo": "string o null",
        "tipo_documento": "CC/CE/NIT/PA o null",
        "numero_documento": "string o null",
        "fecha_expedicion": "YYYY-MM-DD o null",
        "lugar_expedicion": "string o null",
        "fecha_nacimiento": "YYYY-MM-DD o null",
        "direccion": "string o null",
        "ciudad": "string o null",
        "departamento": "string o null",
        "telefono": "string o null",
        "celular": "string o null",
        "email": "string o null",
        "ocupacion": "string o null",
        "empresa": "string o null",
        "cargo": "string o null",
        "ingresos_mensuales": number o null
    },
    "codeudor": {
        "existe": boolean,
        "nombre_completo": "string o null",
        "tipo_documento": "CC/CE/NIT/PA o null",
        "numero_documento": "string o null",
        "fecha_expedicion": "YYYY-MM-DD o null",
        "lugar_expedicion": "string o null",
        "direccion": "string o null",
        "ciudad": "string o null",
        "departamento": "string o null",
        "telefono": "string o null",
        "celular": "string o null",
        "email": "string o null",
        "relacion_deudor": "string o null"
    },
    "referencias": [
        {
            "nombre": "string",
            "telefono": "string",
            "relacion": "string"
        }
    ],
    "solicitudes_vinculacion": {
        "detectadas": boolean,
        "paginas": [number] 
    }
}

Analiza los documentos y extrae toda la información disponible:"""

