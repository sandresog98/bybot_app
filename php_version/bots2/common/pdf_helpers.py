from __future__ import annotations

import logging
import re
import time
from pathlib import Path

from playwright.sync_api import (
    Frame,
    Locator,
    Page,
    TimeoutError as PlaywrightTimeoutError,
)

logger = logging.getLogger(__name__)

IMG_CONSULTAR = 'img[src*="bot_consultar.jpg"].borderImage'

IMG_ACEPTAR_PERIODO = 'img[src*="bot_aceptar.jpg"]'

SELECTORES_ACEPTAR_PERIODO = [
    'img[src*="bot_aceptar.jpg"].borderImage',
    r'img.borderImage[src*="bot_aceptar.jpg"]',
    IMG_ACEPTAR_PERIODO,
    r'img[onclick*="actualizarPeriodoRestringido"][onclick*="periodoCotizacion"]',
    r'img[onclick*="actualizarPeriodoRestringido"]',
]

SELECTORES_DESCARGA_PDF = [
    r'[id="listaPlanillasPagadas:0:j_idt150"]',
    'input[type="image"][src*="pdfLogo.png"]',
    'input.borderImage[src*="pdfLogo.png"]',
    'table#cuadro1 input[type=image][src*="pdfLogo"]',
    r'[id^="listaPlanillasPagadas:"][src*="pdfLogo.png"]',
    'table#cuadro1 [id^="listaPlanillasPagadas:"][id$=":j_idt150"]',
    r'input[id^="listaPlanillasPagadas:"][src*="pdfLogo.png"]',
]

SELECTORES_TRIGGER_CALENDARIO = [
    "img#img_calendar.ui-datepicker-trigger",
    "#img_calendar.ui-datepicker-trigger",
    "img#img_calendar",
    r'img.ui-datepicker-trigger[onclick*="mostrarPeriodoRestringido"]',
    r'img[onclick*="mostrarPeriodoRestringido"][onclick*="periodoCotizacion"]',
    r'img[src*="/images/botones_pequenos/calendar.gif"]',
]

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


def as_page(sup: Page | Frame) -> Page:
    return sup if isinstance(sup, Page) else sup.page


def _superficies(p: Page) -> list[Page | Frame]:
    return [p, *p.frames]


def _superficies_pagina(p: Page | Frame) -> list[Page | Frame]:
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


def _superficies_busqueda_calendario(sup: Page | Frame) -> list[Page | Frame]:
    if isinstance(sup, Page):
        return [sup]
    return [sup, as_page(sup)]


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


def _clic_locator_robusto(mono: Locator) -> bool:
    try:
        mono.scroll_into_view_if_needed(timeout=10000)
    except Exception as e:
        logger.debug("scrollIntoView: %s", e)
    for force in (False, True):
        try:
            mono.click(timeout=20000, force=force, delay=30)
            return True
        except Exception as e:
            logger.debug("click (force=%s): %s", force, e)
    return False


def _esperar_cargando_suave(page: Page) -> None:
    for sel in (".ui-blockui", ".ui-blockui-document"):
        try:
            loc = page.locator(sel)
            if loc.count() and loc.first.is_visible():
                loc.first.wait_for(state="hidden", timeout=120000)
        except Exception as e:
            logger.debug("Espera %s: %s", sel, e)
    try:
        t = page.get_by_text("Favor, Espere", exact=False)
        if t.count() and t.first.is_visible():
            t.first.wait_for(state="hidden", timeout=120000)
    except Exception as e:
        logger.debug("Espera texto cargando: %s", e)


def clic_radio_periodo_cotizacion(p: Page) -> Page | Frame:
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
            except Exception:
                pass
        busca += 1
        for sup in _superficies(p):
            for pat in (r"periodo.*cotiz", r"período.*cotiz", r"periodo"):
                try:
                    g = sup.get_by_role("radio", name=re.compile(pat, re.IGNORECASE))
                    c = g.count()
                    for i in range(c):
                        u = g.nth(i)
                        if _clic_locator_robusto(u):
                            logger.info("Radio: get_by_role(radio) pat=%r", pat)
                            return sup
                except Exception as e:
                    logger.debug("get_by_role %r: %s", pat, e)
            for sel in SELECTORES_RADIO_PERIODO:
                try:
                    loc = sup.locator(sel)
                except Exception as e:
                    logger.debug("selector %r: %s", sel, e)
                    continue
                n = loc.count()
                for i in range(n):
                    uno = loc.nth(i)
                    try:
                        if not uno.is_enabled():
                            continue
                    except Exception:
                        continue
                    if _clic_locator_robusto(uno):
                        logger.info("Radio: selector %r indice %s", sel, i)
                        return sup
        time.sleep(0.25)
    err = f"No se encontro o no se pudo pulsar el radio de periodo. {resumen_pagina(p)}"
    raise RuntimeError(err)


def _esperar_panel_mes_anio(_raiz: Page) -> None:
    time.sleep(0.45)


def abrir_panel_calendario_periodo(sup: Page | Frame) -> None:
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
                except Exception as e:
                    logger.debug("calendario %r force=%s: %s", sel, force, e)

    if raiz.evaluate(
        """() => {
            if (typeof mostrarPeriodoRestringido !== 'function') return false;
            mostrarPeriodoRestringido('periodoCotizacion');
            return true;
        }"""
    ):
        logger.info("Panel periodo: mostrarPeriodoRestringido('periodoCotizacion') via JavaScript")
        time.sleep(0.5)
        _esperar_panel_mes_anio(raiz)
        return

    raise RuntimeError(
        "No se pudo abrir el panel de periodo (icono calendario o mostrarPeriodoRestringido)."
    )


def _indices_prioridad_select(locator: Locator) -> list[int]:
    n = locator.count()
    if n <= 1:
        return [0]
    visibles: list[int] = []
    for i in range(n):
        try:
            if locator.nth(i).is_visible():
                visibles.append(i)
        except Exception as e:
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


def _asignar_select_nativo_jsf(page: Page | Frame, id_select: str, value: str) -> bool:
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


def seleccionar_mes_anio(
    page: Page | Frame,
    *,
    id_mes: str,
    id_anio: str,
    mes: int,
    anio: int,
) -> None:
    loc_mes = page.locator(f'[id="{id_mes}"]')
    loc_anio = page.locator(f'[id="{id_anio}"]')
    loc_mes.first.wait_for(state="attached", timeout=60000)
    loc_anio.first.wait_for(state="attached", timeout=60000)

    mes_ok = False
    nm = loc_mes.count()
    orden_m = _indices_prioridad_select(loc_mes)

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
                        valor, idx, nm, force,
                    )
                    mes_ok = True
                    break
                except Exception as e:
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
                        etiqueta, idx, nm, force,
                    )
                    mes_ok = True
                    break
                except Exception as e:
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
        raise RuntimeError(f"No se pudo seleccionar el mes {mes} ({MESES_HTML[mes]}) en {id_mes}")

    _aplicar_valor_select_periodo_dom(page, id_mes, str(mes))

    sa = str(anio)
    na = loc_anio.count()
    orden_a = _indices_prioridad_select(loc_anio)

    anio_ok = False
    for idx in orden_a:
        uno = loc_anio.nth(idx)
        for force in (False, True):
            try:
                uno.select_option(value=sa, timeout=8000, force=force)
                logger.info(
                    "periodoCotizacion:anio value=%r (nth=%s/%s force=%s)",
                    sa, idx, na, force,
                )
                anio_ok = True
                break
            except Exception as e:
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
                        sa, idx, na, force,
                    )
                    anio_ok = True
                    break
                except Exception as e:
                    logger.debug("anio label nth=%s force=%s: %s", idx, force, e)
            if anio_ok:
                break

    if not anio_ok:
        if _asignar_select_nativo_jsf(page, id_anio, sa):
            logger.info("periodoCotizacion:anio via evaluate")
        else:
            raise RuntimeError(f"No se pudo seleccionar el anio {anio} en {id_anio}")
    else:
        _aplicar_valor_select_periodo_dom(page, id_anio, sa)

    _aplicar_valor_select_periodo_dom(page, id_mes, str(mes))
    logger.info("periodoCotizacion:anio = %s", sa)


def cerrar_overlays_primefaces(p: Page | Frame) -> None:
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
    except Exception as e:
        logger.debug("cerrar overlays: %s", e)
    try:
        raiz.keyboard.press("Escape")
    except Exception as e:
        logger.debug("Escape tras overlay: %s", e)


def clic_aceptar_periodo_restringido(p: Page | Frame) -> None:
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
                        "Periodo: Aceptar (%r force=%s) en %s",
                        sel, force, type(surface).__name__,
                    )
                    time.sleep(0.6)
                    try:
                        raiz.wait_for_load_state("load", timeout=15000)
                    except PlaywrightTimeoutError:
                        logger.debug("Sin load tras Aceptar (postback AJAX JSF habitual).")
                    time.sleep(0.35)
                    return
                except Exception as e:
                    logger.debug("aceptar %r force=%s: %s", sel, force, e)

    if raiz.evaluate(
        """() => {
            if (typeof actualizarPeriodoRestringido !== 'function') return false;
            actualizarPeriodoRestringido('periodoCotizacion');
            return true;
        }"""
    ):
        logger.info("Periodo: actualizarPeriodoRestringido('periodoCotizacion') via JavaScript")
        time.sleep(0.9)
        return

    raise RuntimeError(
        "No se encontro el boton Aceptar del periodo (bot_aceptar.jpg / actualizarPeriodoRestringido)."
    )


def clic_consultar(p: Page | Frame, *, force: bool = True) -> None:
    cerrar_overlays_primefaces(p)
    loc = p.locator(IMG_CONSULTAR)
    n = loc.count()
    t = loc.last if n > 1 else loc.first
    t.scroll_into_view_if_needed()
    t.click(force=force, timeout=120000)


def localizar_boton_descarga_pdf(p: Page | Frame) -> tuple[Page | Frame, Locator] | None:
    for surface in _superficies_pagina(p):
        for sel in SELECTORES_DESCARGA_PDF:
            loc = surface.locator(sel)
            try:
                n = loc.count()
            except Exception as e:
                logger.debug("pdf selector %r: %s", sel, e)
                continue
            if n > 0:
                logger.info(
                    "Descarga PDF: selector %r en %s (n=%s)",
                    sel, type(surface).__name__, n,
                )
                return surface, loc.first
    return None


def esperar_boton_pdf(
    p: Page | Frame,
    *,
    max_espera_s: float = 120.0,
    intervalo_s: float = 0.75,
) -> tuple[Page | Frame, Locator] | None:
    t0 = time.monotonic()
    intento = 0
    while time.monotonic() - t0 < max_espera_s:
        intento += 1
        r = localizar_boton_descarga_pdf(p)
        if r is not None:
            logger.info(
                "Boton PDF localizado tras ~%.1fs (%s intentos)",
                time.monotonic() - t0, intento,
            )
            return r
        if intento == 1 or intento % 8 == 0:
            logger.debug(
                "Esperando boton PDF... %.0fs / %.0fs",
                time.monotonic() - t0, max_espera_s,
            )
        time.sleep(intervalo_s)
    return None


def preparar_tabla_cuadro_antes_pdf(p: Page | Frame) -> None:
    for surface in _superficies_pagina(p):
        try:
            row = surface.locator("table#cuadro1 tbody tr").first
            if row.count() > 0:
                row.scroll_into_view_if_needed()
                row.click(timeout=8000)
                logger.info("Clic primera fila table#cuadro1 (%s)", type(surface).__name__)
                time.sleep(0.3)
        except Exception as e:
            logger.debug("fila cuadro1: %s", e)
        try:
            rad = surface.locator(
                'input[type="radio"][value*="PDF" i], '
                'input[type="radio"][id*="PDF" i], '
                'input[type="radio"][name*="formato" i]'
            )
            if rad.count() > 0:
                rad.first.click(timeout=5000)
                logger.info("Opcion formato PDF (%s)", type(surface).__name__)
                time.sleep(0.25)
        except Exception as e:
            logger.debug("radio PDF: %s", e)


def _click_boton_pdf_js(surface: Page | Frame) -> bool:
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
    pg = as_page(surface_con_boton)
    with pg.expect_download(timeout=180000) as d_info:
        btn_pdf.scroll_into_view_if_needed(timeout=15000)
        try:
            btn_pdf.click(timeout=90000, force=True, delay=60)
        except Exception as e:
            logger.warning("Clic PDF Playwright fallo (%s); intento JS.", e)
            if not _click_boton_pdf_js(surface_con_boton):
                try:
                    btn_pdf.dispatch_event("click")
                except Exception as e2:
                    logger.debug("dispatch_event PDF: %s", e2)
                _click_boton_pdf_js(surface_con_boton)

    dl = d_info.value
    dl.save_as(out_pdf)
