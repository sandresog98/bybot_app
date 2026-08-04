from __future__ import annotations

import logging
from pathlib import Path

from playwright.sync_api import BrowserContext, sync_playwright

from common.stealth import aplicar_stealth

logger = logging.getLogger(__name__)

DEFAULT_USER_DATA_DIR = Path.home() / ".bybot" / "chrome_profile"

ARGS_ANTI_DETECCION = [
    "--disable-blink-features=AutomationControlled",
    "--no-sandbox",
    "--disable-dev-shm-usage",
    "--disable-infobars",
]


def crear_contexto_persistente(
    *,
    headless: bool = True,
    user_data_dir: str | Path | None = None,
    viewport: dict | None = None,
    locale: str = "es-CO",
    timezone_id: str = "America/Bogota",
    user_agent: str | None = None,
    extra_http_headers: dict | None = None,
    accept_downloads: bool = False,
    args: list[str] | None = None,
    stealth: bool = True,
    default_timeout: int = 60000,
) -> tuple:
    ud_dir = Path(user_data_dir or DEFAULT_USER_DATA_DIR)
    ud_dir.mkdir(parents=True, exist_ok=True)

    all_args = list(ARGS_ANTI_DETECCION)
    if args:
        all_args.extend(args)

    pw = sync_playwright().start()
    context: BrowserContext = pw.chromium.launch_persistent_context(
        user_data_dir=str(ud_dir),
        headless=headless,
        viewport=viewport or {"width": 1280, "height": 900},
        locale=locale,
        timezone_id=timezone_id,
        user_agent=user_agent,
        extra_http_headers=extra_http_headers,
        accept_downloads=accept_downloads,
        args=all_args,
    )

    context.set_default_timeout(default_timeout)

    if stealth:
        aplicar_stealth(context)

    logger.info(
        "Contexto persistente listo | headless=%s | perfil=%s | stealth=%s",
        headless, ud_dir, stealth,
    )
    return pw, context
