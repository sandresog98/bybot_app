# SuAporte — Consulta directa de comprobantes

Nombre del bot en codigo: constante `NOMBRE_BOT` en `suaporte/bot.py` (mismo texto que el titulo).

Automatiza el flujo inicial en el portal SuAporte:

1. Abre la pagina principal de consulta directa (URL de SuAporte).
2. Clic en el campo `numeroDocumentoUsuario` (`#numeroDocumentoUsuario` o variantes JSF).
3. Escribir el numero de cedula (por defecto `1073710057`).
4. Clic en la imagen del boton **Consultar** (`bot_consultar.jpg`).
5. Si **no** aparece el aviso de sin pagos en 6 meses: **Periodo de cotizacion** (etiqueta o radio), **calendario** `#img_calendar` (`mostrarPeriodoRestringido`), **Marzo** (`value="3"`) y **anio civil actual** en Bogota en `periodoCotizacion:mes` / `periodoCotizacion:anio`, **Aceptar** periodo (`bot_aceptar.jpg`), y **Consultar** (`bot_consultar.jpg`). La seleccion de mes/anio usa `simpleco.bot.seleccionar_mes_anio`.
6. Si tras el 2.º **Consultar** aparece *«Nuestro sistema no registra información para los parámetros seleccionados»*, hace clic en **Aceptar** (`span.ui-button-text` en el dialogo PrimeFaces visible).
7. **Descarga PDF**: localiza el boton de planilla (`listaPlanillasPagadas:…:j_idt150` / `pdfLogo.png`, misma logica que Simple.co), hace clic y guarda el archivo en `suaporte/salidas_suaporte/` (o `-o`). Si no aparece el boton, estado `ERROR_SIN_PDF`.
8. Si aparece el texto de que no hay pagos en los ultimos 6 meses, vuelve a cargar la URL de consulta directa (pagina principal del flujo).
9. Si la vista muestra el boton **Borrar** (`bot_borrar.jpg`), hace clic para limpiar el formulario (no falla si no esta visible).

URL objetivo:

- [SuAporte - Consulta Directa](https://www.suaporte.com.co/Web/faces/pages/comprobantes/consultadirecta/consultaDirectaLogin.xhtml)

## Estructura

- `suaporte/bot.py`: automatizacion Playwright (periodo de cotizacion vía helpers de `simpleco.bot`).
- `suaporte/service.py`: API `run_suaporte_bot`.
- `suaporte/cli.py`: interfaz por consola.
- `suaporte/suaporte_consultas.csv`: auditoria (incluye columna `archivo_pdf`).
- `suaporte/salidas_suaporte/`: PDFs descargados.

## Uso CLI

Desde `bots/`:

```powershell
.\venv\Scripts\python -m suaporte.cli --headed -v
```

Para ver el flujo con ritmo guiado (recomendado al depurar):

```powershell
.\venv\Scripts\python -m suaporte.cli --headed --modo-lento -v
```

Opciones:

- `--numero`: documento a usar (default `1073710057`).
- `-o` / `--output`: carpeta del PDF (default `suaporte/salidas_suaporte`).
- `--registro-csv`: ruta personalizada del CSV.
- `--url`: URL de inicio personalizada.
- `--headed`: ejecuta con navegador visible.
- `--pausa`: deja abierto hasta `ENTER` (solo con `--headed`).
- `--modo-lento`: `slow_mo` del navegador (~150 ms), ~0.55 s entre pasos y escritura visible (~38 ms/tecla).
- `--slow-mo-ms`, `--delay-pasos`, `--delay-teclas-ms`: afinar los tiempos (sobreescriben los valores por defecto de `--modo-lento` si los indicas).
