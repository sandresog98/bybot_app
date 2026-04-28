#!/usr/bin/env python3
"""
Bot consulta de comprobantes (Simple.co) — consulta directa, periodo, descarga PDF.

Requiere: pip install -r requirements.txt (Playwright) y `playwright install chromium`.
"""

from __future__ import annotations

import argparse
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
    Locator,
    Page,
    sync_playwright,
    TimeoutError as PlaywrightTimeoutError,
)

logger = logging.getLogger(__name__)


def _zona_america_bogota() -> tzinfo:
    try:
        return ZoneInfo("America/Bogota")
    except Exception:
        # En Windows a veces falta el paquete `tzdata` para ZoneInfo IANA.
        return timezone(timedelta(hours=-5), name="COT")


ZONA_BOGOTA = _zona_america_bogota()

DEFAULT_URL = (
    "https://www.simple.co/Web/faces/pages/comprobantes/consultadirecta/"
    "consultaDirectaLogin.xhtml"
)
# El portal usa imágenes como botones «Consultar».
IMG_CONSULTAR = 'img[src*="bot_consultar.jpg"].borderImage'

# Tras elegir mes/año en el panel: confirma con Aceptar (no es el mismo «Consultar»).
# onclick: actualizarPeriodoRestringido('periodoCotizacion'); return false;
IMG_ACEPTAR_PERIODO = 'img[src*="bot_aceptar.jpg"]'
SELECTORES_ACEPTAR_PERIODO = [
    'img[src*="bot_aceptar.jpg"].borderImage',
    r'img.borderImage[src*="bot_aceptar.jpg"]',
    IMG_ACEPTAR_PERIODO,
    r'img[onclick*="actualizarPeriodoRestringido"][onclick*="periodoCotizacion"]',
    r'img[onclick*="actualizarPeriodoRestringido"]',
]

# Botón descarga PDF: input:image con pdfLogo.png — onclick lanza waitForDownload/descargaAsincrona.
# El sufijo j_idt… puede cambiar entre despliegues; pdfLogo es estable.
SELECTORES_DESCARGA_PDF = [
    r'[id="listaPlanillasPagadas:0:j_idt150"]',
    'input[type="image"][src*="pdfLogo.png"]',
    'input.borderImage[src*="pdfLogo.png"]',
    'table#cuadro1 input[type=image][src*="pdfLogo"]',
    r'[id^="listaPlanillasPagadas:"][src*="pdfLogo.png"]',
    'table#cuadro1 [id^="listaPlanillasPagadas:"][id$=":j_idt150"]',
    # Variante índice de fila distinto de 0
    r'input[id^="listaPlanillasPagadas:"][src*="pdfLogo.png"]',
]

# Dispara el panel de periodo; onclick del sitio: mostrarPeriodoRestringido('periodoCotizacion')
SELECTORES_TRIGGER_CALENDARIO = [
    "img#img_calendar.ui-datepicker-trigger",
    "#img_calendar.ui-datepicker-trigger",
    "img#img_calendar",
    r'img.ui-datepicker-trigger[onclick*="mostrarPeriodoRestringido"]',
    r'img[onclick*="mostrarPeriodoRestringido"][onclick*="periodoCotizacion"]',
    r'img[src*="/images/botones_pequenos/calendar.gif"]',
]

SIMPLECO_DATA_DIR = Path(__file__).resolve().parent

# Tras 1.er Consultar con el documento: sin planillas en 6 meses (caso cerrado, sin PDF).
_FRASE_SIN_PAGOS = (
    "en el sistema no hay pagos realizados durante los últimos 6 meses"
)


def _hay_sin_pagos_ultimos_6_meses(texto_pagina: str) -> bool:
    if not texto_pagina:
        return False
    n = " ".join(texto_pagina.lower().split())
    if _FRASE_SIN_PAGOS in n:
        return True
    # Variantes sin tilde / texto cercano
    if "no hay pagos realizados" in n and ("últimos 6 meses" in n or "ultimos 6 meses" in n):
        return True
    return False


def _resultado_sin_pagos_portal(err_base: dict[str, str | int]) -> dict[str, str | int]:
    logger.info(
        "Tras 1.º Consultar con la cédula: sin pagos en los últimos 6 meses — fin sin PDF."
    )
    return {
        **err_base,
        "estado": "SIN_PAGOS_6_MESES",
        "motivo": "Sin pagos en los últimos 6 meses (mensaje del portal).",
        "archivo_pdf": "",
    }


# Navegador de escritorio (ayuda a sitios que condicionan el DOM) + JS/PrimeFaces: el id
# real suele ser "form1:numeroDocumentoUsuario", no solo #numeroDocumentoUsuario.
SELECTORES_NUMERO_DOC = [
    r'[id$=":numeroDocumentoUsuario"]',
    r'[id$="numeroDocumentoUsuario"]',
    "#numeroDocumentoUsuario",
    r'input[name$=":numeroDocumentoUsuario"]',
    "input[name=\"numeroDocumentoUsuario\"]",
    r'[id*=":numeroDocumentoUsuario"]',
    r'[id*="numeroDocumentoUsuario"]',
]

# Tras 1.º consultar, el «periodo de cotización» a menudo vive en un iframe o el input es
# type=radio oculto (JSF) con un label visible.
SELECTORES_RADIO_PERIODO = [
    r'[id$=":radio_periodoCotizacion:0"]',
    r'[id$=":radio_periodoCotizacion:1"]',
    r'[id$=":radio_periodoCotizacion"]',
    "input#radio_periodoCotizacion",
    "#radio_periodoCotizacion",
    r'[id$="radio_periodoCotizacion"]',
    "input[type='radio'][id*='radio_periodoCotizacion']",
    "input[type='radio'][name*='radio_periodoCotizacion']",
    r'label:has(input[id*="radio_periodoCotizacion"])',
    r'label:has([for*="radio_periodoCotizacion"])',
    r'label:has([for$=":radio_periodoCotizacion"])',
]

# User-Agent de Chrome reciente (evita respuestas mínimas en algunos WAFs).
CHROME_WA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)


def as_page(sup: Page | Frame) -> Page:
    return sup if isinstance(sup, Page) else sup.page


def _superficies(p: Page) -> list[Page | Frame]:
    return [p, *p.frames]


def resumen_pagina(page: Page) -> str:
    partes: list[str] = [f"url={page.url!r}", f"title={page.title()!r}"]
    for i, fr in enumerate(page.frames):
        u = "?"
        try:
            u = fr.url or ""
        except Exception as e:  # noqa: BLE001
            u = f"<? {e}>"
        partes.append(f"frame[{i}]={u!r}")
    return " | ".join(partes)


def _esperar_cargando_suave(page: Page) -> None:
    """Deshace 'Favor, Espere…' o bloqueos comunes de JSF/PrimeFaces si aparecen."""
    for sel in (".ui-blockui", ".ui-blockui-document"):
        try:
            loc = page.locator(sel)
            if loc.count() and loc.first.is_visible():
                loc.first.wait_for(state="hidden", timeout=120000)
        except Exception as e:  # noqa: BLE001
            logger.debug("Espera %s: %s", sel, e)
    try:
        t = page.get_by_text("Favor, Espere", exact=False)
        if t.count() and t.first.is_visible():
            t.first.wait_for(state="hidden", timeout=120000)
    except Exception as e:  # noqa: BLE001
        logger.debug("Espera texto cargando: %s", e)


def _clic_locator_robusto(mono: Locator) -> bool:
    try:
        mono.scroll_into_view_if_needed(timeout=10000)
    except Exception as e:  # noqa: BLE001
        logger.debug("scrollIntoView: %s", e)
    for force in (False, True):
        try:
            mono.click(timeout=20000, force=force, delay=30)
            return True
        except Exception as e:  # noqa: BLE001
            logger.debug("click (force=%s): %s", force, e)
    return False


def clic_radio_periodo_cotizacion(p: Page) -> Page | Frame:
    """
    Encuentra y pulsa el control de periodo de cotización (documento o iframe) y
    devuelve la superficie donde se encontró, para reutilizarla en calendario y formulario.
    """
    _esperar_cargando_suave(p)
    t0 = time.monotonic()
    busca = 0
    while time.monotonic() - t0 < 120.0:
        if busca == 0 or (busca % 40 == 0):
            try:
                logger.info(
                    "Buscando radio periodo | url=%r | n_frames=%s",
                    p.url,
                    len(p.frames),
                )
            except Exception:  # noqa: BLE001
                pass
        busca += 1
        for sup in _superficies(p):
            # ARIA/rol: suele descripto como «periodo» en la fila
            for pat in (r"periodo.*cotiz", r"período.*cotiz", r"periodo"):
                try:
                    g = sup.get_by_role("radio", name=re.compile(pat, re.IGNORECASE))
                    c = g.count()
                    for i in range(c):
                        u = g.nth(i)
                        if _clic_locator_robusto(u):
                            logger.info("Radio: get_by_role(radio) pat=%r", pat)
                            return sup
                except Exception as e:  # noqa: BLE001
                    logger.debug("get_by_role %r: %s", pat, e)
            for sel in SELECTORES_RADIO_PERIODO:
                try:
                    loc = sup.locator(sel)
                except Exception as e:  # noqa: BLE001
                    logger.debug("selector %r: %s", sel, e)
                    continue
                n = loc.count()
                for i in range(n):
                    uno = loc.nth(i)
                    try:
                        if not uno.is_enabled():
                            continue
                    except Exception:  # noqa: BLE001
                        continue
                    if _clic_locator_robusto(uno):
                        logger.info("Radio: selector %r índice %s", sel, i)
                        return sup
        time.sleep(0.25)
    err = f"No se encontró o no se pudo pulsar el radio de periodo. {resumen_pagina(p)}"
    raise RuntimeError(err)


def localizar_campo_numero_doc(page: Page, *, max_espera_s: float = 120.0) -> tuple[Page | Frame, Locator]:
    """
    Busca el input en el documento principal y en iframes. JSF suele poner
    `formId:...:numeroDocumentoUsuario` en lugar de un id global corto.
    """
    t0 = time.monotonic()
    while time.monotonic() - t0 < max_espera_s:
        for sup in (page, *page.frames):
            for sel in SELECTORES_NUMERO_DOC:
                try:
                    loc = sup.locator(sel)
                except Exception as e:  # noqa: BLE001
                    logger.debug("selector %r: %s", sel, e)
                    continue
                n = loc.count()
                for i in range(n):
                    uno = loc.nth(i)
                    try:
                        if not uno.is_visible():
                            continue
                    except Exception:  # noqa: BLE001
                        continue
                    try:
                        furl = sup.url
                    except Exception:  # noqa: BLE001
                        furl = "?"
                    quien = "main" if sup is page else f"frame(name={getattr(sup, 'name', None)!r} url={furl!r})"
                    logger.info("Campo documento con %r (índice %s) en %s", sel, i, quien)
                    return sup, uno
        time.sleep(0.25)
    err = f"No se encontró el input de documento en {max_espera_s:.0f}s. {resumen_pagina(page)}"
    raise RuntimeError(err)


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


def periodo_mes_inmediatamente_anterior() -> tuple[int, int, str, str]:
    """
    «Mes inmediatamente anterior» a la fecha actual (Bogotá) y su año.
    p. ej. en abril 2026 → (3, 2026, "03" o "3", "2026") según lo que acepte el <select>.
    """
    hoy = datetime.now(ZONA_BOGOTA).date()
    primer = hoy.replace(day=1)
    ultimo_mes_anterior = primer - timedelta(days=1)
    mes = ultimo_mes_anterior.month
    anio = ultimo_mes_anterior.year
    return mes, anio, str(mes), str(mes).zfill(2)


def resolver_salida_pdf(salida_dir: Path, numero_id: str) -> Path:
    salida_dir.mkdir(parents=True, exist_ok=True)
    ts = time.strftime("%Y%m%d_%H%M%S")
    safe = reemplazar_nombre_seguro(numero_id)
    return salida_dir / f"simpleco_{safe}_{ts}.pdf"


def reemplazar_nombre_seguro(s: str) -> str:
    return "".join(c if c.isalnum() else "_" for c in s)[:64]


def _indices_prioridad_select(locator: Locator) -> list[int]:
    """
    Hay duplicados ilegales del mismo id (uno oculto + uno en el panel).
    Preferimos los visibles y, entre ellos, el mayor índice (el panel suele ir al final).
    """
    n = locator.count()
    if n <= 1:
        return [0]
    visibles: list[int] = []
    for i in range(n):
        try:
            if locator.nth(i).is_visible():
                visibles.append(i)
        except Exception as e:  # noqa: BLE001
            logger.debug("nth=%s visible?: %s", i, e)
    if visibles:
        return sorted(visibles, reverse=True)
    return list(reversed(range(n)))


def _aplicar_valor_select_periodo_dom(
    page: Page | Frame,
    html_id: str,
    valor_opcion: str,
    *,
    grupo_limpiar: str = "periodoCotizacion",
) -> None:
    """
    Iguala value, selectedIndex y atributos selected en cada <option> (JSF a veces no
    actualiza «selected» solo con Playwright). Duplica la lógica en todos los select con ese id.
    """
    page.evaluate(
        """([hid, val, grp]) => {
            const strVal = String(val);
            const lista = [...document.querySelectorAll('select')].filter((s) => s.id === hid);
            if (!lista.length) return false;
            for (const el of lista) {
                let idx = -1;
                for (let i = 0; i < el.options.length; i++) {
                    const o = el.options[i];
                    o.removeAttribute('selected');
                    if (o.value === strVal) idx = i;
                }
                if (idx < 0) continue;
                el.selectedIndex = idx;
                const sel = el.options[idx];
                sel.selected = true;
                sel.setAttribute('selected', 'selected');
                el.value = strVal;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                if (typeof window.jQuery !== 'undefined') {
                    try { window.jQuery(el).trigger('change'); } catch (e) {}
                }
                if (typeof limpiarTextoPeriodoRestringido === 'function') {
                    try { limpiarTextoPeriodoRestringido(grp, el); } catch (e) {}
                }
            }
            return true;
        }""",
        [html_id, valor_opcion, grupo_limpiar],
    )


def _asignar_select_nativo_jsf(
    page: Page | Frame,
    id_select: str,
    value: str,
) -> bool:
    """
    Último recurso: el <select> existe pero está oculto (estilos/PrimeFaces).
    Asigna value y dispara eventos para que JSF/limpiarTextoPeriodoRestringido reaccionen.
    """
    return bool(
        page.evaluate(
            """([eid, val]) => {
                const el = document.getElementById(eid);
                if (!el || el.tagName !== 'SELECT') return false;
                let ok = false;
                for (const o of el.options) {
                    if (o.value === val) { el.value = val; ok = true; break; }
                }
                if (!ok) {
                    for (const o of el.options) {
                        if (o.value === val || o.text.trim() === val) {
                            el.value = o.value;
                            ok = true;
                            break;
                        }
                    }
                }
                if (!ok) return false;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                if (typeof window.limpiarTextoPeriodoRestringido === 'function') {
                    try { window.limpiarTextoPeriodoRestringido('periodoCotizacion', el); } catch (e) {}
                }
                if (el.onchange) {
                    try { el.onchange(); } catch (e) {}
                }
                return true;
            }""",
            [id_select, value],
        )
    )


def seleccionar_mes_anio(
    page: Page | Frame,
    *,
    id_mes: str,
    id_anio: str,
    mes: int,
    anio: int,
) -> None:
    """
    Los <option value> del portal son «1»…«12», sin cero a la izquierda (no «03»).
    Tras abrir el panel puede haber más de un <select> con el mismo id en el DOM:
    se prueba cada coincidencia (nth).
    """
    loc_mes = page.locator(f'[id="{id_mes}"]')
    loc_anio = page.locator(f'[id="{id_anio}"]')
    loc_mes.first.wait_for(state="attached", timeout=60000)
    loc_anio.first.wait_for(state="attached", timeout=60000)

    MESES_HTML = (
        "",
        "Enero",
        "Febrero",
        "Marzo",
        "Abril",
        "Mayo",
        "Junio",
        "Julio",
        "Agosto",
        "Septiembre",
        "Octubre",
        "Noviembre",
        "Diciembre",
    )

    mes_ok = False
    nm = loc_mes.count()
    orden_m = _indices_prioridad_select(loc_mes)
    logger.debug("orden índices mes (prioridad visible/final DOM): %s", orden_m)

    # value como en HTML: "3" para marzo (prioridad antes que "03")
    valores_mes = (str(mes), f"{mes:02d}")
    for valor in valores_mes:
        if mes_ok:
            break
        for idx in orden_m:
            uno = loc_mes.nth(idx)
            for force in (False, True):
                try:
                    uno.select_option(value=valor, timeout=8000, force=force)
                    logger.info(
                        "periodoCotizacion:mes value=%r (nth=%s/%s force=%s)",
                        valor,
                        idx,
                        nm,
                        force,
                    )
                    mes_ok = True
                    break
                except Exception as e:  # noqa: BLE001
                    logger.debug("mes value=%r nth=%s force=%s: %s", valor, idx, force, e)
            if mes_ok:
                break

    if not mes_ok:
        etiqueta = MESES_HTML[mes]
        for idx in orden_m:
            uno = loc_mes.nth(idx)
            for force in (False, True):
                try:
                    uno.select_option(label=etiqueta, timeout=8000, force=force)
                    logger.info(
                        "periodoCotizacion:mes label=%r (nth=%s/%s force=%s)",
                        etiqueta,
                        idx,
                        nm,
                        force,
                    )
                    mes_ok = True
                    break
                except Exception as e:  # noqa: BLE001
                    logger.debug("mes label nth=%s force=%s: %s", idx, force, e)
            if mes_ok:
                break

    if not mes_ok:
        for valtry in (str(mes), f"{mes:02d}", MESES_HTML[mes]):
            if _asignar_select_nativo_jsf(page, id_mes, valtry):
                logger.info("periodoCotizacion:mes evaluate val=%r", valtry)
                mes_ok = True
                break

    if not mes_ok:
        raise RuntimeError(
            f"No se pudo seleccionar el mes {mes} ({MESES_HTML[mes]}) en {id_mes}"
        )

    _aplicar_valor_select_periodo_dom(page, id_mes, str(mes))

    sa = str(anio)
    na = loc_anio.count()
    orden_a = _indices_prioridad_select(loc_anio)
    logger.debug("orden índices año: %s", orden_a)

    anio_ok = False
    for idx in orden_a:
        uno = loc_anio.nth(idx)
        for force in (False, True):
            try:
                uno.select_option(value=sa, timeout=8000, force=force)
                logger.info(
                    "periodoCotizacion:anio value=%r (nth=%s/%s force=%s)",
                    sa,
                    idx,
                    na,
                    force,
                )
                anio_ok = True
                break
            except Exception as e:  # noqa: BLE001
                logger.debug("anio value nth=%s force=%s: %s", idx, force, e)
        if anio_ok:
            break

    if not anio_ok:
        for idx in orden_a:
            uno = loc_anio.nth(idx)
            for force in (False, True):
                try:
                    uno.select_option(label=sa, timeout=8000, force=force)
                    logger.info(
                        "periodoCotizacion:anio label=%r (nth=%s/%s force=%s)",
                        sa,
                        idx,
                        na,
                        force,
                    )
                    anio_ok = True
                    break
                except Exception as e:  # noqa: BLE001
                    logger.debug("anio label nth=%s force=%s: %s", idx, force, e)
            if anio_ok:
                break

    if not anio_ok:
        if _asignar_select_nativo_jsf(page, id_anio, sa):
            logger.info("periodoCotizacion:anio vía evaluate")
        else:
            raise RuntimeError(f"No se pudo seleccionar el año {anio} en {id_anio}")
    else:
        _aplicar_valor_select_periodo_dom(page, id_anio, sa)

    # Algunos flujos JSF al tocar año vuelven a fijar el mes en el valor por defecto.
    _aplicar_valor_select_periodo_dom(page, id_mes, str(mes))

    logger.info("periodoCotizacion:anio = %s", sa)


def _sanear_motivo_csv(texto: str, *, max_chars: int = 220) -> str:
    """
    Una sola línea legible para CSV (sin trazas Playwright ni saltos que rompan filas).
    """
    if not texto:
        return ""
    s = texto.strip()
    for marcador in ("Call log:", "Traceback (most recent call last):"):
        if marcador in s:
            s = s.split(marcador, 1)[0].strip()
    s = " ".join(s.split())
    if len(s) > max_chars:
        s = s[: max_chars - 3].rstrip() + "..."
    return s


def registrar_consulta_csv(
    ruta_csv: Path,
    *,
    numero_id: str,
    periodo_mes: int,
    periodo_anio: int,
    estado: str,
    motivo: str,
    archivo_pdf: str,
) -> None:
    ruta_csv.parent.mkdir(parents=True, exist_ok=True)
    existe = ruta_csv.exists()
    motivo_limpio = _sanear_motivo_csv(motivo)
    with ruta_csv.open("a", newline="", encoding="utf-8") as f:
        w = csv.writer(f)
        if not existe:
            w.writerow(
                [
                    "timestamp",
                    "numero_id",
                    "periodo_mes",
                    "periodo_anio",
                    "estado",
                    "motivo",
                    "archivo_pdf",
                ]
            )
        w.writerow(
            [
                time.strftime("%Y-%m-%d %H:%M:%S"),
                numero_id,
                periodo_mes,
                periodo_anio,
                estado,
                motivo_limpio,
                archivo_pdf.strip(),
            ]
        )


def abrir_pagina_tras_consultar_inicial(
    target: Page | Frame,
) -> Page:
    """
    Tras rellenar el documento, el primer «Consultar» suele ser un postback JSF en
    la **misma ventana**. Si en algún entorno se abriera otra pestaña, se detecta
    en ~3 s sin usar expect_page (que asumía pestaña nueva y podía retrasar el flujo).
    """
    raiz = as_page(target)
    ctx = raiz.context
    ids_antes = {id(p) for p in ctx.pages}

    logger.info(
        "1.º «Consultar» (por defecto misma ventana). url antes=%r",
        raiz.url,
    )
    target.locator(IMG_CONSULTAR).first.scroll_into_view_if_needed()
    target.locator(IMG_CONSULTAR).first.click()

    for _ in range(15):  # ~3 s: si hay pestaña nueva, aparece ya
        for p in ctx.pages:
            if id(p) not in ids_antes:
                logger.info("Nueva pestaña detectada. URL: %r", p.url)
                p.set_default_timeout(120000)
                try:
                    p.wait_for_load_state("load", timeout=120000)
                except PlaywrightTimeoutError:
                    logger.warning("Timeout load en la nueva pestaña; se continúa.")
                return p
        time.sleep(0.2)

    logger.info("Misma ventana (postback JSF / misma URL o actualización parcial). url=%r", raiz.url)
    try:
        raiz.wait_for_load_state("load", timeout=120000)
    except PlaywrightTimeoutError:
        logger.debug("Sin evento 'load' (actualización parcial sin recargar el documento).")
    time.sleep(0.5)
    return raiz


def cerrar_overlays_primefaces(p: Page | Frame) -> None:
    """
    Tras elegir mes/año suele quedar .ui-widget-overlay encima del fondo y bloquea
    el clic en el segundo «Consultar» (intercepta pointer events).
    """
    raiz = as_page(p)
    try:
        raiz.evaluate(
            """() => {
            document.querySelectorAll('.ui-widget-overlay').forEach((el) => {
                el.style.display = 'none';
                el.style.visibility = 'hidden';
                el.style.pointerEvents = 'none';
            });
            document.querySelectorAll('.ui-blockui, .ui-blockui-document').forEach((el) => {
                el.style.display = 'none';
            });
        }"""
        )
    except Exception as e:  # noqa: BLE001
        logger.debug("cerrar overlays: %s", e)
    try:
        raiz.keyboard.press("Escape")
    except Exception as e:  # noqa: BLE001
        logger.debug("Escape tras overlay: %s", e)


def clic_aceptar_periodo_restringido(p: Page | Frame) -> None:
    """
    Cierra/confirma el periodo elegido en el panel. Obligatorio antes del 2.º «Consultar»;
    sin esto el sitio muestra «este campo es obligatorio».
    """
    raiz = as_page(p)
    for surface in _superficies_busqueda_calendario(p):
        for sel in SELECTORES_ACEPTAR_PERIODO:
            loc = surface.locator(sel)
            n = loc.count()
            if n == 0:
                continue
            el = loc.last if n > 1 else loc.first
            for force in (False, True):
                try:
                    el.wait_for(state="attached", timeout=15000)
                    el.scroll_into_view_if_needed()
                    el.click(timeout=30000, force=force, delay=40)
                    logger.info(
                        "Periodo: «Aceptar» (%r force=%s) en %s",
                        sel,
                        force,
                        type(surface).__name__,
                    )
                    time.sleep(0.6)
                    try:
                        raiz.wait_for_load_state("load", timeout=15000)
                    except PlaywrightTimeoutError:
                        logger.debug("Sin load tras Aceptar (postback AJAX JSF habitual).")
                    time.sleep(0.35)
                    return
                except Exception as e:  # noqa: BLE001
                    logger.debug("aceptar %r force=%s: %s", sel, force, e)

    if raiz.evaluate(
        """() => {
            if (typeof actualizarPeriodoRestringido !== 'function') return false;
            actualizarPeriodoRestringido('periodoCotizacion');
            return true;
        }"""
    ):
        logger.info("Periodo: actualizarPeriodoRestringido('periodoCotizacion') vía JavaScript")
        time.sleep(0.9)
        return

    raise RuntimeError(
        "No se encontró el botón «Aceptar» del periodo (bot_aceptar.jpg / actualizarPeriodoRestringido)."
    )


def clic_consultar(p: Page | Frame, *, force: bool = True) -> None:
    """En pantalla periodo: segundo «Consultar». force=True evita bloqueo por overlays residuales."""
    cerrar_overlays_primefaces(p)
    loc = p.locator(IMG_CONSULTAR)
    n = loc.count()
    t = loc.last if n > 1 else loc.first
    t.scroll_into_view_if_needed()
    t.click(force=force, timeout=120000)


def _superficies_busqueda_calendario(sup: Page | Frame) -> list[Page | Frame]:
    """Icono #img_calendar en el frame del formulario o, si aplica, en la página raíz."""
    if isinstance(sup, Page):
        return [sup]
    return [sup, as_page(sup)]


def abrir_panel_calendario_periodo(sup: Page | Frame) -> None:
    """
    Muestra el desplegable de mes/año: el calendario real va ligado a
    mostrarPeriodoRestringido('periodoCotizacion') en #img_calendar.
    """
    raiz = as_page(sup)
    for surface in _superficies_busqueda_calendario(sup):
        for sel in SELECTORES_TRIGGER_CALENDARIO:
            loc = surface.locator(sel)
            n = loc.count()
            if n == 0:
                continue
            for force in (False, True):
                try:
                    el = loc.last if n > 1 else loc.first
                    el.wait_for(state="attached", timeout=15000)
                    el.scroll_into_view_if_needed()
                    el.click(timeout=20000, force=force, delay=50)
                    logger.info(
                        "Panel periodo: clic %r (force=%s) en %s",
                        sel,
                        force,
                        type(surface).__name__,
                    )
                    time.sleep(0.4)
                    _esperar_panel_mes_anio(raiz)
                    return
                except Exception as e:  # noqa: BLE001
                    logger.debug("calendario %r force=%s: %s", sel, force, e)

    if raiz.evaluate(
        """() => {
            if (typeof mostrarPeriodoRestringido !== 'function') return false;
            mostrarPeriodoRestringido('periodoCotizacion');
            return true;
        }"""
    ):
        logger.info("Panel periodo: mostrarPeriodoRestringido('periodoCotizacion') vía JavaScript")
        time.sleep(0.5)
        _esperar_panel_mes_anio(raiz)
        return

    raise RuntimeError(
        "No se pudo abrir el panel de periodo (ícono calendario o mostrarPeriodoRestringido)."
    )


def _esperar_panel_mes_anio(_raiz: Page) -> None:
    """Breve pausa tras abrir el panel (no usa datepicker jQuery estándar)."""
    time.sleep(0.45)


def _superficies_pagina(p: Page | Frame) -> list[Page | Frame]:
    """Página principal + iframes (la tabla puede estar en un frame)."""
    pg = as_page(p)
    seen: set[int] = set()
    out: list[Page | Frame] = []
    for f in (pg, *pg.frames):
        fid = id(f)
        if fid in seen:
            continue
        seen.add(fid)
        out.append(f)
    return out


def preparar_tabla_cuadro_antes_pdf(p: Page | Frame) -> None:
    """
    En algunas vistas hay que enfocar la fila o marcar formato PDF antes del ícono.
    """
    for surface in _superficies_pagina(p):
        try:
            row = surface.locator("table#cuadro1 tbody tr").first
            if row.count() > 0:
                row.scroll_into_view_if_needed()
                row.click(timeout=8000)
                logger.info("Clic primera fila table#cuadro1 (%s)", type(surface).__name__)
                time.sleep(0.3)
        except Exception as e:  # noqa: BLE001
            logger.debug("fila cuadro1: %s", e)
        try:
            rad = surface.locator(
                'input[type="radio"][value*="PDF" i], '
                'input[type="radio"][id*="PDF" i], '
                'input[type="radio"][name*="formato" i]'
            )
            if rad.count() > 0:
                rad.first.click(timeout=5000)
                logger.info("Opción formato PDF (%s)", type(surface).__name__)
                time.sleep(0.25)
        except Exception as e:  # noqa: BLE001
            logger.debug("radio PDF: %s", e)


def esperar_boton_pdf(
    p: Page | Frame,
    *,
    max_espera_s: float = 120.0,
    intervalo_s: float = 0.75,
) -> tuple[Page | Frame, Locator] | None:
    """Sondeo hasta que exista el input pdfLogo / listaPlanillasPagadas (sin exigir #cuadro1)."""
    t0 = time.monotonic()
    intento = 0
    while time.monotonic() - t0 < max_espera_s:
        intento += 1
        r = localizar_boton_descarga_pdf(p)
        if r is not None:
            logger.info(
                "Botón PDF localizado tras ~%.1fs (%s intentos)",
                time.monotonic() - t0,
                intento,
            )
            return r
        if intento == 1 or intento % 8 == 0:
            logger.debug(
                "Esperando botón PDF… %.0fs / %.0fs",
                time.monotonic() - t0,
                max_espera_s,
            )
        time.sleep(intervalo_s)
    return None


def localizar_boton_descarga_pdf(p: Page | Frame) -> tuple[Page | Frame, Locator] | None:
    """Busca input pdfLogo / listaPlanillasPagadas en página y en cada iframe."""
    for surface in _superficies_pagina(p):
        for sel in SELECTORES_DESCARGA_PDF:
            loc = surface.locator(sel)
            try:
                n = loc.count()
            except Exception as e:  # noqa: BLE001
                logger.debug("pdf selector %r: %s", sel, e)
                continue
            if n > 0:
                logger.info(
                    "Descarga PDF: selector %r en %s (n=%s)",
                    sel,
                    type(surface).__name__,
                    n,
                )
                return surface, loc.first
    return None


def _click_boton_pdf_js(surface: Page | Frame) -> bool:
    """click() nativo — mismo comportamiento que el usuario (onclick JSF incluido)."""
    return bool(
        surface.evaluate(
            """() => {
                let el = document.getElementById('listaPlanillasPagadas:0:j_idt150');
                if (!el) {
                  el = document.querySelector(
                    'input[type="image"][src*="pdfLogo.png"], input.borderImage[src*="pdfLogo"]'
                  );
                }
                if (!el) return false;
                el.click();
                return true;
            }"""
        )
    )


def ejecutar_descarga_pdf_a_archivo(
    surface_con_boton: Page | Frame,
    btn_pdf: Locator,
    out_pdf: Path,
) -> None:
    """
    La descarga se captura con page.expect_download (compatible con todas las versiones;
    BrowserContext.expect_download no existe en Playwright Python antiguos).
    """
    pg = as_page(surface_con_boton)

    # No llamar cerrar_overlays aquí: podría cerrar el panel donde está el ícono PDF.

    with pg.expect_download(timeout=180000) as d_info:
        btn_pdf.scroll_into_view_if_needed(timeout=15000)
        try:
            btn_pdf.click(timeout=90000, force=True, delay=60)
        except Exception as e:  # noqa: BLE001
            logger.warning("Clic PDF Playwright falló (%s); intento JS.", e)
            if not _click_boton_pdf_js(surface_con_boton):
                try:
                    btn_pdf.dispatch_event("click")
                except Exception as e2:  # noqa: BLE001
                    logger.debug("dispatch_event PDF: %s", e2)
                _click_boton_pdf_js(surface_con_boton)

    dl = d_info.value
    dl.save_as(out_pdf)


def ejecutar_consulta(
    *,
    salida_pdf: Path,
    numero_documento: str,
    headless: bool,
) -> dict[str, str | int]:
    mes, anio, _, _ = periodo_mes_inmediatamente_anterior()
    out_final = resolver_salida_pdf(salida_pdf, numero_documento)
    err_base: dict = {"periodo_mes": mes, "periodo_anio": anio}
    logger.info(
        "Inicio Simple.co | headless=%s | salida=%s | periodo=%s/%s | doc=%s",
        headless,
        out_final,
        mes,
        anio,
        numero_documento,
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

            # Este mensaje solo aparece tras documento + 1.er «Consultar» (no más adelante).
            texto_ini = (p_work.locator("body").inner_text() or "")
            if not _hay_sin_pagos_ultimos_6_meses(texto_ini):
                time.sleep(0.85)
                texto_ini = (p_work.locator("body").inner_text() or "")
            if _hay_sin_pagos_ultimos_6_meses(texto_ini):
                return _resultado_sin_pagos_portal(err_base)

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
                as_page(p_sup).wait_for_load_state("load", timeout=120000)
            except PlaywrightTimeoutError:
                logger.warning("Tras 2.º consultar, load timeout; comprobando tabla…")

            try:
                p_sup.locator("table#cuadro1").first.wait_for(state="visible", timeout=8000)
                logger.info("Tabla #cuadro1 visible (opcional)")
            except PlaywrightTimeoutError:
                logger.info(
                    "No apareció table#cuadro1 a tiempo; se ignora y se busca el botón PDF."
                )

            preparar_tabla_cuadro_antes_pdf(p_sup)

            encontrado = esperar_boton_pdf(p_sup, max_espera_s=120.0)
            if encontrado is None:
                texto = as_page(p_sup).locator("body").inner_text() or ""
                texto_corto = texto[:800]
                return {
                    **err_base,
                    "estado": "ERROR_SIN_BOTON",
                    "motivo": (
                        "No se encontró el botón PDF (pdfLogo / listaPlanillasPagadas en página/iframes). "
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
    ap.add_argument("--numero", required=True, help="Número de documento (campo en el sitio)")
    ap.add_argument(
        "-o",
        "--output",
        type=Path,
        default=SIMPLECO_DATA_DIR / "salidas_simpleco",
        help="Carpeta de salida del PDF",
    )
    ap.add_argument("--headed", action="store_true", help="Mostrar navegador (depuración)")
    ap.add_argument("-v", "--verbose", action="store_true", help="Log DEBUG")
    ap.add_argument(
        "--registro-csv",
        type=Path,
        default=SIMPLECO_DATA_DIR / "simpleco_consultas.csv",
        help="Ruta del CSV de auditoría de consultas",
    )
    args = ap.parse_args()

    configurar_logging(verbose=args.verbose)
    _silenciar_logs_ruidosos()
    logging.getLogger("playwright").setLevel(logging.WARNING)

    try:
        r = ejecutar_consulta(
            salida_pdf=args.output,
            numero_documento=args.numero,
            headless=not args.headed,
        )
        registrar_consulta_csv(
            args.registro_csv,
            numero_id=args.numero,
            periodo_mes=int(r["periodo_mes"]),
            periodo_anio=int(r["periodo_anio"]),
            estado=r["estado"],
            motivo=r["motivo"],
            archivo_pdf=r.get("archivo_pdf", "") or "",
        )
        logger.info("Registro CSV actualizado: %s", args.registro_csv.resolve())
    except KeyboardInterrupt:
        m, a, _, _ = periodo_mes_inmediatamente_anterior()
        registrar_consulta_csv(
            args.registro_csv,
            numero_id=args.numero,
            periodo_mes=m,
            periodo_anio=a,
            estado="ERROR_BOT",
            motivo="Interrumpido por el usuario (Ctrl+C)",
            archivo_pdf="",
        )
        logger.warning("Interrumpido por el usuario (Ctrl+C).")
        raise SystemExit(130)
    except Exception:
        m, a, _, _ = periodo_mes_inmediatamente_anterior()
        registrar_consulta_csv(
            args.registro_csv,
            numero_id=args.numero,
            periodo_mes=m,
            periodo_anio=a,
            estado="ERROR_BOT",
            motivo="Excepción durante ejecución (ver logs)",
            archivo_pdf="",
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
