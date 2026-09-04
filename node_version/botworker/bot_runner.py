#!/usr/bin/env python3
"""
bot_runner.py — Daemon que procesa la cola de consultas de bots (bybot:consultar).

Hace polling de app_colas_trabajos cada COLA_POLL_INTERVAL segundos,
reclama trabajos pendientes de la cola 'bybot:consultar' y ejecuta
el bot correspondiente (fosiga, ruaf, rues) con los datos del proceso.

Uso:
    python3 bot_runner.py
"""
import importlib
import json
import logging
import socket
import sys
import time
from datetime import datetime
from pathlib import Path

_ROOT = str(Path(__file__).resolve().parent.parent)
if _ROOT not in sys.path:
    sys.path.insert(0, _ROOT)
_BOTS_DIR = str(Path(_ROOT) / 'bots')
if _BOTS_DIR not in sys.path:
    sys.path.insert(0, _BOTS_DIR)

from shared.config import COLA_POLL_INTERVAL
from shared import utils

logger = logging.getLogger("bot_runner")
WORKER_ID = f"bot_runner-{socket.gethostname()}-{int(time.time())}"

# Mapa: nombre_bot -> (modulo_service, tabla_db)
BOT_REGISTRY = {
    "fosiga": ("bots.fosiga.service", "fosiga_consultas"),
    "ruaf": ("bots.ruaf.service", "ruaf_consultas"),
    "rues": ("bots.rues.service", "rues_consultas"),
    "simpleco": ("bots.simpleco.service", "simpleco_consultas"),
}


def claim_next(conn):
    cur = utils.dict_cursor(conn)
    try:
        conn.start_transaction()
        cur.execute(
            "SELECT * FROM app_colas_trabajos "
            "WHERE cola = %s AND estado = 'pendiente' "
            "ORDER BY prioridad ASC, created_at ASC LIMIT 1 FOR UPDATE",
            ("bybot:consultar",),
        )
        job = cur.fetchone()
        if not job:
            conn.rollback()
            return None
        cur.execute(
            "UPDATE app_colas_trabajos SET estado = 'procesando', started_at = NOW(), worker_id = %s WHERE id = %s",
            (WORKER_ID, job["id"]),
        )
        conn.commit()
        return job
    except Exception:
        conn.rollback()
        raise
    finally:
        cur.close()


def mark_complete(conn, job_id: int, resultado: dict):
    cur = conn.cursor()
    cur.execute(
        "UPDATE app_colas_trabajos SET estado = 'completado', resultado = %s, finished_at = NOW(), "
        "duracion_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 WHERE id = %s",
        (json.dumps(resultado, ensure_ascii=False), job_id),
    )
    conn.commit()
    cur.close()


def mark_failed(conn, job_id: int, error: str, max_intentos: int, intentos_actuales: int):
    nuevo_estado = "pendiente" if intentos_actuales < max_intentos else "fallido"
    cur = conn.cursor()
    cur.execute(
        "UPDATE app_colas_trabajos SET estado = %s, error_mensaje = %s, intentos = %s, finished_at = NOW() WHERE id = %s",
        (nuevo_estado, error, intentos_actuales, job_id),
    )
    conn.commit()
    cur.close()


def actualizar_progreso(consulta_id: int, estado: str, mensaje: str, conn=None):
    """Actualiza procesos_consultas y error_mensaje del job."""
    cerrar = conn is None
    if cerrar:
        conn = utils.get_db()
    try:
        cur = conn.cursor()
        cur.execute(
            "UPDATE procesos_consultas SET estado = %s, resultado_resumen = %s, updated_at = NOW() WHERE id = %s",
            (estado, json.dumps({"mensaje": mensaje}) if mensaje else None, consulta_id),
        )
        conn.commit()
        cur.close()
    finally:
        if cerrar:
            conn.close()


def _mapear_estado(bot_estado: str) -> str:
    """Traduce el estado devuelto por un bot a un estado canónico de procesos_consultas."""
    e = (bot_estado or "").upper()
    if e == "EXITOSA":
        return "exitoso"
    if e in ("SIN_PAGOS_6_MESES", "SIN_RESULTADO"):
        return "sin_pagos"
    # ERROR_SEGURIDAD, ERROR_PREGUNTAS_SEGURIDAD, ERROR_SIN_BOTON, ERROR, ERROR_BOT, ...
    return "fallido"


def vincular_consulta(conn, consulta_id: int, tabla: str, row_id: int, resultado: dict):
    """Vincula la consulta del proceso con la fila insertada en la tabla del bot."""
    cur = conn.cursor()
    cur.execute(
        "UPDATE procesos_consultas SET consulta_tabla = %s, consulta_id = %s, estado = %s, "
        "resultado_resumen = %s, updated_at = NOW() WHERE id = %s",
        (tabla, row_id, _mapear_estado(str(resultado.get("estado", ""))),
         json.dumps(resultado, ensure_ascii=False), consulta_id),
    )
    conn.commit()
    cur.close()


def obtener_ultimo_id(conn, tabla: str, numero_id: str) -> int | None:
    """Obtiene el último id insertado en la tabla del bot para un numero_id."""
    cur = utils.dict_cursor(conn)
    cur.execute(
        f"SELECT id FROM {tabla} WHERE numero_id = %s ORDER BY id DESC LIMIT 1",
        (numero_id,),
    )
    row = cur.fetchone()
    cur.close()
    return row["id"] if row else None


def ejecutar_bot(bot_name: str, numero_id: str, persona_tipo: str, consulta_id: int) -> dict:
    """Ejecuta el bot correspondiente y retorna resultado + id de la fila insertada."""
    entry = BOT_REGISTRY.get(bot_name)
    if not entry:
        raise ValueError(f"Bot desconocido: {bot_name}")

    mod_path, tabla_db = entry
    mod = importlib.import_module(mod_path)

    # Cada bot expone run_{nombre}_bot()
    func_name = f"run_{bot_name}_bot"
    run_func = getattr(mod, func_name, None)
    if not run_func:
        raise ValueError(f"El módulo {mod_path} no expone {func_name}")

    kwargs = {"verbose": False, "headless": True}

    if bot_name == "fosiga":
        kwargs["numero_documento"] = numero_id
    elif bot_name == "rues":
        kwargs["numero_busqueda"] = numero_id
    elif bot_name == "simpleco":
        kwargs["numero_documento"] = numero_id
    else:
        kwargs["numero_id"] = numero_id

    if bot_name == "ruaf":
        hoy = datetime.now().strftime("%d/%m/%Y")
        kwargs["fecha"] = hoy
        kwargs["tipo_doc"] = "CEDULA DE CIUDADANIA"

    logger.info(f"Ejecutando {bot_name} para {persona_tipo} ({numero_id})…")
    actualizar_progreso(consulta_id, "procesando", f"Ejecutando {bot_name}…")

    resultado = run_func(**kwargs)

    # Obtener el id insertado en la tabla del bot
    conn = utils.get_db()
    try:
        row_id = obtener_ultimo_id(conn, tabla_db, numero_id)
        if row_id:
            vincular_consulta(conn, consulta_id, tabla_db, row_id, {
                "estado": resultado.get("estado", ""),
                "motivo": resultado.get("motivo", ""),
                "archivo": resultado.get("archivo_html") or resultado.get("archivo_pdf", ""),
            })
            logger.info(f"{bot_name} para {numero_id}: {resultado.get('estado')} (id={row_id})")
        else:
            logger.warning(f"{bot_name} para {numero_id}: no se encontró fila insertada")
            actualizar_progreso(consulta_id, _mapear_estado(str(resultado.get("estado", ""))), resultado.get("motivo", ""), conn)
    finally:
        conn.close()

    return resultado


def process_job(job):
    payload = job["payload"]
    if isinstance(payload, str):
        payload = json.loads(payload)

    consulta_id = payload["consulta_id"]
    proceso_id = payload["proceso_id"]
    persona_tipo = payload["persona_tipo"]
    bot_name = payload["bot"]
    numero_id = payload["numero_id"]
    intentos = job["intentos"] + 1
    max_intentos = job["max_intentos"]

    logger.info(f"Procesando job {job['job_id']}: {bot_name} para {persona_tipo} ({numero_id})")

    conn = utils.get_db()
    try:
        cur = conn.cursor()
        cur.execute("UPDATE app_colas_trabajos SET intentos = %s WHERE id = %s", (intentos, job["id"]))
        conn.commit()
        cur.close()

        actualizar_progreso(consulta_id, "procesando", "Iniciando…", conn)

        resultado = ejecutar_bot(bot_name, numero_id, persona_tipo, consulta_id)
        mark_complete(conn, job["id"], {
            "bot": bot_name,
            "estado": resultado.get("estado", ""),
            "motivo": resultado.get("motivo", ""),
        })
        logger.info(f"Job {job['job_id']} completado: {resultado.get('estado')}")
    except Exception as e:
        logger.error(f"Job {job['job_id']} falló: {e}")
        try:
            actualizar_progreso(consulta_id, "fallido", f"Error: {e}", conn)
        except Exception:
            pass
        mark_failed(conn, job["id"], str(e), max_intentos, intentos)
        # No cambiamos estado del proceso
    finally:
        conn.close()


def main():
    logger.info(f"Bot Runner iniciado. worker_id={WORKER_ID} poll_interval={COLA_POLL_INTERVAL}s")
    while True:
        try:
            conn = utils.get_db()
            job = claim_next(conn)
            conn.close()
            if job:
                process_job(job)
            else:
                time.sleep(COLA_POLL_INTERVAL)
        except KeyboardInterrupt:
            logger.info("Bot Runner detenido por usuario")
            sys.exit(0)
        except Exception as e:
            logger.error(f"Error en loop principal: {e}")
            time.sleep(COLA_POLL_INTERVAL)


if __name__ == "__main__":
    main()
