from __future__ import annotations

from pathlib import Path

from common.logging_config import configurar_logging, silenciar_logs_ruidosos
from common.storage import registrar_consulta
from . import bot


def run_ruaf_bot(
    *,
    numero_id: str,
    fecha: str,
    tipo_doc: str = "CEDULA DE CIUDADANIA",
    headless: bool = True,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    save_captchas: bool = False,
    captchas_dir: Path | None = None,
    verbose: bool = False,
) -> dict[str, str]:
    base_dir = Path(__file__).resolve().parent

    salida_html = output_dir or (base_dir / "salidas_ruaf")
    registro = registro_csv or (base_dir / "ruaf_consultas.csv")
    carpeta_captchas = None
    if save_captchas:
        carpeta_captchas = captchas_dir or (base_dir / "captcha_intentos")

    configurar_logging(verbose=verbose)
    silenciar_logs_ruidosos()

    resultado = bot.ejecutar_consulta(
        salida_html=salida_html,
        numero_id=numero_id,
        fecha=fecha,
        tipo_doc=tipo_doc,
        headless=headless,
        captchas_dir=carpeta_captchas,
    )
    registrar_consulta(
        tabla_db="ruaf_consultas",
        csv_path=registro,
        numero_id=numero_id,
        estado=resultado["estado"],
        motivo=resultado["motivo"],
        archivo_original=resultado["archivo_html"],
        campos_extra=resultado.get("datos_extraidos"),
    )
    return resultado
