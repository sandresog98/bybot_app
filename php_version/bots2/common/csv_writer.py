from __future__ import annotations

import csv
from datetime import datetime
from pathlib import Path

from common.timezone_utils import ZONA_BOGOTA

CAMPOS_BASE = [
    "fecha_hora",
    "numero_id",
    "estado",
    "motivo",
    "archivo_original",
]


def _sanear_motivo(texto: str, max_chars: int = 220) -> str:
    if not texto:
        return ""
    s = texto.strip()
    for marcador in ("Call log:", "Traceback (most recent call last):"):
        if marcador in s:
            s = s.split(marcador, 1)[0].strip()
    s = " ".join(s.split())
    if len(s) > max_chars:
        s = s[: max_chars - 3].rstrip() + "..."
    return s


def registrar_consulta_csv(
    ruta_csv: Path,
    *,
    numero_id: str,
    estado: str,
    motivo: str,
    archivo_original: str = "",
    campos_extra: dict[str, str] | None = None,
) -> None:
    ruta_csv.parent.mkdir(parents=True, exist_ok=True)

    encabezados = list(CAMPOS_BASE)
    if campos_extra:
        for k in campos_extra:
            if k not in encabezados:
                encabezados.append(k)

    fila = {
        "fecha_hora": datetime.now(ZONA_BOGOTA).strftime("%Y-%m-%d %H:%M:%S"),
        "numero_id": numero_id,
        "estado": estado,
        "motivo": _sanear_motivo(motivo),
        "archivo_original": (archivo_original or "").strip(),
    }
    if campos_extra:
        fila.update(campos_extra)

    existe = ruta_csv.exists() and ruta_csv.stat().st_size > 0
    with ruta_csv.open("a", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=encabezados)
        if not existe:
            w.writeheader()
        w.writerow(fila)
