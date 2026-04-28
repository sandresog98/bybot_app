from __future__ import annotations

from pathlib import Path

from interssi import bot


def run_asopagos_bot(
    *,
    numero_id: str,
    headless: bool = True,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    url_inicio: str | None = None,
    captcha_text: str | None = None,
    captchas_dir: Path | None = None,
    captcha_interactivo: bool = False,
    keep_open_after_consultar: bool = True,
    verbose: bool = False,
) -> dict[str, str]:
    """
    API para ejecutar el bot ASOPAGOS desde otros scripts.
    Devuelve: estado, motivo, archivo_pdf.
    """
    base_dir = Path(__file__).resolve().parent
    salida = output_dir or (base_dir / "salidas_asopagos")
    registro = registro_csv or (base_dir / "asopagos_consultas.csv")
    captchas = captchas_dir or (base_dir / "captcha_intentos")

    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        salida_pdf=salida,
        numero_id=numero_id,
        headless=headless,
        url_inicio=url_inicio,
        captcha_text=captcha_text,
        captchas_dir=captchas,
        captcha_interactivo=captcha_interactivo,
        keep_open_after_consultar=keep_open_after_consultar,
    )
    bot.registrar_consulta_csv(
        registro,
        numero_id=numero_id,
        estado=resultado["estado"],
        motivo=resultado["motivo"],
        archivo_pdf=resultado.get("archivo_pdf", "") or "",
    )
    return resultado
