from __future__ import annotations

import argparse
import sys
from pathlib import Path

from fosiga import run_fosiga_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        description="FOSIGA/ADRES — consulta EPS y exportacion de HTML."
    )
    parser.add_argument(
        "--numero",
        default="1022434547",
        help="Numero de documento a diligenciar en el formulario.",
    )
    parser.add_argument(
        "-o",
        "--output-dir",
        default=None,
        help="Directorio de salida para exportar el HTML resultante.",
    )
    parser.add_argument(
        "--registro-csv",
        default=None,
        help="Ruta del CSV de auditoria (por defecto: fosiga/fosiga_consultas.csv).",
    )
    parser.add_argument(
        "--headless",
        action="store_true",
        help="Ejecutar sin interfaz grafica (no recomendado por validacion/captcha del portal).",
    )
    parser.add_argument(
        "--keep-open-at-end",
        action="store_true",
        help="Mantener navegador abierto al finalizar.",
    )
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    try:
        resultado = run_fosiga_bot(
            numero_documento=args.numero,
            headless=args.headless,
            output_dir=Path(args.output_dir) if args.output_dir else None,
            registro_csv=Path(args.registro_csv) if args.registro_csv else None,
            keep_open_after_step=args.keep_open_at_end,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    if resultado.get("estado") in {"EXITOSA", "FINALIZADO"}:
        if resultado.get("archivo_html"):
            print(resultado.get("archivo_html", ""))
        else:
            print(resultado.get("motivo", "Finalizado sin archivo HTML."))
        return

    print(resultado.get("motivo", "Error desconocido"), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
