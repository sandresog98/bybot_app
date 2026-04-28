#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys
from pathlib import Path

from asopagos import run_asopagos_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        description="CLI del bot ASOPAGOS: certificado, captcha y descarga PDF."
    )
    parser.add_argument("--numero", required=True, help="Número de identificación")
    parser.add_argument(
        "--url",
        type=str,
        default=None,
        help="URL de inicio (por defecto: la del módulo).",
    )
    parser.add_argument("--headed", action="store_true", help="Mostrar navegador")
    parser.add_argument(
        "-o", "--output", type=Path, default=None, help="Carpeta de salida PDF"
    )
    parser.add_argument("--registro-csv", type=Path, default=None, help="CSV de auditoría")
    parser.add_argument(
        "--captcha-text",
        type=str,
        default=None,
        help="Captcha manual (4 caracteres) para escribir en #captchaIn sin OCR.",
    )
    parser.add_argument(
        "--captchas-dir",
        type=Path,
        default=None,
        help="Carpeta para guardar evidencias de captcha (PNG + meta).",
    )
    parser.add_argument(
        "--captcha-interactivo",
        action="store_true",
        help="Pide captcha por consola en cada ejecución (sin OCR).",
    )
    parser.add_argument(
        "--no-pausa-consultar",
        action="store_true",
        help="No pausar tras clic en Consultar.",
    )
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_asopagos_bot(
            numero_id=args.numero,
            headless=not args.headed,
            output_dir=args.output,
            registro_csv=args.registro_csv,
            url_inicio=args.url,
            captcha_text=args.captcha_text,
            captchas_dir=args.captchas_dir,
            captcha_interactivo=args.captcha_interactivo,
            keep_open_after_consultar=not args.no_pausa_consultar,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    if resultado.get("estado") == "EXITOSA" and resultado.get("archivo_pdf"):
        print(resultado.get("archivo_pdf", ""))
        return
    sys.exit(1)


if __name__ == "__main__":
    main()
