# Bots Project

Carpeta base para bots de automatización del proyecto.

## Convención recomendada

Cada bot vive en su propia carpeta:

```text
bots/
  requirements.txt
  <bot_name>/
    __init__.py
    bot.py
    service.py
    cli.py
    README.md
    salidas_<bot_name>/
    <bot_name>_consultas.csv
```

## Dependencias universales

Instalar una sola vez para todos los bots:

```bash
cd /opt/lampp/htdocs/projects/bybot_v1/bots
python3 -m venv venv
./venv/bin/pip install -r requirements.txt
./venv/bin/playwright install chromium
```

Requisito del sistema para OCR:

```bash
sudo apt install tesseract-ocr tesseract-ocr-spa
```

## Bots actuales

- `ruaf/`: bot de consultas RUAF.

Ejemplo rápido:

```bash
cd /opt/lampp/htdocs/projects/bybot_v1/bots
./venv/bin/python -m ruaf.cli --numero 1022434547 --fecha 14/04/2026 --save-captchas -v
```

