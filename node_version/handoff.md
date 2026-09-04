# Handoff — ByBot App (node_version)

Documento de contexto para la siguiente sesión de IA/consola. Resume dónde vamos, qué
está hecho, qué falta y qué pasos ejecutar.

> Ámbito: SOLO `node_version/`. La carpeta `php_version/` es legado (versión anterior en
> PHP) y no debe tocarse a menos que se indique lo contrario.

---

## 1. Estado general del proyecto

App de **carga de archivos + análisis con IA + consultas bot** para procesos de un estudio
jurídico/cobranza. Fases F0b→F3 completadas y funcionales. **+ F4: ingesta multi-entidad**
(ver §1.b).

Servicios (los 3 corriendo):
- **Backend** Fastify+Prisma → `http://localhost:3001` (health: `/api/v1/health`)
- **Frontend** React+Vite+Bootstrap → `http://localhost:5173`
- **Botstorage** microservicio de archivos → `http://localhost:3002`

Daemons Python:
- **Análisis** (Gemini): log en `/tmp/bydaemon.log`
- **Bot runner** (consultas): log en `/tmp/bybotrunner.log`

Login: `admin` / **`admin555`** (la clave por defecto `admin123` es de un solo uso y ya fue
cambiada en esta BD de desarrollo).

---

## 1.a Entorno en este Mac (macOS) — cómo levantar

> El proyecto nació en Linux/XAMPP (`/opt/lampp/...`), pero aquí corre en macOS con Docker.
> La red corporativa **bloquea el registro npm/PyPI público**; el único registro es **Fury**.

1. **Docker = Colima** (no Docker Desktop): `colima start`.
2. **Base de datos** (contenedor, no XAMPP):
   ```bash
   docker run -d --name bybot-mariadb -e MARIADB_ROOT_PASSWORD=bybot_root \
     -e MARIADB_DATABASE=bybot_consolidado -p 3306:3306 mariadb:10.11
   # cargar esquema (reemplaza a `npm run db:reset`, que usa el path XAMPP):
   docker exec -i bybot-mariadb mariadb -uroot -pbybot_root bybot_consolidado < sql/ddl.sql
   ```
   Conexión en `.env`: `DATABASE_URL=mysql://root:bybot_root@127.0.0.1:3306/bybot_consolidado`.
3. **Registro de paquetes (Fury)**: si `npm install`/`pip` da **403**, autenticar:
   `PATH=~/.fury/fury_venv/bin:$PATH fury registry login` (las credenciales expiran ~8h).
4. **Node**: `npm install` (raíz, workspaces) + `npm -w backend run db:generate`.
5. **Python worker**: `python3 -m venv botworker/.venv`; `pip install -r botworker/requirements.txt -r bots/requirements.txt`;
   `playwright install chromium`; `brew install tesseract`.
6. **Arrancar**: `npm run dev` (3 servicios) y los daemons con `nohup` (macOS no tiene `setsid`):
   ```bash
   nohup ./botworker/.venv/bin/python botworker/daemon.py     > /tmp/bydaemon.log 2>&1 &
   nohup ./botworker/.venv/bin/python botworker/bot_runner.py > /tmp/bybotrunner.log 2>&1 &
   ```
   Tras editar `analizador.py` o cambiar la `GEMINI_API_KEY`/config del `.env`, **reiniciar el daemon**.

---

## 1.b F4 — Ingesta multi-entidad de documentos (sesión reciente)

Cada **entidad** (cliente/cooperativa) define su propio catálogo de documentos y sus prompts;
el análisis mapea todo a una **taxonomía canónica** (`pagare, estado_cuenta, amortizacion,
vinculacion, poder, anexo, identificacion, otro`).

- **Datos**: tablas `entidades` y `entidades_tipos_doc`; `procesos.entidad_id`; `app_prompts.entidad_id`
  (prompt específico de entidad gana sobre el global). Migraciones: `sql/migrations/f4_entidades.sql`,
  `f4b_prompts_globales_y_catalogos.sql`, `f4c_tokens.sql`, `f4d_prompts_referencias.sql`,
  `f4e_prompts_observaciones.sql`, `f4f_crearcoop_estado_cuenta.sql` (f4/f4b/f4c reflejadas en `sql/ddl.sql`;
  f4d–f4f solo como migración — aplicar sobre la BD existente).
- **Backend**: módulo `entidades/` (CRUD admin + `GET /entidades`, `/entidades/:id/tipos-doc`,
  `/entidades/:id/catalogo`). `archivos.service.ts` valida el `tipo` contra el catálogo de la entidad,
  detecta el MIME por **magic-bytes** (anti-spoofing) y acepta **TIFF**.
- **Worker** `analizador.py`: bucle genérico por categoría (una llamada Gemini por categoría),
  fusión canónica, reintentos ante 429/503, reparación de JSON truncado, y **deduplicación de listas**
  (`_dedupe_lists`, mitiga bucles de repetición del modelo). `shared/documentos.py` convierte
  **TIFF→PDF** con Pillow (Gemini no lee TIFF). Guarda `tokens_entrada/salida`. **Thinking desactivado**
  por defecto (`GEMINI_THINKING_BUDGET=0`).
- **Calidad de extracción (prompts)**: se endurecieron los prompts para evitar dos fallos vistos:
  (a) `referencias` repetidas en bucle (f4d) y (b) `observaciones` con transcripción de cláusulas
  legales (f4e → "resumen breve, no transcribir"). Además **prompt específico de Crearcoop para
  estado_cuenta** (f4f): su documento es un *detalle de movimientos*, no un resumen; el prompt captura
  la tabla de `movimientos` y usa la fila final **"TOTAL SALDO A CARGO A: <fecha>"** como resumen
  (calcula `total_deuda` = suma de sus columnas).
- **Frontend**: módulo **Entidades** (sidebar, admin) para CRUD + catálogo; selector de entidad al
  crear proceso; tipos de documento dinámicos; `ValidacionForm` renderiza arrays de objetos como
  **tabla** (amortización, movimientos, referencias); **formato numérico es-CO solo visual**
  (miles `.`/decimales `,`, sin alterar el dato guardado — inputs muestran crudo al enfocar);
  textos largos con **"ver más/menos"** (`CollapsibleText`); tarjeta **"Consumo IA"** (tokens + costo USD).
- **Config IA** (`.env` + `app_configuracion`): `GEMINI_MODEL=gemini-2.5-flash`,
  `GEMINI_MAX_TOKENS=26000` (evita truncar amortizaciones largas), `GEMINI_THINKING_BUDGET=0`,
  `precio_ia_entrada_usd_1m=0.30`, `precio_ia_salida_usd_1m=2.50`.

**Entidades sembradas**: `confiar` (prompts propios), `crearcoop` (prompt propio de estado_cuenta),
`somec` (prompts globales). Procesos de ejemplo: **Confiar** (id 2, 36 cuotas, 2 referencias),
**Somec** (id 5, incluye `formulario.tif` → 48 cuotas), **Crearcoop** (id 3, 46 movimientos).
Docs de muestra en `../archivos/{condiar,crearcoop,somec}/`.

**Para añadir una entidad nueva**: crearla en el módulo Entidades (o SQL), definir su catálogo de
documentos y —si su layout lo requiere— prompts específicos por categoría. Sin tocar código.

**Pendiente / notas**:
- **Reanalizar proceso 3 (Crearcoop)** cuando haya cuota: el prompt f4f ya está, pero la última
  corrida quedó con `estado_cuenta` vacío porque el 429 (cuota diaria agotada) tumbó esa categoría.
  Un reanálisis dejará el estado de cuenta completo con `total_deuda` (esperado **33.187.280**).
- **Opción robusta pendiente**: calcular `total_deuda`/totales en el worker desde el array
  `movimientos` (no depender de la aritmética del modelo). No implementado aún.
- **Cuota Gemini**: el free-tier (RPD) se agota rápido con reanálisis repetidos; ante 429
  `RESOURCE_EXHAUSTED` esperar el reset diario (medianoche PT) o usar key con facturación.
- Otros: plantilla Excel descargable (regla 4.1) + `openpyxl` en el venv si se procesan `.xlsx`.

**Sesión actual — consultas simpleco (estado)**:
- Se integró `simpleco` al flujo de consultas y a la UI (labels/colores/selector + badge por estado).
- **Estados de bot → `procesos_consultas.estado`** (nuevo `_mapear_estado` en `bot_runner.py`):
  `EXITOSA→exitoso`, `SIN_PAGOS_6_MESES→sin_pagos`, resto→`fallido`. En `ConsultasResult.tsx`
  hay badge/icono/color propio para `sin_pagos` ("Sin pagos en los últimos 6 meses").
- **Parser de comprobante PILA**: `bots/simpleco/parser.py` (pdfplumber) extrae empresa+NIT,
  empleado+cédula, periodo, tipo_admin/EPS, código/NIT/nombre de la entidad y administradoras;
  se persiste en `simpleco_consultas` (columnas detalles + `metadata_json`) desde `service.py`.
  Validado contra un PDF real de SuAporte (misma estructura PILA) → extrae todo.
- **Fixes de BD críticos**: `bots/common/db.py` y `bots/common/storage.py` ahora leen credenciales
  `DB_*` del `.env` (antes hardcodeadas / `BYBOT_DB_*` inexistentes). Se creó la tabla
  `procesos_consultas` (migración `sql/f3_procesos_consultas.sql`) que faltaba, y el modelo
  Prisma `SimplecoConsulta`.
- **Fix de mapeo de datos**: `encolarConsultas` ahora acepta `deudor/codeudor.numero_documento|numero_id`
  y `nombre_completo|nombre` (antes solo `numero_id`/`nombre`, rompía el flujo).
- **PENDIENTE / no validado**: **no se logró un `EXITOSA` real** de simple.co (bloqueo de seguridad
  intermitente; se obtuvieron `SIN_PAGOS_6_MESES` y `ERROR_SEGURIDAD`). El parser quedó **sin validar
  sobre un comprobante real de Simple.co** (validado contra la estructura SuAporte/PILA). Para validar
  end-to-end (PDF→datos→front) hace falta una corrida `EXITOSA` (cuando simple.co deje pasar y el
  deudor tenga pagos en el periodo) — reintentar con los CC ya conocidos o uno con afiliación activa.
- Nota: `BOTS_POR_DEFECTO` en backend ahora incluye `simpleco`; en `app_configuracion`,
  `bot_order=["simpleco"]`.

**Completitud de datos IA (estado de cuenta / tablas) — sesión**: al reanalizar PROC-2 (crearcoop),
la cuota free-tier de Gemini (RPD) hizo que categorías cayeran con 429/503 al azar. Cambios:
- **`getResultados` (backend `analisis.service.ts`) ahora FUSIONA todas las corridas** por clave de
  categoría (gana la más reciente) y suma tokens/costo. Así un re-análisis parcial NO reemplaza lo
  ya extraído (antes rompía el conjunto: solo se mostraba la última versión). `datos_validados` se toma
  de la corrida más reciente que haya sido validada.
- Aplicada **`sql/migrations/f4f_crearcoop_estado_cuenta.sql`** (prompt específico crearcoop: extrae la
  tabla `movimientos` + `total_deuda` calculado + `asociado`/`deudor`). El front ya renderiza arrays de
  objetos como tabla (`ValidacionForm`), así que `movimientos` aparecerá sin código nuevo.
- **PROC-2 actual**: `validado`, con `deudor` (40418092 MAGNOLIA ROCIO BUENO CHAMBO → ya sirve para bots),
  `pagare`, `codeudor`, `entidad`, `observaciones`, y `estado_cuenta` (genérico de corrida previa).
  **PENDIENTE**: la tabla `movimientos` del estado de cuenta (prompt f4f) aún NO se capturó — el re-análisis
  de estado_cuenta cayó por cuota Gemini agotada. **Re-analizar PROC-2 tras el reset diario de quota
  (medianoche PT)** para obtener `movimientos` + `total_deuda`; la fusión garantiza que se sume, no que reemplace.
- **PROC-3 (somec)**: completo (amortización 48 cuotas, deudor, pagare, poder, referencias). No se
  re-ejecutó (ya estaba completo y la cuota está agotada; no aporta).

---

## 2. Arquitectura de bots (cómo fluye una consulta)

```
Frontend/Backend  →  app_colas_trabajos (cola 'bybot:consultar')
        ↓ claim_next()
bot_runner.py  (daemon /tmp/bybotrunner.log)
        ↓ BOT_REGISTRY[name] -> ("bots.<name>.service", "tabla_consultas")
bot/<name>/service.py  (run_<name>_bot(...))
        ↓ Playwright + persistent profile
bot/<name>/bot.py  (automatización del portal)
        ↓ resultado + HTML/PDF
bot/<name>/parser.py  (extracción estructurada)
        ↓
tabla consultas (fosiga_consultas, ruaf_consultas, rues_consultas, simpleco_consultas, ...)
```

Claves:
- `botworker/bot_runner.py` — daemon de cola. `BOT_REGISTRY` (línea 35) mapea
  `nombre_bot -> (modulo_service, tabla_db)`. Claim con `FOR UPDATE`, estado
  `pendiente→procesando→completado/error`.
- `botworker/daemon.py` — daemon genérico/supervisor.
- `botworker/analizador.py` — análisis IA (Gemini) por `proceso_id`.
- Cada bot expone `cli.py` (prueba manual) y `service.py` (invocado por el runner).

---

## 3. Cambio reciente IMPORTANTE: perfil persistente de Chrome

Para combatir bot-detección (reCAPTCHA invisible, "no se pudo validar la seguridad"), se
creó un helper compartido que todos los bots usan:

- **`bots/common/browser.py` → `crear_contexto_persistente(...)`**
  - Usa `launch_persistent_context()` con perfil en `~/.bybot/chrome_profile`
    (cookies/estado persistente entre corridas = fingerprint "real").
  - Aplica stealth scripts + args anti-detección por defecto.
  - Retorna `(pw, context)`; el caller debe hacer `context.new_page()` y en `finally`
    `context.close()` + `pw.stop()`.
- **Migrados los 5 bots** a este helper: `ruaf`, `simpleco`, `fosiga`, `rues`, `aportesenlinea`.

Colisión importante a respetar al migrar un bot: el cambio de `with sync_playwright() as p:`
a `crear_contexto_persistente` elimina **un nivel de indentación** en todo el bloque
`try/finally`. Verificar siempre con `py_compile` tras editar.

---

## 4. Estado del dashboard de bots (6)

| Bot | Código | Registrado en runner | Probado | Blocker / nota |
|-----|--------|----------------------|---------|----------------|
| **simpleco** | OK | ✅ | ✅ | Funciona (CC 1022434547 y 39741702). reCAPTCHA invisible a veces bloquea pero el perfil persistente ayuda. |
| **ruaf** | OK (compila) | ✅ | ⚠️ | Fixes re-aplicados. Servidor SISPRO inestable: NullReferenceException (500), imágenes captcha "rotas". |
| **fosiga** | OK (compila) | ✅ | ⚠️ | Bloqueado por validación/reCAPTCHA del portal ADRES. |
| **rues** | OK (compila) | ✅ | ⚠️ | Timeout del input de búsqueda en headless — probar o detectar estructura. |
| **aportesenlinea** | OK (compila) | ❌ NO registrado | ⚠️ | reCAPTCHA de imágenes no resoluble automático. Tiene `service.py` pero NO está en `BOT_REGISTRY`. |
| **suaporte** | OK | ❌ NO registrado | — | Tiene `service.py` pero NO está en `BOT_REGISTRY`. |
| **asopagos** | ❌ No existe | — | — | Falta construir `bot.py` completo (`bots/asopagos/` no existe). |

---

## 5. Fixes aplicados (y por qué)

### RUAF (`bots/ruaf/bot.py`)
- **Captcha primero Gemini** (antes OCR): `resolver_captcha_ocr` se llama antes que
  `leer_captcha_multipass`. Estrategia `"gemini"`; OCR como fallback.
- **`no_wait_after=True`** en el clic de "Consultar" para evitar timeout de navegación de
  Playwright (`#MainContent_btnConsultar`).
- **Detección de error de servidor**: constantes `MSG_SERVER_ERROR_1`
  ("Ocurrio un error al realizar la consulta...") y `MSG_SERVER_ERROR_2`
  ("Server-unavailable!"), detectadas en `detectar_mensaje_no_exitoso()` y en
  `esperar_pagina_consulta_tras_terminos()` (lanza `RuntimeError` en vez de hacer timeout 90s).

### Simpleco (`bots/simpleco/bot.py`)
- Normalización con `unicodedata` en `_hay_sin_pagos_ultimos_6_meses()` (acento en "últimos").
- `motivo` truncado a 480 chars (evita "Data too long" en SQL).
- Registrado en `BOT_REGISTRY`.

### General
- Todos los bots migrados a `crear_contexto_persistente`.

---

## 6. Tareas pendientes (orden sugerido)

1. **Registrar `suaporte` y `aportesenlinea` en `BOT_REGISTRY`** (`bot_runner.py:35`):
   mapear `("bots.aportenService", "aportesenlinea_consultas")` y `("bots.suaporte.service", "suaporte_consultas")`.
   Revisar que los `service.py` acepten los kwargs que el runner pasa.
2. **Probar RUAF** cuando el servidor SISPRO esté estable (con `test_ruaf.sh`).
3. **Revisar RUES**: timeout del input `#search[name='search']`; probar headed o validar
   cambio de estructura del sitio.
4. **Construir bot `asopagos`** (no existe): `bot.py`, `service.py`, `cli.py`, `parser.py`,
   registrar en runner y en `bots/README.md`.
5. **Fosiga/Aportesenlinea**: resolver o documentar el reCAPTCHA (widget manual / desafío de
   imágenes no resoluble automáticamente).

---

## 7. Comandos útiles

```bash
# Ambiente Python (venv del worker)
VENV=/opt/lampp/htdocs/projects/bybot_v1/node_version/botworker/.venv
source "$VENV/bin/activate"

# Verificar sintaxis de un bot (SIEMPRE tras editar un bot)
python -c "import py_compile; py_compile.compile('bots/ruaf/bot.py', doraise=True)"

# Probar bots
bash bots/ruaf/test_ruaf.sh
bash bots/simpleco/test_simpleco.sh
python -m fosiga.cli --numero 39741702 -v            # desde bots/
python -m rues.cli --numero 52727688 -v              # sin --headed = headless
python -m aportesenlinea.cli --numero 39741702 --no-captcha-interactivo --no-modo-lento --no-pausa -v

# Logs daemons
tail -f /tmp/bydaemon.log      # análisis IA
tail -f /tmp/bybotrunner.log   # bot runner

# Servicios (desde node_version/)
npm run dev
```

---

## 8. Notas críticas / trampas

- **Quota Gemini**: modelo `gemini-2.5-flash`, ~**5 req/min** → errores `429` si se abusa
  (se golpeó en una prueba de RUAF). Key en `.env` raíz (`GEMINI_API_KEY`).
- **`p` vs `pw`/`browser`**: en los bots migrados, el runner de Playwright ya no es
  `p.chromium.launch(...)` ni `browser.close()`; es `crear_contexto_persistente` → `(pw, context)`
  y en `finally` `pw.stop()`. No reintroducir `sync_playwright` en esos archivos.
- `php_version/` es **legado** — no editar. Trabaja en `node_version/`.
- Store de archivos es local via `botstorage` (interfaz swappable a S3/R2).
- Autenticación JWT (access 15 min + refresh 7 días), RBAC desde `roles.json`.
- Consultas y análisis pueden ser manuales o auto-triggered (config en `app_configuracion`).