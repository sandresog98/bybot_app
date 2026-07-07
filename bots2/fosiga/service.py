from __future__ import annotations

from pathlib import Path

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
from . import bot


def run_fosiga_bot(
    *,
    numero_documento: str = "1022434547",
    headless: bool = False,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    keep_open_after_step: bool = False,
    verbose: bool = False,
) -> dict[str, str]:
    configurar_logging(verbose=verbose)
    silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        numero_documento=numero_documento,
        headless=headless,
        output_dir=output_dir,
        keep_open_after_step=keep_open_after_step,
    )

    registro = registro_csv or (Path(__file__).resolve().parent / "fosiga_consultas.csv")
    registrar_consulta(
        tabla_db="fosiga_consultas",
        csv_path=registro,
        numero_id=numero_documento,
        estado=resultado.get("estado", ""),
        motivo=resultado.get("motivo", ""),
        archivo_original=resultado.get("archivo_html", "") or "",
        campos_extra={"url_final": resultado.get("url_final", "")},
    )
    return resultado
