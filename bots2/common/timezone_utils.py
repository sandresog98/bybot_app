from __future__ import annotations

from datetime import datetime, timedelta, timezone, tzinfo
from zoneinfo import ZoneInfo


def _zona_america_bogota() -> tzinfo:
    try:
        return ZoneInfo("America/Bogota")
    except Exception:
        return timezone(timedelta(hours=-5), name="COT")


ZONA_BOGOTA = _zona_america_bogota()


def periodo_mes_anterior() -> tuple[int, int]:
    hoy = datetime.now(ZONA_BOGOTA).date()
    primer = hoy.replace(day=1)
    ultimo_mes_anterior = primer - timedelta(days=1)
    return ultimo_mes_anterior.month, ultimo_mes_anterior.year
