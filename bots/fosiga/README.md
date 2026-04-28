# Bot FOSIGA (ADRES - Consulte su EPS)

Automatiza el primer paso sobre [Consulte su EPS](https://www.adres.gov.co/consulte-su-eps):

1. Abrir la página de ADRES.
2. Escribir el número de cédula en el campo **Número**.
3. Dar clic en **Consultar**.
4. Si se abre pestaña nueva, exportar el HTML completo de esa pestaña.
5. Registrar la ejecución en `fosiga_consultas.csv`.

Regla de negocio:
- Si la respuesta indica que el afiliado **no se encuentra en BDUA**, el bot marca estado `FINALIZADO` y **no guarda HTML**.

## Estructura

- `fosiga/bot.py`: lógica Playwright.
- `fosiga/service.py`: API `run_fosiga_bot`.
- `fosiga/cli.py`: CLI.

## Uso (CLI)

Desde `bots/`:

```powershell
.\venv\Scripts\python -m fosiga.cli --numero 1022434547 -v
```

El CLI corre en modo visible por defecto y cierra al finalizar para registrar rápido en CSV.
Si quieres mantenerlo abierto al final, agrega `--keep-open-at-end`.
Si necesitas forzar sin interfaz gráfica, usa `--headless` (no recomendado por validación/captcha).
Por defecto guarda el HTML en `fosiga/salidas_fosiga/` (puedes cambiarlo con `-o`).
Por defecto registra auditoría en `fosiga/fosiga_consultas.csv` (puedes cambiarlo con `--registro-csv`).

## Uso (Python)

```python
from fosiga import run_fosiga_bot

resultado = run_fosiga_bot(
    numero_documento="1022434547",
    headless=False,
    output_dir=None,  # usa fosiga/salidas_fosiga
    verbose=False,
)
print(resultado)
```
