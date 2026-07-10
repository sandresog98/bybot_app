from __future__ import annotations

from pathlib import Path

from . import bot


def run_simpleco_bot(
    *,
    numero_documento: str,
    headless: bool = True,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    verbose: bool = False,
) -> dict[str, str | int]:
    """
    API para ejecutar el bot Simple.co desde otros scripts.
    Devuelve: estado, motivo, archivo_pdf, periodo_mes, periodo_anio.
    """
    base_dir = Path(__file__).resolve().parent
    salida = output_dir or (base_dir / "salidas_simpleco")
    registro = registro_csv or (base_dir / "simpleco_consultas.csv")

    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        salida_pdf=salida,
        numero_documento=numero_documento,
        headless=headless,
    )
    bot.registrar_consulta_csv(
        registro,
        numero_id=numero_documento,
        periodo_mes=int(resultado["periodo_mes"]),
        periodo_anio=int(resultado["periodo_anio"]),
        estado=str(resultado["estado"]),
        motivo=str(resultado["motivo"]),
        archivo_pdf=str(resultado.get("archivo_pdf", "") or ""),
    )
    return resultado
