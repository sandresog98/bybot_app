from __future__ import annotations

from pathlib import Path

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
from . import bot


def run_simpleco_bot(
    *,
    numero_documento: str,
    headless: bool = True,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    verbose: bool = False,
) -> dict[str, str | int]:
    base_dir = Path(__file__).resolve().parent
    salida = output_dir or (base_dir / "salidas_simpleco")
    registro = registro_csv or (base_dir / "simpleco_consultas.csv")

    configurar_logging(verbose=verbose)
    silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        salida_pdf=salida,
        numero_documento=numero_documento,
        headless=headless,
    )
    registrar_consulta(
        tabla_db="simpleco_consultas",
        csv_path=registro,
        numero_id=numero_documento,
        estado=str(resultado.get("estado", "")),
        motivo=str(resultado.get("motivo", "")),
        archivo_original=str(resultado.get("archivo_pdf", "") or ""),
        campos_extra={
            "periodo_mes": str(resultado.get("periodo_mes", "")),
            "periodo_anio": str(resultado.get("periodo_anio", "")),
        },
    )
    return resultado
