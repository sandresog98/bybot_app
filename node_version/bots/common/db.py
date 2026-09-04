from __future__ import annotations

import json
import logging
import os
from datetime import datetime
from pathlib import Path
from typing import Any

from common.timezone_utils import ZONA_BOGOTA

logger = logging.getLogger(__name__)

# Cargar el .env del monorepo (raíz node_version/) para credenciales de BD.
# bots/common/db.py -> common -> bots -> node_version (3 niveles arriba)
try:
    from dotenv import load_dotenv
    _ROOT = Path(__file__).resolve().parent.parent.parent
    load_dotenv(_ROOT / ".env")
except Exception:
    pass

DB_CONFIG = {
    "host": os.getenv("DB_HOST", "127.0.0.1"),
    "port": int(os.getenv("DB_PORT", "3306")),
    "user": os.getenv("DB_USER", "root"),
    "password": os.getenv("DB_PASS", ""),
    "database": os.getenv("DB_NAME", "bybot_consolidado"),
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
