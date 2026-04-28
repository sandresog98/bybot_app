from __future__ import annotations

from pathlib import Path

from . import bot


def run_aportesenlinea_bot(
    *,
    numero_id: str,
    fecha_expedicion: str = "26-MAR-13",
    eps: str = "NUEVA E.P.S.",
    headless: bool = True,
    keep_open_after_fill: bool = True,
    captcha_interactivo: bool = True,
    modo_lento: bool = True,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    verbose: bool = False,
) -> dict[str, str]:
    """
    API para ejecutar el bot de Aportes en Línea.
    Descarga el certificado de aportes en PDF y registra la consulta en CSV.
    """
    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        numero_id=numero_id,
        fecha_expedicion=fecha_expedicion,
        eps=eps,
        headless=headless,
        keep_open_after_fill=keep_open_after_fill,
        captcha_interactivo=captcha_interactivo,
        modo_lento=modo_lento,
        output_dir=output_dir,
    )

    csv_path = registro_csv or bot.CSV_DEFAULT
    bot.registrar_consulta_csv(
        csv_path,
        numero_id=numero_id,
        estado=resultado["estado"],
        motivo=resultado.get("motivo", ""),
        archivo_pdf=resultado.get("archivo_pdf", ""),
    )
    return resultado
