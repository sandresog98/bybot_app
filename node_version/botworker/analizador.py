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
from google.genai import errors as genai_errors

from shared.config import (
    GEMINI_API_KEY, GEMINI_MODEL, GEMINI_TEMPERATURE, GEMINI_MAX_TOKENS, GEMINI_THINKING_BUDGET,
)
from shared import utils, documentos

logger = logging.getLogger("analizador")
MAX_INLINE_SIZE = 20 * 1024 * 1024


def _inline_part(data: bytes, mime: str) -> types.Part:
    return types.Part(inline_data=types.Blob(data=data, mime_type=mime))


def _dedupe_lists(obj):
    """
    Elimina duplicados EXACTOS en listas (por contenido), de forma recursiva y
    preservando el orden. Mitiga bucles de repetición del modelo (p.ej. 583
    'referencias' que en realidad son 4). No afecta listas de filas únicas
    (como las cuotas de amortización, todas distintas).
    """
    if isinstance(obj, dict):
        return {k: _dedupe_lists(v) for k, v in obj.items()}
    if isinstance(obj, list):
        seen = set()
        out = []
        for it in obj:
            it = _dedupe_lists(it)
            key = json.dumps(it, sort_keys=True, ensure_ascii=False) if isinstance(it, (dict, list)) else repr(it)
            if key in seen:
                continue
            seen.add(key)
            out.append(it)
        return out
    return obj


def _merge(dst: dict, src: dict) -> None:
    """
    Fusiona el resultado de una categoría (src) en el resultado canónico (dst):
    - dicts se fusionan en profundidad; listas se concatenan.
    - un valor no vacío no se sobrescribe con null/vacío; los null solo rellenan huecos.
    """
    for k, v in src.items():
        if isinstance(v, dict) and isinstance(dst.get(k), dict):
            _merge(dst[k], v)
        elif isinstance(v, list) and isinstance(dst.get(k), list):
            dst[k].extend(v)
        elif k not in dst or dst.get(k) in (None, "", [], {}):
            dst[k] = v


def _reparar_json(raw: str) -> dict | None:
    """
    Repara JSON truncado/malformado de Gemini. Cierra strings y arrays/objetos
    abiertos y descarta comas colgantes, respetando strings (comillas escapadas).
    Recupera, p.ej., una tabla de amortización cortada por límite de tokens.
    """
    start = raw.find('{')
    if start == -1:
        return None
    s = raw[start:]
    try:
        return json.loads(s)
    except json.JSONDecodeError:
        pass

    stack: list[str] = []
    in_str = False
    esc = False
    for ch in s:
        if in_str:
            if esc:
                esc = False
            elif ch == '\\':
                esc = True
            elif ch == '"':
                in_str = False
            continue
        if ch == '"':
            in_str = True
        elif ch == '{':
            stack.append('}')
        elif ch == '[':
            stack.append(']')
        elif ch in '}]' and stack:
            stack.pop()

    cand = s.rstrip()
    if in_str:
        cand += '"'
    cand = cand.rstrip()
    if cand.endswith(','):
        cand = cand[:-1]
    cand += ''.join(reversed(stack))
    try:
        return json.loads(cand)
    except json.JSONDecodeError:
        return None


def _generate_with_retry(client, contents, config, attempts: int = 3):
    """generate_content con reintento y backoff ante errores transitorios (429/500/503)."""
    for i in range(attempts):
        try:
            return client.models.generate_content(model=GEMINI_MODEL, contents=contents, config=config)
        except genai_errors.APIError as e:
            code = getattr(e, "code", None)
            msg = str(e)
            transient = code in (429, 500, 503) or "UNAVAILABLE" in msg or "RESOURCE_EXHAUSTED" in msg
            if transient and i < attempts - 1:
                wait = 6 * (i + 1)
                logger.warning(f"Gemini transitorio ({code}); reintento en {wait}s ({i + 1}/{attempts})")
                time.sleep(wait)
                continue
            raise


def analyze_files(client, prompt: str, parts: list, config) -> dict[str, Any]:
    contents = [prompt] + parts
    response = _generate_with_retry(client, contents, config)
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
    prompt = u.prompt_token_count or 0
    candidates = u.candidates_token_count or 0
    # Gemini 2.5 razona ("thinking"): esos tokens se facturan como SALIDA y van
    # en total_token_count pero no en candidates. Los sumamos a la salida.
    thoughts = getattr(u, "thoughts_token_count", 0) or 0
    tokens = {
        "prompt": prompt,
        "completion": candidates + thoughts,
        "total": u.total_token_count or (prompt + candidates + thoughts),
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
        # Normaliza el formato (p.ej. TIFF→PDF) para que Gemini pueda leerlo.
        try:
            data, mime = documentos.to_gemini_bytes(path, a["mime_type"])
        except Exception as e:
            errors.append(f"Normalizando {a['nombre_original']}: {e}")
            continue
        if len(data) <= MAX_INLINE_SIZE:
            parts.append(_inline_part(data, mime))
        else:
            try:
                if mime == a["mime_type"]:
                    uf = client.files.upload(file=path)  # sin conversión: subir original
                else:
                    import tempfile
                    tf = tempfile.NamedTemporaryFile(suffix=".pdf", delete=False)
                    tf.write(data)
                    tf.close()
                    uf = client.files.upload(file=tf.name)
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

    gconfig_kwargs = dict(
        temperature=GEMINI_TEMPERATURE,
        max_output_tokens=GEMINI_MAX_TOKENS,
        response_mime_type="application/json",
    )
    # Presupuesto de "thinking": <0 = no fijar (default del modelo); >=0 = fijar (0 desactiva).
    if GEMINI_THINKING_BUDGET >= 0:
        gconfig_kwargs["thinking_config"] = types.ThinkingConfig(thinking_budget=GEMINI_THINKING_BUDGET)
    gconfig = types.GenerateContentConfig(**gconfig_kwargs)

    try:
        t_start = time.time()
        progreso("Leyendo archivos…")
        logger.info("FASE: leer_datos")

        proc = utils.get_proceso(conn, proceso_id)
        if not proc:
            raise ValueError(f"Proceso {proceso_id} no encontrado")

        estado_anterior = proc["estado"]
        entidad_id = proc.get("entidad_id")
        archivos = utils.get_proceso_archivos(conn, proceso_id)
        if not archivos:
            raise ValueError(f"Proceso {proceso_id} no tiene archivos")

        # Prompts activos resueltos para la entidad del proceso (específico > global).
        prompts = utils.get_prompts_activos(conn, entidad_id)
        if not prompts:
            raise ValueError("No hay prompts activos en BD")

        # Agrupar archivos por categoría lógica (columna tipo).
        por_categoria: dict[str, list[dict]] = {}
        for a in archivos:
            por_categoria.setdefault(a["tipo"], []).append(a)

        resultado: dict[str, Any] = {}
        metadata = {
            "archivos_procesados": 0,
            "tokens_total": 0,
            "tokens_entrada": 0,
            "tokens_salida": 0,
            "modelo": GEMINI_MODEL,
            "entidad_id": entidad_id,
            "categorias_procesadas": [],
            "categorias_sin_prompt": [],
            "errores": [],
            "tiempo_total_seg": 0,
        }

        # ── Bucle genérico: una llamada a Gemini por categoría con prompt disponible ──
        for categoria, docs in por_categoria.items():
            if categoria not in prompts:
                metadata["categorias_sin_prompt"].append(categoria)
                continue
            progreso(f"Analizando {categoria}…")
            logger.info(f"FASE: analizar_{categoria} ({len(docs)} archivo(s))")
            parts, uploaded, errs = _build_parts(client, docs)
            metadata["errores"].extend(errs)
            if not parts:
                continue
            try:
                r = analyze_files(client, prompts[categoria], parts, gconfig)
                if r["success"] and isinstance(r["data"], dict):
                    _merge(resultado, r["data"])
                    metadata["archivos_procesados"] += len(docs)
                    metadata["tokens_total"] += r["tokens"]["total"] or 0
                    metadata["tokens_entrada"] += r["tokens"]["prompt"] or 0
                    metadata["tokens_salida"] += r["tokens"]["completion"] or 0
                    metadata["categorias_procesadas"].append(categoria)
            except Exception as e:
                metadata["errores"].append(f"Categoría {categoria}: {e}")
            finally:
                _cleanup(client, uploaded)

        if not metadata["categorias_procesadas"]:
            raise ValueError(
                "Ningún documento pudo analizarse: no hay prompt activo para las "
                f"categorías presentes {sorted(por_categoria.keys())}."
            )

        # Deduplicar listas (mitiga bucles de repetición del modelo).
        resultado = _dedupe_lists(resultado)

        # ── Guardar ──
        progreso("Guardando resultados…")
        logger.info("FASE: guardar")
        t_elapsed = time.time() - t_start
        metadata["tiempo_total_seg"] = round(t_elapsed, 2)
        datos_id = utils.insert_datos_ia(conn, proceso_id, resultado, GEMINI_MODEL,
                                          metadata["tokens_total"], metadata,
                                          metadata["tokens_entrada"], metadata["tokens_salida"])
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
