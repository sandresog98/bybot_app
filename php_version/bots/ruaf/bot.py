#!/usr/bin/env python3
"""
Bot para consulta RUAF (sispro): términos, formulario, captcha (OCR) y extracción del reporte.

Entorno (ejemplo con venv):
  python3 -m venv venv && ./venv/bin/pip install -r requirements-ruaf.txt
  && ./venv/bin/playwright install chromium
Sistema: Tesseract OCR (p. ej. sudo apt install tesseract-ocr tesseract-ocr-spa)

Depuración captcha: ./venv/bin/python ruaf.py --save-captchas
(guarda bots/captcha_intentos/intento_NNN_*_original.png y *_meta.txt por intento)

OCR: Tesseract puede confundir pares parecidos (p. ej. J/5, 8/S, B/8). Se mitiga con
varias escalas, OEM 1+3 y voto por posición; no hay garantía al 100 %% sin revisión humana o otro motor.
"""

from __future__ import annotations

import argparse
import base64
import csv
import hashlib
import io
import logging
import re
import sys
import time
from collections import Counter
from pathlib import Path
from urllib.parse import urljoin

from PIL import Image, ImageEnhance, ImageFilter, ImageOps
from playwright.sync_api import Page, sync_playwright, TimeoutError as PlaywrightTimeoutError

try:
    import pytesseract
except ImportError:
    pytesseract = None  # type: ignore

logger = logging.getLogger(__name__)


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


# --- Configuración por defecto (sobrescribible por CLI) ---
DEFAULT_URL_INICIO = "https://ruaf.sispro.gov.co/TerminosCondiciones.aspx"
DEFAULT_TIPO_DOC_TEXTO = "CEDULA DE CIUDADANIA"
DEFAULT_NUMERO_ID = "1022434547"
DEFAULT_FECHA = "14/04/2016"
MAX_INTENTOS_CAPTCHA = 40
POST_VERIFY_WAIT_MS = 15000
RUAF_DATA_DIR = Path(__file__).resolve().parent
MAX_FALLOS_CAPTCHA_FUENTE = 3
MAX_REINICIOS_FORMULARIO = 3
MSG_NO_INFO_1 = (
    "No existe información con este tipo y número de documento Ministerio de Salud y "
    "Protección Social, Por favor verifique!"
)
MSG_NO_INFO_2 = (
    "La fecha de expedición o nacimiento no coincide con la la información reportada "
    "en las tablas de referencia del Ministerio de Salud y Protección Social, Por favor verifique!"
)


def _norm_captcha(s: str) -> str:
    s = re.sub(r"[^A-Z0-9]", "", s.upper())
    return s[:5] if len(s) >= 5 else s


def _voto_por_posicion(textos_5: list[str]) -> str:
    """Mayoría carácter a carácter (misma longitud 5); reduce errores puntuales entre pasadas OCR."""
    if not textos_5:
        return ""
    out: list[str] = []
    for i in range(5):
        col = [t[i] for t in textos_5 if len(t) > i]
        if not col:
            return ""
        out.append(Counter(col).most_common(1)[0][0])
    return "".join(out)


def esperar_pagina_consulta_tras_terminos(page: Page) -> None:
    """
    Tras enviar términos, la pantalla de consulta muestra el desplegable de tipo de documento.
    No usar networkidle: analytics/long-polling impiden que la red quede inactiva y se agota el timeout.
    """
    sel = "#MainContent_ddlTiposDocumentos, select[name='ctl00$MainContent$ddlTiposDocumentos']"
    page.wait_for_selector(sel, state="visible", timeout=90000)


def esperar_tras_consultar(page: Page) -> None:
    """
    Tras «Consultar» (postback ASP.NET). Evitamos networkidle; intentamos evento load y seguimos igual.
    """
    try:
        page.wait_for_load_state("load", timeout=90000)
    except PlaywrightTimeoutError:
        logger.warning(
            "Timeout esperando load tras Consultar (postback puede no disparar load); "
            "se sigue con la extracción del reporte."
        )


def cerrar_datepicker_jquery_ui(page: Page) -> None:
    """
    jQuery UI deja #ui-datepicker-div encima del formulario y bloquea el clic en «Verificar».
    """
    try:
        page.keyboard.press("Escape")
    except Exception as e:
        logger.debug("Escape tras datepicker: %s", e)
    time.sleep(0.12)
    try:
        page.evaluate(
            """() => {
            const el = document.getElementById('MainContent_datepicker');
            if (window.jQuery && el) {
                const $e = window.jQuery(el);
                if ($e.datepicker) {
                    try { $e.datepicker('hide'); } catch (e) {}
                }
            }
            const div = document.getElementById('ui-datepicker-div');
            if (div) {
                div.style.display = 'none';
                div.style.visibility = 'hidden';
            }
        }"""
        )
    except Exception as e:
        logger.debug("JS hide datepicker: %s", e)
    try:
        page.locator("#ui-datepicker-div").wait_for(state="hidden", timeout=4000)
    except Exception:
        logger.debug("ui-datepicker-div no pasó a hidden a tiempo (puede estar ya oculto).")


def _silenciar_logs_ruidosos() -> None:
    """Evita STREAM/IDAT de Pillow, asyncio selector y la línea de comando de pytesseract en DEBUG."""
    logging.getLogger("PIL").setLevel(logging.WARNING)
    logging.getLogger("PIL.PngImagePlugin").setLevel(logging.WARNING)
    logging.getLogger("pytesseract").setLevel(logging.WARNING)
    logging.getLogger("asyncio").setLevel(logging.WARNING)


def _variantes_preprocesado(img: Image.Image) -> list[tuple[str, Image.Image]]:
    """
    Varias transformaciones típicas para captchas pequeños / ruidosos.
    Equilibrio: calidad vs. tiempo; el bucle OCR añade OEM 1+3 sobre estas variantes.
    """
    out: list[tuple[str, Image.Image]] = []
    if img.mode not in ("L", "RGB"):
        img = img.convert("RGB")
    g = ImageOps.grayscale(img)
    w, h = g.size

    ac = ImageOps.autocontrast(g)
    med = ac.filter(ImageFilter.MedianFilter(size=3))
    out.append(("gray", g))
    out.append(("autocontrast_median", med))

    # Ampliar solo si el recorte es pequeño (típico captcha web)
    if w < 220:
        mults = (3, 4, 5) if w < 140 else (3, 4)
        for mult in mults:
            nw, nh = w * mult, h * mult
            scaled_ac = ac.resize((nw, nh), Image.Resampling.LANCZOS)
            scaled_med = med.resize((nw, nh), Image.Resampling.LANCZOS)
            out.append((f"scale{mult}x_ac", scaled_ac))
            out.append((f"scale{mult}x_med", scaled_med))
            try:
                out.append(
                    (f"scale{mult}x_sharp", ImageEnhance.Sharpness(scaled_med).enhance(2.1))
                )
            except Exception:
                pass

    try:
        out.append(("sharp_median", ImageEnhance.Sharpness(med).enhance(2.0)))
    except Exception:
        pass

    for t in (140, 160, 180):
        bw = g.point(lambda p, th=t: 255 if p > th else 0)
        out.append((f"bin{t}", bw))
        inv = g.point(lambda p, th=t: 0 if p > th else 255)
        out.append((f"bin{t}_inv", inv))

    return out


def leer_captcha_multipass(img: Image.Image) -> tuple[str, str]:
    """
    Prueba varias combinaciones preprocesado × PSM × motor Tesseract (OEM).
    OEM 3 = LSTM (actual); OEM 1 = legado; más diversidad suele ayudar en J/5, 8/S, etc.
    Devuelve (texto_normalizado, descripción de la variante ganadora).
    """
    if pytesseract is None:
        raise RuntimeError("Instale pytesseract: pip install pytesseract")

    whitelist = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
    base_cfg = f"-c tessedit_char_whitelist={whitelist}"
    psms = (7, 8, 13)  # línea / palabra / línea cruda (suficiente para 5 caracteres)
    oems = (3, 1)  # LSTM + legado: lecturas distintas para votar mejor

    candidatos: list[tuple[str, str]] = []
    for oem in oems:
        for nombre, proc in _variantes_preprocesado(img):
            for psm in psms:
                cfg = f"--oem {oem} --psm {psm} {base_cfg}"
                try:
                    raw = pytesseract.image_to_string(proc, config=cfg)
                except Exception as e:
                    logger.debug("OCR falló (oem=%s %s psm=%s): %s", oem, nombre, psm, e)
                    continue
                norm = _norm_captcha(raw)
                tag = f"oem{oem} {nombre} psm={psm}"
                candidatos.append((norm, tag))

    if not candidatos:
        return "", "sin resultados"

    con5 = [c for c in candidatos if len(c[0]) == 5]
    if con5:
        lecturas = [c[0] for c in con5]
        unicos = set(lecturas)
        if len(unicos) == 1:
            mejor_txt, mejor_tag = con5[0]
        else:
            mejor_txt = _voto_por_posicion(lecturas)
            mejor_tag = f"mayoria_{len(lecturas)}_lecturas"
            if len(mejor_txt) != 5:
                mejor_txt, mejor_tag = max(con5, key=lambda x: lecturas.count(x[0]))
            else:
                logger.debug("Voto por posición entre lecturas: %s → %r", unicos, mejor_txt)
    else:
        mejor_txt, mejor_tag = max(candidatos, key=lambda x: len(x[0]))

    logger.info(
        "Mejor OCR captcha: %r (longitud=%s) vía [%s] | textos distintos: %s",
        mejor_txt,
        len(mejor_txt),
        mejor_tag,
        len({c[0] for c in candidatos}),
    )
    logger.debug("Evaluadas %s combinaciones OCR", len(candidatos))
    return mejor_txt, mejor_tag


def seleccionar_tipo_documento(page: Page, tipo_doc: str) -> None:
    sel = page.locator("#MainContent_ddlTiposDocumentos, select[name='ctl00$MainContent$ddlTiposDocumentos']")
    try:
        sel.select_option(label=tipo_doc)
        logger.info("Tipo de documento seleccionado (select_option): %s", tipo_doc)
        return
    except Exception as e:
        logger.debug("select_option(label) falló: %s", e)
    ok = page.evaluate(
        """(texto) => {
            const s = document.querySelector('select[name="ctl00$MainContent$ddlTiposDocumentos"]')
              || document.getElementById('MainContent_ddlTiposDocumentos');
            if (!s) return false;
            const t = texto.trim();
            for (const o of s.options) {
                if (o.text.trim() === t || o.text.trim().includes(t)) {
                    s.value = o.value;
                    s.dispatchEvent(new Event('input', { bubbles: true }));
                    s.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                }
            }
            return false;
        }""",
        tipo_doc,
    )
    if not ok:
        raise RuntimeError(f"No se pudo seleccionar el tipo de documento: {tipo_doc!r}")
    logger.info("Tipo de documento seleccionado (JavaScript): %s", tipo_doc)


def guardar_intento_captcha(
    directorio: Path,
    intento: int,
    imagen: Image.Image,
    texto_ocr: str,
    estrategia: str,
) -> None:
    """Guarda PNG original + metadatos para revisar intentos y afinar OCR."""
    directorio.mkdir(parents=True, exist_ok=True)
    stamp = time.strftime("%Y%m%d_%H%M%S")
    nombre = f"intento_{intento:03d}_{stamp}"
    png_path = directorio / f"{nombre}_original.png"
    meta_path = directorio / f"{nombre}_meta.txt"
    rgb = imagen.convert("RGB") if imagen.mode in ("RGBA", "P") else imagen
    rgb.save(png_path)
    meta_path.write_text(
        f"intento={intento}\n"
        f"timestamp={stamp}\n"
        f"ocr={texto_ocr}\n"
        f"longitud={len(texto_ocr)}\n"
        f"estrategia_ganadora={estrategia}\n",
        encoding="utf-8",
    )
    logger.info("Captcha guardado: %s | %s", png_path.name, meta_path.name)




def resolver_salida_html(base_output: Path, numero_id: str) -> Path:
    """
    Construye nombre NUMEROID_YYYYMMDD_HHMMSS.html.
    - Si base_output termina en .html, se usa su carpeta padre.
    - Si no, base_output se toma como carpeta destino.
    """
    carpeta = base_output.parent if base_output.suffix.lower() == ".html" else base_output
    carpeta.mkdir(parents=True, exist_ok=True)
    stamp = time.strftime("%Y%m%d_%H%M%S")
    return carpeta / f"{numero_id}_{stamp}.html"


def exportar_html_pagina_completa(page: Page) -> str:
    """
    Exporta el HTML completo de la pagina principal y agrega, como respaldo, el HTML
    de iframes para no perder informacion cargada fuera del main frame.
    """
    html_main = page.content()
    extras: list[str] = []
    for i, fr in enumerate(page.frames):
        if fr == page.main_frame:
            continue
        try:
            frame_html = fr.content()
            extras.append(
                f"\n<!-- FRAME_DUMP_START index={i} name={fr.name!r} url={fr.url!r} -->\n"
                f"{frame_html}\n"
                f"<!-- FRAME_DUMP_END index={i} -->\n"
            )
        except Exception as e:
            extras.append(
                f"\n<!-- FRAME_DUMP_ERROR index={i} name={fr.name!r} url={fr.url!r} error={e!r} -->\n"
            )
    if not extras:
        return html_main
    bloque_frames = "\n<!-- EXTRA_IFRAME_CONTENT -->\n" + "\n".join(extras)
    if "</body>" in html_main:
        return html_main.replace("</body>", f"{bloque_frames}\n</body>")
    return html_main + bloque_frames


def detectar_mensaje_no_exitoso(texto: str) -> str | None:
    t = " ".join(texto.split()).lower()
    m1 = " ".join(MSG_NO_INFO_1.split()).lower()
    m2 = " ".join(MSG_NO_INFO_2.split()).lower()
    if m1 in t:
        return MSG_NO_INFO_1
    if m2 in t:
        return MSG_NO_INFO_2
    return None


def registrar_consulta_csv(
    ruta_csv: Path,
    *,
    numero_id: str,
    fecha: str,
    tipo_doc: str,
    estado: str,
    motivo: str,
    archivo_html: str,
) -> None:
    ruta_csv.parent.mkdir(parents=True, exist_ok=True)
    existe = ruta_csv.exists()
    with ruta_csv.open("a", newline="", encoding="utf-8") as f:
        writer = csv.writer(f)
        if not existe:
            writer.writerow(
                [
                    "timestamp",
                    "numero_id",
                    "fecha_consulta",
                    "tipo_doc",
                    "estado",
                    "motivo",
                    "archivo_html",
                ]
            )
        writer.writerow(
            [
                time.strftime("%Y-%m-%d %H:%M:%S"),
                numero_id,
                fecha,
                tipo_doc,
                estado,
                motivo,
                archivo_html,
            ]
        )


def _es_imagen_captcha_valida(img_locator) -> bool:
    """
    Filtra imágenes candidatas para evitar íconos/miniaturas que contienen "captcha"
    en atributos pero no son el código de 5 caracteres.
    """
    try:
        box = img_locator.bounding_box()
        if not box:
            return False
        w = float(box["width"])
        h = float(box["height"])
        # Rango típico captcha RUAF: evita íconos pequeños (OCR suele dar "24", etc.).
        if w < 70 or w > 400:
            return False
        if h < 22 or h > 140:
            return False
        return True
    except Exception:
        return False


def localizar_imagen_captcha(page: Page):
    # IDs del control suelen ser más fiables que src*=captcha (puede haber otros assets).
    candidatos = [
        "#MainContent_imgCaptcha",
        "#ctl00_MainContent_imgCaptcha",
        'img[id*="Captcha" i]',
        'img[id*="captcha" i]',
        'img[alt*="captcha" i]',
        'img[src*="Captcha" i]',
        'img[src*="captcha" i]',
    ]
    for sel in candidatos:
        loc = page.locator(sel)
        try:
            if loc.count() == 0:
                continue
            first = loc.first
            if not first.is_visible(timeout=2000):
                continue
            if _es_imagen_captcha_valida(first):
                logger.info("Captcha localizado con selector: %s", sel)
                return first
        except Exception as e:
            logger.debug("Selector captcha %r: %s", sel, e)
            continue
    # Último recurso: imagen pequeña típica de captcha junto al campo
    try:
        near = page.locator(
            "#MainContent_txtCaptcha, #ctl00_MainContent_txtCaptcha, "
            "input[name='ctl00$MainContent$txtCaptcha']"
        )
        if near.count() == 0:
            raise RuntimeError("sin campo captcha")
        box = near.first.bounding_box()
        if box:
            for im in page.locator("img").all():
                try:
                    b = im.bounding_box()
                    if not b:
                        continue
                    if (
                        abs(b["y"] - box["y"]) < 90
                        and b["x"] < box["x"]
                        and _es_imagen_captcha_valida(im)
                    ):
                        logger.info("Captcha localizado por proximidad al campo txtCaptcha")
                        return im
                except Exception:
                    continue
            # Fallback de proximidad más flexible (si cambió tamaño del captcha en el sitio)
            for im in page.locator("img").all():
                try:
                    b = im.bounding_box()
                    if not b:
                        continue
                    if (
                        abs(b["y"] - box["y"]) < 110
                        and b["x"] < box["x"]
                        and b["width"] >= 70
                        and b["height"] >= 22
                    ):
                        logger.warning(
                            "Captcha localizado por fallback flexible de proximidad; "
                            "conviene revisar tamaños esperados."
                        )
                        return im
                except Exception:
                    continue
    except Exception as e:
        logger.debug("Búsqueda captcha por proximidad: %s", e)
    raise RuntimeError("No se encontró la imagen del captcha. Revise selectores en el sitio.")


def obtener_png_captcha(page: Page, cap_img) -> bytes:
    """
    Obtiene bytes PNG del captcha.
    Preferir bytes desde atributo src (respuesta directa del servidor): suele evitar pérdidas
    de nitidez del screenshot sobre el elemento; si falla la validación, se usa screenshot.
    """
    src = None
    try:
        src = cap_img.get_attribute("src")
    except Exception as e:
        logger.debug("No se pudo leer src del captcha: %s", e)

    bodies_desde_src: list[bytes] = []
    if src:
        s = src.strip()
        if s.startswith("data:image"):
            try:
                _, b64 = s.split(",", 1)
                bodies_desde_src.append(base64.b64decode(b64))
            except Exception as e:
                logger.debug("No se decodificó data:image captcha: %s", e)
        elif s and not s.lower().startswith("javascript:"):
            try:
                url_img = urljoin(page.url, s)
                resp = page.context.request.get(url_img, timeout=15000)
                if resp.ok:
                    bodies_desde_src.append(resp.body())
                else:
                    logger.debug("HTTP %s descargando captcha: %s", resp.status, url_img)
            except Exception as e:
                logger.debug("Descarga HTTP captcha por src falló: %s", e)

    for body in bodies_desde_src:
        ok, motivo = validar_png_captcha(body)
        if ok:
            return body
        logger.debug("Captcha desde src rechazado: %s", motivo)

    try:
        shot = cap_img.screenshot(timeout=7000)
        ok, motivo = validar_png_captcha(shot)
        if ok:
            return shot
        logger.warning("Screenshot captcha no pasó validación: %s", motivo)
    except PlaywrightTimeoutError as e:
        logger.warning(
            "Timeout capturando screenshot del captcha (%s). ¿src HTTP disponible?…",
            e,
        )

    for body in bodies_desde_src:
        ok, _mot = validar_png_captcha(body)
        if ok:
            return body

    raise RuntimeError("No se pudo capturar captcha válido (screenshot/src).")


def _parece_html(b: bytes) -> bool:
    sniff = b[:400].lstrip().lower()
    return (
        sniff.startswith(b"<!doctype html")
        or sniff.startswith(b"<html")
        or b"<body" in sniff
        or b"<head" in sniff
    )


def validar_png_captcha(b: bytes) -> tuple[bool, str]:
    """
    Detecta respuestas inválidas típicas:
    - HTML/error devuelto con extensión .png
    - imagen demasiado pequeña o corrupta
    """
    if not b:
        return False, "bytes vacíos"
    if _parece_html(b):
        return False, "respuesta HTML en lugar de imagen captcha"
    if len(b) < 600:
        return False, f"imagen demasiado pequeña ({len(b)} bytes)"
    try:
        im = Image.open(io.BytesIO(b))
        im.load()
    except Exception as e:
        return False, f"imagen no decodificable por PIL: {e}"
    w, h = im.size
    if w < 60 or h < 20:
        return False, f"dimensiones anómalas {w}x{h}"
    return True, "ok"


def forzar_renovacion_captcha(page: Page) -> None:
    """
    Intenta forzar un captcha nuevo cuando el sitio repite imagen:
    1) clic sobre la imagen captcha
    2) click en posibles enlaces/botones de refresco
    3) cache-buster sobre src del img captcha
    """
    # 1) clic en imagen captcha conocida
    try:
        loc_img = page.locator(
            "#MainContent_imgCaptcha, #ctl00_MainContent_imgCaptcha, "
            "img[id*='Captcha' i], img[src*='captcha' i], img[alt*='captcha' i]"
        )
        if loc_img.count() > 0 and loc_img.first.is_visible(timeout=1200):
            loc_img.first.click(timeout=1500)
            time.sleep(0.35)
            return
    except Exception:
        pass

    # 2) enlaces/botones de refresco
    try:
        loc_ref = page.locator(
            "a[href*='captcha' i], a[id*='captcha' i], button[id*='captcha' i], "
            "input[id*='captcha' i], input[name*='captcha' i]"
        )
        if loc_ref.count() > 0 and loc_ref.first.is_visible(timeout=1200):
            loc_ref.first.click(timeout=1500)
            time.sleep(0.35)
            return
    except Exception:
        pass

    # 3) cache-buster del src en el DOM (misma política que el locator de arriba)
    try:
        page.evaluate(
            """() => {
                const img = document.getElementById('MainContent_imgCaptcha')
                  || document.getElementById('ctl00_MainContent_imgCaptcha')
                  || document.querySelector(
                    'img[id*="Captcha" i], img[src*="captcha" i], img[alt*="captcha" i]'
                  );
                if (!img) return;
                const now = String(Date.now());
                const src = img.getAttribute('src') || '';
                const sep = src.includes('?') ? '&' : '?';
                img.setAttribute('src', src + sep + 'cb=' + now);
            }"""
        )
    except Exception:
        pass
    time.sleep(0.4)


def ejecutar_consulta(
    *,
    salida_html: Path,
    numero_id: str,
    fecha: str,
    tipo_doc: str,
    headless: bool,
    captchas_dir: Path | None,
) -> dict[str, str]:
    if pytesseract is None:
        logger.error("Falta pytesseract. Ejecute: pip install pytesseract Pillow")
        sys.exit(1)

    salida_final = resolver_salida_html(salida_html, numero_id)
    logger.info(
        "Inicio consulta RUAF | headless=%s | salida=%s | captchas_dir=%s",
        headless,
        salida_final,
        captchas_dir or "(no se guardan imágenes)",
    )

    with sync_playwright() as p:
        logger.info("Iniciando navegador Chromium…")
        browser = p.chromium.launch(headless=headless)
        context = browser.new_context(
            viewport={"width": 1280, "height": 900},
            locale="es-CO",
            timezone_id="America/Bogota",
        )
        page = context.new_page()
        page.set_default_timeout(60000)
        logger.debug("Timeout por defecto de página: 60000 ms")

        try:
            def preparar_formulario() -> None:
                logger.info("Paso 1/… Abriendo términos: %s", DEFAULT_URL_INICIO)
                page.goto(DEFAULT_URL_INICIO, wait_until="domcontentloaded")
                logger.info("Página cargada (domcontentloaded). URL actual: %s", page.url)

                logger.info("Paso 2/… Marcando radio MainContent_RadioButtonList1_0 y enviando formulario")
                page.locator("#MainContent_RadioButtonList1_0").click()
                page.locator("#MainContent_btnEnviar, input[name='ctl00$MainContent$btnEnviar']").first.click()
                logger.info("Esperando pantalla de consulta (selector tipo documento; no networkidle)…")
                esperar_pagina_consulta_tras_terminos(page)
                logger.info("Formulario enviado. URL: %s", page.url)

                logger.info("Paso 3/… Rellenando formulario de consulta")
                seleccionar_tipo_documento(page, tipo_doc)
                page.locator(
                    "#MainContent_txbNumeroIdentificacion, input[name='ctl00$MainContent$txbNumeroIdentificacion']"
                ).fill(numero_id)
                logger.info("Número identificación: %s", numero_id)

                datepicker = page.locator("#MainContent_datepicker, input[name='ctl00$MainContent$datepicker']")
                datepicker.click()
                datepicker.fill("")
                datepicker.fill(fecha)
                logger.info("Fecha (DD/MM/YYYY): %s", fecha)
                try:
                    page.keyboard.press("Tab")
                except Exception as e:
                    logger.debug("Tab tras fecha: %s", e)
                cerrar_datepicker_jquery_ui(page)
                logger.info("Datepicker cerrado (listo para captcha / Verificar).")

            preparar_formulario()
            mensaje = page.locator("#MainContent_lblMessage, span[id='MainContent_lblMessage']")
            ultima_firma_captcha = ""
            repeticiones_captcha = 0
            fallos_captcha_fuente = 0
            reinicios_formulario = 0

            logger.info("Paso 4/… Bucle captcha (máx. %s intentos)", MAX_INTENTOS_CAPTCHA)
            for intento in range(1, MAX_INTENTOS_CAPTCHA + 1):
                logger.info("--- Intento captcha %s/%s ---", intento, MAX_INTENTOS_CAPTCHA)
                try:
                    cap_img = localizar_imagen_captcha(page)
                except Exception as e:
                    fallos_captcha_fuente += 1
                    logger.warning(
                        "Captcha no localizado (fallo %s/%s): %s",
                        fallos_captcha_fuente,
                        MAX_FALLOS_CAPTCHA_FUENTE,
                        e,
                    )
                    if fallos_captcha_fuente >= MAX_FALLOS_CAPTCHA_FUENTE:
                        if reinicios_formulario < MAX_REINICIOS_FORMULARIO:
                            reinicios_formulario += 1
                            logger.warning(
                                "Se alcanzaron %s fallos de captcha. Reiniciando flujo de formulario (%s/%s)…",
                                MAX_FALLOS_CAPTCHA_FUENTE,
                                reinicios_formulario,
                                MAX_REINICIOS_FORMULARIO,
                            )
                            preparar_formulario()
                            fallos_captcha_fuente = 0
                            ultima_firma_captcha = ""
                            repeticiones_captcha = 0
                            time.sleep(0.8)
                            continue
                        return {
                            "estado": "ERROR_PAGINA_CAPTCHA",
                            "motivo": (
                                "La página no está entregando captcha válido "
                                "(imagen ausente/no cargada) tras varios reinicios."
                            ),
                            "archivo_html": "",
                        }
                    forzar_renovacion_captcha(page)
                    time.sleep(0.6)
                    continue
                try:
                    png = obtener_png_captcha(page, cap_img)
                except Exception as e:
                    logger.warning("No se pudo obtener imagen captcha en intento %s: %s", intento, e)
                    fallos_captcha_fuente += 1
                    if fallos_captcha_fuente >= MAX_FALLOS_CAPTCHA_FUENTE:
                        if reinicios_formulario < MAX_REINICIOS_FORMULARIO:
                            reinicios_formulario += 1
                            logger.warning(
                                "Captcha inválido repetido. Reiniciando flujo de formulario (%s/%s)…",
                                reinicios_formulario,
                                MAX_REINICIOS_FORMULARIO,
                            )
                            preparar_formulario()
                            fallos_captcha_fuente = 0
                            ultima_firma_captcha = ""
                            repeticiones_captcha = 0
                            time.sleep(0.8)
                            continue
                        return {
                            "estado": "ERROR_PAGINA_CAPTCHA",
                            "motivo": (
                                "La página no está entregando captcha válido "
                                "(fallo al capturar imagen) tras varios reinicios."
                            ),
                            "archivo_html": "",
                        }
                    forzar_renovacion_captcha(page)
                    time.sleep(0.6)
                    continue
                ok_png, motivo_png = validar_png_captcha(png)
                if not ok_png:
                    logger.warning(
                        "Captcha inválido/no cargado (%s). Forzando renovación e intentando de nuevo…",
                        motivo_png,
                    )
                    fallos_captcha_fuente += 1
                    if fallos_captcha_fuente >= MAX_FALLOS_CAPTCHA_FUENTE:
                        if reinicios_formulario < MAX_REINICIOS_FORMULARIO:
                            reinicios_formulario += 1
                            logger.warning(
                                "Captcha inválido/no cargado %s veces. Reiniciando formulario (%s/%s)…",
                                MAX_FALLOS_CAPTCHA_FUENTE,
                                reinicios_formulario,
                                MAX_REINICIOS_FORMULARIO,
                            )
                            preparar_formulario()
                            fallos_captcha_fuente = 0
                            ultima_firma_captcha = ""
                            repeticiones_captcha = 0
                            time.sleep(0.8)
                            continue
                        return {
                            "estado": "ERROR_PAGINA_CAPTCHA",
                            "motivo": (
                                "La página devuelve contenido inválido en el captcha "
                                "(HTML/imagen rota) tras varios reinicios."
                            ),
                            "archivo_html": "",
                        }
                    forzar_renovacion_captcha(page)
                    time.sleep(0.6)
                    continue
                fallos_captcha_fuente = 0

                firma = hashlib.sha1(png).hexdigest()
                if firma == ultima_firma_captcha:
                    repeticiones_captcha += 1
                else:
                    repeticiones_captcha = 0
                    ultima_firma_captcha = firma
                if repeticiones_captcha >= 1:
                    logger.warning(
                        "Captcha repetido detectado (hash igual). Forzando renovación e intentando de nuevo…"
                    )
                    forzar_renovacion_captcha(page)
                    continue

                pil = Image.open(io.BytesIO(png))
                texto, estrategia = leer_captcha_multipass(pil)
                if captchas_dir is not None:
                    guardar_intento_captcha(captchas_dir, intento, pil, texto, estrategia)
                logger.info("Texto OCR (5 caracteres esperados): %r (longitud=%s)", texto, len(texto))
                if len(texto) != 5:
                    logger.warning(
                        "OCR no devolvió 5 caracteres; forzando renovación de captcha y reintentando…"
                    )
                    forzar_renovacion_captcha(page)
                    time.sleep(0.6)
                    cerrar_datepicker_jquery_ui(page)
                    continue

                page.locator("#MainContent_txtCaptcha, input[name='ctl00$MainContent$txtCaptcha']").fill(texto)
                logger.info("Captcha escrito en txtCaptcha; pulsando Verificar…")
                cerrar_datepicker_jquery_ui(page)
                page.locator("#MainContent_btnVerify, input[name='ctl00$MainContent$btnVerify']").click()

                try:
                    mensaje.wait_for(state="visible", timeout=POST_VERIFY_WAIT_MS)
                    logger.debug("lblMessage visible")
                except PlaywrightTimeoutError:
                    logger.warning(
                        "lblMessage no quedó visible en %s ms; se leerá el texto igualmente",
                        POST_VERIFY_WAIT_MS,
                    )

                txt = ""
                try:
                    txt = (mensaje.inner_text() or "").strip()
                except Exception as e:
                    logger.debug("No se pudo leer inner_text de lblMessage: %s", e)

                logger.info("Mensaje verificación captcha: %r", txt)

                if "Texto Válido" in txt or "Texto Valido" in txt:
                    logger.info("Captcha aceptado (Texto Válido).")
                    break
                if "Texto Inválido" in txt or "Texto Invalido" in txt:
                    if intento == MAX_INTENTOS_CAPTCHA:
                        raise RuntimeError("Captcha inválido tras el máximo de intentos.")
                    logger.warning(
                        "Captcha rechazado; el sitio regenera la imagen y limpia el campo. "
                        "Forzando renovación por seguridad y reintentando OCR…"
                    )
                    forzar_renovacion_captcha(page)
                    time.sleep(0.85)
                    cerrar_datepicker_jquery_ui(page)
                    continue
                # Mensaje distinto o vacío: reintentar
                logger.warning("Mensaje inesperado o vacío; reintentando en 0.5s…")
                if intento == MAX_INTENTOS_CAPTCHA:
                    raise RuntimeError(f"No se obtuvo mensaje esperado. Último texto: {txt!r}")
                time.sleep(0.5)
            else:
                raise RuntimeError("No se validó el captcha.")

            logger.info("Paso 5/… Pulsando Consultar")
            page.locator("#MainContent_btnConsultar, input[name='ctl00$MainContent$btnConsultar']").click()
            esperar_tras_consultar(page)
            logger.info("Tras Consultar. URL: %s", page.url)

            # Reglas de negocio: si el portal responde "sin información", no guardar HTML.
            texto_pagina = (page.locator("body").inner_text() or "").strip()
            motivo_no_exitoso = detectar_mensaje_no_exitoso(texto_pagina)
            if motivo_no_exitoso:
                logger.warning("Consulta no exitosa por mensaje final: %s", motivo_no_exitoso)
                return {
                    "estado": "NO_EXITOSA_NEGOCIO",
                    "motivo": motivo_no_exitoso,
                    "archivo_html": "",
                }

            selector_reporte = "#ctl00_MainContent_rvConsulta_ctl13"

            def extraer_ctl13() -> str | None:
                for fr in page.frames:
                    try:
                        loc = fr.locator(selector_reporte)
                        if loc.count() > 0:
                            return loc.first.evaluate("el => el.outerHTML")
                    except Exception:
                        continue
                try:
                    loc = page.locator(selector_reporte)
                    if loc.count() > 0:
                        return loc.first.evaluate("el => el.outerHTML")
                except Exception:
                    pass
                return None

            logger.info("Paso 6/… Esperando elemento reporte %s", selector_reporte)
            html_fragment = None
            for espera in range(60):
                html_fragment = extraer_ctl13()
                if html_fragment:
                    logger.info("Elemento encontrado tras ~%s s de sondeo", espera + 1)
                    break
                if espera == 0:
                    logger.info("Aún no visible; reintentando cada 1s (máx. 60s)…")
                time.sleep(1.0)
            if not html_fragment:
                logger.info("Esperando selector con wait_for_selector (hasta 120s)…")
                page.wait_for_selector(selector_reporte, state="attached", timeout=120000)
                html_fragment = extraer_ctl13()
            if not html_fragment:
                raise RuntimeError(
                    "No se encontró ctl00_MainContent_rvConsulta_ctl13 (¿cambió el id del ReportViewer?)."
                )

            logger.info(
                "Reporte detectado (bloque ctl13 aprox. %s caracteres). Exportando página completa…",
                len(html_fragment),
            )
            html_completo = exportar_html_pagina_completa(page)
            salida_final.write_text(html_completo, encoding="utf-8")
            logger.info("Paso 7/… Archivo guardado: %s", salida_final.resolve())
            logger.info("Proceso finalizado correctamente.")
            return {
                "estado": "EXITOSA",
                "motivo": "OK",
                "archivo_html": str(salida_final.resolve()),
            }

        finally:
            logger.info("Cerrando contexto y navegador…")
            context.close()
            browser.close()
            logger.info("Navegador cerrado.")


def main() -> None:
    ap = argparse.ArgumentParser(description="Bot consulta RUAF (captura HTML del reporte).")
    ap.add_argument(
        "-o",
        "--output",
        type=Path,
        default=RUAF_DATA_DIR / "salidas_ruaf",
        help="Carpeta o ruta base de salida (archivo final: NUMEROID_YYYYMMDD_HHMMSS.html)",
    )
    ap.add_argument("--numero", default=DEFAULT_NUMERO_ID, help="Número de identificación")
    ap.add_argument("--fecha", default=DEFAULT_FECHA, help='Fecha DD/MM/YYYY (ej. "14/04/2026")')
    ap.add_argument("--tipo-doc", default=DEFAULT_TIPO_DOC_TEXTO, help='Texto exacto del desplegable')
    ap.add_argument("--headed", action="store_true", help="Mostrar navegador (depuración)")
    ap.add_argument(
        "-v",
        "--verbose",
        action="store_true",
        help="Log DEBUG (OCR crudo, selectores, excepciones internas)",
    )
    ap.add_argument(
        "--save-captchas",
        action="store_true",
        help="Guardar cada intento: PNG del captcha + meta (ocr, estrategia) en --captchas-dir",
    )
    ap.add_argument(
        "--captchas-dir",
        type=Path,
        default=None,
        help=f"Carpeta para capturas de captcha (por defecto: junto al script, carpeta captcha_intentos)",
    )
    ap.add_argument(
        "--registro-csv",
        type=Path,
        default=RUAF_DATA_DIR / "ruaf_consultas.csv",
        help="Ruta del CSV de auditoría de consultas",
    )
    args = ap.parse_args()

    configurar_logging(verbose=args.verbose)
    _silenciar_logs_ruidosos()
    logging.getLogger("playwright").setLevel(logging.WARNING)

    captchas_dir: Path | None = None
    if args.save_captchas:
        captchas_dir = args.captchas_dir or (RUAF_DATA_DIR / "captcha_intentos")

    try:
        resultado = ejecutar_consulta(
            salida_html=args.output,
            numero_id=args.numero,
            fecha=args.fecha,
            tipo_doc=args.tipo_doc,
            headless=not args.headed,
            captchas_dir=captchas_dir,
        )
        registrar_consulta_csv(
            args.registro_csv,
            numero_id=args.numero,
            fecha=args.fecha,
            tipo_doc=args.tipo_doc,
            estado=resultado["estado"],
            motivo=resultado["motivo"],
            archivo_html=resultado["archivo_html"],
        )
        logger.info("Registro CSV actualizado: %s", args.registro_csv.resolve())
    except KeyboardInterrupt:
        registrar_consulta_csv(
            args.registro_csv,
            numero_id=args.numero,
            fecha=args.fecha,
            tipo_doc=args.tipo_doc,
            estado="ERROR_BOT",
            motivo="Interrumpido por el usuario (Ctrl+C)",
            archivo_html="",
        )
        logger.warning("Interrumpido por el usuario (Ctrl+C).")
        sys.exit(130)
    except Exception:
        registrar_consulta_csv(
            args.registro_csv,
            numero_id=args.numero,
            fecha=args.fecha,
            tipo_doc=args.tipo_doc,
            estado="ERROR_BOT",
            motivo="Excepción durante ejecución (ver logs)",
            archivo_html="",
        )
        logger.exception("Error durante la consulta RUAF")
        sys.exit(1)


if __name__ == "__main__":
    main()
