from __future__ import annotations

import json
import logging
import re
from pathlib import Path

logger = logging.getLogger(__name__)

EXTRACTION_CONFIG = {
    "identificacion": {"etiqueta": "Identificación"},
    "razon_social": {"etiqueta": "Razón Social"},
    "nit": {"etiqueta": "NIT"},
    "numero_inscripcion": {"etiqueta": "Numero de Inscripción"},
    "categoria": {"etiqueta": "Categoria"},
    "camara_comercio": {"etiqueta": "Cámara de Comercio"},
    "matricula_mercantil": {"etiqueta": "Número de Matrícula"},
    "estado_matricula": {"etiqueta": "Estado"},
    "direccion": {"etiqueta": "Dirección"},
    "municipio": {"etiqueta": "Municipio"},
    "departamento": {"etiqueta": "Departamento"},
    "fecha_renovacion": {"etiqueta": "Fecha de Renovación"},
    "categoria_matricula": {"etiqueta": "Categoria"},
}


def _normalizar(texto: str) -> str:
    import unicodedata
    t = texto.upper().strip()
    t = unicodedata.normalize("NFKD", t)
    t = "".join(c for c in t if not unicodedata.combining(c))
    return " ".join(t.split())


def extraer_datos(html_file: Path) -> dict[str, str]:
    try:
        from bs4 import BeautifulSoup
    except ImportError:
        logger.warning("BeautifulSoup no instalado.")
        return {}

    html_content = html_file.read_text(encoding="utf-8", errors="replace")
    soup = BeautifulSoup(html_content, "lxml")

    resultado: dict[str, str] = {}
    no_extraidos: dict[str, str] = {}

    registros = soup.select("div.registroapi")
    etiqueta_a_valor: dict[str, str] = {}

    for registro in registros:
        etiqueta_el = registro.select_one("p.registroapi__etiqueta")
        if not etiqueta_el:
            continue
        etiqueta = _normalizar(etiqueta_el.get_text())
        valor_el = registro.select_one("p.registroapi__valor")
        if valor_el:
            valor = valor_el.get_text(strip=True)
        else:
            texto_completo = registro.get_text(" ", strip=True)
            valor = texto_completo.replace(etiqueta_el.get_text(strip=True), "").strip()
        etiqueta_a_valor[etiqueta] = valor

    for campo, config in EXTRACTION_CONFIG.items():
        etiqueta_esperada = _normalizar(config["etiqueta"])
        if etiqueta_esperada in etiqueta_a_valor and etiqueta_a_valor[etiqueta_esperada]:
            resultado[campo] = etiqueta_a_valor[etiqueta_esperada]
        else:
            no_extraidos[campo] = ""

    if no_extraidos:
        resultado["metadata_json"] = json.dumps({"campos_no_extraidos": no_extraidos}, ensure_ascii=False)

    logger.info("RUES: %s campos extraidos, %s pendientes", len(resultado) - (1 if "metadata_json" in resultado else 0), len(no_extraidos))
    return resultado
