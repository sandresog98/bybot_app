from __future__ import annotations

import base64
import json
import logging
import os
import re
from pathlib import Path
from typing import Any

try:
    from dotenv import load_dotenv
    _ENV_FILE = Path(__file__).resolve().parent.parent / ".env"
    if _ENV_FILE.exists():
        load_dotenv(_ENV_FILE)
except ImportError:
    pass

logger = logging.getLogger(__name__)

GEMINI_API_KEY = os.environ.get("GEMINI_API_KEY", "")
GEMINI_MODEL = os.environ.get("BYBOT_GEMINI_MODEL", "gemini-2.5-flash")

_VALIDACIONES_POR_CAMPO: dict[str, str] = {
    "nit": r"^\d{8,11}$",
    "cedula": r"^\d{5,12}$",
    "numero_planilla": r"^\d{5,15}$",
    "periodo_cotizacion": r"^\d{6}$",
    "periodo_servicio": r"^\d{6}$",
    "matricula_mercantil": r"^\d{6,12}$",
    "fecha_afiliacion_efectiva": r"^\d{1,2}/\d{1,2}/\d{2,4}$",
    "fecha_finalizacion_afiliacion": r"^\d{1,2}/\d{1,2}/\d{2,4}$",
    "fecha_renovacion": r"^\d{1,2}/\d{1,2}/\d{2,4}$",
    "fecha_nacimiento": r"^\d{1,2}/\d{1,2}/\d{2,4}$",
    "departamento": r"^[A-ZÑ .-]{3,40}$",
    "municipio": r"^[A-ZÑ .-]{3,40}$",
}


def _validar_campo(nombre: str, valor: str) -> bool:
    if not valor or valor == "NO_ENCONTRADO":
        return False
    if nombre in _VALIDACIONES_POR_CAMPO:
        patron = _VALIDACIONES_POR_CAMPO[nombre]
        if not re.match(patron, valor.strip()):
            logger.debug("Gemini: valor %r del campo %r no pasa validacion %r", valor, nombre, patron)
            return False
    if len(valor.strip()) < 2:
        return False
    return True


def _llamar_gemini(prompt: str, imagen_bytes: bytes | None = None) -> str | None:
    if not GEMINI_API_KEY:
        logger.warning("GEMINI_API_KEY no configurada. Omitiendo llamada a Gemini.")
        return None

    try:
        import google.generativeai as genai
    except ImportError:
        logger.warning("google-generativeai no instalado. Ejecuta: pip install google-generativeai")
        return None

    try:
        genai.configure(api_key=GEMINI_API_KEY)
        model = genai.GenerativeModel(GEMINI_MODEL)

        if imagen_bytes:
            imagen_b64 = base64.b64encode(imagen_bytes).decode("utf-8")
            parts = [
                {"inline_data": {"mime_type": "image/png", "data": imagen_b64}},
                {"text": prompt},
            ]
            response = model.generate_content(parts)
        else:
            response = model.generate_content(prompt)

        if response and response.text:
            return response.text.strip()
        return None
    except Exception as e:
        logger.warning("Error llamando a Gemini: %s", e)
        return None


def resolver_captcha_ocr(imagen_bytes: bytes, num_caracteres: int = 5) -> str | None:
    if not GEMINI_API_KEY:
        return None

    prompt = (
        f"Esta imagen contiene un codigo de verificacion (captcha) de exactamente {num_caracteres} "
        f"caracteres alfanumericos en mayusculas (letras A-Z y numeros 0-9). "
        f"Devuelve UNICAMENTE los {num_caracteres} caracteres, sin espacios, sin explicacion, "
        f"sin ningun otro texto. Solo los {num_caracteres} caracteres."
    )

    texto = _llamar_gemini(prompt, imagen_bytes=imagen_bytes)
    if not texto:
        return None

    limpio = re.sub(r"[^A-Z0-9]", "", texto.upper())
    if len(limpio) == num_caracteres:
        logger.info("Gemini resolvio captcha: %s", limpio)
        return limpio

    if len(limpio) > num_caracteres:
        truncado = limpio[:num_caracteres]
        logger.info("Gemini devolvio %s chars, truncado a %s: %s", len(limpio), num_caracteres, truncado)
        return truncado

    logger.debug("Gemini devolvio %s chars (%r), se esperaban %s. Descartado.", len(limpio), limpio, num_caracteres)
    return None


def extraer_campo(
    texto_documento: str,
    nombre_campo: str,
    descripcion: str,
    ejemplo: str = "",
) -> tuple[str | None, str]:
    if not GEMINI_API_KEY:
        return None, "sin_api_key"

    prompt = (
        f"Del siguiente texto de documento colombiano, extrae SOLO el campo '{nombre_campo}'. "
        f"{descripcion}\n"
        f"Si no encuentras el valor, responde literalmente 'NO_ENCONTRADO'. "
        f"No inventes valores. No des explicaciones. Solo el valor o 'NO_ENCONTRADO'."
    )
    if ejemplo:
        prompt += f"\nEjemplo de valor esperado: {ejemplo}"
    prompt += f"\n\nTexto:\n{texto_documento[:6000]}"

    texto = _llamar_gemini(prompt)
    if not texto:
        return None, "error_api"

    valor = texto.strip()
    if valor.upper() == "NO_ENCONTRADO":
        return None, "no_encontrado"

    if _validar_campo(nombre_campo, valor):
        logger.info("Gemini extrajo %r: %r", nombre_campo, valor)
        return valor, "gemini"

    logger.debug("Gemini devolvio %r para %r, no pasa validacion. Descartado.", valor, nombre_campo)
    return None, "validacion_fallida"


def extraer_campos_validados(
    texto_documento: str,
    campos: list[dict[str, str]],
) -> dict[str, dict[str, Any]]:
    resultado: dict[str, dict[str, Any]] = {}
    for campo_info in campos:
        nombre = campo_info["nombre"]
        desc = campo_info.get("descripcion", "")
        ejemplo = campo_info.get("ejemplo", "")
        valor, fuente = extraer_campo(texto_documento, nombre, desc, ejemplo)
        resultado[nombre] = {
            "valor": valor,
            "fuente": fuente if valor else "no_encontrado",
        }
    return resultado


def diagnosticar_error(screenshot_bytes: bytes) -> str | None:
    if not GEMINI_API_KEY:
        return None

    prompt = (
        "Esta es una captura de pantalla de un navegador automatizado que intentaba "
        "consultar un portal web colombiano y encontro un error. "
        "Describe en UNA FRASE CORTA (maximo 120 caracteres) en espanol que salio mal. "
        "Ejemplos: 'El portal muestra sesion expirada', 'Aparecio un captcha no resuelto', "
        "'La pagina muestra error 500', 'El formulario pide un dato faltante'. "
        "Solo la frase, sin explicacion adicional."
    )

    texto = _llamar_gemini(prompt, imagen_bytes=screenshot_bytes)
    if not texto:
        return None
    return texto.strip()[:150]


def extraer_datos_reporte_imagen(
    imagen_bytes: bytes,
    campos: list[dict[str, str]],
) -> dict[str, tuple[str | None, str]]:
    if not GEMINI_API_KEY:
        return {c["nombre"]: (None, "sin_api_key") for c in campos}

    nombres = [c["nombre"] for c in campos]
    descripciones = "\n".join(
        f"  - {c['nombre']}: {c.get('descripcion', '')}"
        + (f" (ejemplo: {c['ejemplo']})" if c.get("ejemplo") else "")
        for c in campos
    )

    prompt = (
        "Esta imagen es un reporte de afiliacion del sistema RUAF/SISPRO de Colombia. "
        "Extrae UNICAMENTE los siguientes campos. Si un campo no aparece en la imagen, "
        "responde 'NO_ENCONTRADO' para ese campo. No inventes valores.\n\n"
        "Responde en formato JSON con exactamente estos campos:\n"
        f"{descripciones}\n\n"
        "Ejemplo de respuesta: "
        + json.dumps({n: "valor_ejemplo" for n in nombres}, ensure_ascii=False)
        + "\n\nResponde SOLO el JSON, sin texto adicional."
    )

    texto = _llamar_gemini(prompt, imagen_bytes=imagen_bytes)
    if not texto:
        return {c["nombre"]: (None, "error_api") for c in campos}

    texto = texto.strip()
    if texto.startswith("```"):
        texto = texto.split("```")[1]
        if texto.startswith("json"):
            texto = texto[4:]
    texto = texto.strip()

    try:
        datos = json.loads(texto)
    except json.JSONDecodeError:
        import re
        datos = {}
        for c in campos:
            match = re.search(
                rf'"{c["nombre"]}"\s*:\s*"([^"]*)"',
                texto
            )
            if match:
                datos[c["nombre"]] = match.group(1)

    resultado: dict[str, tuple[str | None, str]] = {}
    for c in campos:
        nombre = c["nombre"]
        valor = datos.get(nombre, "")
        if valor and valor.upper() != "NO_ENCONTRADO" and len(valor.strip()) > 1:
            resultado[nombre] = (valor.strip(), "gemini_vision")
        else:
            resultado[nombre] = (None, "no_encontrado")

    return resultado
