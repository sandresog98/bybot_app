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
    numero_busqueda: str,
    estado: str,
    motivo: str,
    url_final: str,
    archivo_html: str,
) -> None:
    registro_csv.parent.mkdir(parents=True, exist_ok=True)
    encabezados_default = [
        "fecha_hora",
        "numero_busqueda",
        "estado",
        "motivo",
        "archivo_html",
        "url_final",
    ]
    encabezados_legacy = ["fecha_hora", "numero_busqueda", "estado", "motivo", "url_final"]
    crear_encabezado = (not registro_csv.exists()) or registro_csv.stat().st_size == 0
    encabezados = encabezados_default
    if not crear_encabezado:
        with registro_csv.open("r", newline="", encoding="utf-8") as fh_lectura:
            primera = fh_lectura.readline().strip()
            if primera:
                actuales = [c.strip() for c in primera.split(",")]
                if actuales == encabezados_legacy:
                    encabezados = encabezados_legacy

    fila = {
        "fecha_hora": datetime.now(ZONA_BOGOTA).strftime("%Y-%m-%d %H:%M:%S"),
        "numero_busqueda": numero_busqueda,
        "estado": estado,
        "motivo": motivo,
        "archivo_html": archivo_html,
        "url_final": url_final,
    }

    with registro_csv.open("a", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(fh, fieldnames=encabezados)
        if crear_encabezado:
            writer.writeheader()
        writer.writerow({k: fila.get(k, "") for k in encabezados})


def run_rues_bot(
    *,
    numero_busqueda: str = "52727688",
    headless: bool = True,
    registro_csv: Path | None = None,
    keep_open_after_step: bool = False,
    verbose: bool = False,
) -> dict[str, str]:
    """
    API para ejecutar el bot RUES.
    Alcance actual: abrir RUES, cerrar aviso inicial, diligenciar búsqueda y pulsar buscar.
    """
    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()
    resultado = bot.ejecutar_consulta(
        numero_busqueda=numero_busqueda,
        headless=headless,
        keep_open_after_step=keep_open_after_step,
    )
    registro = registro_csv or (Path(__file__).resolve().parent / "rues_consultas.csv")
    _registrar_consulta_csv(
        registro,
        numero_busqueda=numero_busqueda,
        estado=resultado.get("estado", ""),
        motivo=resultado.get("motivo", ""),
        url_final=resultado.get("url_final", "") or "",
        archivo_html=resultado.get("archivo_html", "") or "",
    )
    return resultado
