#!/usr/bin/env python3
from __future__ import annotations

import logging
import sys
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

from playwright.sync_api import BrowserContext, Frame, Page, sync_playwright

logger = logging.getLogger(__name__)

URL_CONSULTA_EPS = "https://www.adres.gov.co/consulte-su-eps"
FOSIGA_DATA_DIR = Path(__file__).resolve().parent
ZONA_BOGOTA = ZoneInfo("America/Bogota")


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


def _obtener_frame_formulario(page_url_frames: list[Frame]) -> Frame:
    for frame in page_url_frames:
        if "BDUA_Internet/Pages/ConsultarAfiliadoWeb_2.aspx" in frame.url:
            return frame
    raise RuntimeError("No se encontró el iframe del formulario de consulta EPS.")


def _diligenciar_numero(frame: Frame, numero_documento: str) -> None:
    # Estrategia con varios selectores para tolerar cambios menores del portal.
    selectores = [
        frame.locator("input#txtNumDoc").first,
        frame.locator("input[name='txtNumDoc']").first,
        frame.get_by_label("Número", exact=False).first,
        frame.locator("input[id*='num' i], input[name*='num' i]").first,
        frame.locator("input[type='text']").first,
    ]

    ultimo_error: Exception | None = None
    for campo in selectores:
        try:
            campo.wait_for(state="visible", timeout=8000)
            campo.fill(numero_documento)
            valor = campo.input_value(timeout=2000)
            if valor.strip() == numero_documento:
                return
        except Exception as exc:
            ultimo_error = exc

    if ultimo_error:
        raise RuntimeError(
            "No se pudo ubicar o diligenciar el campo 'Número' en ADRES."
        ) from ultimo_error
    raise RuntimeError("No se pudo diligenciar el campo 'Número' en ADRES.")


def _clic_consultar_y_capturar_pestana(
    *, frame: Frame, context: BrowserContext
) -> Page | None:
    boton = frame.locator("input#btnConsultar").first
    boton.wait_for(state="visible", timeout=10000)
    logger.info("Clic en consultar")
    paginas_antes = set(context.pages)
    boton.click(timeout=15000)

    # Evita race conditions de expect_page en algunos escenarios headless.
    limite = datetime.now().timestamp() + 35
    while datetime.now().timestamp() < limite:
        for pagina in context.pages:
            if pagina in paginas_antes:
                continue
            try:
                pagina.wait_for_load_state("domcontentloaded", timeout=15000)
            except Exception:
                pass
            return pagina
        frame.page.wait_for_timeout(500)
    return None


def _guardar_html_resultado(html: str, *, output_dir: Path, numero_documento: str) -> Path:
    output_dir.mkdir(parents=True, exist_ok=True)
    ts = datetime.now(ZONA_BOGOTA).strftime("%Y%m%d_%H%M%S")
    archivo_html = output_dir / f"fosiga_{numero_documento}_{ts}.html"
    archivo_html.write_text(html, encoding="utf-8")
    return archivo_html


def _esperar_html_resultado_en_frame(frame: Frame, *, timeout_ms: int = 180000) -> str:
    html_inicial = frame.content()
    inicio = datetime.now().timestamp()
    while (datetime.now().timestamp() - inicio) * 1000 < timeout_ms:
        try:
            frame.page.wait_for_timeout(700)
            html_actual = frame.content()
            if _es_texto_bloqueo_validacion(html_actual):
                raise RuntimeError(
                    "La validación/captcha bloqueó la continuación del flujo en el iframe."
                )
            if html_actual != html_inicial and len(html_actual) > 500:
                return html_actual
        except RuntimeError:
            raise
        except Exception as exc:
            raise RuntimeError(
                "El navegador/carga se cerró antes de obtener la página de resultado."
            ) from exc
    raise RuntimeError(
        "No se detectó carga de página de resultado después de Consultar. "
        "Es posible que falte completar captcha/validación."
    )


def _es_no_encontrado_bdua(html: str) -> bool:
    t = " ".join(html.lower().split())
    return ("no se encuentra en bdua" in t) or ("no se encuentra en la bdua" in t)


def _leer_token_recaptcha(frame: Frame) -> str:
    try:
        return frame.locator("#recaptchaToken").input_value(timeout=1500) or ""
    except Exception:
        return ""


def _es_texto_bloqueo_validacion(html: str) -> bool:
    t = " ".join(html.lower().split())
    patrones = [
        "no soy un robot",
        "debe desactivar el bloqueo predeterminado de las ventanas emergentes",
        "imágenes de validación que genera la prueba captcha",
    ]
    return any(p in t for p in patrones)


def _intentar_generar_token_recaptcha(frame: Frame) -> str:
    script = """
() => new Promise((resolve) => {
  try {
    const input = document.querySelector('#recaptchaToken');
    const current = (input && input.value) ? input.value : '';
    if (current) {
      resolve(current);
      return;
    }
    if (!(window.grecaptcha && grecaptcha.enterprise && grecaptcha.enterprise.execute)) {
      resolve('');
      return;
    }
    grecaptcha.enterprise.ready(async () => {
      try {
        const token = await grecaptcha.enterprise.execute(
          '6LdjqjksAAAAAAduGUnDTl7-kSoeSDI7S-vAazXp',
          { action: 'btnConsultar' }
        );
        if (input) input.value = token || '';
        resolve(token || '');
      } catch (e) {
        resolve('');
      }
    });
  } catch (e) {
    resolve('');
  }
})
"""
    try:
        return frame.evaluate(script) or ""
    except Exception:
        return ""


def _asegurar_token_recaptcha(frame: Frame, *, headless: bool) -> str:
    token = _leer_token_recaptcha(frame)
    if token:
        return token

    token = _intentar_generar_token_recaptcha(frame)
    if token:
        return token

    espera_max_s = 45 if not headless else 10
    inicio = datetime.now().timestamp()
    while datetime.now().timestamp() - inicio < espera_max_s:
        frame.page.wait_for_timeout(700)
        token = _leer_token_recaptcha(frame)
        if token:
            return token
    return ""


def ejecutar_consulta(
    *,
    numero_documento: str,
    headless: bool,
    output_dir: Path | None = None,
    keep_open_after_step: bool = False,
) -> dict[str, str]:
    logger.info("Abriendo ADRES: Consulte su EPS.")
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
            page.goto(URL_CONSULTA_EPS, wait_until="domcontentloaded", timeout=60000)
            page.wait_for_timeout(2500)
            frame_formulario = _obtener_frame_formulario(page.frames)
            logger.info("Digitando numero de identificacion: %s", numero_documento)
            _diligenciar_numero(frame_formulario, numero_documento)
            token = _asegurar_token_recaptcha(frame_formulario, headless=headless)
            logger.info("Token reCAPTCHA disponible: %s", "SI" if token else "NO")
            nueva_pestana = _clic_consultar_y_capturar_pestana(
                frame=frame_formulario, context=context
            )
            page.wait_for_timeout(2500)

            if nueva_pestana:
                logger.info("Se detecto nueva pestaña; se exporta HTML completo de esa pestaña.")
                html_resultado = nueva_pestana.content()
                url_final = nueva_pestana.url
            else:
                logger.info("No se detecto nueva pestaña; esperando HTML de resultado en el iframe.")
                try:
                    html_resultado = _esperar_html_resultado_en_frame(frame_formulario)
                except RuntimeError as exc:
                    html_actual = frame_formulario.content()
                    if headless and _es_texto_bloqueo_validacion(html_actual):
                        return {
                            "estado": "ERROR",
                            "motivo": (
                                "El portal bloqueó la consulta en modo headless por validación/captcha "
                                "(no se abrió la página de respuesta)."
                            ),
                            "url_final": frame_formulario.url or page.url,
                            "archivo_html": "",
                        }
                    raise exc
                url_final = frame_formulario.url or page.url

            token_len = len(_leer_token_recaptcha(frame_formulario))
            logger.info("Longitud token reCAPTCHA detectada: %s", token_len)

            if _es_no_encontrado_bdua(html_resultado):
                logger.info("Documento no encontrado en BDUA; se finaliza sin guardar HTML.")
                return {
                    "estado": "FINALIZADO",
                    "motivo": (
                        f"El afiliado con número de documento {numero_documento} no se encuentra en BDUA."
                    ),
                    "url_final": url_final,
                    "archivo_html": "",
                }

            salida_dir = output_dir or (FOSIGA_DATA_DIR / "salidas_fosiga")
            archivo_html = _guardar_html_resultado(
                html_resultado, output_dir=salida_dir, numero_documento=numero_documento
            )
            logger.info("HTML exportado desde: %s", url_final)

            return {
                "estado": "EXITOSA",
                "motivo": "Paso completado: número diligenciado, clic en Consultar y HTML exportado.",
                "url_final": url_final,
                "archivo_html": str(archivo_html),
            }
        except Exception as e:
            logger.error("Error en flujo FOSIGA: %s", e)
            return {
                "estado": "ERROR",
                "motivo": str(e),
                "url_final": "",
            }
        finally:
            if (not headless) and keep_open_after_step:
                try:
                    input()
                except EOFError:
                    pass
            context.close()
            browser.close()
