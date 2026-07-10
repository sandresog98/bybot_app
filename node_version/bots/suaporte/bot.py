#!/usr/bin/env python3
from __future__ import annotations

import csv
import logging
import re
import sys
import time
from datetime import datetime, timedelta, timezone, tzinfo
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import (
    Frame,
    Page,
    sync_playwright,
    TimeoutError as PlaywrightTimeoutError,
)

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.csv_writer import registrar_consulta_csv
from common.pdf_helpers import (
    as_page,
    _esperar_cargando_suave,
    clic_radio_periodo_cotizacion,
    abrir_panel_calendario_periodo,
    seleccionar_mes_anio,
    clic_aceptar_periodo_restringido,
    clic_consultar,
    preparar_tabla_cuadro_antes_pdf,
    esperar_boton_pdf,
    ejecutar_descarga_pdf_a_archivo,
)

logger = logging.getLogger(__name__)

NOMBRE_BOT = "SuAporte — Consulta directa de comprobantes"

DEFAULT_URL = (
    "https://www.suaporte.com.co/Web/faces/pages/comprobantes/consultadirecta/"
    "consultaDirectaLogin.xhtml"
)

SUAPORTE_DATA_DIR = Path(__file__).resolve().parent
DEFAULT_SALIDA_PDF_DIR = SUAPORTE_DATA_DIR / "salidas_suaporte"

_CAMPOS_CSV = ["fecha_hora", "numero_id", "estado", "motivo", "url_final", "archivo_pdf"]

SELECTORES_NUMERO_DOC = [
    "#numeroDocumentoUsuario",
    'input[name="numeroDocumentoUsuario"]',
    '[id$=":numeroDocumentoUsuario"]',
    '[id*="numeroDocumentoUsuario"]',
]
SELECTOR_IMG_CONSULTAR = 'img[src*="bot_consultar.jpg"].borderImage'
SELECTOR_IMG_BORRAR = 'img[src*="bot_borrar.jpg"].borderImage'

_FRASE_SIN_PAGOS = "en el sistema no hay pagos realizados durante los ultimos 6 meses"
_FRASE_SIN_INFO_PARAMETROS = "nuestro sistema no registra informacion para los parametros seleccionados"

ZONA_BOGOTA = ZoneInfo("America/Bogota")
MES_PERIODO_COTIZACION_SUAPORTE = 3


def _mes_y_anio_periodo_cotizacion_suaporte() -> tuple[int, int]:
    anio = datetime.now(ZONA_BOGOTA).date().year
    return MES_PERIODO_COTIZACION_SUAPORTE, anio


def _hay_sin_pagos_ultimos_6_meses(texto_pagina: str) -> bool:
    if not texto_pagina:
        return False
    n = " ".join(texto_pagina.lower().split())
    if _FRASE_SIN_PAGOS in n:
        return True
    if "no hay pagos realizados" in n and ("ultimos 6 meses" in n or "ultimos 6 meses" in n):
        return True
    return False


def _esperar_mensaje_sin_pagos(page: Page, *, max_espera_s: float = 6.0, intervalo_s: float = 0.18) -> bool:
    t0 = time.monotonic()
    while time.monotonic() - t0 < max_espera_s:
        try:
            texto = page.locator("body").inner_text() or ""
        except Exception:
            texto = ""
        if _hay_sin_pagos_ultimos_6_meses(texto):
            return True
        time.sleep(intervalo_s)
    return False


def _hay_mensaje_sin_info_parametros(texto_pagina: str) -> bool:
    if not texto_pagina:
        return False
    n = " ".join(texto_pagina.lower().split())
    return (
        _FRASE_SIN_INFO_PARAMETROS in n
        or ("no registra informacion" in n and "parametros seleccionados" in n)
    )


def _texto_body_raiz_y_frames(raiz: Page) -> str:
    partes: list[str] = []
    try:
        partes.append(raiz.locator("body").inner_text() or "")
    except Exception:
        pass
    for fr in raiz.frames:
        try:
            partes.append(fr.locator("body").inner_text() or "")
        except Exception:
            pass
    return "\n".join(partes)


def _cerrar_dialogo_sin_info_si_aparece(raiz: Page, *, delay_entre_pasos_s: float, max_espera_s: float = 8.0) -> bool:
    t0 = time.monotonic()
    intervalo = 0.18
    while time.monotonic() - t0 < max_espera_s:
        texto = _texto_body_raiz_y_frames(raiz)
        if not _hay_mensaje_sin_info_parametros(texto):
            time.sleep(intervalo)
            continue
        logger.info("Dialogo del portal: sin informacion para parametros; buscando Aceptar...")
        _pausa(delay_entre_pasos_s)
        dlg = raiz.locator(".ui-dialog:visible").filter(
            has_text=re.compile(r"no registra", re.IGNORECASE)
        )
        if dlg.count() > 0:
            acept = dlg.first.locator("span.ui-button-text").filter(
                has_text=re.compile(r"Aceptar", re.IGNORECASE)
            )
            if acept.count() > 0:
                try:
                    acept.first.click(timeout=15000)
                    _pausa(delay_entre_pasos_s)
                    _esperar_cargando_suave(raiz)
                    logger.info("Clic en Aceptar (dialogo sin informacion).")
                    return True
                except Exception as e:
                    logger.debug("Aceptar en dialogo filtrado: %s", e)
        acept2 = raiz.locator(".ui-dialog:visible span.ui-button-text").filter(
            has_text=re.compile(r"Aceptar", re.IGNORECASE)
        )
        if acept2.count() > 0:
            try:
                acept2.first.click(timeout=15000)
                _pausa(delay_entre_pasos_s)
                _esperar_cargando_suave(raiz)
                logger.info("Clic en Aceptar (dialogo visible).")
                return True
            except Exception as e:
                logger.debug("Aceptar visible dialog: %s", e)
        try:
            fall = raiz.locator("span.ui-button-text").filter(
                has_text=re.compile(r"Aceptar", re.IGNORECASE)
            )
            if fall.count() > 0:
                fall.first.click(timeout=15000)
                _pausa(delay_entre_pasos_s)
                _esperar_cargando_suave(raiz)
                logger.info("Clic en Aceptar (span.ui-button-text).")
                return True
        except Exception as e:
            logger.warning("Fallo al pulsar Aceptar: %s", e)
            return False
    return False


def _localizar_input_documento(page: Page):
    for sel in SELECTORES_NUMERO_DOC:
        loc = page.locator(sel)
        if loc.count() > 0:
            return loc.first
    raise RuntimeError("No se encontro el campo numeroDocumentoUsuario.")


def _ruta_salida_pdf(salida_dir: Path, numero_id: str) -> Path:
    salida_dir.mkdir(parents=True, exist_ok=True)
    ts = time.strftime("%Y%m%d_%H%M%S")
    safe = "".join(c if c.isalnum() else "_" for c in numero_id)[:64]
    return salida_dir / f"suaporte_{safe}_{ts}.pdf"


def _pausa(segundos: float) -> None:
    if segundos > 0:
        time.sleep(segundos)


def _resolver_ids_select_periodo(surf: Page | Frame) -> tuple[str, str]:
    exact_m, exact_a = "periodoCotizacion:mes", "periodoCotizacion:anio"
    if surf.locator(f'[id="{exact_m}"]').count():
        return exact_m, exact_a
    loc = surf.locator('[id$=":periodoCotizacion:mes"]')
    for i in range(loc.count()):
        el = loc.nth(i)
        try:
            if not el.is_visible():
                continue
        except Exception:
            continue
        full_m = el.get_attribute("id") or ""
        if full_m.endswith(":periodoCotizacion:mes"):
            full_a = full_m.replace(":periodoCotizacion:mes", ":periodoCotizacion:anio")
            return full_m, full_a
    raise RuntimeError("No se encontro el select periodoCotizacion:mes.")


def _superficie_clic_periodo_cotizacion(page: Page, *, delay_entre_pasos_s: float) -> Page | Frame:
    _esperar_cargando_suave(page)
    _pausa(delay_entre_pasos_s)
    label_sels = (
        'label[for="radio_periodoCotizacion"]',
        r'label[for$=":radio_periodoCotizacion"]',
    )
    for sup in (page, *page.frames):
        for sel in label_sels:
            loc = sup.locator(sel)
            if loc.count() == 0:
                continue
            lab = loc.first
            try:
                if not lab.is_visible():
                    continue
                lab.scroll_into_view_if_needed(timeout=10000)
                lab.click(timeout=15000)
                logger.info("Periodo de cotizacion: clic en etiqueta (%s)", sel)
                _pausa(delay_entre_pasos_s)
                return sup
            except Exception as e:
                logger.debug("Etiqueta periodo %r: %s", sel, e)
    sup = clic_radio_periodo_cotizacion(page)
    _pausa(delay_entre_pasos_s)
    return sup


def _pasos_periodo_cotizacion_post_consultar(page: Page, *, delay_entre_pasos_s: float) -> tuple[int, int]:
    p_sup = _superficie_clic_periodo_cotizacion(page, delay_entre_pasos_s=delay_entre_pasos_s)
    _pausa(delay_entre_pasos_s)
    abrir_panel_calendario_periodo(p_sup)
    _pausa(delay_entre_pasos_s)
    mes, anio = _mes_y_anio_periodo_cotizacion_suaporte()
    id_mes, id_anio = _resolver_ids_select_periodo(p_sup)
    seleccionar_mes_anio(p_sup, id_mes=id_mes, id_anio=id_anio, mes=mes, anio=anio)
    _pausa(delay_entre_pasos_s)
    clic_aceptar_periodo_restringido(p_sup)
    _pausa(delay_entre_pasos_s)
    logger.info("Periodo de cotizacion: Marzo/%s (America/Bogota) tras calendario y Aceptar periodo.", anio)
    clic_consultar(p_sup)
    _esperar_cargando_suave(as_page(p_sup))
    _pausa(max(delay_entre_pasos_s, 0.22))
    try:
        as_page(p_sup).wait_for_load_state("load", timeout=45000)
    except PlaywrightTimeoutError:
        logger.debug("Sin evento load tras 2.o Consultar (postback AJAX habitual).")
    logger.info("2.o clic en Consultar (bot_consultar.jpg) tras periodo.")
    _cerrar_dialogo_sin_info_si_aparece(page, delay_entre_pasos_s=delay_entre_pasos_s)
    return mes, anio


def _clic_borrar_si_visible(page: Page, delay_entre_pasos_s: float) -> bool:
    loc = page.locator(SELECTOR_IMG_BORRAR)
    try:
        if loc.count() == 0:
            return False
        btn = loc.first
        btn.wait_for(state="visible", timeout=4500)
    except Exception as e:
        logger.debug("Borrar: no visible o no encontrado: %s", e)
        return False

    _pausa(delay_entre_pasos_s)
    try:
        btn.scroll_into_view_if_needed(timeout=15000)
    except Exception as e:
        logger.debug("Borrar scrollIntoView: %s", e)
    _pausa(delay_entre_pasos_s)

    for force in (False, True):
        try:
            btn.click(timeout=20000, force=force, delay=30)
            logger.info("Clic en Borrar (bot_borrar.jpg).")
            _pausa(delay_entre_pasos_s)
            return True
        except Exception as e:
            logger.debug("Borrar click force=%s: %s", force, e)
    return False


def ejecutar_consulta(
    *,
    numero_documento: str,
    headless: bool,
    url_inicio: str | None = None,
    output_dir: Path | None = None,
    keep_open_after_step: bool = False,
    slow_mo_ms: int = 0,
    delay_entre_pasos_s: float = 0.0,
    delay_teclas_ms: int = 0,
) -> dict[str, str]:
    url = url_inicio or DEFAULT_URL
    salida_pdf = output_dir or DEFAULT_SALIDA_PDF_DIR
    if slow_mo_ms > 0 or delay_entre_pasos_s > 0 or delay_teclas_ms > 0:
        logger.info(
            "Ritmo pausado: slow_mo_ms=%s delay_entre_pasos_s=%s delay_teclas_ms=%s",
            slow_mo_ms, delay_entre_pasos_s, delay_teclas_ms,
        )
    with sync_playwright() as pw:
        if slow_mo_ms > 0:
            browser = pw.chromium.launch(headless=headless, slow_mo=slow_mo_ms)
        else:
            browser = pw.chromium.launch(headless=headless)
        context = browser.new_context(
            viewport={"width": 1366, "height": 900},
            locale="es-CO",
            timezone_id="America/Bogota",
            accept_downloads=True,
        )
        page = context.new_page()
        page.set_default_timeout(120000)

        try:
            logger.info("%s — abriendo pagina principal de consulta directa...", NOMBRE_BOT)
            page.goto(url, wait_until="load", timeout=120000)
            _pausa(delay_entre_pasos_s)

            campo = _localizar_input_documento(page)
            campo.scroll_into_view_if_needed()
            _pausa(delay_entre_pasos_s)
            campo.click()
            _pausa(delay_entre_pasos_s)

            if delay_teclas_ms > 0:
                campo.press_sequentially(numero_documento, delay=delay_teclas_ms)
            else:
                campo.fill(numero_documento)
            _pausa(delay_entre_pasos_s)

            boton = page.locator(SELECTOR_IMG_CONSULTAR).first
            boton.scroll_into_view_if_needed()
            _pausa(delay_entre_pasos_s)
            boton.click()

            _pausa(max(delay_entre_pasos_s, 0.28))
            if _esperar_mensaje_sin_pagos(page):
                logger.info(
                    "Aviso del portal: sin pagos en los ultimos 6 meses; "
                    "navegando de nuevo a la pagina principal de consulta."
                )
                page.goto(url, wait_until="load", timeout=120000)
                _pausa(delay_entre_pasos_s)
                resultado = {
                    "estado": "SIN_PAGOS_6_MESES",
                    "motivo": (
                        "El portal indico que no hay pagos en los ultimos 6 meses; "
                        "se volvio a cargar la pagina principal de consulta."
                    ),
                    "url_final": page.url,
                    "archivo_pdf": "",
                }
            else:
                try:
                    mes_p, anio_p = _pasos_periodo_cotizacion_post_consultar(
                        page, delay_entre_pasos_s=delay_entre_pasos_s
                    )
                    preparar_tabla_cuadro_antes_pdf(page)
                    encontrado = esperar_boton_pdf(page, max_espera_s=120.0)
                    if encontrado is None:
                        resultado = {
                            "estado": "ERROR_SIN_PDF",
                            "motivo": (
                                "No se encontro el boton PDF "
                                "(listaPlanillasPagadas / pdfLogo en pagina o iframes)."
                            ),
                            "url_final": page.url,
                            "archivo_pdf": "",
                        }
                    else:
                        sup_pdf, btn_pdf = encontrado
                        out_pdf = _ruta_salida_pdf(salida_pdf, numero_documento)
                        ejecutar_descarga_pdf_a_archivo(sup_pdf, btn_pdf, out_pdf)
                        ruta_pdf = str(out_pdf.resolve())
                        logger.info("PDF guardado: %s", ruta_pdf)
                        resultado = {
                            "estado": "EXITOSA",
                            "motivo": "Documento y consultas OK; periodo Marzo %s/%s; PDF descargado." % (mes_p, anio_p),
                            "url_final": page.url,
                            "archivo_pdf": ruta_pdf,
                        }
                except Exception as e:
                    logger.exception("Fallo en periodo de cotizacion o descarga PDF")
                    resultado = {
                        "estado": "ERROR",
                        "motivo": f"Periodo de cotizacion o PDF: {e}",
                        "url_final": page.url,
                        "archivo_pdf": "",
                    }

            _clic_borrar_si_visible(page, delay_entre_pasos_s)

            if keep_open_after_step and not headless:
                logger.info("Pausa activa; presiona ENTER para cerrar navegador...")
                input()
            return resultado
        except Exception as e:
            logger.exception("Error en el flujo SuAporte")
            return {
                "estado": "ERROR",
                "motivo": str(e),
                "url_final": page.url,
                "archivo_pdf": "",
            }
        finally:
            context.close()
            browser.close()
