# Bots — Automatizacion de consultas (Linux)

Bots de registros públicos enfocados en Linux, con modulo `common/` compartido,
parsers de extraccion de datos y base de datos MariaDB consolidada.

> Histórico: este paquete nació como `bots2/` (migración Linux del `bots/` original
> de Windows). El `bots/` original fue eliminado y `bots2/` renombrado a `bots/`
> al integrarse en `node_version/`.

> **Integración actual**: el backend Node no invoca a Python por `child_process`; escribe a la
> cola `app_colas_trabajos` (`cola='bybot:consultar'`) y `botworker/bot_runner.py` la consume,
> resolviendo cada bot en `BOT_REGISTRY` → `bots/<name>/service.py`.

## Navegador: perfil persistente (bot-detección)

Todos los bots usan `common/browser.py::crear_contexto_persistente()` en vez de
`sync_playwright` directo, para combatir bloqueos por bot-detección (reCAPTCHA invisible,
"no se pudo validar la seguridad"). Usa `launch_persistent_context()` con perfil en
`~/.bybot/chrome_profile`, stealth y args anti-detección. Ver `../handoff.md`.

## Estado de bots

| Bot | Estado | Archivo descargado | Parser | Tiene datos de prueba |
|-----|--------|--------------------|--------|-----------------------|
| **ruaf** | Funcional (servidor SISPRO inestable) | HTML (ReportViewer) | `parser.py` activo | 3 HTML en `bots/ruaf/salidas_ruaf/` |
| **simpleco** | Funcional ✅ probado | PDF (comprobante) | `parser.py` stub | 2 PDF en `bots/suaporte/` |
| **fosiga** | Funcional (bloqueado por reCAPTCHA) | HTML (GridView) | `parser.py` activo | 2 HTML en `bots/fosiga/salidas_fosiga/` |
| **rues** | Funcional (timeout en headless) | HTML (Angular SPA) | `parser.py` activo | 3 HTML en `bots/rues/salidas_rues/` |
| **suaporte** | Funcional | PDF (comprobante) | `parser.py` stub | 2 PDF en `bots/suaporte/salidas_suaporte/` |
| **aportesenlinea** | Funcional (reCAPTCHA imágenes) | PDF (certificado) | `parser.py` stub | 1 PDF en `bots/aportesenlinea/salidas_aportesenlinea/` |
| **asopagos** | PENDIENTE | PDF (certificado) | No existe | No existe — falta `bot.py` |

> Nota integración runner: `BOT_REGISTRY` (en `botworker/bot_runner.py`) tiene registrados
> `fosiga`, `ruaf`, `rues`, `simpleco`. **SuAporte y Aportes en Línea aún NO están registrados**
> en el runner, aunque tienen `service.py`.

## Instalacion (Linux)

```bash
cd /opt/lampp/htdocs/projects/bybot_v1/node_version/bots

python3 -m venv venv
./venv/bin/pip install -r requirements.txt
./venv/bin/playwright install chromium

sudo apt install tesseract-ocr tesseract-ocr-spa
```

## Variables de entorno

| Variable | Descripcion | Default |
|----------|-------------|---------|
| `GEMINI_API_KEY` | Token de Google Gemini para OCR de captcha, parseo y diagnostico de errores | _(vacio — deshabilitado)_ |
| `BYBOT_GEMINI_MODEL` | Modelo de Gemini a usar | `gemini-2.5-flash` |
| `BYBOT_DB_HOST` | Host de MySQL/MariaDB | `127.0.0.1` |
| `BYBOT_DB_PORT` | Puerto de MySQL/MariaDB | `3306` |
| `BYBOT_DB_USER` | Usuario de MySQL/MariaDB | `root` |
| `BYBOT_DB_PASSWORD` | Contrasena de MySQL/MariaDB | _(vacio)_ |
| `BYBOT_DB_NAME` | Nombre de la base de datos | `bybot_consolidado` |
| `BYBOT_FOSIGA_RECAPTCHA_KEY` | Site key de reCAPTCHA Enterprise para ADRES | _(key hardcodeada de fallback)_ |

## Uso rapido

```bash
cd /opt/lampp/htdocs/projects/bybot_v1/node_version/bots

# RUAF — consulta de afiliacion con OCR de captcha
./venv/bin/python -m ruaf.cli --numero 1022434547 --fecha 14/04/2026 -v

# Simple.co — descarga de comprobante PDF
./venv/bin/python -m simpleco.cli --numero 12345678 -v

# FOSIGA/ADRES — consulta EPS
./venv/bin/python -m fosiga.cli --numero 1022434547 -v

# RUES — consulta Registro Mercantil
./venv/bin/python -m rues.cli --numero 52727688 -v

# SuAporte — descarga de comprobante PDF
./venv/bin/python -m suaporte.cli --headed -v

# Aportes en Linea — certificado de aportes PDF
./venv/bin/python -m aportesenlinea.cli --headed -v
```

## Scripts de prueba
```bash
bash ruaf/test_ruaf.sh          # ruaf, headed — CC 39741702 / nac 26/08/1993
bash simpleco/test_simpleco.sh  # simpleco, headed — CC 1022434547 y 39741702
```

> Todos los bots usan el **perfil persistente** (`~/.bybot/chrome_profile`) por defecto para
> reducir bot-detección; no requieren `sync_playwright` manual.

## Estructura del proyecto

```
bots/
├── README.md
├── requirements.txt
│
├── common/                    # Utilidades compartidas
│   ├── browser.py             # crear_contexto_persistente() (perfil Chrome + stealth) ★
│   ├── stealth.py             # Scripts anti-deteccion (navigator.webdriver, etc.)
│   ├── ai.py                  # Cliente Gemini (resolver_captcha_ocr, extraer_datos_reporte)
│   ├── captcha.py             # helpers de captcha (localizacion, renovacion)
│   ├── logging_config.py      # configurar_logging(), silenciar_logs_ruidosos()
│   ├── csv_writer.py          # registrar_consulta_csv() unificado
│   ├── timezone_utils.py      # ZONA_BOGOTA, periodo_mes_anterior()
│   ├── pdf_helpers.py         # Funciones JSF/PrimeFaces (calendario, periodo, PDF)
│   └── db.py                  # Conexion MySQL, insert_consulta()
│
├── sql/
│   └── ddl.sql                # CREATE DATABASE + tablas + vista consolidada
│
├── ruaf/                      # Bot RUAF — consulta SISPRO con OCR
├── simpleco/                  # Bot Simple.co — comprobante PDF
├── fosiga/                    # Bot ADRES — consulta EPS
├── rues/                      # Bot RUES — Registro Mercantil
├── suaporte/                  # Bot SuAporte — comprobante PDF
├── aportesenlinea/            # Bot Aportes en Linea — certificado PDF
└── asopagos/                  # PENDIENTE — sin bot.py
```

## Base de datos

Ejecutar una sola vez para crear la base de datos y tablas:

```bash
mysql -u root < sql/ddl.sql
```

La vista `consultas_consolidadas` unifica todos los bots. Una misma cedula
puede aparecer en multiples bots (JOIN por `numero_id`).

```sql
SELECT * FROM consultas_consolidadas WHERE numero_id = '1022434547';
```

## Parsers

Cada bot tiene un `parser.py` que extrae datos estructurados del archivo
descargado (HTML o PDF). Los parsers usan configuracion declarativa
(`EXTRACTION_CONFIG`) para facilitar ajustes futuros sin modificar codigo.

- **HTML**: BeautifulSoup con selectores CSS + estrategias regex como fallback
- **PDF**: pdfplumber (extraccion de texto y tablas)

Los parsers de PDF (`simpleco`, `suaporte`, `aportesenlinea`) son stubs
que requieren ejecutar pruebas con PDF reales para definir los campos especificos.

## Diferencias con la versión original (histórico)

| Aspecto | `bots/` original (eliminado) | `bots/` actual (ex `bots2/`) |
|---------|--------------------|--------------------|
| SO objetivo | Windows (paths `.\venv\Scripts\`) | Linux (paths `./venv/bin/`) |
| Codigo duplicado | ~500 lineas repetidas por bot | Extraido a `common/` |
| Dependencia suaporte→simpleco | Import directo `from simpleco.bot` | Ambos importan de `common/pdf_helpers` |
| CSV | Formatos inconsistentes (`timestamp` vs `fecha_hora`) | Unificado en `common/csv_writer` |
| Base de datos | No existe | MySQL con vista consolidada |
| Parsers | No existe | Por bot, configuracion declarativa |
| User-Agent | Windows Chrome | Linux Chrome |
