from __future__ import annotations

import logging
import time

from common.timezone_utils import ZONA_BOGOTA

logger = logging.getLogger(__name__)

STEALTH_SCRIPTS = [
    "Object.defineProperty(navigator, 'webdriver', {get: () => undefined})",
    """
    Object.defineProperty(navigator, 'plugins', {
        get: () => [
            {filename:'internal-pdf-viewer',description:'Portable Document Format'},
            {filename:'mhjfbmdgcfjbbpaeojofohoefgiehjai',description:'Portable Document Format'},
            {filename:'mhjfbmdgcfjbbpaeojofohoefgiehjai',description:'Native Client'},
        ]
    });
    """,
    "Object.defineProperty(navigator, 'languages', {get: () => ['es-CO', 'es', 'en']});",
    """
    window.chrome = {
        runtime: {
            onConnect: {addListener: () => {}},
            onMessage: {addListener: () => {}},
        },
        loadTimes: function() {},
        csi: function() {},
        app: {}
    };
    """,
    """
    const origQuery = window.navigator.permissions.query.bind(navigator.permissions);
    window.navigator.permissions.query = (parameters) =>
        parameters.name === 'notifications'
            ? Promise.resolve({state: Notification.permission})
            : origQuery(parameters);
    """,
]

CHROME_UA_LINUX = (
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36"
)

VIEWPORT_DESKTOP = {"width": 1366, "height": 900}


def aplicar_stealth(context, *, extra_scripts: list[str] | None = None) -> None:
    scripts = list(STEALTH_SCRIPTS)
    if extra_scripts:
        scripts.extend(extra_scripts)
    for script in scripts:
        context.add_init_script(script)
    logger.debug("Stealth aplicado: %s scripts", len(scripts))


def movimiento_mouse_natural(page, x: float, y: float, pasos: int = 6) -> None:
    try:
        for i in range(1, pasos + 1):
            factor = i / pasos
            cx = x * factor
            cy = y * factor
            page.mouse.move(cx, cy)
            time.sleep(0.04)
    except Exception as e:
        logger.debug("Movimiento mouse natural: %s", e)
