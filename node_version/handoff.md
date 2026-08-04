# Handoff — ByBot App (node_version)

Documento de contexto para la siguiente sesión de IA/consola. Resume dónde vamos, qué
está hecho, qué falta y qué pasos ejecutar.

> Ámbito: SOLO `node_version/`. La carpeta `php_version/` es legado (versión anterior en
> PHP) y no debe tocarse a menos que se indique lo contrario.

---

## 1. Estado general del proyecto

App de **carga de archivos + análisis con IA + consultas bot** para procesos de un estudio
jurídico/cobranza. Fases F0b→F3 completadas y funcionales.

Servicios (los 3 corriendo):
- **Backend** Fastify+Prisma → `http://localhost:3001` (health: `/api/v1/health`)
- **Frontend** React+Vite+Bootstrap → `http://localhost:5173`
- **Botstorage** microservicio de archivos → `http://localhost:3002`

Daemons Python:
- **Análisis** (Gemini): log en `/tmp/bydaemon.log`
- **Bot runner** (consultas): log en `/tmp/bybotrunner.log`

Login por defecto: `admin` / `admin123` (password de un solo uso → se pide cambiarla).

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