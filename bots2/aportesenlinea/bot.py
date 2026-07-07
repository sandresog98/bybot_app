#!/usr/bin/env python3
from __future__ import annotations

import csv
import logging
import sys
import time
from datetime import datetime, timedelta
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import Frame, Page, sync_playwright

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
from common.timezone_utils import ZONA_BOGOTA
from common.stealth import aplicar_stealth, CHROME_UA_LINUX

logger = logging.getLogger(__name__)

URL_CERTIFICADOS = "https://empresas.aportesenlinea.com/Autoservicio/CertificadoAportes.aspx"
EPS_DEFAULT = "NUEVA E.P.S."
MES_DESDE_DEFAULT = "3"
APORTES_DATA_DIR = Path(__file__).resolve().parent
SALIDAS_DIR = APORTES_DATA_DIR / "salidas_aportesenlinea"
CSV_DEFAULT = APORTES_DATA_DIR / "aportesenlinea_consultas.csv"


def _mes_anterior_valor() -> str:
    ahora = datetime.now(ZONA_BOGOTA).date()
    return str((ahora.replace(day=1) - timedelta(days=1)).month)


def _normalizar_eps(s: str) -> str:
    return s.upper().replace(".", "").replace(" ", "")


def _seleccionar_eps(page: Page, *, eps: str, modo_lento: bool) -> bool:
    eps_norm = _normalizar_eps(eps)
    for sel in ("#contenido_txtAdmin", 'input[name="ctl00$contenido$txtAdmin"]'):
        inp = page.locator(sel).first
        if inp.count() == 0:
            continue
        try:
            inp.wait_for(state="visible", timeout=5000)
            inp.click()
            inp.fill("")
            prefijo = eps.split("(")[0].strip()
            if modo_lento:
                inp.type(prefijo, delay=90)
            else:
                inp.fill(prefijo)
            page.wait_for_timeout(700)

            opcion = page.locator(
                f"li:has-text('{eps}'), "
                f".autocomplete-item:has-text('{eps}'), "
                f"[role='option']:has-text('{eps}'), "
                f"div.ui-menu-item:has-text('{eps}')"
            ).first
            try:
                opcion.wait_for(state="visible", timeout=3000)
                opcion.click()
                page.wait_for_timeout(300)
                valor = inp.input_value().strip()
                logger.info("EPS seleccionada (clic dropdown): %r", valor)
                return True
            except Exception:
                pass

            for _ in range(30):
                inp.press("ArrowDown")
                time.sleep(0.08)
                valor = inp.input_value().strip()
                if eps_norm in _normalizar_eps(valor):
                    inp.press("Enter")
                    page.wait_for_timeout(300)
                    logger.info("EPS seleccionada (ArrowDown): %r", valor)
                    return True

        except Exception as e:
            logger.debug("EPS selector %r: %s", sel, e)
            continue

    return bool(
        page.evaluate(
            """(epsTexto) => {
                const norm = (s) => (s || "").normalize("NFD")
                    .replace(/[\\u0300-\\u036f]/g, "")
                    .replace(/[\\s.]+/g, "")
                    .toUpperCase();
                const obj = norm(epsTexto);
                for (const s of document.querySelectorAll("select")) {
                    for (const op of s.options) {
                        if (norm(op.textContent) === obj) {
                            s.value = op.value;
                            s.dispatchEvent(new Event("input", {bubbles:true}));
                            s.dispatchEvent(new Event("change", {bubbles:true}));
                            return true;
                        }
                    }
                }
                return false;
            }""",
            eps,
        )
    )


def _localizar_frame_anchor(page: Page, *, max_espera_s: float = 25.0) -> Frame | None:
    t0 = time.monotonic()
    while time.monotonic() - t0 < max_espera_s:
        for fr in page.frames:
            if "recaptcha" in fr.url and "anchor" in fr.url:
                return fr
        page.wait_for_timeout(400)
    return None


def _recaptcha_marcado(frame: Frame) -> bool:
    try:
        return frame.locator("#recaptcha-anchor").first.get_attribute("aria-checked") == "true"
    except Exception:
        return False


def _hay_desafio_imagenes(page: Page) -> bool:
    return any("bframe" in fr.url for fr in page.frames)


def _intentar_clic_automatico(page: Page, frame: Frame) -> bool:
    iframe_el = page.locator('iframe[src*="recaptcha"][src*="anchor"]').first
    try:
        box = iframe_el.bounding_box()
        if box:
            cx = box["x"] + 24
            cy = box["y"] + box["height"] / 2
            for dx, dy in [(-60, 30), (-20, -15), (10, 20), (0, -5)]:
                page.mouse.move(cx + dx, cy + dy)
                time.sleep(0.12)
            page.mouse.move(cx + 2, cy)
            time.sleep(0.18)
    except Exception:
        pass

    try:
        checkbox = frame.locator(".recaptcha-checkbox-border").first
        checkbox.wait_for(state="visible", timeout=8000)
        checkbox.click(delay=55)
        logger.info("Clic automatico en checkbox reCAPTCHA realizado.")
    except Exception as e:
        logger.debug("Clic automatico fallo: %s", e)
        return False

    for i in range(20):
        if _recaptcha_marcado(frame):
            logger.info("reCAPTCHA marcado automaticamente.")
            return True
        if _hay_desafio_imagenes(page):
            logger.info("reCAPTCHA lanzo desafio de imagenes (iter %s).", i)
            return False
        time.sleep(0.4)

    logger.warning("reCAPTCHA no quedo marcado tras el clic automatico.")
    return False


def _resolver_recaptcha(page: Page, *, captcha_interactivo: bool) -> bool:
    frame = _localizar_frame_anchor(page)
    if not frame:
        logger.warning("No se encontro el iframe anchor de reCAPTCHA.")
        return False

    if _recaptcha_marcado(frame):
        return True

    if _intentar_clic_automatico(page, frame):
        return True

    if not captcha_interactivo:
        logger.warning("reCAPTCHA no resuelto. Ejecuta sin --no-captcha-interactivo para resolverlo manualmente.")
        return False

    logger.info(
        "reCAPTCHA requiere resolucion manual.\n"
        "   Marca 'No soy un robot' en el navegador y presiona ENTER aqui para continuar..."
    )
    input("   Presiona ENTER tras resolver el captcha... ")
    return _recaptcha_marcado(frame)


def _resolver_salida_pdf(output_dir: Path, numero_id: str) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now(ZONA_BOGOTA).strftime("%Y%m%d_%H%M%S")
    return output_dir / f"aportesenlinea_{numero_id}_{ts}.pdf"


def _clic_generar_y_esperar_descarga(page: Page, *, numero_id: str, output_dir: Path) -> str:
    salida = _resolver_salida_pdf(output_dir, numero_id)
    boton = page.locator(
        "button:has-text('Generar certificado'), "
        "input[type='submit'][value*='Generar'], "
        "a:has-text('Generar certificado')"
    ).first
    boton.wait_for(state="visible", timeout=30000)

    try:
        with page.context.expect_page(timeout=30000) as nueva_info:
            boton.click()
        nueva = nueva_info.value
        nueva.set_default_timeout(60000)

        t0 = time.monotonic()
        url_nueva = nueva.url or ""
        while (not url_nueva or url_nueva == "about:blank") and time.monotonic() - t0 < 45:
            time.sleep(0.5)
            try:
                url_nueva = nueva.url or ""
            except Exception:
                break
        logger.info("Nueva pestana/popup URL: %s", url_nueva)

        try:
            nueva.wait_for_load_state("domcontentloaded", timeout=30000)
        except Exception:
            pass
        time.sleep(0.8)

        if url_nueva.lower().startswith("http"):
            try:
                resp = nueva.context.request.get(url_nueva, timeout=60000)
                ct = resp.headers.get("content-type", "")
                body = resp.body()
                if resp.ok and (
                    "pdf" in ct.lower()
                    or body[:4] == b"%PDF"
                    or (len(body) > 5000 and "html" not in ct.lower())
                ):
                    salida.write_bytes(body)
                    logger.info("PDF descargado via request (Content-Type=%s): %s", ct, salida)
                    return str(salida.resolve())
            except Exception as e:
                logger.debug("Request fetch fallo: %s", e)

        selectores_descarga = [
            "cr-icon-button#download",
            "#download",
            "button[aria-label*='Download']",
            "button[aria-label*='Descargar']",
            "[aria-label*='download' i]",
        ]
        for sel in selectores_descarga:
            loc = nueva.locator(sel).first
            try:
                loc.wait_for(state="visible", timeout=3500)
                with nueva.expect_download(timeout=40000) as dl_info:
                    loc.click()
                dl = dl_info.value
                dl.save_as(salida)
                logger.info("PDF descargado via boton visor (%s): %s", sel, salida)
                return str(salida.resolve())
            except Exception:
                continue

        try:
            enlace = nueva.locator(
                "a[href*='.pdf' i], a[href*='download' i], "
                "input[type='submit'][value*='escargar'], "
                "button:has-text('Descargar'), a:has-text('Descargar')"
            ).first
            enlace.wait_for(state="visible", timeout=5000)
            with nueva.expect_download(timeout=40000) as dl_info:
                enlace.click()
            dl = dl_info.value
            dl.save_as(salida)
            logger.info("PDF descargado via enlace/boton de la pagina: %s", salida)
            return str(salida.resolve())
        except Exception as e:
            logger.debug("Enlace descarga en pagina: %s", e)

        raise RuntimeError(
            f"Nueva pestana abierta ({url_nueva!r}) pero no se pudo descargar el PDF. "
            "Verifica manualmente que el certificado este disponible."
        )

    except RuntimeError:
        raise
    except Exception:
        pass

    try:
        with page.expect_download(timeout=30000) as dl_info:
            boton.click()
        dl = dl_info.value
        dl.save_as(salida)
        logger.info("PDF descargado como attachment: %s", salida)
        return str(salida.resolve())
    except Exception as e:
        raise RuntimeError(
            "No se pudo descargar el PDF: no se abrio nueva pestana ni se capturo descarga directa."
        ) from e


def _pausa(modo_lento: bool, segundos: float) -> None:
    if modo_lento:
        time.sleep(segundos)


def _tipo_lento(locator, texto: str, *, modo_lento: bool) -> None:
    locator.click()
    locator.fill("")
    if modo_lento:
        locator.type(texto, delay=95)
    else:
        locator.fill(texto)


def _scroll_visual(page: Page, *, modo_lento: bool) -> None:
    if not modo_lento:
        return
    for delta in (280, 520, 340, 620, 400):
        page.mouse.wheel(0, delta)
        time.sleep(0.55)
    page.evaluate("window.scrollTo({top: 0, behavior: 'smooth'})")
    time.sleep(0.5)


def ejecutar_consulta(
    *,
    numero_id: str,
    fecha_expedicion: str,
    eps: str,
    headless: bool,
    keep_open_after_fill: bool,
    captcha_interactivo: bool,
    modo_lento: bool,
    output_dir: Path | None,
) -> dict[str, str]:
    logger.info(
        "Iniciando bot Aportes en Linea | headless=%s | numero_id=%s",
        headless, numero_id,
    )
    out = output_dir or SALIDAS_DIR
    mes_hasta_valor = _mes_anterior_valor()

    with sync_playwright() as pw:
        browser = pw.chromium.launch(
            headless=headless,
            args=[
                "--disable-blink-features=AutomationControlled",
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--disable-infobars",
            ],
        )
        context = browser.new_context(
            viewport={"width": 1366, "height": 900},
            locale="es-CO",
            timezone_id="America/Bogota",
            user_agent=CHROME_UA_LINUX,
            extra_http_headers={"Accept-Language": "es-CO,es;q=0.9,en;q=0.5"},
            accept_downloads=True,
        )
        aplicar_stealth(context)

        page = context.new_page()
        page.set_default_timeout(30000)

        try:
            page.goto(URL_CERTIFICADOS, wait_until="domcontentloaded", timeout=60000)
            page.get_by_role("heading", name="Certificado de aportes").first.wait_for(
                state="visible", timeout=30000
            )
            _scroll_visual(page, modo_lento=modo_lento)

            campo_num = page.locator("#contenido_tbNumeroIdentificacion").first
            campo_num.wait_for(state="visible", timeout=30000)
            _tipo_lento(campo_num, numero_id, modo_lento=modo_lento)
            _pausa(modo_lento, 0.8)
            if campo_num.input_value().strip() != numero_id:
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo diligenciar el numero de documento. Leido: {campo_num.input_value()!r}",
                    "archivo_pdf": "",
                }

            campo_fecha = page.locator("#contenido_txtFechaExp").first
            campo_fecha.wait_for(state="visible", timeout=30000)
            _tipo_lento(campo_fecha, fecha_expedicion, modo_lento=modo_lento)
            campo_fecha.press("Tab")
            _pausa(modo_lento, 0.8)
            if fecha_expedicion not in campo_fecha.input_value():
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo diligenciar la fecha de expedicion. Leido: {campo_fecha.input_value()!r}",
                    "archivo_pdf": "",
                }

            if not _seleccionar_eps(page, eps=eps, modo_lento=modo_lento):
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo seleccionar EPS: {eps}",
                    "archivo_pdf": "",
                }
            _pausa(modo_lento, 0.6)

            mes_desde = page.locator("#contenido_ddlMesIni").first
            mes_desde.wait_for(state="visible", timeout=30000)
            mes_desde.select_option(value=MES_DESDE_DEFAULT)
            _pausa(modo_lento, 0.6)

            mes_hasta = page.locator("#contenido_ddlMesFin").first
            mes_hasta.wait_for(state="visible", timeout=30000)
            mes_hasta.select_option(value=mes_hasta_valor)
            _pausa(modo_lento, 0.6)

            logger.info("Intentando resolver reCAPTCHA...")
            if not _resolver_recaptcha(page, captcha_interactivo=captcha_interactivo):
                return {
                    "estado": "ERROR_CAPTCHA",
                    "motivo": "No fue posible validar el reCAPTCHA.",
                    "archivo_pdf": "",
                }
            logger.info("reCAPTCHA superado.")
            _pausa(modo_lento, 0.6)

            archivo_pdf = _clic_generar_y_esperar_descarga(page, numero_id=numero_id, output_dir=out)
            logger.info("PDF guardado: %s", archivo_pdf)

            if keep_open_after_fill and not headless:
                logger.info("Navegador abierto. Presiona ENTER para cerrarlo...")
                input("Presiona ENTER para cerrar el navegador... ")

            return {
                "estado": "EXITOSA",
                "motivo": "OK",
                "archivo_pdf": archivo_pdf,
            }

        except Exception as e:
            logger.exception("Error en flujo Aportes en Linea: %s", e)
            return {
                "estado": "ERROR",
                "motivo": str(e),
                "archivo_pdf": "",
            }
        finally:
            context.close()
            browser.close()
            logger.info("Navegador cerrado.")
