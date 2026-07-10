from __future__ import annotations

import argparse
import sys
from pathlib import Path

from suaporte import NOMBRE_BOT, run_suaporte_bot


def main() -> None:
    parser = argparse.ArgumentParser(
        prog="suaporte",
        description=(
            f"{NOMBRE_BOT}. Campo documento, diligenciar cedula y Consultar; "
            "manejo de aviso sin pagos 6 meses y Borrar si aplica."
        ),
    )
    parser.add_argument("--numero", default="1073710057", help="Numero de documento")
    parser.add_argument("--registro-csv", type=Path, default=None, help="Ruta CSV de auditoria")
    parser.add_argument("--url", type=str, default=None, help="URL de inicio personalizada")
    parser.add_argument("-o", "--output", type=Path, default=None, help="Carpeta de salida del PDF")
    parser.add_argument("--headed", action="store_true", help="Mostrar navegador")
    parser.add_argument("--pausa", action="store_true", help="Mantener navegador abierto al finalizar")
    parser.add_argument("--modo-lento", action="store_true", help="Pausas entre pasos (ritmo depuracion)")
    parser.add_argument("--slow-mo-ms", type=int, default=None, metavar="MS", help="Retraso Playwright (ms)")
    parser.add_argument("--delay-pasos", type=float, default=None, metavar="SEG", help="Segundos entre pasos")
    parser.add_argument("--delay-teclas-ms", type=int, default=None, metavar="MS", help="ms entre teclas")
    parser.add_argument("-v", "--verbose", action="store_true", help="Logs DEBUG")
    args = parser.parse_args()

    slow_mo = args.slow_mo_ms if args.slow_mo_ms is not None else (150 if args.modo_lento else 0)
    delay_pasos = args.delay_pasos if args.delay_pasos is not None else (0.55 if args.modo_lento else 0.0)
    delay_teclas = args.delay_teclas_ms if args.delay_teclas_ms is not None else (38 if args.modo_lento else 0)

    try:
        resultado = run_suaporte_bot(
            numero_documento=args.numero,
            headless=not args.headed,
            registro_csv=args.registro_csv,
            url_inicio=args.url,
            output_dir=args.output,
            keep_open_after_step=args.headed and args.pausa,
            slow_mo_ms=slow_mo,
            delay_entre_pasos_s=delay_pasos,
            delay_teclas_ms=delay_teclas,
            verbose=args.verbose,
        )
    except KeyboardInterrupt:
        sys.exit(130)

    est = resultado.get("estado")
    if est == "EXITOSA":
        pdf = resultado.get("archivo_pdf", "") or ""
        if pdf:
            print(pdf)
        else:
            print(resultado.get("motivo", "OK"))
        return
    if est == "SIN_PAGOS_6_MESES":
        print(resultado.get("motivo", ""))
        return

    print(resultado.get("motivo", "Error desconocido"), file=sys.stderr)
    sys.exit(1)


if __name__ == "__main__":
    main()
