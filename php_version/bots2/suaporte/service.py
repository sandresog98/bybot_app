from __future__ import annotations

from pathlib import Path

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
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
    base_dir = Path(__file__).resolve().parent
    csv_path = registro_csv or (base_dir / "suaporte_consultas.csv")

    configurar_logging(verbose=verbose)
    silenciar_logs_ruidosos()

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
    registrar_consulta(
        tabla_db="suaporte_consultas",
        csv_path=csv_path,
        numero_id=numero_documento,
        estado=resultado.get("estado", ""),
        motivo=resultado.get("motivo", ""),
        archivo_original=resultado.get("archivo_pdf", "") or "",
        campos_extra={"url_final": resultado.get("url_final", "")},
    )
    return resultado
