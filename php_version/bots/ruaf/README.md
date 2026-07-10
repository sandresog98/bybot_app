# Bot RUAF

Bot de consulta RUAF con Playwright + OCR para captcha.

## Estructura

- `ruaf/bot.py`: implementación principal del bot
- `ruaf/service.py`: API programática (`run_ruaf_bot`)
- `ruaf/cli.py`: CLI simplificada del bot
- `ruaf/salidas_ruaf/`: HTML exitosos
- `ruaf/captcha_intentos/`: evidencias captcha (opcional)
- `ruaf/ruaf_consultas.csv`: auditoría de consultas

## Instalación

Desde `bots/`:

```bash
python3 -m venv venv
./venv/bin/pip install -r requirements.txt
./venv/bin/playwright install chromium
```

Requisito del sistema:

```bash
sudo apt install tesseract-ocr tesseract-ocr-spa
```

## Uso por código (fácil)

```python
from ruaf import run_ruaf_bot

resultado = run_ruaf_bot(
    numero_id="1022434547",
    fecha="14/04/2026",
    tipo_doc="CEDULA DE CIUDADANIA",
    headless=True,
    save_captchas=False,
)
print(resultado)
```

## Uso por CLI

```bash
cd /opt/lampp/htdocs/projects/bybot_v1/bots
./venv/bin/python -m ruaf.cli --numero 1022434547 --fecha 14/04/2026
```

También puedes ejecutar el módulo principal directamente:

```bash
./venv/bin/python -m ruaf.bot --numero 1022434547 --fecha 14/04/2026
```

Con depuración y evidencias captcha:

```bash
./venv/bin/python -m ruaf.cli --numero 1022434547 --fecha 14/04/2026 --save-captchas -v
```

## Reglas de salida

- Solo guarda HTML cuando la consulta es `EXITOSA`.
- Si el portal responde mensajes de no encontrado/inconsistencia de fecha, no guarda HTML.
- Toda ejecución queda registrada en CSV con `estado`, `motivo` y `archivo_html`.

## Troubleshooting rápido

- Si OCR no devuelve 5 caracteres, el bot reintenta automáticamente.
- Revisa `ruaf/ruaf_consultas.csv` para estado final de cada ejecución.
- Si usas `--save-captchas`, revisa `ruaf/captcha_intentos/intento_*_meta.txt` y `*_original.png`.

