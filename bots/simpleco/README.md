# Bot Simple.co (comprobantes)

Automatiza la [consulta directa de comprobantes de pago en Simple.co](https://www.simple.co/Web/faces/pages/comprobantes/consultadirecta/consultaDirectaLogin.xhtml): documento, periodo (mes inmediatamente anterior al calendario actual en `America/Bogota`) y descarga del PDF de planilla asociada en la tabla `#cuadro1`.

- `simpleco/bot.py`: lógica Playwright
- `simpleco/service.py`: API `run_simpleco_bot`
- `simpleco/cli.py`: CLI
- `simpleco/salidas_simpleco/`: PDFs exitosos
- `simpleco/simpleco_consultas.csv`: auditoría

## Instalación

Desde `bots/` (igual que RUAF):

```bash
python3 -m venv venv
./venv/Scripts/pip install -r requirements.txt
./venv/Scripts/playwright install chromium
```

## Uso (Python)

```python
from simpleco import run_simpleco_bot

r = run_simpleco_bot(
    numero_documento="12345678",
    headless=True,
    verbose=False,
)
print(r)
```

## Uso (CLI)

```bash
cd .../bybot_app/bots
./venv/Scripts/python -m simpleco.cli --numero 12345678 -v
```

Con salida y CSV explícitos:

```bash
./venv/Scripts/python -m simpleco.cli --numero 12345678 -o salidas_simpleco --registro-csv simpleco_consultas.csv
```

## Notas

- El botón de descarga se localiza con el patrón `listaPlanillasPagadas:…:j_idt150` dentro de `#cuadro1` (IDs JSF pueden cambiar en nuevas versiones; ajusta `SEL_DESCARGA_PDF` en `bot.py` si el portal actualiza el sufijo `j_idt…`).
- El primer “Consultar” **por defecto** hace el postback en la **misma ventana**; el bot deja de usar `expect_page` largo. Si el portal abriera otra pestaña, se detecta en unos segundos y se sigue allí.
