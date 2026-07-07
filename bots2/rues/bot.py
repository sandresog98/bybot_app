#!/usr/bin/env python3
from __future__ import annotations

import logging
from datetime import datetime
from pathlib import Path
import unicodedata
from zoneinfo import ZoneInfo

from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
from playwright.sync_api import sync_playwright

from common.logging_config import configurar_logging, silenciar_logs_ruidosos

logger = logging.getLogger(__name__)

RUES_DATA_DIR = Path(__file__).resolve().parent
URL_RUES = "https://www.rues.org.co/"
URLS_RUES_FALLBACK = [
    "https://www.rues.org.co/",
    "https://rues.org.co/",
    "http://www.rues.org.co/",
]
ZONA_BOGOTA = ZoneInfo("America/Bogota")


def _cerrar_modal_inicial(page) -> None:
    boton_cerrar = page.locator("button.swal2-close[aria-label='Close this dialog']").first
    try:
        boton_cerrar.wait_for(state="visible", timeout=8000)
        boton_cerrar.click(timeout=8000)
        logger.info("Aviso inicial cerrado con la X.")
    except PlaywrightTimeoutError:
        logger.info("No aparecio aviso inicial para cerrar.")


def _clic_boton_buscar(page, input_busqueda) -> None:
    form_busqueda = input_busqueda.locator("xpath=ancestor::form[1]")
    boton = form_busqueda.locator(
        "button[type='submit'].d-none.d-sm-block.btn.btn-primary.input-group-append.btn-busqueda.busqueda__button--xs:has(i.bi-search)"
    ).first

    try:
        boton.wait_for(state="visible", timeout=12000)
        boton.scroll_into_view_if_needed(timeout=5000)
        page.wait_for_timeout(800)
        boton.hover(timeout=5000)
        page.wait_for_timeout(700)
        boton.click(timeout=12000)
        logger.info("Clic ejecutado en boton Buscar (selector exacto con icono bi-search).")
        return
    except Exception as exc:
        logger.warning("Fallo clic normal en boton Buscar: %s", exc)

    try:
        boton.click(timeout=12000, force=True)
        logger.info("Clic forzado ejecutado en boton Buscar.")
        return
    except Exception as exc:
        raise RuntimeError(
            "No se pudo hacer clic en el boton Buscar de Registro Mercantil."
        ) from exc


def _guardar_html_resultado(html: str, *, numero_busqueda: str) -> Path:
    salida_dir = RUES_DATA_DIR / "salidas_rues"
    salida_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now(ZONA_BOGOTA).strftime("%Y%m%d_%H%M%S")
    archivo = salida_dir / f"rues_{numero_busqueda}_{ts}.html"
    archivo.write_text(html, encoding="utf-8")
    return archivo


def _esperar_estado_consulta(page) -> str:
    def normalizar(texto: str) -> str:
        base = unicodedata.normalize("NFKD", texto)
        sin_tildes = "".join(ch for ch in base if not unicodedata.combining(ch))
        return " ".join(sin_tildes.lower().split())

    logger.info("Esperando resultado de busqueda en RUES...")
    limite_s = 60
    inicio = datetime.now().timestamp()
    while datetime.now().timestamp() - inicio < limite_s:
        try:
            page.wait_for_timeout(900)
            texto_pagina = page.locator("body").inner_text(timeout=5000)
            texto_norm = normalizar(texto_pagina)

            if "numero de matricula" in texto_norm:
                logger.info("Resultado detectado: Numero de Matricula.")
                return "EXITOSA"
            if "no se encontraron resultados" in texto_norm:
                logger.info("Resultado detectado: No se encontraron resultados.")
                return "FINALIZADO"
        except Exception:
            continue

    raise RuntimeError("No se detecto resultado en RUES (matricula/sin resultados).")


def _motivo_error_corto(exc: Exception) -> str:
    texto = str(exc).strip()
    if not texto:
        return "Error en ejecucion de RUES."
    primera_linea = texto.splitlines()[0].strip()
    if len(primera_linea) > 120:
        return primera_linea[:117] + "..."
    return primera_linea


def _abrir_rues(page) -> None:
    ultimo_error: Exception | None = None
    for url in URLS_RUES_FALLBACK:
        try:
            page.goto(url, wait_until="domcontentloaded", timeout=60000)
            return
        except Exception as exc:
            ultimo_error = exc
            if "ERR_HTTP_RESPONSE_CODE_FAILURE" in str(exc):
                try:
                    page.goto(url, wait_until="commit", timeout=60000)
                    page.wait_for_timeout(1500)
                    if page.url:
                        return
                except Exception as exc_commit:
                    ultimo_error = exc_commit
    raise RuntimeError("No fue posible abrir RUES.") from ultimo_error


def ejecutar_consulta(
    *,
    numero_busqueda: str,
    headless: bool,
    keep_open_after_step: bool = False,
) -> dict[str, str]:
    logger.info("Abriendo RUES: %s", URL_RUES)
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=headless)
        context = browser.new_context(
            viewport={"width": 1366, "height": 900},
            locale="es-CO",
            timezone_id="America/Bogota",
        )
        page = context.new_page()
        page.set_default_timeout(30000)

        try:
            _abrir_rues(page)
            page.wait_for_timeout(1500)

            _cerrar_modal_inicial(page)

            input_busqueda = page.locator("input#search[name='search']").first
            input_busqueda.wait_for(state="visible", timeout=15000)
            input_busqueda.fill(numero_busqueda)
            logger.info("Numero escrito en Registro Mercantil: %s", numero_busqueda)

            _clic_boton_buscar(page, input_busqueda)

            estado = _esperar_estado_consulta(page)
            url_final = page.url

            if estado == "FINALIZADO":
                return {
                    "estado": "FINALIZADO",
                    "motivo": "No se encontraron resultados.",
                    "url_final": url_final,
                    "archivo_html": "",
                }

            html_resultado = page.content()
            archivo_html = _guardar_html_resultado(
                html_resultado, numero_busqueda=numero_busqueda
            )
            return {
                "estado": "EXITOSA",
                "motivo": "Consulta encontrada en RUES.",
                "url_final": url_final,
                "archivo_html": str(archivo_html),
            }
        except Exception as exc:
            logger.error("Error en flujo RUES: %s", exc)
            return {
                "estado": "ERROR",
                "motivo": _motivo_error_corto(exc),
                "url_final": page.url if page else "",
                "archivo_html": "",
            }
        finally:
            if (not headless) and keep_open_after_step:
                try:
                    input()
                except EOFError:
                    pass
            context.close()
            browser.close()
