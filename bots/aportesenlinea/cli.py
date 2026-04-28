#!/usr/bin/env python3
from __future__ import annotations

import argparse
import sys

from aportesenlinea import run_aportesenlinea_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Aportes en Línea: abrir opción 'Certificados de aportes'."
    )
    parser.add_argument("--headed", action="store_true", help="Mostrar navegador")
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_aportesenlinea_bot(
            headless=not args.headed,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    if resultado.get("estado") == "EXITOSA":
        print(resultado.get("url_final", ""))
        return
    print(resultado.get("motivo", "Error desconocido"), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
