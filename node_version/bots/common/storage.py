from __future__ import annotations

import csv
import json
import logging
import os
from datetime import datetime
from pathlib import Path
from typing import Any

from common.timezone_utils import ZONA_BOGOTA

logger = logging.getLogger(__name__)

CAMPOS_BASE = [
    "fecha_hora",
    "numero_id",
    "estado",
    "motivo",
    "archivo_original",
]

DB_CONFIG = {
    "host": os.environ.get("BYBOT_DB_HOST", "127.0.0.1"),
    "port": int(os.environ.get("BYBOT_DB_PORT", "3306")),
    "user": os.environ.get("BYBOT_DB_USER", "root"),
    "password": os.environ.get("BYBOT_DB_PASSWORD", ""),
    "database": os.environ.get("BYBOT_DB_NAME", "bybot_consolidado"),
    "charset": "utf8mb4",
    "autocommit": True,
    "connect_timeout": 5,
}

_db_disponible: bool | None = None


def _sanear_motivo(texto: str, max_chars: int = 220) -> str:
    if not texto:
        return ""
    s = texto.strip()
    for marcador in ("Call log:", "Traceback (most recent call last):"):
        if marcador in s:
            s = s.split(marcador, 1)[0].strip()
    s = " ".join(s.split())
    if len(s) > max_chars:
        s = s[: max_chars - 3].rstrip() + "..."
    return s


def _verificar_db() -> bool:
    global _db_disponible
    if _db_disponible is not None:
        return _db_disponible
    try:
        import mysql.connector
        conn = mysql.connector.connect(**DB_CONFIG)
        conn.close()
        _db_disponible = True
        logger.debug("MySQL disponible en %s:%s", DB_CONFIG["host"], DB_CONFIG["port"])
        return True
    except Exception as e:
        _db_disponible = False
        logger.debug("MySQL no disponible: %s. Se usara CSV como fallback.", e)
        return False


def _guardar_db(
    tabla: str,
    *,
    numero_id: str,
    estado: str,
    motivo: str,
    archivo_original: str = "",
    campos_extra: dict[str, Any] | None = None,
) -> bool:
    try:
        import mysql.connector
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor()
    except Exception as e:
        logger.debug("Insercion MySQL fallida: %s", e)
        return False

    now = datetime.now(ZONA_BOGOTA).strftime("%Y-%m-%d %H:%M:%S")
    columnas = ["numero_id", "fecha_consulta", "estado", "motivo", "archivo_original"]
    valores: list[Any] = [numero_id, now, estado, motivo, archivo_original]
    placeholders = ["%s", "%s", "%s", "%s", "%s"]

    metadata: dict[str, Any] = {}
    if campos_extra:
        for k, v in campos_extra.items():
            if k not in columnas and k != "metadata_json":
                columnas.append(k)
                valores.append(v)
                placeholders.append("%s")
            else:
                metadata[k] = v

    if metadata:
        columnas.append("metadata_json")
        valores.append(json.dumps(metadata, ensure_ascii=False))
        placeholders.append("%s")

    sql = f"INSERT INTO {tabla} ({', '.join(columnas)}) VALUES ({', '.join(placeholders)})"
    try:
        cursor.execute(sql, valores)
        conn.commit()
        cursor.close()
        conn.close()
        return True
    except Exception as e:
        logger.debug("Error SQL en %s: %s", tabla, e)
        cursor.close()
        conn.close()
        return False


def _guardar_csv(
    ruta_csv: Path,
    *,
    numero_id: str,
    estado: str,
    motivo: str,
    archivo_original: str = "",
    campos_extra: dict[str, Any] | None = None,
) -> None:
    ruta_csv.parent.mkdir(parents=True, exist_ok=True)

    encabezados = list(CAMPOS_BASE)
    extras_planos: dict[str, str] = {}
    if campos_extra:
        for k, v in campos_extra.items():
            if k not in encabezados:
                encabezados.append(k)
            extras_planos[k] = str(v) if not isinstance(v, str) else v

    fila = {
        "fecha_hora": datetime.now(ZONA_BOGOTA).strftime("%Y-%m-%d %H:%M:%S"),
        "numero_id": numero_id,
        "estado": estado,
        "motivo": _sanear_motivo(motivo),
        "archivo_original": (archivo_original or "").strip(),
    }
    fila.update(extras_planos)

    existe = ruta_csv.exists() and ruta_csv.stat().st_size > 0
    with ruta_csv.open("a", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=encabezados)
        if not existe:
            w.writeheader()
        w.writerow(fila)


def registrar_consulta(
    *,
    tabla_db: str = "",
    csv_path: Path | None = None,
    numero_id: str,
    estado: str,
    motivo: str,
    archivo_original: str = "",
    campos_extra: dict[str, Any] | None = None,
) -> None:
    guardado_db = False
    if tabla_db and _verificar_db():
        guardado_db = _guardar_db(
            tabla_db,
            numero_id=numero_id,
            estado=estado,
            motivo=motivo,
            archivo_original=archivo_original,
            campos_extra=campos_extra,
        )

    if csv_path is not None:
        _guardar_csv(
            csv_path,
            numero_id=numero_id,
            estado=estado,
            motivo=motivo,
            archivo_original=archivo_original,
            campos_extra=campos_extra,
        )

    if not guardado_db and csv_path is None:
        logger.warning(
            "Sin DB ni CSV configurado. Consulta %s (%s) no persistida.",
            numero_id, estado,
        )
