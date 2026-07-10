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

logger = logging.getLogger(__name__)

URL_CERTIFICADOS = "https://empresas.aportesenlinea.com/Autoservicio/CertificadoAportes.aspx"
EPS_DEFAULT = "NUEVA E.P.S."
MES_DESDE_DEFAULT = "3"
APORTES_DATA_DIR = Path(__file__).resolve().parent
SALIDAS_DIR = APORTES_DATA_DIR / "salidas_aportesenlinea"
CSV_DEFAULT = APORTES_DATA_DIR / "aportesenlinea_consultas.csv"

# User-Agent de Chrome reciente para evitar fingerprinting básico
CHROME_UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/124.0.0.0 Safari/537.36"
)

# Scripts de stealth: ocultan las señales más comunes de automatización
# que detecta reCAPTCHA Enterprise antes de mostrar el checkbox.
_STEALTH_SCRIPTS = [
    # Elimina navigator.webdriver
    "Object.defineProperty(navigator, 'webdriver', {get: () => undefined})",
    # Simula plugins reales de Chrome
    """
    Object.defineProperty(navigator, 'plugins', {
        get: () => [
            {filename:'internal-pdf-viewer',description:'Portable Document Format'},
            {filename:'mhjfbmdgcfjbbpaeojofohoefgiehjai',description:'Portable Document Format'},
            {filename:'mhjfbmdgcfjbbpaeojofohoefgiehjai',description:'Native Client'},
        ]
    });
    """,
    # Idiomas reales de Colombia
    "Object.defineProperty(navigator, 'languages', {get: () => ['es-CO', 'es', 'en']});",
    # Hace que window.chrome exista con runtime básico
    """
    window.chrome = {
        runtime: {
            onConnect: {addListener: () => {}},
            onMessage: {addListener: () => {}},
        },
        loadTimes: function() {},
        csi: function() {},
        app: {}
    };
    """,
    # Permisos API: evita que la consulta de permisos delate el headless
    """
    const origQuery = window.navigator.permissions.query.bind(navigator.permissions);
    window.navigator.permissions.query = (parameters) =>
        parameters.name === 'notifications'
            ? Promise.resolve({state: Notification.permission})
            : origQuery(parameters);
    """,
]


# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------

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


# ---------------------------------------------------------------------------
# CSV de auditoría
# ---------------------------------------------------------------------------

def _sanear_motivo(texto: str, *, max_chars: int = 220) -> str:
    if not texto:
        return ""
    s = " ".join(texto.strip().split())
    for marcador in ("Call log:", "Traceback (most recent call last):"):
        if marcador in s:
            s = s.split(marcador, 1)[0].strip()
    return s[:max_chars - 3].rstrip() + "..." if len(s) > max_chars else s


def registrar_consulta_csv(
    ruta_csv: Path,
    *,
    numero_id: str,
    estado: str,
    motivo: str,
    archivo_pdf: str,
) -> None:
    ruta_csv.parent.mkdir(parents=True, exist_ok=True)
    existe = ruta_csv.exists()
    with ruta_csv.open("a", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        if not existe:
            w.writerow(["timestamp", "numero_id", "estado", "motivo", "archivo_pdf"])
        w.writerow([
            time.strftime("%Y-%m-%d %H:%M:%S"),
            numero_id,
            estado,
            _sanear_motivo(motivo),
            archivo_pdf.strip() if archivo_pdf else "",
        ])


# ---------------------------------------------------------------------------
# Helpers de llenado lento
# ---------------------------------------------------------------------------

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
    """Desplazamiento suave por el formulario antes de empezar a llenar."""
    if not modo_lento:
        return
    for delta in (280, 520, 340, 620, 400):
        page.mouse.wheel(0, delta)
        time.sleep(0.55)
    # Volver al inicio
    page.evaluate("window.scrollTo({top: 0, behavior: 'smooth'})")
    time.sleep(0.5)


# ---------------------------------------------------------------------------
# Mes anterior
# ---------------------------------------------------------------------------

def _mes_anterior_valor() -> str:
    ahora = datetime.now(ZoneInfo("America/Bogota")).date()
    return str((ahora.replace(day=1) - timedelta(days=1)).month)


# ---------------------------------------------------------------------------
# EPS
# ---------------------------------------------------------------------------

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
            # Escribir solo las primeras palabras clave para filtrar el autocomplete
            prefijo = eps.split("(")[0].strip()   # "NUEVA E.P.S."
            if modo_lento:
                inp.type(prefijo, delay=90)
            else:
                inp.fill(prefijo)
            page.wait_for_timeout(700)

            # Buscar la opción exacta en el dropdown (lista ul/li típica)
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

            # Fallback: ArrowDown hasta encontrar la opción correcta (máx 30 pasos)
            for _ in range(30):
                inp.press("ArrowDown")
                time.sleep(0.08)
                valor = inp.input_value().strip()
                if eps_norm in _normalizar_eps(valor):
                    inp.press("Enter")
                    page.wait_for_timeout(300)
                    logger.info("EPS seleccionada (ArrowDown): %r", valor)
                    return True

            # Último intento: seleccionar via JS en el campo oculto de EPS
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


# ---------------------------------------------------------------------------
# reCAPTCHA
# ---------------------------------------------------------------------------

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
    """
    Intenta marcar el checkbox de reCAPTCHA automáticamente.
    1. Obtiene posición del iframe en la página y hace movimientos suaves del mouse.
    2. Hace clic en .recaptcha-checkbox-border vía el locator del frame.
    3. Espera hasta 8s para confirmar aria-checked=true.
    Devuelve True si quedó marcado, False si requiere desafío de imágenes o falló.
    """
    iframe_el = page.locator('iframe[src*="recaptcha"][src*="anchor"]').first
    try:
        box = iframe_el.bounding_box()
        if box:
            cx = box["x"] + 24
            cy = box["y"] + box["height"] / 2
            # Movimientos suaves hacia el checkbox
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
        logger.info("Clic automático en checkbox reCAPTCHA realizado.")
    except Exception as e:
        logger.debug("Clic automático falló: %s", e)
        return False

    # Esperar resultado del clic
    for i in range(20):
        if _recaptcha_marcado(frame):
            logger.info("reCAPTCHA marcado automáticamente.")
            return True
        if _hay_desafio_imagenes(page):
            logger.info("reCAPTCHA lanzó desafío de imágenes (iter %s).", i)
            return False
        logger.debug("reCAPTCHA aria-checked: false (iter %s)", i)
        time.sleep(0.4)

    logger.warning("reCAPTCHA no quedó marcado tras el clic automático.")
    return False


def _resolver_recaptcha(page: Page, *, captcha_interactivo: bool) -> bool:
    """
    Flujo completo:
    1. Intenta clic automático en el checkbox.
    2. Si queda marcado → OK.
    3. Si reCAPTCHA lanza desafío de imágenes y captcha_interactivo=True → pausa manual.
    """
    frame = _localizar_frame_anchor(page)
    if not frame:
        logger.warning("No se encontró el iframe anchor de reCAPTCHA.")
        return False

    # Ya marcado antes de intentar (poco probable pero posible)
    if _recaptcha_marcado(frame):
        return True

    # Intento automático
    if _intentar_clic_automatico(page, frame):
        return True

    # Fallback manual
    if not captcha_interactivo:
        logger.warning(
            "reCAPTCHA no resuelto. Ejecuta sin --no-captcha-interactivo para resolverlo manualmente."
        )
        return False

    logger.info(
        "reCAPTCHA requiere resolución manual.\n"
        "   Marca 'No soy un robot' en el navegador y presiona ENTER aquí para continuar..."
    )
    input("   Presiona ENTER tras resolver el captcha... ")
    return _recaptcha_marcado(frame)


# ---------------------------------------------------------------------------
# Generar certificado → PDF
# ---------------------------------------------------------------------------

def _resolver_salida_pdf(output_dir: Path, numero_id: str) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now(ZoneInfo("America/Bogota")).strftime("%Y%m%d_%H%M%S")
    return output_dir / f"aportesenlinea_{numero_id}_{ts}.pdf"


def _clic_generar_y_esperar_descarga(page: Page, *, numero_id: str, output_dir: Path) -> str:
    """
    Hace clic en 'Generar certificado' y descarga el PDF resultante.

    Orden de estrategias:
    1. Botón abre nueva pestaña → URL .pdf → descarga vía request con las cookies del contexto.
    2. Botón de descarga del visor PDF de Chromium (cr-icon-button#download / #download).
    3. Download interceptado directamente (PDF servido como Content-Disposition attachment).
    """
    salida = _resolver_salida_pdf(output_dir, numero_id)
    boton = page.locator(
        "button:has-text('Generar certificado'), "
        "input[type='submit'][value*='Generar'], "
        "a:has-text('Generar certificado')"
    ).first
    boton.wait_for(state="visible", timeout=30000)

    # ── Estrategia principal: capturar nueva pestaña/popup ───────────────────
    try:
        with page.context.expect_page(timeout=30000) as nueva_info:
            boton.click()
        nueva = nueva_info.value
        nueva.set_default_timeout(60000)

        # Esperar a que el popup navegue desde about:blank a la URL real (máx 45s)
        t0 = time.monotonic()
        url_nueva = nueva.url or ""
        while (not url_nueva or url_nueva == "about:blank") and time.monotonic() - t0 < 45:
            time.sleep(0.5)
            try:
                url_nueva = nueva.url or ""
            except Exception:
                break
        logger.info("Nueva pestaña/popup URL: %s", url_nueva)

        # Esperar carga después de la navegación
        try:
            nueva.wait_for_load_state("domcontentloaded", timeout=30000)
        except Exception:
            pass
        time.sleep(0.8)

        # 1. Fetch de la URL con las cookies de sesión y verificar si devuelve PDF
        if url_nueva.lower().startswith("http"):
            try:
                resp = nueva.context.request.get(url_nueva, timeout=60000)
                ct = resp.headers.get("content-type", "")
                body = resp.body()
                logger.debug("Content-Type: %s | tamaño: %s bytes", ct, len(body))
                if resp.ok and (
                    "pdf" in ct.lower()
                    or body[:4] == b"%PDF"
                    or (len(body) > 5000 and "html" not in ct.lower())
                ):
                    salida.write_bytes(body)
                    logger.info("PDF descargado vía request (Content-Type=%s): %s", ct, salida)
                    return str(salida.resolve())
            except Exception as e:
                logger.debug("Request fetch falló: %s", e)

        # 2. Visor PDF de Chromium → botón de descarga
        selectores_descarga = [
            "cr-icon-button#download",
            "#download",
            "button[aria-label*='Download']",
            "button[aria-label*='Descargar']",
            "button[title*='Download']",
            "button[title*='Descargar']",
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
                logger.info("PDF descargado vía botón visor (%s): %s", sel, salida)
                return str(salida.resolve())
            except Exception:
                continue

        # 3. Buscar enlace o botón de descarga dentro de la página ASPX
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
            logger.info("PDF descargado vía enlace/botón de la página: %s", salida)
            return str(salida.resolve())
        except Exception as e:
            logger.debug("Enlace descarga en página: %s", e)

        raise RuntimeError(
            f"Nueva pestaña abierta ({url_nueva!r}) pero no se pudo descargar el PDF. "
            "Verifica manualmente que el certificado esté disponible."
        )

    except RuntimeError:
        raise
    except Exception:
        pass

    # ── Fallback: descarga directa (attachment) ───────────────────────────────
    try:
        with page.expect_download(timeout=30000) as dl_info:
            boton.click()
        dl = dl_info.value
        dl.save_as(salida)
        logger.info("PDF descargado como attachment: %s", salida)
        return str(salida.resolve())
    except Exception as e:
        raise RuntimeError(
            "No se pudo descargar el PDF: no se abrió nueva pestaña ni se capturó descarga directa."
        ) from e


# ---------------------------------------------------------------------------
# Flujo principal
# ---------------------------------------------------------------------------

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
        "Iniciando bot Aportes en Línea | headless=%s | numero_id=%s",
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
            user_agent=CHROME_UA,
            extra_http_headers={"Accept-Language": "es-CO,es;q=0.9,en;q=0.5"},
            accept_downloads=True,
        )
        for script in _STEALTH_SCRIPTS:
            context.add_init_script(script)

        page = context.new_page()
        page.set_default_timeout(30000)

        try:
            page.goto(URL_CERTIFICADOS, wait_until="domcontentloaded", timeout=60000)
            page.get_by_role("heading", name="Certificado de aportes").first.wait_for(
                state="visible", timeout=30000
            )
            _scroll_visual(page, modo_lento=modo_lento)

            # — Número de documento —
            campo_num = page.locator("#contenido_tbNumeroIdentificacion").first
            campo_num.wait_for(state="visible", timeout=30000)
            _tipo_lento(campo_num, numero_id, modo_lento=modo_lento)
            _pausa(modo_lento, 0.8)
            if campo_num.input_value().strip() != numero_id:
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo diligenciar el número de documento. Leído: {campo_num.input_value()!r}",
                    "archivo_pdf": "",
                }

            # — Fecha de expedición —
            campo_fecha = page.locator("#contenido_txtFechaExp").first
            campo_fecha.wait_for(state="visible", timeout=30000)
            _tipo_lento(campo_fecha, fecha_expedicion, modo_lento=modo_lento)
            campo_fecha.press("Tab")
            _pausa(modo_lento, 0.8)
            if fecha_expedicion not in campo_fecha.input_value():
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo diligenciar la fecha de expedición. Leído: {campo_fecha.input_value()!r}",
                    "archivo_pdf": "",
                }

            # — EPS —
            if not _seleccionar_eps(page, eps=eps, modo_lento=modo_lento):
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo seleccionar EPS: {eps}",
                    "archivo_pdf": "",
                }
            _pausa(modo_lento, 0.6)

            # — Mes desde —
            mes_desde = page.locator("#contenido_ddlMesIni").first
            mes_desde.wait_for(state="visible", timeout=30000)
            mes_desde.select_option(value=MES_DESDE_DEFAULT)
            _pausa(modo_lento, 0.6)
            if mes_desde.input_value().strip() != MES_DESDE_DEFAULT:
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo seleccionar el mes inicial. Leído: {mes_desde.input_value()!r}",
                    "archivo_pdf": "",
                }

            # — Mes hasta (mes anterior dinámico) —
            mes_hasta = page.locator("#contenido_ddlMesFin").first
            mes_hasta.wait_for(state="visible", timeout=30000)
            mes_hasta.select_option(value=mes_hasta_valor)
            _pausa(modo_lento, 0.6)
            if mes_hasta.input_value().strip() != mes_hasta_valor:
                return {
                    "estado": "ERROR",
                    "motivo": f"No se pudo seleccionar el mes final. Leído: {mes_hasta.input_value()!r}",
                    "archivo_pdf": "",
                }

            # — reCAPTCHA —
            logger.info("Intentando resolver reCAPTCHA…")
            if not _resolver_recaptcha(page, captcha_interactivo=captcha_interactivo):
                return {
                    "estado": "ERROR_CAPTCHA",
                    "motivo": "No fue posible validar el reCAPTCHA.",
                    "archivo_pdf": "",
                }
            logger.info("reCAPTCHA superado.")
            _pausa(modo_lento, 0.6)

            # — Generar certificado + descargar PDF —
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
            logger.exception("Error en flujo Aportes en Línea: %s", e)
            return {
                "estado": "ERROR",
                "motivo": str(e),
                "archivo_pdf": "",
            }
        finally:
            context.close()
            browser.close()
            logger.info("Navegador cerrado.")
