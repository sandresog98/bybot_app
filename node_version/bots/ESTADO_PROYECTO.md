# ByBot — Estado del proyecto

> Ultima actualizacion: 2026-06-30

---

## 1. Resumen general

| Bot | Estado | Archivo | Parser | Campos extraidos | Tiempo exec |
|-----|--------|---------|--------|-----------------|-------------|
| **rues** | Funcional | HTML 700 KB | 6/9 | matricula, estado, camara, categoria, identificacion | ~5s |
| **fosiga** | Funcional | HTML 9 KB | 13/13 | nombre, apellidos, EPS, regimen, estado, fechas afiliacion | ~10s |
| **suaporte** | Funcional | PDF 15 KB | stub | Texto + 3 tablas extraidas (pendiente campos especificos) | ~35s |
| **simpleco** | Parcial | PDF (~20 KB) | stub | Bloqueado por verificacion de identidad del portal | 6s (corta) |
| **aportesenlinea** | Parcial | PDF (~400 KB) | stub | Bloqueado por reCAPTCHA + conexion rechazada | — |
| **ruaf** | Funcional | HTML 250 KB | Gemini Vision | Captcha: Gemini OCR fallback. Reporte: Gemini Vision extrae EPS, regimen, estado | ~40s |
| **asopagos** | ROTO | — | — | Sin bot.py. Modulo `interssi` inexistente | — |

---

## 2. Campos extraidos por bot

### fosiga (ADRES — Consulte su EPS)

| Campo | Valor de ejemplo |
|-------|-----------------|
| tipo_identificacion | CC |
| numero_identificacion | 1022434547 |
| nombres | ANDRES FELIPE |
| apellidos | OLAYA GARAY |
| fecha_nacimiento | **/**/** |
| departamento | BOGOTA D.C. |
| municipio | BOGOTA D.C. |
| estado | ACTIVO |
| entidad | ENTIDAD PROMOTORA DE SALUD SANITAS S.A.S. |
| regimen | CONTRIBUTIVO |
| fecha_afiliacion_efectiva | 01/01/2026 |
| fecha_finalizacion_afiliacion | 31/12/2999 |
| tipo_afiliado | COTIZANTE |

### rues (Registro Mercantil)

| Campo | Valor de ejemplo |
|-------|-----------------|
| identificacion | 52727688-7 |
| categoria | Persona natural |
| camara_comercio | Bogota |
| matricula_mercantil | 2587739 |
| estado_matricula | Activa |
| Pendientes: razon_social, nit, direccion, municipio, departamento, fecha_renovacion | (requiere clic en vista detalle) |

### ruaf (SISPRO — Afiliacion)

| Campo | Estado |
|-------|--------|
| eps_afiliado | Extraido via Gemini Vision |
| regimen | Extraido via Gemini Vision |
| estado_afiliacion | Extraido via Gemini Vision |
| fecha_afiliacion_eps | Extraido via Gemini Vision |
| tipo_afiliado | Extraido via Gemini Vision |

> El captcha se resuelve con Tesseract multipass (90 combinaciones) + Gemini como
> fallback cuando Tesseract falla. El reporte se renderiza como imagen en un control
> Microsoft ReportViewer — Gemini Vision lee la imagen y extrae los campos.

### suaporte (comprobante PDF)

| Campo | Estado |
|-------|--------|
| tipo_planilla | E |
| numero_planilla | 84815260 |
| periodo_cotizacion | 202603 |
| periodo_servicio | 202604 |
| empresa | COORDINADORA MERCANTIL SA |
| empleado | ESPEJO MARTINEZ NAILLIBETH CECILIA |
| cedula | 1073710057 |
| Campos de administradoras (ARL, EPS, AFP, CCF) | Pendiente extraccion especifica |

### simpleco (comprobante PDF)

| Campo | Estado |
|-------|--------|
| tipo_planilla | Pendiente (sin PDF de prueba) |
| numero_planilla | Pendiente |
| periodo_cotizacion | Pendiente |
| empresa | Pendiente |
| empleado | Pendiente |

### aportesenlinea (certificado PDF)

| Campo | Estado |
|-------|--------|
| nombre_certifica | WILLIAM GIOVANNY ROJAS LAITON |
| cedula | 1012420137 |
| aportante | SOCIEDAD DE INGENIERIA CIVIL TELECOMUNICACIONES ELECTRICAS |
| nit | 830113252 |

---

## 3. Mejoras implementadas

| Mejora | Archivo | Impacto |
|--------|---------|---------|
| Backend dual MySQL+CSV con fallback automatico | `common/storage.py` | Datos nunca se pierden |
| Gemini para OCR captcha + diagnostico errores | `common/ai.py` | Resuelve captchas donde Tesseract falla. Probado: "P48H4" aceptado por RUAF |
| Gemini Vision para extraer datos del reporte RUAF | `common/ai.py` + `ruaf/bot.py` | Lee imagen del ReportViewer, extrae EPS/regimen/estado |
| Carga segura de API key via `.env` + `dotenv` | `common/ai.py` + `.env` | Key nunca en codigo, `.env` en `.gitignore` |
| Logica comun de reintentos captcha + estadisticas | `common/captcha.py` | `BucleCaptcha`, tasa exito/fallo |
| Script de pruebas masivas | `herramientas/prueba_masiva.py` | Multiples cedulas × multiples bots, reporte cobertura |
| Stealth scripts unificados (anti-deteccion) | `common/stealth.py` | 5 scripts, `CHROME_UA_LINUX` |
| Funciones JSF/PrimeFaces compartidas | `common/pdf_helpers.py` | 10 funciones extraidas de simpleco |
| Deteccion de "No fue posible validar la seguridad" | `simpleco/bot.py` | Timeout 120s → 1s |
| Deteccion de redirect a `Preguntas.xhtml` | `simpleco/bot.py` | `ERROR_PREGUNTAS_SEGURIDAD` documentado |
| Deteccion de imagen captcha rota (`naturalWidth===0`) | `ruaf/bot.py` | `forzar_renovacion` ~18s → `preparar_formulario` 0.1s |
| Site key reCAPTCHA externalizada a `.env` | `fosiga/bot.py` | `BYBOT_FOSIGA_RECAPTCHA_KEY` |
| Arreglo de tildes en "Valido/Invalido" | `ruaf/bot.py` | Ahora detecta "Válido"/"Inválido" con acento |
| Arreglo de tildes en `detectar_mensaje_no_exitoso` | `ruaf/bot.py` | `unicodedata.normalize("NFKD")` |

---

## 4. Mejoras pendientes

### Alta prioridad

| Mejora | Descripcion | Esfuerzo |
|--------|-------------|----------|
| ~~Gemini API key en RUAF~~ ✅ | Configurar `GEMINI_API_KEY` para que el captcha de RUAF use Gemini como fallback del OCR. Tasa de exito esperada: 90%+ | Completado |
| ~~RUAF — OCR Gemini sobre imagen reporte~~ ✅ | Usar Gemini Vision para leer la imagen del ReportViewer y extraer EPS, regimen, estado | Completado |
| Campos especificos PDF SuAporte | Extraer empresa, empleado, cedula, NIT, ARL, EPS, AFP, CCF del texto ya disponible | Medio (parser regex) |
| Campos especificos PDF Aportes en Linea | Extraer nombre, cedula, aportante, NIT del texto ya disponible | Bajo (parser regex) |
| Campos especificos PDF Simple.co | Requiere correr el bot con una cedula que pase verificacion de identidad y obtener PDF real | Medio (prueba + parser) |

### Media prioridad

| Mejora | Descripcion | Esfuerzo |
|--------|-------------|----------|
| RUES — clic en resultado | Hacer clic en la fila de resultado para obtener `razon_social`, `nit`, `direccion` de la vista detalle | Medio |
| Simple.co — automatizar verificacion | Leer datos del afiliado (nombre, apellido, email, EPS) de fuente externa para llenar form de preguntas | Alto |
| Aportes en Linea — debug conexion | Investigar por que el portal rechaza `ERR_CONNECTION_CLOSED` desde este servidor | Medio |
| RUAF — OCR Gemini sobre imagen reporte | Usar Gemini Vision para leer la imagen del ReportViewer y extraer EPS, regimen, estado | Medio |
| Base de datos MySQL | Ejecutar `sql/ddl.sql` y poblar con datos reales. La vista `consultas_consolidadas` ya esta definida | Bajo |

### Baja prioridad

| Mejora | Descripcion |
|--------|-------------|
| Dockerfile | Contenedor con Python, Playwright, Tesseract, Chromium |
| CI/CD | Pruebas automaticas al hacer push |
| asopagos | Reconstruir `bot.py` desde cero (requiere acceso al portal) |
| Logging a archivo | Opcion `--log-file` para guardar logs en disco |

---

## 5. Arquitectura

```
bots/
├── common/           # Utilidades compartidas (8 modulos)
│   ├── ai.py         # Gemini Vision (OCR, parseo validado, diagnostico)
│   ├── captcha.py    # BucleCaptcha, estadisticas
│   ├── db.py         # Conexion MySQL (legacy, reemplazado por storage)
│   ├── csv_writer.py # Escritura CSV (legacy, reemplazado por storage)
│   ├── logging_config.py
│   ├── pdf_helpers.py   # 10 funciones JSF/PrimeFaces
│   ├── stealth.py       # Anti-deteccion reCAPTCHA
│   ├── storage.py       # Backend dual MySQL+CSV
│   └── timezone_utils.py
│
├── sql/
│   └── ddl.sql       # 7 tablas + vista consultas_consolidadas
│
├── ruaf/             # SISPRO — OCR captcha + HTML
├── fosiga/           # ADRES — reCAPTCHA Enterprise + HTML
├── rues/             # Registro Mercantil — HTML
├── suaporte/         # Comprobante PDF
├── simpleco/         # Comprobante PDF (bloqueado por verificacion)
├── aportesenlinea/   # Certificado PDF (bloqueado por reCAPTCHA)
└── asopagos/         # ROTO (sin bot.py)
```

---

## 6. Variables de entorno

```bash
# Gemini AI (ocr captcha, diagnostico errores)
export GEMINI_API_KEY="tu-api-key"

# MySQL (primario, CSV es fallback automatico)
export BYBOT_DB_HOST="127.0.0.1"
export BYBOT_DB_PORT="3306"
export BYBOT_DB_USER="root"
export BYBOT_DB_PASSWORD=""
export BYBOT_DB_NAME="bybot_consolidado"

# reCAPTCHA Enterprise (ADRES)
export BYBOT_FOSIGA_RECAPTCHA_KEY="6Ldjqjks..."

# Modelo Gemini
export BYBOT_GEMINI_MODEL="gemini-2.5-flash"
```

---

## 7. Prompt para infografia

```
Crea un diagrama de arquitectura de datos en español para el proyecto "ByBot v2".

FORMATO: Diagrama de flujo de izquierda a derecha que muestre el pipeline
de extraccion y consolidacion de datos. NADA de bullets, solo cajas y flechas.
Dimension: 16:9 (horizontal, presentacion).

ESTRUCTURA DEL DIAGRAMA (3 columnas):

COLUMNA 1 — PORTALES WEB (7 cajas, una por portal):
  • SISPRO (RUAF)          — Afiliacion a salud
  • ADRES                   — Consulta EPS
  • RUES                    — Registro Mercantil
  • SuAporte                — Comprobante PDF
  • Simple.co               — Comprobante PDF
  • Aportes en Linea        — Certificado PDF
  • ASOPAGOS                — Certificado (roto ❌)

COLUMNA 2 — TABLAS POR BOT (6 cajas, una por bot funcional):
  Cada caja muestra el nombre de la tabla y sus columnas principales.
  • ruaf_consultas       → numero_id, eps_afiliado, regimen, estado_afiliacion
  • fosiga_consultas     → numero_id, nombres, apellidos, entidad (EPS), regimen, estado
  • rues_consultas       → identificacion, matricula_mercantil, razon_social, nit, estado_matricula
  • suaporte_consultas   → numero_id, empresa, empleado, periodo_cotizacion, nombre_eps, nombre_afp
  • simpleco_consultas   → numero_id, empresa, empleado, periodo_cotizacion, nombre_entidad
  • aportesenlinea_consultas → numero_id, nombre_certifica, aportante, nit_aportante

COLUMNA 3 — TABLA CONSOLIDADA (1 caja grande):
  • consultas_consolidadas (VISTA SQL)
    Columnas unificadas: fuente, numero_id, fecha_consulta, estado, entidad_1,
    razon_social, nit, empresa, empleado, periodo_cotizacion

FLECHAS:
  De cada portal → a su tabla respectiva (lineas de color)
  De las 6 tablas → convergiendo a la vista consolidada

CODIGO DE COLORES POR ESTADO DEL BOT:
  🟢 Verde  — 100% funcional, datos reales extraidos: RUES, FOSIGA, SuAporte, RUAF
  🟡 Amarillo — Funcional pero con bloqueo: Simple.co, Aportes en Linea
  🔴 Rojo — Roto: ASOPAGOS

ESTILO:
  Fondo oscuro (#1a1a2e). Cajas con bordes redondeados y sombra suave.
  Titulo grande arriba: "ByBot v2 — Pipeline de Datos"
  Subtitulo: "Automatizacion de consultas a portales colombianos"
  Tecnologias en una barra inferior: Python · Playwright · Chromium · BeautifulSoup · pdfplumber · MySQL
  Sin bullets, sin texto explicativo largo. Solo el diagrama.
```

---

## 9. Prompt para infografia de proximos pasos

```
Crea un diagrama de roadmap en espanol para el proyecto "ByBot v2".
Formato: fila de 5 tarjetas apiladas de arriba hacia abajo, una por cada
proximo paso, ordenadas por prioridad (la mas urgente arriba).
Dimension: 16:9 (horizontal, presentacion).

TITULO: "ByBot v2 — Proximos Pasos"
SUBTITULO: "Seleccione la prioridad para la siguiente fase"

TARJETA 1 — COMPLETAR PARSERS PDF
  Icono: documento o archivo
  Descripcion: Definir campos especificos para los PDF de SuAporte
  (empresa, empleado, cedula, ARL, EPS, AFP, CCF), Simple.co y Aportes
  en Linea. Los stubs ya extraen el texto, solo faltan los patrones regex.

TARJETA 2 — PIPELINE ETL DIARIO
  Icono: reloj o calendario
  Descripcion: Programar cron job que ejecute todos los bots diariamente
  a las 2am, alimente la base de datos MySQL y rote los archivos
  descargados. Monitoreo basico con log de ejecucion.

TARJETA 3 — DASHBOARD WEB
  Icono: grafico de barras
  Descripcion: Interfaz web simple (Flask o FastAPI) que permita buscar
  por numero de cedula, ver el historial consolidado de todos los bots,
  descargar PDFs originales y exportar a Excel.

TARJETA 4 — INTERFAZ DE CARGA Y EJECUCION PERIODICA
  Icono: pantalla o monitor
  Descripcion: Pantalla donde el cliente cargue un archivo Excel/CSV con
  cedulas a consultar, seleccione que bots ejecutar, y el sistema procese
  automaticamente con reintentos y notificaciones al terminar.

TARJETA 5 — RECONSTRUIR BOT ASOPAGOS
  Icono: martillo o herramienta
  Descripcion: Reconstruir desde cero el bot de ASOPAGOS. Requiere acceso
  al portal para analizar formulario, tipo de captcha y flujo de descarga
  del certificado PDF.

ESTILO:
  Fondo oscuro (#1a1a2e). Cada tarjeta con borde redondeado, sombra
  suave y un numero grande a la izquierda (1-5) indicando prioridad.
  Colores: azul (#3b82f6) para las primeras 2 (corto plazo),
  naranja (#ed8936) para las siguientes 2 (medio plazo),
  gris (#6b7280) para la ultima (largo plazo).
  Sin bullets, sin texto explicativo largo. Solo las tarjetas.
  Al pie: "Junio 2026 · 3 de 8 mejoras completadas · El orden puede ajustarse".
```

---

## 10. Prompt para infografia del pipeline completo

```
Crea un diagrama de proceso en español que muestre las 6 fases del negocio
completo de ByBot, de izquierda a derecha.
Dimension: 16:9 (horizontal, presentacion).

TITULO: "ByBot — Fases del Negocio"
SUBTITULO: "Desde la recepcion de datos hasta el seguimiento de demandas"

FASE 1 — RECEPCION DE INFORMACION
  Icono: bandeja de entrada o upload
  Color: gris claro (#9ca3af) con borde punteado
  Texto: El cliente entrega los datos de la persona (cedula, nombre,
  fecha de nacimiento) via archivo Excel, formulario web o carga manual.
  Etiqueta inferior: "PENDIENTE — Requiere interfaz de carga"

FASE 2 — EXTRACCION DE INFORMACION
  Icono: lupa sobre documento
  Color: gris claro (#9ca3af) con borde punteado
  Texto: El sistema extrae y normaliza los datos del archivo recibido.
  Valida cedulas, completa campos faltantes y prepara el lote de consulta.
  Etiqueta inferior: "PENDIENTE — Requiere parser de documentos"

FASE 3 — LEVANTAMIENTO CON BOTS
  Icono: robot o engranaje
  Color: verde (#10b981) con borde solido
  Texto: Los 4 bots funcionales (RUES, FOSIGA, SuAporte, RUAF) consultan
  los portales colombianos y extraen datos estructurados. Los resultados
  se consolidan en la vista consultas_consolidadas.
  Etiqueta inferior: "IMPLEMENTADO ✅"

FASE 4 — GENERACION DE DEMANDA
  Icono: documento con pluma
  Color: naranja (#ed8936) con borde punteado
  Texto: Con los datos consolidados de todos los bots, el sistema genera
  automaticamente el documento de demanda con los anexos probatorios
  (HTML y PDF descargados).

FASE 5 — PRESENTACION DE DEMANDA
  Icono: edificio o tribunal
  Color: naranja (#ed8936) con borde punteado
  Texto: El abogado revisa y presenta la demanda ante la entidad
  correspondiente. El sistema registra la fecha de radicacion y el
  numero de proceso.

FASE 6 — SEGUIMIENTO DE ESTADOS
  Icono: grafico de seguimiento o checklist
  Color: naranja (#ed8936) con borde punteado
  Texto: Nuevos bots consultan periodicamente las paginas de estado
  de procesos judiciales (consultaprocesos.ramajudicial.gov.co y otros)
  para detectar cambios, notificar al cliente y actualizar el expediente.

FLECHAS: Una flecha gruesa conecta cada fase con la siguiente.
Las fases 1 y 2 estan ligeramente mas arriba que la 3, con una
anotacion: "FASES DEL PROYECTO — Por construir".
Las fases 4, 5 y 6 estan ligeramente mas abajo, con una anotacion:
"FASES DEL NEGOCIO — Futuro".

RESALTAR: Un recuadro brillante alrededor de la FASE 3 con el texto
"UNICA FASE IMPLEMENTADA — Arrancamos por lo mas complejo".

ESTILO:
  Fondo oscuro (#1a1a2e). Cada fase como una caja rectangular con borde
  redondeado, icono centrado arriba, texto descriptivo abajo.
  Solo la fase 3 tiene borde solido verde. Las demas tienen borde gris
  punteado (1-2) o naranja punteado (4-6).
  Sin bullets, sin texto explicativo largo. Solo las 6 cajas en fila.
  Al pie: "Junio 2026 · Andres y Sandra · Propuesta de continuidad".
```

---

## 11. Mensajes para el cliente

### SALUDO

> Buen dia Andres y Sandra, espero que esten muy bien.
>
> Retomando el espacio de los martes en la noche, quiero ponerlos al dia
> con los avances del proyecto ByBot y plantear los siguientes pasos.

### IMAGEN 1 — Estado actual

> **1. ¿Que tenemos hoy?**
>
> La plataforma cuenta con 7 bots de automatizacion que consultan portales
> colombianos. La arquitectura es escalable: cada bot es independiente y
> comparte un modulo comun de utilidades.
>
> Estado actual:
> - 4 bots completos y funcionales con datos reales extraidos
> - 2 bots con bloqueos por cambios en los portales
> - 1 bot pendiente de reconstruccion
>
> *(Ver diagrama de pipeline)*

### IMAGEN 2 — Mejoras pendientes

> **2. ¿Que podemos mejorar?**
>
> Sobre lo ya implementado, estas son las mejoras priorizadas para
> la siguiente fase. El orden puede ajustarse segun sus necesidades.
>
> *(Ver roadmap de proximos pasos)*

### IMAGEN 3 — Flujo completo del negocio

> **3. ¿Como continua el proyecto dentro del negocio?**
>
> El proceso completo del negocio tiene 6 fases. Nosotros arrancamos
> por la fase 3 (los bots) porque era lo mas complejo tecnicamente.
> Hoy esa fase esta implementada y funcional.
>
> Las fases 1 y 2 son prerequisitos del proyecto que faltan construir:
> una interfaz para recibir los documentos del cliente y un sistema
> que extraiga la informacion relevante de esos archivos.
>
> Las fases 4, 5 y 6 son etapas posteriores del negocio que se
> construiran cuando las primeras 3 esten completas.
>
> Segun lo conversado, mi sugerencia es continuar con la fase 6
> (seguimiento de estados en paginas judiciales) porque:
> - Se construye sobre la misma tecnologia de bots que ya dominamos
> - Agrega valor inmediato permitiendo monitorear demandas existentes
> - Podemos avanzarla en paralelo con las fases 1 y 2
>
> ¿Como lo ven ustedes? ¿Priorizamos fases 1-2 primero o fase 6?
>
> *(Ver diagrama de fases del negocio)*

