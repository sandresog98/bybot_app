from __future__ import annotations

from . import bot


def run_aportesenlinea_bot(
    *,
    headless: bool = True,
    verbose: bool = False,
) -> dict[str, str]:
    """
    API para ejecutar el bot de Aportes en Línea.
    Primer alcance: entrar al home empresas y abrir "Certificados de aportes".
    """
    bot.configurar_logging(verbose=verbose)
    bot._silenciar_logs_ruidosos()
    return bot.ejecutar_consulta(headless=headless)
