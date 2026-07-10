from __future__ import annotations

import csv
from datetime import datetime
from pathlib import Path
from zoneinfo import ZoneInfo

from . import bot

ZONA_BOGOTA = ZoneInfo("America/Bogota")


def _registrar_consulta_csv(
    registro_csv: Path,
    *,
    numero_documento: str,
    estado: str,
    motivo: str,
    archivo_html: str,
    url_final: str,
) -> None:
    registro_csv.parent.mkdir(parents=True, exist_ok=True)
    encabezados = [
        "fecha_hora",
        "numero_documento",
        "estado",
        "motivo",
        "archivo_html",
        "url_final",
    ]
    crear_encabezado = (not registro_csv.exists()) or registro_csv.stat().st_size == 0
    with registro_csv.open("a", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=encabezados)
        if crear_encabezado:
            w.writeheader()
        w.writerow(
            {
                "fecha_hora": datetime.now(ZONA_BOGOTA).strftime("%Y-%m-%d %H:%M:%S"),
                "numero_documento": numero_documento,
                "estado": estado,
                "motivo": motivo,
                "archivo_html": archivo_html,
                "url_final": url_final,
            }
        )


def run_fosiga_bot(
    *,
    numero_documento: str = "1022434547",
    headless: bool = False,
    output_dir: Path | None = None,
    registro_csv: Path | None = None,
    keep_open_after_step: bool = False,
    verbose: bool = False,
) -> dict[str, str]:
    """
    API para ejecutar el bot FOSIGA (ADRES - Consulte su EPS).
    Alcance actual: abrir portal y diligenciar campo de número.
    """
    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()
    resultado = bot.ejecutar_consulta(
        numero_documento=numero_documento,
        headless=headless,
        output_dir=output_dir,
        keep_open_after_step=keep_open_after_step,
    )
    registro = registro_csv or (Path(__file__).resolve().parent / "fosiga_consultas.csv")
    _registrar_consulta_csv(
        registro,
        numero_documento=numero_documento,
        estado=resultado.get("estado", ""),
        motivo=resultado.get("motivo", ""),
        archivo_html=resultado.get("archivo_html", "") or "",
        url_final=resultado.get("url_final", "") or "",
    )
    return resultado
