# Bot Aportes en Línea (inicio)

Automatiza el primer paso con ingreso directo a:

`https://empresas.aportesenlinea.com/Autoservicio/CertificadoAportes.aspx`

El bot valida que cargue la pantalla **Certificado de aportes** y confirma que la URL final sea `CertificadoAportes.aspx`.

## Uso (CLI)

Desde `bots/`:

```powershell
.\venv\Scripts\python -m aportesenlinea.cli --headed -v
```

Si termina bien, imprime la URL final abierta.
