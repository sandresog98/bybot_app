# Bot RUES (Registro Mercantil)

Automatiza la consulta de Registro Mercantil en [RUES](https://www.rues.org.co/) con Playwright.

## Flujo actual

1. Abre RUES (con reintentos de URL para tolerar respuestas HTTP inestables del portal).
2. Cierra el aviso inicial (`button.swal2-close`) si aparece.
3. Diligencia el campo de busqueda `input#search` con el numero enviado.
4. Hace clic en el boton **Buscar** de Registro Mercantil (`button ... btn-busqueda ...` con icono `bi-search`).
5. Espera uno de estos dos resultados:
   - **EXITOSA**: si detecta texto `Número de Matrícula`.
   - **FINALIZADO**: si detecta texto `No se encontraron resultados`.
6. Si es `EXITOSA`, guarda HTML en `rues/salidas_rues/`.
7. Registra todas las ejecuciones en `rues/rues_consultas.csv`.
8. Por defecto cierra el navegador al terminar.

## Estructura

- `rues/bot.py`: automatizacion Playwright y reglas de resultado.
- `rues/service.py`: API `run_rues_bot` + registro CSV.
- `rues/cli.py`: interfaz por consola.
- `rues/rues_consultas.csv`: auditoria de ejecuciones.
- `rues/salidas_rues/`: HTML de consultas exitosas.

## Uso CLI

Ejecutar desde `bots/`:

```powershell
.\venv\Scripts\python -m rues.cli --numero 52727688 -v
```

### Opciones

- `--numero`: numero a consultar (por defecto `52727688`).
- `--registro-csv <ruta>`: ruta personalizada del CSV de auditoria.
- `--headed`: ejecuta con navegador visible (por defecto corre headless).
- `--pausa`: mantiene el navegador abierto hasta `ENTER` (solo con `--headed`).
- `-v, --verbose`: logs detallados.

## Resultado de ejecucion

El bot devuelve y registra:

- `estado`: `EXITOSA`, `FINALIZADO` o `ERROR`.
- `motivo`: mensaje corto del resultado.
- `archivo_html`: ruta del HTML (solo en `EXITOSA`).
- `url_final`: URL final de la consulta.

## Ejemplos

Headless (por defecto):

```powershell
.\venv\Scripts\python -m rues.cli --numero 52727688
```

Visible:

```powershell
.\venv\Scripts\python -m rues.cli --numero 52727688 --headed -v
```

Visible y en pausa al final:

```powershell
.\venv\Scripts\python -m rues.cli --numero 52727688 --headed --pausa -v
```
