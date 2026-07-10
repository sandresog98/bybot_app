from __future__ import annotations

import json
import logging
import re
from pathlib import Path

logger = logging.getLogger(__name__)

EXTRACTION_CONFIG = {
    "eps_afiliado": {
        "patterns": [
            r"EPS[:\s]+([^\n<]{3,80})",
            r"Entidad\s*Promotora[^:]*:[:\s]+([^\n<]{3,80})",
        ],
    },
    "regimen": {
        "patterns": [
            r"[Rr][eé]gimen[:\s]+([^\n<]{2,40})",
        ],
    },
    "estado_afiliacion": {
        "patterns": [
            r"Estado\s+de\s+[Aa]filiaci[oó]n[:\s]+([^\n<]{2,40})",
            r"Estado[:\s]+([Aa]ctivo|[Ii]nactivo|[Rr]etirado|[Ss]uspendido)",
        ],
    },
    "fecha_afiliacion_eps": {
        "patterns": [
            r"Fecha\s+de\s+[Aa]filiaci[oó]n[:\s]+(\d{1,2}[/-]\d{1,2}[/-]\d{2,4})",
        ],
    },
    "novedad": {
        "patterns": [
            r"[Nn]ovedad[:\s]+([^\n<]{3,200})",
        ],
    },
}


def _normalizar(texto: str) -> str:
    import unicodedata
    t = texto.strip()
    t = unicodedata.normalize("NFKD", t)
    t = "".join(c for c in t if not unicodedata.combining(c))
    return t


def extraer_datos(html_file: Path) -> dict[str, str]:
    try:
        from bs4 import BeautifulSoup
    except ImportError:
        logger.warning("BeautifulSoup no instalado.")
        return {}

    html_content = html_file.read_text(encoding="utf-8", errors="replace")
    soup = BeautifulSoup(html_content, "lxml")

    for tag in soup(["style", "script"]):
        tag.decompose()

    texto_visible = soup.get_text(" ", strip=True)
    texto_visible = _normalizar(texto_visible)

    resultado: dict[str, str] = {}
    no_extraidos: dict[str, str] = {}

    for campo, config in EXTRACTION_CONFIG.items():
        encontrado = False
        for pat in config.get("patterns", []):
            match = re.search(pat, texto_visible, re.IGNORECASE)
            if match:
                grupos = match.groups()
                if grupos:
                    resultado[campo] = grupos[0].strip()
                    encontrado = True
                    break
        if not encontrado:
            no_extraidos[campo] = ""

    if no_extraidos:
        resultado["metadata_json"] = json.dumps(
            {
                "campos_no_extraidos": no_extraidos,
                "nota": "RUAF ReportViewer renderiza datos como imagen. "
                        "Usar Gemini Vision con resolver_captcha_ocr() sobre "
                        "la imagen del reporte para extraer datos con precision.",
            },
            ensure_ascii=False,
        )

    logger.info("RUAF: %s campos extraidos, %s pendientes (requiere OCR/Gemini sobre imagen)",
                len(resultado) - (1 if "metadata_json" in resultado else 0),
                len(no_extraidos))
    return resultado
