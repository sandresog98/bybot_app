from __future__ import annotations

import logging
from pathlib import Path

logger = logging.getLogger(__name__)

EXTRACTION_CONFIG = {
    "sections": ["empleado", "aportante", "administradoras"],
}


def extraer_datos(pdf_file: Path) -> dict[str, str]:
    try:
        import pdfplumber
    except ImportError:
        logger.warning("pdfplumber no instalado. No se puede extraer datos del PDF.")
        return {}

    resultado: dict[str, str] = {}

    try:
        with pdfplumber.open(pdf_file) as pdf:
            texto_completo = ""
            tablas = []
            for page in pdf.pages:
                texto_completo += (page.extract_text() or "") + "\n"
                tablas.extend(page.extract_tables() or [])

        resultado["texto_extraido"] = texto_completo.strip()
        resultado["tablas_encontradas"] = str(len(tablas))

        logger.info(
            "SuAporte PDF: %s chars de texto, %s tablas. "
            "Requiere ejecutar pruebas con PDF real para definir campos especificos.",
            len(texto_completo),
            len(tablas),
        )
    except Exception as e:
        logger.warning("Error al leer PDF de SuAporte: %s", e)
        return {"error": str(e)}

    return resultado
