from __future__ import annotations

import hashlib
import logging
import time
from pathlib import Path
from typing import Any

logger = logging.getLogger(__name__)

ESTADISTICAS: dict[str, dict[str, int]] = {}


def registrar_intento(tipo_captcha: str, *, exito: bool, metodo: str) -> None:
    if tipo_captcha not in ESTADISTICAS:
        ESTADISTICAS[tipo_captcha] = {"intentos": 0, "exitos": 0, "fallos": 0}
    ESTADISTICAS[tipo_captcha]["intentos"] += 1
    if exito:
        ESTADISTICAS[tipo_captcha]["exitos"] += 1
    else:
        ESTADISTICAS[tipo_captcha]["fallos"] += 1


def obtener_estadisticas(tipo_captcha: str) -> dict[str, Any]:
    stats = ESTADISTICAS.get(tipo_captcha, {"intentos": 0, "exitos": 0, "fallos": 0})
    total = stats["intentos"]
    tasa = (stats["exitos"] / total * 100) if total > 0 else 0.0
    return {**stats, "tasa_exito": round(tasa, 1)}


def calcular_hash_imagen(imagen_bytes: bytes) -> str:
    return hashlib.sha1(imagen_bytes).hexdigest()


class BucleCaptcha:
    def __init__(self, tipo_captcha: str, max_intentos: int = 40):
        self.tipo = tipo_captcha
        self.max_intentos = max_intentos
        self.intentos = 0
        self.ultimo_hash = ""
        self.repeticiones = 0

    def debe_reintentar(self) -> bool:
        self.intentos += 1
        return self.intentos <= self.max_intentos

    def detectar_repeticion(self, imagen_bytes: bytes) -> bool:
        h = calcular_hash_imagen(imagen_bytes)
        if h == self.ultimo_hash:
            self.repeticiones += 1
        else:
            self.repeticiones = 0
            self.ultimo_hash = h
        return self.repeticiones >= 1

    def resuelto(self, metodo: str) -> None:
        registrar_intento(self.tipo, exito=True, metodo=metodo)

    def fallido(self, metodo: str) -> None:
        registrar_intento(self.tipo, exito=False, metodo=metodo)

    def resumen(self) -> dict[str, Any]:
        stats = obtener_estadisticas(self.tipo)
        return {
            "intentos_usados": self.intentos,
            **stats,
        }


def guardar_intento_captcha(directorio: Path, intento: int, png_bytes: bytes, texto: str, estrategia: str) -> None:
    directorio.mkdir(parents=True, exist_ok=True)
    stamp = time.strftime("%Y%m%d_%H%M%S")
    base = f"intento_{intento:03d}_{stamp}"
    png_path = directorio / f"{base}_original.png"
    meta_path = directorio / f"{base}_meta.txt"
    png_path.write_bytes(png_bytes)
    meta_path.write_text(
        f"intento={intento}\n"
        f"timestamp={stamp}\n"
        f"ocr={texto}\n"
        f"longitud={len(texto)}\n"
        f"estrategia={estrategia}\n",
        encoding="utf-8",
    )
    logger.info("Captcha guardado: %s | %s", png_path.name, meta_path.name)
