from __future__ import annotations

import json
import logging
import unicodedata
from pathlib import Path

logger = logging.getLogger(__name__)

EXTRACTION_CONFIG = {
    "tipo_identificacion": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "TIPO DE IDENTIFICACION",
    },
    "numero_identificacion": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "NUMERO DE IDENTIFICACION",
    },
    "nombres": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "NOMBRES",
    },
    "apellidos": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "APELLIDOS",
    },
    "fecha_nacimiento": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "FECHA DE NACIMIENTO",
    },
    "departamento": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "DEPARTAMENTO",
    },
    "municipio": {
        "source": "html_table",
        "table_selector": "#GridViewBasica",
        "key_col": 0,
        "value_col": 1,
        "key_text": "MUNICIPIO",
    },
    "estado": {
        "source": "html_table",
        "table_selector": "#GridViewAfiliacion",
        "key_col": 0,
        "value_col": 0,
        "key_text": None,
        "header": "ESTADO",
    },
    "entidad": {
        "source": "html_table",
        "table_selector": "#GridViewAfiliacion",
        "header": "ENTIDAD",
    },
    "regimen": {
        "source": "html_table",
        "table_selector": "#GridViewAfiliacion",
        "header": "REGIMEN",
    },
    "fecha_afiliacion_efectiva": {
        "source": "html_table",
        "table_selector": "#GridViewAfiliacion",
        "header": "FECHA DE AFILIACION",
    },
    "fecha_finalizacion_afiliacion": {
        "source": "html_table",
        "table_selector": "#GridViewAfiliacion",
        "header": "FECHA DE FINALIZACION",
    },
    "tipo_afiliado": {
        "source": "html_table",
        "table_selector": "#GridViewAfiliacion",
        "header": "TIPO DE AFILIADO",
    },
}


def _normalizar_clave(texto: str) -> str:
    t = texto.upper().strip()
    t = unicodedata.normalize("NFKD", t)
    t = "".join(c for c in t if not unicodedata.combining(c))
    return " ".join(t.split())


def _extraer_de_tabla_keyvalue(soup, table_selector: str, key_text: str, key_col: int, value_col: int) -> str | None:
    table = soup.select_one(table_selector)
    if not table:
        return None
    key_norm = _normalizar_clave(key_text)
    for row in table.select("tr"):
        cells = row.find_all(["td", "th"])
        if len(cells) <= max(key_col, value_col):
            continue
        cell_key = _normalizar_clave(cells[key_col].get_text())
        if key_norm in cell_key or cell_key in key_norm:
            return cells[value_col].get_text(strip=True)
    return None


def _extraer_de_tabla_header(soup, table_selector: str, header_text: str) -> str | None:
    table = soup.select_one(table_selector)
    if not table:
        return None
    headers = table.select("tr th")
    col_idx = None
    for i, th in enumerate(headers):
        if _normalizar_clave(header_text) in _normalizar_clave(th.get_text()):
            col_idx = i
            break
    if col_idx is None:
        return None
    data_rows = table.select("tr")[1:]
    for row in data_rows:
        cells = row.find_all("td")
        if len(cells) > col_idx:
            return cells[col_idx].get_text(strip=True)
    return None


def extraer_datos(html_file: Path) -> dict[str, str]:
    try:
        from bs4 import BeautifulSoup
    except ImportError:
        logger.warning("BeautifulSoup no instalado. No se puede extraer datos del HTML.")
        return {}

    html_content = html_file.read_text(encoding="utf-8", errors="replace")
    soup = BeautifulSoup(html_content, "lxml")

    resultado: dict[str, str] = {}
    extra: dict[str, str] = {}

    for campo, config in EXTRACTION_CONFIG.items():
        source = config.get("source", "html_table")
        if source == "html_table":
            table_sel = config.get("table_selector", "")
            if config.get("key_text"):
                val = _extraer_de_tabla_keyvalue(
                    soup, table_sel,
                    config["key_text"],
                    config.get("key_col", 0),
                    config.get("value_col", 1),
                )
            else:
                val = _extraer_de_tabla_header(soup, table_sel, config.get("header", ""))
            if val:
                resultado[campo] = val
            else:
                extra[campo] = ""

        elif source == "css_selector":
            for sel in config.get("selectors", []):
                el = soup.select_one(sel)
                if el:
                    resultado[campo] = el.get_text(strip=True)
                    break

    if extra:
        resultado["metadata_json"] = json.dumps({"campos_no_extraidos": extra}, ensure_ascii=False)

    return resultado
