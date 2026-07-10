#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from ruaf import run_ruaf_bot


def main() -> None:
    parser = argparse.ArgumentParser(description="CLI wrapper del bot RUAF")
    parser.add_argument("--numero", required=True, help="Número de identificación")
    parser.add_argument("--fecha", required=True, help='Fecha DD/MM/YYYY (ej. "14/04/2026")')
    parser.add_argument("--tipo-doc", default="CEDULA DE CIUDADANIA", help="Tipo de documento")
    parser.add_argument("--headed", action="store_true", help="Mostrar navegador")
    parser.add_argument("-o", "--output", type=Path, default=None, help="Carpeta de salida HTML")
    parser.add_argument("--registro-csv", type=Path, default=None, help="Ruta CSV de auditoría")
    parser.add_argument("--save-captchas", action="store_true", help="Guardar imágenes de captcha")
    parser.add_argument("--captchas-dir", type=Path, default=None, help="Carpeta de captchas")
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_ruaf_bot(
            numero_id=args.numero,
            fecha=args.fecha,
            tipo_doc=args.tipo_doc,
            headless=not args.headed,
            output_dir=args.output,
            registro_csv=args.registro_csv,
            save_captchas=args.save_captchas,
            captchas_dir=args.captchas_dir,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    if resultado.get("estado") == "EXITOSA":
        print(resultado.get("archivo_html", ""))
        return
    sys.exit(1)


if __name__ == "__main__":
    main()

