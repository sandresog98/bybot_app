# Bot Aportes en Línea (inicio)

Automatiza el primer paso con ingreso directo a:

`https://empresas.aportesenlinea.com/Autoservicio/CertificadoAportes.aspx`

El bot valida que cargue la pantalla **Certificado de aportes** y confirma que la URL final sea `CertificadoAportes.aspx`.
Luego diligencia `#contenido_tbNumeroIdentificacion` con `1012420137`.
También diligencia `#contenido_txtFechaExp` con `26-MAR-13`.
Además selecciona el mes inicial `03` en `#contenido_ddlMesIni` (Generar certificado desde).
En `#contenido_ddlMesFin` (Generar certificado hasta) selecciona dinámicamente el **mes anterior** a la fecha actual.
Después espera resolución manual del reCAPTCHA, pulsa **Generar certificado** y descarga el PDF en `aportesenlinea/salidas_aportesenlinea/`.

## Uso (CLI)

Desde `bots/`:

```powershell
.\venv\Scripts\python -m aportesenlinea.cli --headed -v
```

Por defecto, en modo visible (`--headed`) el navegador queda abierto para que veas el proceso y se cierra cuando presionas `ENTER` en consola.

Si quieres que cierre automáticamente al terminar:

```powershell
.\venv\Scripts\python -m aportesenlinea.cli --headed --no-pausa -v
```

Opciones útiles:

- `--no-modo-lento`: desactiva pausas visuales del llenado.
- `--no-captcha-interactivo`: no espera captcha manual (fallará si no está resuelto).
- `-o <carpeta>`: cambia carpeta de salida del PDF.
