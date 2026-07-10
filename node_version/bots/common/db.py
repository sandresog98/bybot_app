from __future__ import annotations

import json
import logging
from datetime import datetime
from typing import Any

from common.timezone_utils import ZONA_BOGOTA

logger = logging.getLogger(__name__)

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "bybot_consolidado",
    "charset": "utf8mb4",
    "autocommit": True,
}


def get_connection():
    try:
        import mysql.connector
    except ImportError:
        raise RuntimeError("mysql-connector-python no esta instalado. Ejecuta: pip install mysql-connector-python")

    return mysql.connector.connect(**DB_CONFIG)


def insert_consulta(
    tabla: str,
    *,
    numero_id: str,
    estado: str,
    motivo: str,
    archivo_original: str = "",
    campos_extra: dict[str, Any] | None = None,
) -> int | None:
    try:
        conn = get_connection()
        cursor = conn.cursor()
    except Exception as e:
        logger.warning("No se pudo conectar a MySQL: %s. Se omite insercion en BD.", e)
        return None

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
        last_id = cursor.lastrowid
        cursor.close()
        conn.close()
        return last_id
    except Exception as e:
        logger.warning("Error al insertar en %s: %s", tabla, e)
        cursor.close()
        conn.close()
        return None
