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
- `simpleco/`: descarga de comprobante (PDF) en [Simple.co — consulta directa](https://www.simple.co/Web/faces/pages/comprobantes/consultadirecta/consultaDirectaLogin.xhtml).
- `asopagos/`: consulta en ASOPAGOS con validación de captcha y descarga de certificado en PDF.
- `aportesenlinea/`: paso inicial del portal empresas; abre opción **Certificados de aportes** y valida navegación a `CertificadoAportes.aspx`.
- `fosiga/`: primer paso en ADRES `Consulte su EPS`; abre la página y diligencia el campo **Número**.

Ejemplo rápido:

```bash
cd /opt/lampp/htdocs/projects/bybot_v1/bots
./venv/bin/python -m ruaf.cli --numero 1022434547 --fecha 14/04/2026 --save-captchas -v
```

Comprobante Simple.co:

- El `venv` vive en **`bots/venv`**, no en la raíz del repositorio. Primero: `cd bots` y (si aún no existe) `py -3 -m venv venv`, luego `.\venv\Scripts\pip install -r requirements.txt` y `.\venv\Scripts\playwright install chromium`.
- Desde **`bots/`** (Windows):

```powershell
.\venv\Scripts\python -m simpleco.cli --numero 12345678 -v
```

- El módulo `simpleco` está en esta carpeta; **hay que ejecutar el `-m` con el directorio actual en `bots/`** (si no, Python no encuentra el paquete). Desde la raíz del repo:

```powershell
Set-Location bots
.\venv\Scripts\python -m simpleco.cli --numero 12345678 -v
```

ASOPAGOS:

```powershell
Set-Location bots
.\venv\Scripts\python -m asopagos.cli --numero 12345678 --headed -v
```

