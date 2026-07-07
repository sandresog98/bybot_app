#!/usr/bin/env python3
"""
Prueba masiva de bots — ejecuta los bots funcionales con multiples cedulas
y genera un reporte de cobertura.

Uso:
  python3 herramientas/prueba_masiva.py --bots rues,fosiga,suaporte --cedulas cedulas.txt
  python3 herramientas/prueba_masiva.py --bots rues,fosiga --cedulas 1022434547,52727688,79431670
"""

from __future__ import annotations

import argparse
import csv
import logging
import sys
import time
from datetime import datetime
from pathlib import Path

BOTS2_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BOTS2_DIR))

from common.storage import registrar_consulta
from common.timezone_utils import ZONA_BOGOTA
from common.logging_config import configurar_logging, silenciar_logs_ruidosos

logger = logging.getLogger(__name__)

BOTS_DISPONIBLES = {
    "rues": {
        "nombre": "RUES — Registro Mercantil",
        "func": None,
        "modulo": "rues.service",
        "func_name": "run_rues_bot",
        "param": "numero_busqueda",
    },
    "fosiga": {
        "nombre": "FOSIGA — ADRES Consulte su EPS",
        "func": None,
        "modulo": "fosiga.service",
        "func_name": "run_fosiga_bot",
        "param": "numero_documento",
    },
    "suaporte": {
        "nombre": "SuAporte — Comprobante PDF",
        "func": None,
        "modulo": "suaporte.service",
        "func_name": "run_suaporte_bot",
        "param": "numero_documento",
    },
    "simpleco": {
        "nombre": "Simple.co — Comprobante PDF",
        "func": None,
        "modulo": "simpleco.service",
        "func_name": "run_simpleco_bot",
        "param": "numero_documento",
    },
    "aportesenlinea": {
        "nombre": "Aportes en Linea — Certificado PDF",
        "func": None,
        "modulo": "aportesenlinea.service",
        "func_name": "run_aportesenlinea_bot",
        "param": "numero_id",
    },
    "ruaf": {
        "nombre": "RUAF — Afiliacion SISPRO",
        "func": None,
        "modulo": "ruaf.service",
        "func_name": "run_ruaf_bot",
        "param": "numero_id",
    },
}


def cargar_funciones(bot_names: list[str]) -> None:
    import importlib
    for name in bot_names:
        if name not in BOTS_DISPONIBLES:
            logger.warning("Bot desconocido: %s", name)
            continue
        info = BOTS_DISPONIBLES[name]
        try:
            mod = importlib.import_module(info["modulo"])
            info["func"] = getattr(mod, info["func_name"])
            logger.debug("%s cargado", name)
        except Exception as e:
            logger.warning("No se pudo cargar %s: %s", name, e)


def ejecutar_prueba_masiva(
    bots: list[str],
    cedulas: list[str],
    *,
    headless: bool = True,
    pausa_entre_consultas: float = 3.0,
    output_dir: Path | None = None,
) -> list[dict]:
    out = output_dir or (BOTS2_DIR / "resultados_pruebas")
    out.mkdir(parents=True, exist_ok=True)

    cargar_funciones(bots)
    resultados: list[dict] = []
    total = len(cedulas) * len(bots)
    ejecutadas = 0

    reporte_path = out / f"reporte_pruebas_{datetime.now(ZONA_BOGOTA).strftime('%Y%m%d_%H%M%S')}.csv"

    for cedula in cedulas:
        for bot_name in bots:
            ejecutadas += 1
            info = BOTS_DISPONIBLES[bot_name]
            func = info.get("func")
            if not func:
                logger.warning("[%s/%s] %s: SKIP (no cargado)", ejecutadas, total, bot_name)
                continue

            logger.info("[%s/%s] %s | cedula=%s", ejecutadas, total, bot_name, cedula)

            t0 = time.monotonic()
            try:
                kwargs = {info["param"]: cedula, "headless": headless, "verbose": False}
                if bot_name in ("aportesenlinea",):
                    kwargs["captcha_interactivo"] = False
                if bot_name in ("ruaf",):
                    kwargs["fecha"] = "14/04/2026"
                if bot_name == "aportesenlinea":
                    kwargs["fecha_expedicion"] = "26-MAR-13"

                resultado = func(**kwargs)
                duracion = round(time.monotonic() - t0, 1)
                estado = resultado.get("estado", "?")

            except Exception as e:
                duracion = round(time.monotonic() - t0, 1)
                estado = "ERROR_SCRIPT"
                resultado = {"estado": "ERROR_SCRIPT", "motivo": str(e), "archivo_html": "", "archivo_pdf": ""}

            archivo = resultado.get("archivo_html") or resultado.get("archivo_pdf") or ""
            fila = {
                "bot": bot_name,
                "cedula": cedula,
                "estado": estado,
                "motivo": (resultado.get("motivo", "") or "")[:200],
                "duracion_s": duracion,
                "archivo": archivo,
            }
            resultados.append(fila)

            campos_extra = resultado.get("datos_extraidos")
            registrar_consulta(
                tabla_db=f"{bot_name}_consultas",
                csv_path=reporte_path,
                numero_id=cedula,
                estado=estado,
                motivo=resultado.get("motivo", ""),
                archivo_original=archivo,
                campos_extra=campos_extra,
            )

            icono = "OK" if estado == "EXITOSA" else ("~" if estado in ("FINALIZADO", "SIN_PAGOS_6_MESES") else "FAIL")
            logger.info("  -> %s %s (%.1fs)", icono, estado, duracion)

            if ejecutadas < total:
                time.sleep(pausa_entre_consultas)

    return resultados


def generar_reporte_consola(resultados: list[dict], bots: list[str]) -> None:
    if not resultados:
        print("\nSin resultados.")
        return

    total = len(resultados)
    exitosas = sum(1 for r in resultados if r["estado"] == "EXITOSA")
    finalizadas = sum(1 for r in resultados if r["estado"] in ("FINALIZADO", "SIN_PAGOS_6_MESES"))
    errores = total - exitosas - finalizadas
    duraciones = [r["duracion_s"] for r in resultados if r["duracion_s"] > 0]

    print()
    print("=" * 60)
    print("REPORTE DE COBERTURA")
    print("=" * 60)
    print(f"  Total consultas: {total}")
    print(f"  Exitosas:        {exitosas} ({exitosas/total*100:.1f}%)")
    print(f"  Finalizadas:     {finalizadas} ({finalizadas/total*100:.1f}%)")
    print(f"  Errores:         {errores} ({errores/total*100:.1f}%)")
    if duraciones:
        print(f"  Duracion total:  {sum(duraciones):.1f}s")
        print(f"  Duracion prom:   {sum(duraciones)/len(duraciones):.1f}s")
    print()

    print("Por bot:")
    for bot_name in bots:
        res_bot = [r for r in resultados if r["bot"] == bot_name]
        ex = sum(1 for r in res_bot if r["estado"] == "EXITOSA")
        print(f"  {bot_name}: {ex}/{len(res_bot)} exitosas")

    print()
    print("Detalle:")
    for r in resultados:
        icono = "OK" if r["estado"] == "EXITOSA" else ("~" if r["estado"] in ("FINALIZADO", "SIN_PAGOS_6_MESES") else "FAIL")
        print(f"  [{icono}] {r['bot']:12s} {r['cedula']:20s} {r['estado']:25s} {r['duracion_s']:5.1f}s  {r['motivo'][:80]}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Prueba masiva de bots ByBot")
    parser.add_argument(
        "--bots",
        default="rues,fosiga,suaporte",
        help="Bots a ejecutar, separados por coma (default: rues,fosiga,suaporte)",
    )
    parser.add_argument(
        "--cedulas",
        required=True,
        help="Cedulas a probar, separadas por coma o ruta a archivo .txt (una por linea)",
    )
    parser.add_argument(
        "--headed",
        action="store_true",
        help="Mostrar navegador (por defecto headless)",
    )
    parser.add_argument(
        "--pausa",
        type=float,
        default=3.0,
        help="Segundos de pausa entre consultas (default: 3)",
    )
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        default=None,
        help="Directorio de salida para reportes",
    )
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    configurar_logging(verbose=args.verbose)
    silenciar_logs_ruidosos()

    bots = [b.strip() for b in args.bots.split(",") if b.strip() in BOTS_DISPONIBLES]
    if not bots:
        print("Error: Ningun bot valido. Disponibles:", ", ".join(BOTS_DISPONIBLES))
        sys.exit(1)

    cedulas_raw = args.cedulas
    if Path(cedulas_raw).exists():
        cedulas = [line.strip() for line in Path(cedulas_raw).read_text().splitlines() if line.strip()]
    else:
        cedulas = [c.strip() for c in cedulas_raw.split(",") if c.strip()]

    if not cedulas:
        print("Error: Sin cedulas para procesar.")
        sys.exit(1)

    print(f"Bots: {', '.join(bots)}")
    print(f"Cedulas: {len(cedulas)}")
    print(f"Total consultas: {len(cedulas) * len(bots)}")
    print(f"Modo: {'headed' if args.headed else 'headless'}")
    print()

    t_inicio = time.monotonic()
    resultados = ejecutar_prueba_masiva(
        bots=bots,
        cedulas=cedulas,
        headless=not args.headed,
        pausa_entre_consultas=args.pausa,
        output_dir=args.output,
    )
    t_total = time.monotonic() - t_inicio

    generar_reporte_consola(resultados, bots)
    print(f"\nTiempo total: {t_total:.1f}s")


if __name__ == "__main__":
    main()
