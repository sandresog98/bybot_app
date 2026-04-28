#!/usr/bin/env python3
from __future__ import annotations

import logging
import sys

from playwright.sync_api import sync_playwright

logger = logging.getLogger(__name__)

URL_CERTIFICADOS = "https://empresas.aportesenlinea.com/Autoservicio/CertificadoAportes.aspx"


def configurar_logging(*, verbose: bool) -> None:
    nivel = logging.DEBUG if verbose else logging.INFO
    root = logging.getLogger()
    root.setLevel(nivel)
    if not root.handlers:
        h = logging.StreamHandler(sys.stderr)
        h.setLevel(nivel)
        h.setFormatter(
            logging.Formatter(
                fmt="%(asctime)s | %(levelname)-7s | %(message)s",
                datefmt="%H:%M:%S",
            )
        )
        root.addHandler(h)
    else:
        for h in root.handlers:
            h.setLevel(nivel)


def _silenciar_logs_ruidosos() -> None:
    logging.getLogger("asyncio").setLevel(logging.WARNING)
    logging.getLogger("playwright").setLevel(logging.WARNING)


def ejecutar_consulta(*, headless: bool) -> dict[str, str]:
    logger.info("Abriendo URL directa de Certificado de Aportes.")
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
            page.goto(URL_CERTIFICADOS, wait_until="domcontentloaded", timeout=60000)
            page.get_by_role("heading", name="Certificado de aportes").first.wait_for(
                state="visible", timeout=30000
            )
            url_final = page.url

            if "CertificadoAportes.aspx" not in url_final:
                return {
                    "estado": "ERROR",
                    "motivo": (
                        "Se abrió la página, pero la URL final no corresponde a "
                        f"certificados: {url_final}"
                    ),
                    "url_final": url_final,
                }

            return {
                "estado": "EXITOSA",
                "motivo": "Primer paso completado: ingreso directo a Certificado de aportes.",
                "url_final": url_final,
            }
        except Exception as e:
            logger.exception("Error en flujo Aportes en Línea: %s", e)
            return {
                "estado": "ERROR",
                "motivo": str(e),
                "url_final": "",
            }
        finally:
            context.close()
            browser.close()
