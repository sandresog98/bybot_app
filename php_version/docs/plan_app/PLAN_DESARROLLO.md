# Plan de Desarrollo — App ByBot (Carga de archivos → IA)

> Documento maestro de planificación. Vivo: actualizar conforme se tomen decisiones.
>
> Fecha de creación: 2026-07-06
> Proyecto raíz: `/opt/lampp/htdocs/projects/bybot_v1/`
> Referencia legacy: `/opt/lampp/htdocs/projects/byb/bybot_app/` (PHP + Python, NO probado)
> Normativa a seguir: `php_rules.md`

---

## 1. Contexto y objetivo

Construir la **app web** para cargar, almacenar y analizar con IA los documentos de los casos del estudio jurídico/cobranza:

1. **Carga de archivos** (PDF, imágenes, HTML, Excel) de los procesos desde una interfaz.
2. **Análisis con IA** (Gemini) para extraer y estructurar la información del proceso (deudor, codeudor, estado de cuenta, referencias, etc.).

**Alcance inmediato (plan operativo)**: Fase 0 (fundamentos) + Fase 1 (carga + almacenamiento) + Fase 2 (análisis con IA).

> **Futuro (fuera de alcance hoy)**: integración con `bots2/` para enriquecer procesos con registros públicos, y eventual generación de documentos (no necesariamente demandas). Pendiente de planificación detallada cuando se arranque.

El objetivo NO es replicar `bybot_app/` tal cual: esa versión dependía de **n8n en un VPS separado** con webhooks HTTP entre Hostinger y el VPS. En este nuevo plan **todo vive en un único servidor** (por ahora XAMPP local) y se elimina n8n en favor de una arquitectura **PHP + worker Python local**, más simple, mantenible y auditable. No se migra el diligenciamiento/llenado de PDFs del legado.

---

## 2. Análisis de lo que se hizo antes (`bybot_app/`)

### 2.1 Qué había y se puede aprovechar

| Componente | Valor reaprovechable | Dónde está |
|---|---|---|
| **DDL de BD** (`procesos`, `procesos_anexos`, `procesos_datos_ia`, `procesos_historial`, `colas_trabajos`, `prompts`, `control_usuarios`, `control_logs`, `configuracion`) | Modelo de datos sólido. Se adapta a `php_rules.md` (sin ENUM, con FK, comentarios de valores, prefijo por módulo). | `byb/bybot_app/sql/ddl.sql` |
| **Cliente Gemini** (`GeminiClient`) con prompts de estado de cuenta y anexos que devuelven JSON estructurado | Reutilizable casi intacto. Migrar a `app_worker/` Python. | `byb/bybot_app/n8n/scripts/analyzer/gemini_client.py` |
| **Prompts** embebidos (estado de cuenta + anexos) | Reutilizar como versión inicial. Versionarlos en BD (`app_prompts`). | `byb/bybot_app/sql/ddl.sql` y `gemini_client.py` |
| **Patrones de diseño PHP** (`BaseModel`, `BaseService`, `Response`, `Validator`) | Sirven como guía para el `core/` del nuevo proyecto, reescritos para invocar Python local. | `byb/bybot_app/web/core/` |

### 2.2 Qué se descarta

- **n8n como orquestador**: agrega un VPS, webhooks, tokens cruzados y debugging visual pero complejiza deploy y mantenimiento. Se reemplaza por un **worker Python local** invocado por PHP.
- **Diligenciamiento/llenado de PDFs** (`PDFFiller` + PyMuPDF): fuera de alcance por ahora. No se migra.
- **Colas Redis (legacy)**: no se usaba. Se usará **cola en BD** (`app_colas_trabajos`) + daemon Python que hace polling. Simple, durable, auditable.
- **Estructura `web/admin` + `web/api` + `web/modules`** plana: se reorganiza bajo el estándar `interfaz/modules/*/` exigido por `php_rules.md`.

### 2.3 Lecciones aprendidas

- El código anterior **se escribió pero nunca se probó**. Esta vez: **fase por fase con verificación real** (manual primero, luego suite de pruebas).
- Los prompts embebidos en el `.py` son frágiles. Se externalizan a BD (`app_prompts`) con versionado y editor en el admin.

---

## 3. Decisiones de arquitectura

### 3.1 Lenguaje: PHP 8.2 (app web) + Python (worker IA)

**PHP** para la app web y la API, siguiendo `php_rules.md`.
**Python** para el análisis con IA (Gemini), porque:
- La librería `google-generativeai` es Python-native; en PHP habría que envolverla igual.
- Reaprovechar sin reescribir los scripts del `bybot_app/`.

**No usamos Node.js/JS** salvo el JS de cliente del navegador (Bootstrap + Fetch hacia la API PHP).

### 3.2 Topología (un solo servidor local)

```
                         +-----------------------------+
                         |      Navegador (UX)         |
                         |  Bootstrap 5 + fetch + JS   |
                         +--------------+--------------+
                                        | HTTPS
                                        v
+----------------------------------------------------------+
|  Servidor local XAMPP (PHP 8.2 + MariaDB 11.8)           |
|                                                          |
|  app/admin/  (interfaz operador)                         |
|  app/api/    (REST v1, también para móvil futuro)        |
|  app/core/   (BaseModel, BaseService, Response, ...)     |
|                                                          |
|  app  -->  ejecuta vía exec/subprocess -->  worker cli   |
|                                                          |
|  app/procesos   (módulo de carga de archivos + IA)       |
+----------------------------------------------------------+
                  |                         ^
   exec Python    |  colas en BD            |  escribe resultados
                  v                         |
+----------------------------------------------------------+
|  Worker Python local (daemon + CLI)                      |
|                                                          |
|  app_worker/                                             |
|  ├── jobs/  analizador.py (Gemini)                       |
|  └── daemon.py  (polling app_colas_trabajos)             |
+----------------------------------------------------------+
                          |
                          v
            +-----------------------------+
            |  Google Gemini API          |
            +-----------------------------+
```

### 3.3 Comunicación PHP ↔ Python

- **Síncrono (caso simple, < 30s)**: PHP llama `exec("python3 app_worker/jobs/analizador.py --proceso_id 123")` y lee el JSON de stdout.
- **Asíncrono (largos, reintentos)**: PHP inserta una fila en `app_colas_trabajos` (estado `pendiente`); el **daemon Python** (systemd/cron) la toma por polling (cada 5s), ejecuta, escribe resultado y marca `completado`. La UI actualiza por **polling de estado** (XHR cada N segundos) — suficiente y simple, sin WebSockets.

Esto reemplaza con ventaja el esquema n8n + webhooks del `bybot_app/`.

### 3.4 Almacenamiento de archivos

- **Por defecto: servidor local** en `uploads/` con renombrado (`tipo` + `codigo_proceso` + `llave_unica` + `ext`), tal cual `php_rules.md` §2.3.
- **Abstracción Storage** (`core/Storage/StorageInterface`) con dos implementaciones: `LocalStorage` (default) y `RemoteStorage` (placeholder para S3/B2/Cloudflare R2 por definir). Se decide por `.env` (`STORAGE_DRIVER=local|remote`).
- **Servido solo vía API** (`/api/v1/archivos/{id}`) tras sesión válida (`php_rules.md` §2.4 y §6.3/6.4). Nunca URL directa a `uploads/`.
- Límites por entorno: `UPLOAD_MAX_SIZE_IMAGE`, `UPLOAD_MAX_SIZE_PDF`, `UPLOAD_MAX_SIZE_HTML`, `UPLOAD_MAX_SIZE_EXCEL` (`.env`).

### 3.5 Base de datos

- **Una sola BD**: `bybot_consolidado` (ya usada por `bots2/`), extendida con los nuevos módulos **prefijados por módulo** según `php_rules.md` §3.4.
- **Con llaves foráneas** (`php_rules.md` §3.2). El DDL legacy las omitía; lo corregimos.
- **Sin ENUM** (`php_rules.md` §3.5): columnas `VARCHAR` + comentario con valores válidos.
- Archivos SQL:
  - `sql/ddl.sql` — creación completa (reescrito, unifica bots2 + app).
  - `sql/reset_db.sql` — DROP de todo + ddl.
  - `sql/migrations/` — ajustes posteriores numerados (`001_*.sql`...).
- Tablas nuevas (prefijo por módulo):
  - `control_usuarios`, `control_logs`, `control_sesiones` — autenticación/auditoría.
  - `procesos`, `procesos_archivos`, `procesos_datos_ia`, `procesos_historial` — módulo **procesos** (carga + IA).
  - `app_colas_trabajos`, `app_configuracion`, `app_prompts` — transversales.

### 3.6 Roles y seguridad (sigue `php_rules.md` §6)

- `roles.json` define rol → módulos permitidos.
- Login con **usuario + contraseña de un solo uso** cambiada al primer ingreso (§6.5).
- Credenciales y tokens en `.env`.
- Rutas de `uploads/` bloqueadas por `.htaccess`; solo se accede vía API con sesión.

---

## 4. Estructura del proyecto (ajuste a `php_rules.md` §5)

```
bybot_v1/
├── app/                              # = la app web
│   ├── admin/                        # Interfaz administrador/operador (UI)
│   │   ├── config/paths.php
│   │   ├── controllers/AuthController.php
│   │   ├── views/layouts/{header,footer,sidebar}.php
│   │   ├── modules/
│   │   │   ├── dashboard/{api,models,pages,utils}/
│   │   │   ├── procesos/{api,models,pages,utils}/      # Fase 1 + Fase 2
│   │   │   ├── analisis/{api,models,pages,utils}/       # Fase 2 (validación de datos IA)
│   │   │   ├── prompts/{api,models,pages,utils}/        # gestión de prompts IA
│   │   │   ├── usuarios/{api,models,pages,utils}/
│   │   │   └── configuracion/{api,models,pages,utils}/
│   │   ├── index.php                 # router principal
│   │   ├── login.php
│   │   └── logout.php
│   │
│   ├── api/                          # API REST (consumida por admin y móvil futuro)
│   │   ├── index.php
│   │   ├── .htaccess
│   │   ├── middleware/ (auth, cors, rate_limit)
│   │   └── v1/
│   │       ├── auth/router.php
│   │       ├── procesos/router.php
│   │       ├── archivos/router.php
│   │       ├── analisis/router.php
│   │       ├── trabajos/router.php   # estado de colas (polling)
│   │       └── configuracion/router.php
│   │
│   └── core/                         # núcleo PHP
│       ├── BaseModel.php
│       ├── BaseService.php
│       ├── Response.php
│       ├── Validator.php
│       ├── Environ.php               # cargador .env
│       ├── Database.php              # PDO MariaDB
│       ├── Queue.php                  # encolar/desencolar trabajos
│       ├── PythonInvoker.php          # exec/subprocess hacia app_worker
│       ├── Storage/                  # abstracción de archivos
│       │   ├── StorageInterface.php
│       │   ├── LocalStorage.php
│       │   └── RemoteStorage.php
│       └── Auth.php
│
├── app_worker/                       # Worker Python local (reemplaza n8n)
│   ├── README.md
│   ├── requirements.txt
│   ├── .env.example
│   ├── daemon.py                     # poller de app_colas_trabajos
│   ├── jobs/
│   │   └── analizador.py             # Gemini — migra de bybot_app/n8n/scripts/analyzer
│   ├── shared/ (config.py, utils.py) # migra de bybot_app/n8n/scripts/shared
│   └── tests/
│
├── bots/                             # (legacy, congelado)
├── bots2/                            # bots Py (no integrados por ahora)
│
├── utils/                            # utilidades PHP generales
│   ├── PhpMailer/
│   └── vendor/                       # PDFs/Excel en PHP si se necesita futuro
│
├── assets/
│   ├── css/{variables.css, common.css, admin.css}
│   ├── js/{common.js, admin.js}
│   ├── img/  favicons/  plantillas/
│
├── sql/
│   ├── ddl.sql                       # reescrito (unifica bots2 + app, con FKs)
│   ├── reset_db.sql
│   └── migrations/                   # ajustes incrementales
│
├── uploads/                          # archivos servidos solo por API
├── docs/
│   └── plan_app/PLAN_DESARROLLO.md   # este archivo
├── .env
├── .env.example
├── roles.json
├── .gitignore
└── php_rules.md
```

Notas:
- `bots/` y `bots2/` se dejan intactos. La integración con `bots2/` queda fuera de alcance en este plan.

---

## 5. Modelo de datos (alto nivel)

> Tablas clave. El DDL final va en `sql/ddl.sql`. Aquí solo el resumen conceptual.

### 5.1 Control (transversal)
- `control_usuarios(id, usuario, password, nombre, email, rol, clave_un_solo_uso, estado_activo, ultimo_acceso, created_at, updated_at)`
- `control_sesiones(id, usuario_id, token, ip, user_agent, expires_at)`
- `control_logs(id, usuario_id, timestamp, accion, modulo, entidad_tipo, entidad_id, detalle, nivel)`
- `app_configuracion(clave UNIQUE, valor, tipo, categoria, descripcion)`
- `app_prompts(id, nombre, version, tipo, contenido, activo)` — versionado de prompts IA
- `app_colas_trabajos(id, job_id UNIQUE, cola, proceso_id, tipo_trabajo, estado, payload JSON, resultado JSON, error, intentos, max_intentos, prioridad, created_at, started_at, finished_at, duracion_ms)`

### 5.2 Módulo **procesos** (Fases 1+2)
- `procesos(id, codigo UNIQUE, tipo, estado, prioridad, creado_por, asignado_a, notas, fechas…)`
  - `estado`: creado, archivos_cargados, en_analisis, analizado, validado, completado, error, cancelado
- `procesos_archivos(id, proceso_id FK, nombre_original, nombre_archivo, ruta_storage, driver, tipo, mime, tamanio_bytes, hash_sha256, orden, subido_por, created_at)`
  - `tipo`: estado_cuenta, anexo, solicitud_deudor, solicitud_codeudor, identificacion, otro
- `procesos_datos_ia(id, proceso_id FK, version, datos_originales JSON, datos_validados JSON, metadata JSON, modelo, tokens_total, fecha_analisis, validado_por)`
- `procesos_historial(id, proceso_id FK, usuario_id, accion, estado_anterior, estado_nuevo, descripcion, datos_cambio JSON, fecha)`

### 5.3 Tablas bots2 (ya existen, sin cambios en este plan)
`ruaf_consultas`, `fosiga_consultas`, `rues_consultas`, `simpleco_consultas`, `suaporte_consultas`, `aportesenlinea_consultas`, `asopagos_consultas`, vista `consultas_consolidadas`. No se integran por ahora.

---

## 6. Fases del plan

> Cada fase termina con **verificación real** (checklist manual + script de prueba) antes de pasar a la siguiente.

### Fase 0 — Fundamentos (semana 1)

Objetivo: cimientos sólidos siguiendo `php_rules.md`.
- [ ] Crear estructura `app/`, `app_worker/`, `sql/`, `assets/`, `utils/`, `uploads/`.
- [ ] `.env.example` completo y `.env` local.
- [ ] `sql/ddl.sql` reescrito (unifica `bots2/sql/ddl.sql` + nuevas tablas con FK, sin ENUM).
- [ ] `sql/reset_db.sql` + carpeta `sql/migrations/`.
- [ ] `app/core/`: Database (PDO), Environ, Response, Validator, Auth, BaseModel, BaseService, Queue, PythonInvoker, Storage (Local + Remote stub).
- [ ] `assets/css/variables.css` con paleta `#003268 / #1D4191 / #7D7D7D`, fuente Poppins ExtraBold (`php_rules.md` §1.4–1.5). Bootstrap 5 vía CDN o local.
- [ ] `roles.json` inicial: `admin`, `supervisor`, `operador` con accesos a módulos.
- [ ] Login con usuario + contraseña de un solo uso + cambio obligado al primer ingreso.
- [ ] Layout base (header/sidebar/footer) y dashboard vacío.
- [ ] **Verificación F0**: login funciona, roles aplican, BD se crea limpia desde `ddl.sql`, assets cargan con la paleta correcta.

### Fase 1 — Carga de archivos (semana 2)

Objetivo: módulo **procesos/analisis** permite crear un proceso y subir archivos.
- [ ] `admin/modules/procesos/pages/`: listado, crear, ver, subir archivos (drag & drop).
- [ ] `admin/modules/procesos/api/`: POST/GET/DELETE archivos, validación de tipo y tamaño (límites por `.env`).
- [ ] Renombrado de archivos: `{tipo}_{codigo_proceso}_{llaveuniq}{ext}` (§2.3).
- [ ] Servido por API: `GET /api/v1/archivos/{id}` requiere sesión; rutas de `uploads/` bloqueadas.
- [ ] Soporta PDF, JPG/PNG, HTML, XLSX. SHA-256 para integridad y dedupe.
- [ ] Vista previa de PDF/imagenes/HTML inline dentro del proceso.
- [ ] Plantilla Excel descargable en cada lugar donde se pida carga estructurada (§4.1).
- [ ] Historial (`procesos_historial`) por cada alta/baja/edición.
- [ ] **Verificación F1**: crear proceso, subir 3 tipos de archivo, descargarlos por API, ver que no son accesibles por URL directa.

### Fase 2 — Análisis con IA (semana 3)

Objetivo: extraer datos estructurados por proceso, validables por el operador.
- [ ] Migrar `bybot_app/n8n/scripts/analyzer/gemini_client.py` → `app_worker/jobs/analizador.py` (sin n8n, sin callback HTTP; escribe directo a BD).
- [ ] Migrar `shared/config.py` y `shared/utils.py` a `app_worker/shared/`.
- [ ] Tabla `app_prompts` + editor en `admin/modules/prompts` con versionado y activación.
- [ ] `admin/modules/analisis/pages/`: botón "Analizar proceso" → encola trabajo → poll de estado → muestra datos extraídos.
- [ ] Pantalla de **validación**: campos editables lado a lado (original IA vs. validado), marca qué datos quedan aprobados.
- [ ] Guardado de `procesos_datos_ia.datos_originales` (IA) y `datos_validados` (humano), con `version`.
- [ ] Manejo de reintentos (máx. `app_configuracion.max_intentos_analisis`), timeouts y errores visibles.
- [ ] **Verificación F2**: con 1 proceso real (estado de cuenta + anexos) → análisis exitoso → datos editados → estado `validado`.

### Fase 3 — Pulido, pruebas y deploy (semana 4)

- [ ] Suite de pruebas PHP (PHPUnit si añaden Composer; o scripts manuales en `tools/`).
- [ ] Suite de pruebas Python (`pytest`) para `app_worker`.
- [ ] Logs estructurados en `control_logs` + archivos en `logs/`.
- [ ] Backups `.sql` automáticos + script `reset_db.sql` probado en limpio.
- [ ] `.env` de producción, hardening `.htaccess`, rate-limit en API.
- [ ] Documentación: README raíz, `app/README.md`, `app_worker/README.md`, guía de deploy.
- [ ] *Pensar en móvil*: todos los flows expuestos por `/api/v1/` — sin lógica de negocio en páginas PHP, solo presentación.
- [ ] **Verificación F3**: tests pasan, `reset_db.sql` deja BD limpia, deploy documentado.

> **Futuro (no planificado)**: Fase 4 podría ejecutar `bots2/` sobre el número de documento extraído por IA, y Fase 5 podría generar documentos (no necesariamente demandas). Se planificarán en detalle si/luego se arranque.

---

## 7. Decisiones que aún hay que tomar

> Marcar con el cliente/equipo antes de iniciar cada fase.

1. **Proveedor de IA**: ¿Gemini (como antes) o evaluar OpenAI / Claude / un proveedor local? Confirmar `GEMINI_API_KEY` vigente y modelo (`gemini-1.5-flash` o `gemini-2.5-flash` ya usado en bots2).
2. **Almacenamiento remoto de archivos**: ¿S3-compatible? ¿B2? ¿Cloudflare R2? Mientras se decide, `STORAGE_DRIVER=local` y se deja `RemoteStorage` como stub.
3. **Servidor de producción a futuro**: por definir (suficientemente potente para PHP + worker Python). Por ahora XAMPP local es suficiente.
4. **Autenticación móvil/futura**: ¿sesiones PHP (current) o JWT para la API? Recomendado: sesiones para admin, **API tokens** (tabla `control_api_tokens`) para móvil/API.
5. **Daemon Python**: ¿systemd service, cron `* * * * *`, o un `nohup python daemon.py`? Recomendado: **systemd** en Linux servidor; en XAMPP local un terminal.
6. **Roles/perfiles exactos** y qué módulo ve cada uno.
7. **Migración de datos legacy**: ¿hay datos previos en el `bybot_app/` que migrar, o se arranca limpio?

---

## 8. Métricas de éxito

| Métrica | Objetivo |
|---|---|
| Tiempo de subida de 10 MB | < 5 s en local |
| Tiempo de análisis IA por proceso | < 60 s |
| Tasa de éxito de análisis (sin reintento) | > 95 % |
| Uptime app local | ≥ 99 % |
| Errores no controlados por día | 0 |

---

## 9. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Cambios de API de Gemini (deprecación de modelo) | `app_prompts` + config `gemini_model` editable; interfaz `IAProvider` deja swap a futuro |
| Archivos pesados saturan `uploads/` | Límites por `.env` + Storage remoto como evasión futura |
| Cola en BD se satura | Índices en `app_colas_trabajos.estado/prioridad`; daemon con workers paralelos opcionales |
| Análisis IA falla por PDFs escaneados / ilegibles | Detección de calidad al subir + reintento configurable + log del error en `procesos_historial` |

---

## 10. Próximos pasos inmediatos

1. **Confirmar decisiones de §7** (al menos 1, 2, 5, 6).
2. **Aprobar este plan** y bloquear el alcance de la Fase 0.
3. Crear rama git `feature/app-v1` (osimilar).
4. Ejecutar Fase 0 y firmar la **Verificación F0** con el cliente.

---

## 11. Apéndice: mapeo "código legacy → destino"

| Origen (`byb/bybot_app/`) | Destino (`bybot_v1/`) | Acción |
|---|---|---|
| `sql/ddl.sql` (tablas de procesos/control/colas/prompts) | `sql/ddl.sql` | Adaptar: añadir FK, sin ENUM, prefijo por módulo, fundir con `bots2/sql/ddl.sql` |
| `n8n/scripts/analyzer/gemini_client.py` | `app_worker/jobs/analizador.py` + `shared/` | Migrar; quitar n8n/callback HTTP; leer de BD por `--proceso_id` |
| `n8n/scripts/analyzer/main.py` | (merge) en `analizador.py` | Simplificar CLI |
| `n8n/scripts/shared/config.py`, `utils.py` | `app_worker/shared/` | Reutilización directa, ajustar `.env` vars |
| `web/core/BaseModel.php`, `BaseService.php`, `Response.php`, `Validator.php` | `app/core/` | Reescribir según `php_rules.md` |
| `web/core/N8nClient.php` | — **descartar** | Reemplazado por `app/core/PythonInvoker.php` + `Queue.php` |
| `web/admin/` estructura plana | `app/admin/modules/<modulo>/` | Reorganizar por `php_rules.md` §5 |
| `web/api/v1/webhook/n8n.php` | — **descartar** | El worker escribe directo a BD; no hay webhook |
| `n8n/scripts/filler/` (PDFFiller, llenado de pagarés) | — **descartar** | Diligenciamiento de PDFs fuera de alcance en este plan |
| `config/templates/crearcoop/posiciones.json` + `plantillas_pagare` | — **descartar** | Sin generación de documentos por ahora |
| Prompts embebidos en `gemini_client.py` | `app_prompts` (tabla) + `admin/modules/prompts` | Externalizar y versionar |