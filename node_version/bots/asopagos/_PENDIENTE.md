# Bot ASOPAGOS — PENDIENTE

**Estado**: NO FUNCIONAL

**Razon**: El archivo `bot.py` no existe en el repositorio original (`bots/`).
El `service.py` importa `from interssi import bot` — el modulo `interssi` no existe.

## Que existe

| Archivo | Estado |
|---------|--------|
| `cli.py` | Conservado, pero no funciona porque `run_asopagos_bot` falla al importar |
| `service.py` | Conservado como referencia de la firma de API esperada |
| `__init__.py` | Stub |

## Que falta

1. **`bot.py`** — Logica de automatizacion Playwright para el portal ASOPAGOS:
   - Navegar al portal de ASOPAGOS
   - Diligenciar formulario (numero de identificacion)
   - Resolver captcha (OCR o manual)
   - Descargar certificado PDF
2. **`parser.py`** — Extraccion de datos del PDF de ASOPAGOS
3. **Archivos de prueba** — No hay PDFs descargados de ejemplo en `salidas_asopagos/`

## Para reconstruir

Se necesita acceso al portal ASOPAGOS para:
1. Analizar la estructura del formulario y flujo de navegacion
2. Identificar los selectores CSS/JSF necesarios
3. Determinar el tipo de captcha y estrategia de resolucion
4. Probar la descarga real de un certificado PDF
5. Definir los campos a extraer del PDF
