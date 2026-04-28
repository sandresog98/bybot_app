#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from aportesenlinea import run_aportesenlinea_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Aportes en Línea: genera y descarga certificado de aportes en PDF."
    )
    parser.add_argument("--numero", required=True, help="Número de identificación")
    parser.add_argument(
        "--fecha-exp",
        default="26-MAR-13",
        help="Fecha de expedición del documento (ej. 26-MAR-13)",
    )
    parser.add_argument("--eps", default="NUEVA E.P.S.", help="Nombre exacto de la EPS")
    parser.add_argument("--headed", action="store_true", help="Mostrar navegador")
    parser.add_argument(
        "--no-pausa",
        action="store_true",
        help="No esperar ENTER al finalizar (cierra el navegador automáticamente).",
    )
    parser.add_argument(
        "--no-captcha-interactivo",
        action="store_true",
        help="No pausar para resolución manual del captcha.",
    )
    parser.add_argument(
        "--no-modo-lento",
        action="store_true",
        help="Desactiva pausas y escritura lenta (más rápido, menos natural).",
    )
    parser.add_argument(
        "-o", "--output",
        type=Path,
        default=None,
        help="Carpeta donde guardar el PDF descargado.",
    )
    parser.add_argument(
        "--registro-csv",
        type=Path,
        default=None,
        help="Ruta del CSV de auditoría (por defecto: aportesenlinea_consultas.csv junto al módulo).",
    )
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_aportesenlinea_bot(
            numero_id=args.numero,
            fecha_expedicion=args.fecha_exp,
            eps=args.eps,
            headless=not args.headed,
            keep_open_after_fill=not args.no_pausa,
            captcha_interactivo=not args.no_captcha_interactivo,
            modo_lento=not args.no_modo_lento,
            output_dir=args.output,
            registro_csv=args.registro_csv,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    if resultado.get("estado") == "EXITOSA" and resultado.get("archivo_pdf"):
        print(resultado["archivo_pdf"])
        return

    print(resultado.get("motivo", "Error desconocido"), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
