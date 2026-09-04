from __future__ import annotations

import logging
from pathlib import Path

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
from . import bot
from .parser import parsear_comprobante, merge_para_campos_extra

logger = logging.getLogger(__name__)


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

    campos_extra: dict[str, object] = {
        "periodo_mes": str(resultado.get("periodo_mes", "")),
        "periodo_anio": str(resultado.get("periodo_anio", "")),
    }

    # Si se descargó el comprobante, extraer sus datos y persistirlos.
    archivo_pdf = str(resultado.get("archivo_pdf", "") or "")
    if resultado.get("estado") == "EXITOSA" and archivo_pdf and Path(archivo_pdf).exists():
        try:
            parsed = parsear_comprobante(archivo_pdf)
            campos_extra.update(merge_para_campos_extra(parsed))
        except Exception as e:
            logger.warning("Fallo al parsear el comprobante Simple.co: %s", e)

    registrar_consulta(
        tabla_db="simpleco_consultas",
        csv_path=registro,
        numero_id=numero_documento,
        estado=str(resultado.get("estado", "")),
        motivo=str(resultado.get("motivo", "")),
        archivo_original=archivo_pdf,
        campos_extra=campos_extra,
    )
    return resultado
