"""
Utilidades compartidas para botworker: conexión MySQL, logging, helpers.
"""
import json
import logging
import mysql.connector
from pathlib import Path
from typing import Any, Optional

from shared.config import DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS

LOG_FORMAT = "%(asctime)s - %(name)s - %(levelname)s - %(message)s"
logging.basicConfig(level=logging.INFO, format=LOG_FORMAT)
logger = logging.getLogger("botworker")


def get_db():
    """Conexión a MariaDB/MySQL. Llamar en un with o cerrar manualmente."""
    return mysql.connector.connect(
        host=DB_HOST, port=DB_PORT, database=DB_NAME,
        user=DB_USER, password=DB_PASS, charset="utf8mb4",
        autocommit=False,
    )


def dict_cursor(conn):
    return conn.cursor(dictionary=True)


def get_prompts_activos(conn) -> dict[str, str]:
    """Retorna {tipo: contenido} de los prompts activos."""
    cur = dict_cursor(conn)
    cur.execute("SELECT tipo, contenido FROM app_prompts WHERE activo = 1")
    rows = cur.fetchall()
    cur.close()
    return {r["tipo"]: r["contenido"] for r in rows}


def get_proceso_archivos(conn, proceso_id: int) -> list[dict]:
    """Retorna la lista de archivos del proceso."""
    cur = dict_cursor(conn)
    cur.execute(
        "SELECT id, nombre_original, nombre_archivo, ruta_storage, tipo, mime_type "
        "FROM procesos_archivos WHERE proceso_id = %s ORDER BY orden ASC",
        (proceso_id,),
    )
    rows = cur.fetchall()
    cur.close()
    return rows


def get_proceso(conn, proceso_id: int) -> Optional[dict]:
    cur = dict_cursor(conn)
    cur.execute("SELECT * FROM procesos WHERE id = %s", (proceso_id,))
    row = cur.fetchone()
    cur.close()
    return row


def insert_datos_ia(conn, proceso_id: int, datos: dict, modelo: str, tokens_total: int, metadata: dict) -> int:
    """Inserta una fila en procesos_datos_ia. Devuelve el id."""
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO procesos_datos_ia (proceso_id, version, datos_originales, metadata, modelo, tokens_total) "
        "VALUES (%s, 1, %s, %s, %s, %s)",
        (proceso_id, json.dumps(datos, ensure_ascii=False), json.dumps(metadata, ensure_ascii=False), modelo, tokens_total),
    )
    conn.commit()
    last_id = cur.lastrowid
    cur.close()
    return last_id


def update_proceso_estado(conn, proceso_id: int, estado: str, campo_fecha: str | None = None):
    """Actualiza estado del proceso y opcionalmente un campo de fecha."""
    if campo_fecha:
        sql = f"UPDATE procesos SET estado = %s, {campo_fecha} = NOW() WHERE id = %s"
    else:
        sql = "UPDATE procesos SET estado = %s WHERE id = %s"
    cur = conn.cursor()
    cur.execute(sql, (estado, proceso_id))
    conn.commit()
    cur.close()


def insert_historial(conn, proceso_id: int, usuario_id: int | None, accion: str,
                     estado_anterior: str | None, estado_nuevo: str | None, descripcion: str):
    cur = conn.cursor()
    cur.execute(
        "INSERT INTO procesos_historial (proceso_id, usuario_id, accion, estado_anterior, estado_nuevo, descripcion) "
        "VALUES (%s, %s, %s, %s, %s, %s)",
        (proceso_id, usuario_id, accion, estado_anterior, estado_nuevo, descripcion),
    )
    conn.commit()
    cur.close()


def reportar_progreso(job_id: int, mensaje: str):
    """Actualiza error_mensaje de un trabajo con la fase actual del análisis."""
    conn = get_db()
    cur = conn.cursor()
    cur.execute(
        "UPDATE app_colas_trabajos SET error_mensaje = %s WHERE id = %s",
        (mensaje, job_id),
    )
    conn.commit()
    cur.close()
    conn.close()


def get_uploads_path(ruta_storage: str) -> Path:
    """Construye path absoluto de un archivo en uploads/."""
    from shared.config import UPLOADS_DIR
    return UPLOADS_DIR / ruta_storage