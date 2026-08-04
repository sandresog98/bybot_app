#!/usr/bin/env python3
"""
daemon.py — Daemon que procesa la cola de trabajos de análisis.

Hace polling de app_colas_trabajos cada COLA_POLL_INTERVAL segundos,
reclama trabajos pendientes de la cola 'bybot:analizar' y ejecuta
analizador.run(proceso_id) en el mismo proceso Python.

Uso:
    python3 daemon.py
"""
import json
import logging
import socket
import sys
import time
from datetime import datetime

from shared.config import COLA_POLL_INTERVAL
from shared import utils

logger = logging.getLogger("daemon")
WORKER_ID = f"daemon-{socket.gethostname()}-{int(time.time())}"


def claim_next(conn):
    """Reclama atómicamente el siguiente trabajo pendiente. Retorna dict o None."""
    cur = utils.dict_cursor(conn)
    try:
        conn.start_transaction()
        cur.execute(
            "SELECT * FROM app_colas_trabajos "
            "WHERE cola = %s AND estado = 'pendiente' "
            "ORDER BY prioridad ASC, created_at ASC LIMIT 1 FOR UPDATE",
            ("bybot:analizar",),
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


def process_job(job):
    """Ejecuta el analizador para el trabajo reclamado."""
    proceso_id = job["proceso_id"]
    job_id = job["id"]
    intentos = job["intentos"] + 1
    max_intentos = job["max_intentos"]

    logger.info(f"Procesando job {job['job_id']} proceso_id={proceso_id} intento={intentos}")

    conn = utils.get_db()
    try:
        # Actualizar intentos
        cur = conn.cursor()
        cur.execute("UPDATE app_colas_trabajos SET intentos = %s WHERE id = %s", (intentos, job_id))
        conn.commit()
        cur.close()

        # Progreso inicial
        utils.reportar_progreso(job_id, "Iniciando análisis…")

        # Ejecutar analizador (import diferido para no cargar Gemini si no hay jobs)
        import analizador

        resultado = analizador.run(proceso_id, job_id)
        mark_complete(conn, job_id, resultado)
        logger.info(f"Job {job_id} completado OK")
    except Exception as e:
        logger.error(f"Job {job_id} falló: {e}")
        utils.reportar_progreso(job_id, f"Error: {e}")
        mark_failed(conn, job_id, str(e), max_intentos, intentos)
        cur = conn.cursor()
        cur.execute("UPDATE procesos SET estado = 'error' WHERE id = %s AND estado = 'en_analisis'", (proceso_id,))
        conn.commit()
        cur.close()
    finally:
        conn.close()


def main():
    logger.info(f"Daemon iniciado. worker_id={WORKER_ID} poll_interval={COLA_POLL_INTERVAL}s")
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
            logger.info("Daemon detenido por usuario")
            sys.exit(0)
        except Exception as e:
            logger.error(f"Error en loop principal: {e}")
            time.sleep(COLA_POLL_INTERVAL)


if __name__ == "__main__":
    main()