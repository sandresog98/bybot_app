from __future__ import annotations

from pathlib import Path

from . import bot


def run_suaporte_bot(
    *,
    numero_documento: str = "1073710057",
    headless: bool = True,
    registro_csv: Path | None = None,
    url_inicio: str | None = None,
    output_dir: Path | None = None,
    keep_open_after_step: bool = False,
    slow_mo_ms: int = 0,
    delay_entre_pasos_s: float = 0.0,
    delay_teclas_ms: int = 0,
    verbose: bool = False,
) -> dict[str, str]:
    """
    API para ejecutar el bot (ver ``bot.NOMBRE_BOT``).
    Flujo: documento -> Consultar; periodo Marzo + anio Bogota; 2.º Consultar; descarga PDF planilla;
    dialogo sin datos; sin pagos 6 meses; Borrar. PDF en ``output_dir`` (default ``salidas_suaporte/``).
    """
    base_dir = Path(__file__).resolve().parent
    csv_path = registro_csv or (base_dir / "suaporte_consultas.csv")

    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        numero_documento=numero_documento,
        headless=headless,
        url_inicio=url_inicio,
        output_dir=output_dir,
        keep_open_after_step=keep_open_after_step,
        slow_mo_ms=slow_mo_ms,
        delay_entre_pasos_s=delay_entre_pasos_s,
        delay_teclas_ms=delay_teclas_ms,
    )
    bot.registrar_consulta_csv(
        csv_path,
        numero_id=numero_documento,
        estado=resultado.get("estado", ""),
        motivo=resultado.get("motivo", ""),
        url_final=resultado.get("url_final", ""),
        archivo_pdf=resultado.get("archivo_pdf", "") or "",
    )
    return resultado
