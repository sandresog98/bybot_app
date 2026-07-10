from __future__ import annotations

import argparse
import sys
from pathlib import Path

from simpleco import run_simpleco_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Simple.co — descarga de comprobante (PDF) por consulta directa"
    )
    parser.add_argument("--numero", required=True, help="Numero de documento")
    parser.add_argument("-o", "--output", type=Path, default=None, help="Carpeta de salida del PDF")
    parser.add_argument("--registro-csv", type=Path, default=None, help="Ruta CSV de auditoria")
    parser.add_argument("--headed", action="store_true", help="Mostrar navegador")
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_simpleco_bot(
            numero_documento=args.numero,
            headless=not args.headed,
            output_dir=args.output,
            registro_csv=args.registro_csv,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    est = resultado.get("estado")
    if est == "EXITOSA":
        print(resultado.get("archivo_pdf", ""))
        return
    if est == "SIN_PAGOS_6_MESES":
        print(resultado.get("motivo", ""))
        return
    sys.exit(1)


if __name__ == "__main__":
    main()
