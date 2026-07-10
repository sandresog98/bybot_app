#!/usr/bin/env python3
"""
Bot consulta de comprobantes (Simple.co) — consulta directa, periodo, descarga PDF.
"""

from __future__ import annotations

import argparse
import logging
import sys
import time
from datetime import datetime, timedelta, timezone, tzinfo
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import (
    Frame,
    Locator,
    Page,
    sync_playwright,
    TimeoutError as PlaywrightTimeoutError,
)

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
from common.timezone_utils import periodo_mes_anterior
from common.pdf_helpers import (
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

ZONA_BOGOTA = ZoneInfo("America/Bogota")

DEFAULT_URL = (
    "https://www.simple.co/Web/faces/pages/comprobantes/consultadirecta/"
    "consultaDirectaLogin.xhtml"
)
IMG_CONSULTAR = 'img[src*="bot_consultar.jpg"].borderImage'

SELECTORES_NUMERO_DOC = [
    r'[id$=":numeroDocumentoUsuario"]',
    r'[id$="numeroDocumentoUsuario"]',
    "#numeroDocumentoUsuario",
    r'input[name$=":numeroDocumentoUsuario"]',
    'input[name="numeroDocumentoUsuario"]',
    r'[id*=":numeroDocumentoUsuario"]',
    r'[id*="numeroDocumentoUsuario"]',
]

SIMPLECO_DATA_DIR = Path(__file__).resolve().parent

_FRASE_SIN_PAGOS = "en el sistema no hay pagos realizados durante los ultimos 6 meses"

CHROME_WA = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)


def _hay_sin_pagos_ultimos_6_meses(texto_pagina: str) -> bool:
    if not texto_pagina:
        return False
    n = " ".join(texto_pagina.lower().split())
    if _FRASE_SIN_PAGOS in n:
        return True
    if "no hay pagos realizados" in n and ("ultimos 6 meses" in n or "ultimos 6 meses" in n):
        return True
    return False


def _hay_error_seguridad(texto_pagina: str) -> bool:
    if not texto_pagina:
        return False
    n = " ".join(texto_pagina.lower().split())
    frases = [
        "no fue posible validar la seguridad",
        "por favor intente nuevamente",
    ]
    return all(f in n for f in frases)


def _resultado_error_seguridad(err_base: dict[str, str | int]) -> dict[str, str | int]:
    logger.warning("Portal rechazo la consulta por validacion de seguridad.")
    return {
        **err_base,
        "estado": "ERROR_SEGURIDAD",
        "motivo": "El portal rechazo la consulta: 'No fue posible validar la seguridad. Por favor intente nuevamente.'",
        "archivo_pdf": "",
    }


def _es_pagina_preguntas_seguridad(page) -> bool:
    url = (page.url or "").lower()
    if "preguntas" in url:
        return True
    try:
        texto = (page.locator("body").inner_text() or "").lower()
        if "autorizacion consulta directa" in texto and "verificar su identidad" in texto:
            return True
    except Exception:
        pass
    return False


def _resultado_preguntas_seguridad(err_base: dict[str, str | int]) -> dict[str, str | int]:
    logger.warning("Portal redirigio a pagina de preguntas de seguridad (verificacion de identidad).")
    return {
        **err_base,
        "estado": "ERROR_PREGUNTAS_SEGURIDAD",
        "motivo": (
            "El portal requiere verificacion de identidad (pagina de preguntas). "
            "Debe completarse manualmente: nombre, apellido, email, EPS."
        ),
        "archivo_pdf": "",
    }


def _resultado_sin_pagos_portal(err_base: dict[str, str | int]) -> dict[str, str | int]:
    logger.info("Tras 1.er Consultar con la cedula: sin pagos en los ultimos 6 meses — fin sin PDF.")
    return {
        **err_base,
        "estado": "SIN_PAGOS_6_MESES",
        "motivo": "Sin pagos en los ultimos 6 meses (mensaje del portal).",
        "archivo_pdf": "",
    }


def resumen_pagina(page: Page) -> str:
    partes: list[str] = [f"url={page.url!r}", f"title={page.title()!r}"]
    for i, fr in enumerate(page.frames):
        u = "?"
        try:
            u = fr.url or ""
        except Exception:
            u = "<?>"
        partes.append(f"frame[{i}]={u!r}")
    return " | ".join(partes)


def localizar_campo_numero_doc(page: Page, *, max_espera_s: float = 120.0) -> tuple[Page | Frame, Locator]:
    t0 = time.monotonic()
    while time.monotonic() - t0 < max_espera_s:
        for sup in (page, *page.frames):
            for sel in SELECTORES_NUMERO_DOC:
                try:
                    loc = sup.locator(sel)
                except Exception as e:
                    logger.debug("selector %r: %s", sel, e)
                    continue
                n = loc.count()
                for i in range(n):
                    uno = loc.nth(i)
                    try:
                        if not uno.is_visible():
                            continue
                    except Exception:
                        continue
                    try:
                        furl = sup.url
                    except Exception:
                        furl = "?"
                    quien = "main" if sup is page else f"frame(name={getattr(sup, 'name', None)!r} url={furl!r})"
                    logger.info("Campo documento con %r (indice %s) en %s", sel, i, quien)
                    return sup, uno
        time.sleep(0.25)
    raise RuntimeError(f"No se encontro el input de documento en {max_espera_s:.0f}s. {resumen_pagina(page)}")


def resolver_salida_pdf(salida_dir: Path, numero_id: str) -> Path:
    salida_dir.mkdir(parents=True, exist_ok=True)
    ts = time.strftime("%Y%m%d_%H%M%S")
    safe = "".join(c if c.isalnum() else "_" for c in numero_id)[:64]
    return salida_dir / f"simpleco_{safe}_{ts}.pdf"


def abrir_pagina_tras_consultar_inicial(target: Page | Frame) -> Page:
    from common.pdf_helpers import as_page
    raiz = as_page(target)
    ctx = raiz.context
    ids_antes = {id(p) for p in ctx.pages}

    logger.info("1.er Consultar (por defecto misma ventana). url antes=%r", raiz.url)
    target.locator(IMG_CONSULTAR).first.scroll_into_view_if_needed()
    target.locator(IMG_CONSULTAR).first.click()

    for _ in range(15):
        for p in ctx.pages:
            if id(p) not in ids_antes:
                logger.info("Nueva pestana detectada. URL: %r", p.url)
                p.set_default_timeout(120000)
                try:
                    p.wait_for_load_state("load", timeout=120000)
                except PlaywrightTimeoutError:
                    logger.warning("Timeout load en la nueva pestana; se continua.")
                return p
        time.sleep(0.2)

    logger.info("Misma ventana (postback JSF). url=%r", raiz.url)
    try:
        raiz.wait_for_load_state("load", timeout=120000)
    except PlaywrightTimeoutError:
        logger.debug("Sin evento 'load' (actualizacion parcial sin recargar el documento).")
    time.sleep(0.5)
    return raiz


def ejecutar_consulta(
    *,
    salida_pdf: Path,
    numero_documento: str,
    headless: bool,
) -> dict[str, str | int]:
    mes, anio = periodo_mes_anterior()
    out_final = resolver_salida_pdf(salida_pdf, numero_documento)
    err_base: dict = {"periodo_mes": mes, "periodo_anio": anio}
    logger.info(
        "Inicio Simple.co | headless=%s | salida=%s | periodo=%s/%s | doc=%s",
        headless, out_final, mes, anio, numero_documento,
    )

    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=headless)
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            locale="es-CO",
            timezone_id="America/Bogota",
            accept_downloads=True,
            user_agent=CHROME_WA,
            extra_http_headers={"Accept-Language": "es-CO,es;q=0.9,en;q=0.5"},
        )
        page = context.new_page()
        page.set_default_timeout(120000)
        p_work: Page = page

        try:
            logger.info("Abriendo URL de consulta directa")
            p_work.goto(DEFAULT_URL, wait_until="load", timeout=120000)
            _esperar_cargando_suave(p_work)

            doc_sup, doc_loc = localizar_campo_numero_doc(p_work, max_espera_s=120.0)
            doc_loc.scroll_into_view_if_needed()
            doc_loc.fill(numero_documento, timeout=120000)
            logger.info("Documento escrito (selector flexible JSF/iframe)")

            p_work = abrir_pagina_tras_consultar_inicial(doc_sup)
            _esperar_cargando_suave(p_work)

            texto_ini = (p_work.locator("body").inner_text() or "")
            if not _hay_sin_pagos_ultimos_6_meses(texto_ini):
                time.sleep(0.85)
                texto_ini = (p_work.locator("body").inner_text() or "")
            if _hay_sin_pagos_ultimos_6_meses(texto_ini):
                return _resultado_sin_pagos_portal(err_base)

            if _hay_error_seguridad(texto_ini):
                return _resultado_error_seguridad(err_base)

            if _es_pagina_preguntas_seguridad(p_work):
                return _resultado_preguntas_seguridad(err_base)

            p_sup = clic_radio_periodo_cotizacion(p_work)
            abrir_panel_calendario_periodo(p_sup)
            seleccionar_mes_anio(
                p_sup,
                id_mes="periodoCotizacion:mes",
                id_anio="periodoCotizacion:anio",
                mes=mes,
                anio=anio,
            )
            clic_aceptar_periodo_restringido(p_sup)
            clic_consultar(p_sup)
            try:
                p_work.wait_for_load_state("load", timeout=120000)
            except PlaywrightTimeoutError:
                logger.warning("Tras 2.o consultar, load timeout; comprobando tabla...")

            try:
                p_sup.locator("table#cuadro1").first.wait_for(state="visible", timeout=8000)
                logger.info("Tabla #cuadro1 visible (opcional)")
            except PlaywrightTimeoutError:
                logger.info("No aparecio table#cuadro1 a tiempo; se ignora y se busca el boton PDF.")

            preparar_tabla_cuadro_antes_pdf(p_sup)
            encontrado = esperar_boton_pdf(p_sup, max_espera_s=120.0)
            if encontrado is None:
                texto = p_work.locator("body").inner_text() or ""
                texto_corto = texto[:800]
                return {
                    **err_base,
                    "estado": "ERROR_SIN_BOTON",
                    "motivo": (
                        "No se encontro el boton PDF (pdfLogo / listaPlanillasPagadas en pagina/iframes). "
                        f"Texto aprox: {texto_corto!r}"
                    ),
                    "archivo_pdf": "",
                }

            sup_pdf, btn_pdf = encontrado
            ejecutar_descarga_pdf_a_archivo(sup_pdf, btn_pdf, out_final)
            ruta = str(out_final.resolve())
            logger.info("PDF guardado: %s", ruta)
            return {
                **err_base,
                "estado": "EXITOSA",
                "motivo": "OK",
                "archivo_pdf": ruta,
            }

        except Exception as e:
            logger.exception("Error en el flujo Simple.co: %s", e)
            return {
                **err_base,
                "estado": "ERROR",
                "motivo": str(e),
                "archivo_pdf": "",
            }
        finally:
            context.close()
            browser.close()
            logger.info("Navegador cerrado.")


def main() -> None:
    ap = argparse.ArgumentParser(
        description="Simple.co — comprobante planilla (PDF) por documento y periodo."
    )
    ap.add_argument("--numero", required=True, help="Numero de documento (campo en el sitio)")
    ap.add_argument(
        "-o",
        "--output",
        type=Path,
        default=SIMPLECO_DATA_DIR / "salidas_simpleco",
        help="Carpeta de salida del PDF",
    )
    ap.add_argument("--headed", action="store_true", help="Mostrar navegador (depuracion)")
    ap.add_argument("-v", "--verbose", action="store_true", help="Log DEBUG")
    ap.add_argument(
        "--registro-csv",
        type=Path,
        default=SIMPLECO_DATA_DIR / "simpleco_consultas.csv",
        help="Ruta del CSV de auditoria de consultas",
    )
    args = ap.parse_args()

    configurar_logging(verbose=args.verbose)
    silenciar_logs_ruidosos()

    try:
        r = ejecutar_consulta(
            salida_pdf=args.output,
            numero_documento=args.numero,
            headless=not args.headed,
        )
        registrar_consulta(
            tabla_db="simpleco_consultas",
            csv_path=args.registro_csv,
            numero_id=args.numero,
            estado=r.get("estado", ""),
            motivo=r.get("motivo", ""),
            archivo_original=r.get("archivo_pdf", "") or "",
            campos_extra={
                "periodo_mes": str(r.get("periodo_mes", "")),
                "periodo_anio": str(r.get("periodo_anio", "")),
            },
        )
        logger.info("Registro actualizado: %s", args.registro_csv.resolve())
    except KeyboardInterrupt:
        m, a = periodo_mes_anterior()
        registrar_consulta(
            tabla_db="simpleco_consultas",
            csv_path=args.registro_csv,
            numero_id=args.numero,
            estado="ERROR_BOT",
            motivo="Interrumpido por el usuario (Ctrl+C)",
            campos_extra={"periodo_mes": str(m), "periodo_anio": str(a)},
        )
        logger.warning("Interrumpido por el usuario (Ctrl+C).")
        raise SystemExit(130)
    except Exception:
        m, a = periodo_mes_anterior()
        registrar_consulta(
            tabla_db="simpleco_consultas",
            csv_path=args.registro_csv,
            numero_id=args.numero,
            estado="ERROR_BOT",
            motivo="Excepcion durante ejecucion (ver logs)",
            campos_extra={"periodo_mes": str(m), "periodo_anio": str(a)},
        )
        logger.exception("Error durante la consulta Simple.co")
        raise SystemExit(1)

    est = r.get("estado")
    if est == "EXITOSA":
        print(r.get("archivo_pdf", ""))
        raise SystemExit(0)
    if est == "SIN_PAGOS_6_MESES":
        print(r.get("motivo", ""))
        raise SystemExit(0)
    print(r.get("motivo", "ERROR"), file=sys.stderr)
    raise SystemExit(1)


if __name__ == "__main__":
    main()
