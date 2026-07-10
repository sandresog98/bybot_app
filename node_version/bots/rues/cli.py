from __future__ import annotations

import argparse
import sys
from pathlib import Path

from rues import run_rues_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        description="RUES — consulta de Registro Mercantil."
    )
    parser.add_argument(
        "--numero",
        default="52727688",
        help="Numero a escribir en el campo de busqueda de Registro Mercantil.",
    )
    parser.add_argument(
        "--registro-csv",
        default=None,
        help="Ruta del CSV de auditoria (por defecto: rues/rues_consultas.csv).",
    )
    parser.add_argument(
        "--headed",
        action="store_true",
        help="Ejecutar con interfaz grafica (por defecto corre en headless).",
    )
    parser.add_argument(
        "--pausa",
        action="store_true",
        help="Mantener navegador abierto al finalizar (por defecto se cierra).",
    )
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_rues_bot(
            numero_busqueda=args.numero,
            headless=not args.headed,
            registro_csv=Path(args.registro_csv) if args.registro_csv else None,
            keep_open_after_step=args.headed and args.pausa,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    if resultado.get("estado") in {"EXITOSA", "FINALIZADO"}:
        if resultado.get("archivo_html"):
            print(resultado.get("archivo_html", ""))
        else:
            print(resultado.get("motivo", "OK"))
        return

    print(resultado.get("motivo", "Error desconocido"), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
