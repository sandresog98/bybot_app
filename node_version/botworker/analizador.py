#!/usr/bin/env python3
"""
analizador.py — Análisis de documentos de un proceso con Gemini AI (google-genai).

Usa inline data para archivos <20 MB (sin upload phase), sube a File API los
mayores. Reporta progreso en app_colas_trabajos.error_mensaje si se provee job_id.

Uso:
    python3 analizador.py --proceso_id 123 [--job_id 456]
"""
import argparse
import json
import logging
import sys
import time
from pathlib import Path
from typing import Any

from google import genai
from google.genai import types

from shared.config import GEMINI_API_KEY, GEMINI_MODEL, GEMINI_TEMPERATURE, GEMINI_MAX_TOKENS
from shared import utils

logger = logging.getLogger("analizador")
MAX_INLINE_SIZE = 20 * 1024 * 1024


def _inline_part(data: bytes, mime: str) -> types.Part:
    return types.Part(inline_data=types.Blob(data=data, mime_type=mime))


def _reparar_json(raw: str) -> dict | None:
    """Intenta reparar JSON truncado o con errores de Gemini."""
    # Buscar desde el primer { hasta el último }
    start = raw.find('{')
    end = raw.rfind('}')
    if start == -1 or end <= start:
        return None
    candidate = raw[start:end+1]
    try:
        return json.loads(candidate)
    except json.JSONDecodeError:
        pass
    # Si el JSON está truncado (falta } al final), agregar cierres necesarios
    # Contar { y } para saber cuántos faltan
    opens = candidate.count('{')
    closes = candidate.count('}')
    if opens > closes:
        candidate += '}' * (opens - closes)
        try:
            return json.loads(candidate)
        except json.JSONDecodeError:
            pass
    return None


def analyze_files(client, prompt: str, parts: list, config) -> dict[str, Any]:
    contents = [prompt] + parts
    response = client.models.generate_content(
        model=GEMINI_MODEL,
        contents=contents,
        config=config,
    )
    # Intentar parsed primero (JSON mode nativo), fallback a json.loads
    raw = response.text
    if hasattr(response, 'parsed') and response.parsed is not None:
        result = response.parsed
    else:
        try:
            result = json.loads(raw)
        except json.JSONDecodeError as e:
            logger.warning(f"JSON malformado de Gemini: {e}. Intentando reparar…")
            result = _reparar_json(raw)
            if result is None:
                raise ValueError(f"Gemini devolvió JSON inválido: {e}. Raw: {raw[:500]}")
    u = response.usage_metadata
    tokens = {
        "prompt": u.prompt_token_count,
        "completion": u.candidates_token_count,
        "total": u.total_token_count,
    }
    return {"success": True, "data": result, "tokens": tokens}


def _build_parts(client, archivos: list[dict]) -> tuple[list, list, list[str]]:
    parts = []
    uploaded = []
    errors = []
    for a in archivos:
        path = utils.get_uploads_path(a["ruta_storage"])
        if not path.exists():
            errors.append(f"Archivo no encontrado: {a['nombre_original']}")
            continue
        data = path.read_bytes()
        if len(data) <= MAX_INLINE_SIZE:
            parts.append(_inline_part(data, a["mime_type"]))
        else:
            try:
                uf = client.files.upload(file=path)
                uploaded.append(uf)
                parts.append(uf)
            except Exception as e:
                errors.append(f"Upload {a['nombre_original']}: {e}")
    return parts, uploaded, errors


def _cleanup(client, uploaded: list):
    for uf in uploaded:
        try:
            client.files.delete(name=uf.name)
        except Exception:
            pass


def run(proceso_id: int, job_id: int | None = None) -> dict[str, Any]:
    if not GEMINI_API_KEY:
        raise ValueError("GEMINI_API_KEY no configurada en .env")

    client = genai.Client(api_key=GEMINI_API_KEY)
    conn = utils.get_db()

    def progreso(msg: str):
        if job_id:
            utils.reportar_progreso(job_id, msg)

    gconfig = types.GenerateContentConfig(
        temperature=GEMINI_TEMPERATURE,
        max_output_tokens=GEMINI_MAX_TOKENS,
        response_mime_type="application/json",
    )

    try:
        t_start = time.time()
        progreso("Leyendo archivos…")
        logger.info("FASE: leer_datos")

        proc = utils.get_proceso(conn, proceso_id)
        if not proc:
            raise ValueError(f"Proceso {proceso_id} no encontrado")

        estado_anterior = proc["estado"]
        archivos = utils.get_proceso_archivos(conn, proceso_id)
        if not archivos:
            raise ValueError(f"Proceso {proceso_id} no tiene archivos")

        prompts = utils.get_prompts_activos(conn)
        if not prompts:
            raise ValueError("No hay prompts activos en BD")

        resultado = {
            "estado_cuenta": None,
            "entidad": None,
            "deudor": None,
            "codeudor": None,
            "referencias": [],
            "solicitudes_vinculacion": None,
            "observaciones": None,
        }
        metadata = {
            "archivos_procesados": 0,
            "tokens_total": 0,
            "modelo": GEMINI_MODEL,
            "errores": [],
            "tiempo_total_seg": 0,
        }

        # ── Estado de cuenta ──
        ec_list = [a for a in archivos if a["tipo"] == "estado_cuenta"]
        if ec_list and "estado_cuenta" in prompts:
            progreso("Analizando estado de cuenta…")
            logger.info("FASE: analizar_estado_cuenta")
            parts, uploaded, errs = _build_parts(client, ec_list[:1])
            metadata["errores"].extend(errs)
            if parts:
                try:
                    r = analyze_files(client, prompts["estado_cuenta"], parts, gconfig)
                    if r["success"]:
                        data = r["data"]
                        resultado["estado_cuenta"] = data.get("estado_cuenta", data)
                        ent = data.get("entidad")
                        if ent:
                            resultado["entidad"] = ent
                        obs = data.get("observaciones")
                        if obs:
                            resultado["observaciones"] = obs
                        metadata["archivos_procesados"] += 1
                        metadata["tokens_total"] += r["tokens"]["total"]
                finally:
                    _cleanup(client, uploaded)

        # ── Anexos ──
        anexo_tipos = {"anexo", "solicitud_deudor", "solicitud_codeudor",
                       "identificacion", "otro"}
        ax_list = [a for a in archivos if a["tipo"] in anexo_tipos]
        if ax_list and "anexos" in prompts:
            progreso("Analizando anexos…")
            logger.info("FASE: analizar_anexos")
            parts, uploaded, errs = _build_parts(client, ax_list)
            metadata["errores"].extend(errs)
            if parts:
                try:
                    r = analyze_files(client, prompts["anexos"], parts, gconfig)
                    if r["success"]:
                        data = r["data"]
                        resultado["deudor"] = data.get("deudor")
                        resultado["codeudor"] = data.get("codeudor")
                        resultado["referencias"] = data.get("referencias", [])
                        sol = data.get("solicitudes_vinculacion")
                        if sol:
                            resultado["solicitudes_vinculacion"] = sol
                        metadata["archivos_procesados"] += len(ax_list)
                        metadata["tokens_total"] += r["tokens"]["total"]
                finally:
                    _cleanup(client, uploaded)

        # ── Guardar ──
        progreso("Guardando resultados…")
        logger.info("FASE: guardar")
        t_elapsed = time.time() - t_start
        metadata["tiempo_total_seg"] = round(t_elapsed, 2)
        datos_id = utils.insert_datos_ia(conn, proceso_id, resultado, GEMINI_MODEL,
                                          metadata["tokens_total"], metadata)
        utils.update_proceso_estado(conn, proceso_id, "analizado", "fecha_analisis")
        utils.insert_historial(conn, proceso_id, None, "analizado",
                               estado_anterior, "analizado",
                               f"Análisis IA completado. "
                               f"{metadata['archivos_procesados']} archivos, "
                               f"{metadata['tokens_total']} tokens, "
                               f"{t_elapsed:.1f}s.")
        progreso("")
        logger.info(f"Proceso {proceso_id} OK datos_ia_id={datos_id} en {t_elapsed:.1f}s")
        return {
            "success": True,
            "datos_ia_id": datos_id,
            "tokens_total": metadata["tokens_total"],
            "tiempo_seg": round(t_elapsed, 2),
        }

    finally:
        conn.close()


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--proceso_id", type=int, required=True)
    parser.add_argument("--job_id", type=int, default=None)
    args = parser.parse_args()

    try:
        result = run(args.proceso_id, args.job_id)
        print(json.dumps(result, ensure_ascii=False))
        sys.exit(0)
    except Exception as e:
        logger.error(f"Error analizando proceso {args.proceso_id}: {e}")
        if args.job_id:
            utils.reportar_progreso(args.job_id, f"Error: {e}")
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()
