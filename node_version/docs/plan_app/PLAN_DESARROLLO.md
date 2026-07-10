# Plan de Desarrollo — App ByBot (Carga de archivos → IA)

> Documento maestro de planificación. Vivo: actualizar conforme se tomen decisiones.
>
> Fecha creación: 2026-07-06 · Última revisión: 2026-07-09 (stack Node.js)
> Proyecto raíz: `/opt/lampp/htdocs/projects/bybot_v1/node_version/`
> Referencia legacy: `php_version/` (PHP 8.2 + worker Python — archivado, referencia)
> Referencia más legacy: `/opt/lampp/htdocs/projects/byb/bybot_app/` (PHP + n8n + Python, no probado)
> Normativa: `project_rules.md` (Bootstrap, colores corporativos, FKs, sin ENUM, JWT, etc.)

---

## 1. Contexto y objetivo

Construir la **app web** para cargar, almacenar y analizar con IA los documentos de los procesos del estudio jurídico/cobranza:

1. **Carga de archivos** (PDF, imágenes, HTML, Excel) de los procesos desde una interfaz.
2. **Análisis con IA** (Gemini) para extraer y estructurar la información del proceso (deudor, codeudor, estado de cuenta, referencias, etc.).

**Alcance inmediato (plan operativo)**: Fase 0b (fundamentos Node) + Fase 1 (carga + almacenamiento) + Fase 2 (análisis con IA).

> **Futuro (fuera de alcance hoy)**: integración con `bots/` para enriquecer procesos con información de registros públicos (RUAF, FOSIGA, RUES, Simple.co, SuAporte, Aportes en Línea, Asopagos) y eventual generación de documentos (no necesariamente demandas). Se planificarán en detalle cuando se arranque.

### 1.1 Decisión de stack (2026-07-09)

Se migró de PHP a **Node.js + TypeScript** con 3 servicios separados, motivado por:
- Un solo lenguaje web (TS) en backend y frontend → equipo enfocado, herramientas compartidas.
- Frontend como SPA → mejor UX, móvil-future-friendly.
- Ecosistema npm rico para archivos (multer, sharp), validación (zod), filas (BullMQ), ORM (Prisma).
- `bots/` se mantiene en Python (intacto) — backend lo invoca por subprocess. No se elimina Python del runtime.

---

## 2. Análisis del legado

### 2.1 Qué se reutiliza de `php_version/`
- **`sql/ddl.sql`** + **`sql/reset_db.sql`**: BD unificada `bybot_consolidado` con FKs y sin ENUM. Reutilizado sin cambios.
- **`sql/migrations/`**: carpeta para ajustes posteriores.
- **`bots/`**: bots Python intactos (RUAF, FOSIGA, RUES, Simple.co, SuAporte, Aportes en Línea, Asopagos). Serán invocados por el backend Node vía `child_process`.
- **`bots/`**: legacy Windows, congelado como referencia.
- **`project_rules.md`**: normativa del proyecto (paleta, Bootstrap, FKs, sin ENUM, roles.json, .env, límites de archivos, renombrado, servido por API, JWT).
- **`roles.json`**: definición de roles → módulos. Sin cambios.
- **`docs/plan_app/PLAN_DESARROLLO.md`** (este doc): actualizado para Node.

### 2.2 Qué NO se migra
- `app/` PHP (archivado en `legacy_php/` → ahora `php_version/app/`).
- `app_worker/` PHP (igual).
- `assets/` PHP (Bootstrap via CDN + Poppins via Google Fonts; se recrea en `frontend/src/` con los mismos CSS).
- n8n, webhooks, `N8nClient`, `PDFFiller`, generación de demandas — fuera de alcance.
- Llenado de PDFs — fuera de alcance.

### 2.3 Qué se reescribe del core PHP
Equivalencia (PHP → Node/TS):

| PHP (core/) | Node/TS (backend/src/core/) |
|---|---|
| `Environ.php` | `config/env.ts` (dotenv + zod) |
| `Database.php` (PDO) | Prisma Client (`db.ts`) |
| `Response.php` | Fastify reply (`@fastify/sensible`) + helper `sendOk/sendErr` |
| `Validator.php` | zod schemas |
| `BaseModel.php`, `BaseService.php` | Prisma models + service classes por módulo |
| `Auth.php` | `core/auth.ts` (JWT + bcrypt + refresh tokens) |
| `Queue.php` | `core/queue.ts` (tabla `app_colas_trabajos`) |
| `PythonInvoker.php` | `core/pythonInvoker.ts` (`child_process.spawn`) |
| `Storage/StorageInterface` | живет en `botstorage/src/storage/Storage.ts` (Local/Remote) |
| `Roles.php` | `core/roles.ts` (lee `roles.json`) |

---

## 3. Decisiones de arquitectura

### 3.1 Stack por servicio

| Servicio | Stack | Puerto local | Rol |
|---|---|---|---|
| `backend/` | Node 20+ · TypeScript · Fastify · Prisma · zod · JWT · Pino logs | `3001` | API REST + auth + colas + invocador Python |
| `frontend/` | React 18 + Vite + TS · TanStack Query · React Router · Bootstrap 5 | `5173` (dev) | SPA consume `/api/v1/*` con JWT |
| `botstorage/` | Node + TypeScript · Fastify · abstracción `Storage` | `3002` | Microservicio de archivos (Local/Remote futura) + URLs firmadas |
| `botworker/` | Python 3.12 + google-generativeai | — | IA Gemini (Fase 2). Backend invoca vía `child_process`. |
| `bots/` | Python + Playwright (intacto) | — | Bots de registros públicos. Backend invoca vía `child_process`. |

### 3.2 Topología (un solo servidor local, 3 procesos Node + Python on-demand)

```
                         +-----------------------------+
                         |   Navegador (SPA React)     |
                         +--------------+--------------+
                                | HTTPS + JWT
                                v
+----------------------------------------------------------+
|  frontend dev server (Vite :5173) / build estático prod  |
+--------------+------------------------------+-----------+
               | /api/v1/*
               v
+----------------------------------------------------------+
|  backend :3001 (Fastify + Prisma)                        |
|  - auth JWT + roles (roles.json)                         |
|  - módulos: procesos, archivos, analisis, prompts, ...  |
|  - colas app_colas_trabajos                              |
+----+-------------------+------------------+-------------+
     | HTTP internal      | child_process    | Prisma
     v                    v                  v
+------------+    +----------------+   +------------------+
| botstorage |    | botworker.py   |   | MariaDB          |
| :3002      |    | bots/*.py      |   | bybot_consolidado|
| Storage    |    | (subprocess)   |   | (DDL existente)  |
| Local/Rem. |    +----------------+   +------------------+
+----+-------+
     |
     v
 uploads/ (local) o S3/R2/B2 (futuro)
```

### 3.3 Comunicación Backend ↔ Botstorage

- Token interno compartido por `.env` (`BOTSTORAGE_INTERNAL_TOKEN`).
- Backend llama: `POST /internal/store` (multipart interno o stream), `GET /internal/read/:key`, `DELETE /internal/:key`, `POST /internal/sign-url` (URL firmada de descarga con expiración).
- En Fase 0/1 solo `local` driver.

### 3.4 Comunicación Backend ↔ Python (bots + botworker)

- `child_process.spawn('python3', ['-m', 'bots.ruaf.cli', '--numero', '...', '--fecha', '...'])` con timeout configurable.
- Resultados por stdout JSON o por inserción directa a BD (bots ya escribe a `*_consultas`).
- En Fase 0/1 Python no se invoca (no hay bots ni IA aún).

### 3.5 Autenticación y sesiones (JWT)

- **Access token** 15min en `Authorization: Bearer <jwt>` (claims: `userId`, `rol`, `modules`).
- **Refresh token** 7d almacenado hasheado en `control_sesiones` (campo `token`). Rotación opcional en Fase 1.
- Login usuario + contraseña de un solo uso + forzar cambio (mismo flujo que el F0 PHP, reescrito en TS).
- API tokens de largo plazo en `control_api_tokens` (para móvil/integraciones futuras). Pendiente de implementar (Fase 1+).

### 3.6 Reglas que respetamos de `project_rules.md`

Aunque ya no sea PHP, mantenemos:
- Bootstrap 5 + colores corporativos (`#003268 / #1D4191 / #7D7D7D`) + Poppins ExtraBold.
- Límites de tamaño de archivo por entorno (en `.env`).
- Renombrado de archivos (`{tipo}_{codigoProceso}_{llaveUnica}{ext}`).
- Servido por API con auth, sin URL directa a `uploads/` (en SPA: stream vía `botstorage` → `backend` → `frontend`).
- BD con FK, sin ENUM, prefijo por módulo, `ddl.sql` + `reset_db.sql` + `migrations/`.
- Plantilla Excel descargable donde se pida carga estructurada.
- Roles en `roles.json` (backend lo lee) → módulos permitidos. JWT lleva claim `modules`.
- Credenciales en `.env` raíz.
- Pensar en móvil: API REST-first desde el día 1 (SPA consume API, móvil futuro idéntica).

---

## 4. Estructura del proyecto (monorepo npm workspaces)

```
node_version/
├── backend/                        # Fastify + Prisma + TS
│   ├── src/
│   │   ├── server.ts               # entrypoint Fastify
│   │   ├── config/
│   │   │   └── env.ts              # dotenv + zod
│   │   ├── core/
│   │   │   ├── auth.ts             # JWT, bcrypt, hash refresh
│   │   │   ├── db.ts                # Prisma Client singleton
│   │   │   ├── logger.ts            # Pino
│   │   │   ├── queue.ts             # app_colas_trabajos
│   │   │   ├── pythonInvoker.ts     # child_process
│   │   │   ├── storageClient.ts     # HTTP client hacia botstorage
│   │   │   ├── roles.ts             # lee roles.json
│   │   │   └── errors.ts            # helpers de error HTTP
│   │   ├── plugins/
│   │   │   ├── authPlugin.ts        # decorador verifyJWT
│   │   │   └── errorHandler.ts
│   │   ├── modules/
│   │   │   ├── auth/                # login, refresh, change password, logout
│   │   │   │   ├── auth.routes.ts
│   │   │   │   ├── auth.service.ts
│   │   │   │   └── auth.schema.ts
│   │   │   ├── procesos/           # Fase 1
│   │   │   ├── archivos/            # Fase 1
│   │   │   ├── analisis/            # Fase 2
│   │   │   ├── prompts/
│   │   │   ├── usuarios/
│   │   │   └── configuracion/
│   │   └── utils/
│   ├── prisma/
│   │   └── schema.prisma            # generado desde ddl.sql (mysql driver)
│   ├── package.json
│   └── tsconfig.json
│
├── frontend/                       # React + Vite + TS
│   ├── src/
│   │   ├── main.tsx
│   │   ├── App.tsx
│   │   ├── api/
│   │   │   ├── client.ts            # fetch wrapper con JWT
│   │   │   └── queries.ts           # TanStack Query hooks
│   │   ├── auth/
│   │   │   ├── AuthContext.tsx
│   │   │   └── useAuth.ts
│   │   ├── layouts/
│   │   │   ├── AdminLayout.tsx       # header + sidebar + outlet
│   │   │   ├── Sidebar.tsx
│   │   │   └── Header.tsx
│   │   ├── pages/
│   │   │   ├── Login.tsx
│   │   │   ├── ChangePassword.tsx
│   │   │   ├── Dashboard.tsx
│   │   │   ├── Procesos.tsx          # placeholder Fase 1
│   │   │   ├── Analisis.tsx
│   │   │   ├── Prompts.tsx
│   │   │   ├── Usuarios.tsx
│   │   │   └── Configuracion.tsx
│   │   └── styles/
│   │       ├── variables.css         # paleta corporativa (mismo que PHP)
│   │       └── common.css
│   ├── public/
│   │   └── favicons/bybot.svg
│   ├── package.json
│   ├── vite.config.ts
│   └── tsconfig.json
│
├── botstorage/                     # Fastify + TS — microservicio de archivos
│   ├── src/
│   │   ├── server.ts
│   │   ├── config/env.ts
│   │   ├── storage/
│   │   │   ├── Storage.ts           # interfaz
│   │   │   ├── LocalStorage.ts
│   │   │   └── RemoteStorage.ts     # stub
│   │   ├── routes/
│   │   │   ├── store.ts             # POST /internal/store
│   │   │   ├── read.ts              # GET /internal/read/:key
│   │   │   └── delete.ts            # DELETE /internal/:key
│   │   └── plugins/internalAuth.ts  # X-Internal-Token
│   ├── package.json
│   └── tsconfig.json
│
├── botworker/                      # Python IA (Fase 2) — placeholder
│   ├── README.md
│   ├── requirements.txt
│   └── .gitkeep
│
├── bots/                           # bots Python de registros públicos (intacto)
├── sql/                            # ddl.sql + reset_db.sql + migrations/
├── uploads/                        # storage local (.gitkeep)
├── logs/                           # logs (.gitkeep)
├── docs/plan_app/PLAN_DESARROLLO.md  # este archivo
├── .env                            # único, raíz, prefijos por servicio
├── .env.example
├── .gitignore
├── package.json                    # workspaces root + scripts dev/db:reset/build
├── roles.json
├── project_rules.md         # normativa del proyecto
└── README.md
```
---

## 5. Modelo de datos (sin cambios respecto a F0 PHP)

BD `bybot_consolidado` creada por `sql/ddl.sql` (ya verificada con 13 FKs, sin ENUM, prefijada por módulo). Tablas clave:

- **control**: `control_usuarios`, `control_sesiones`, `control_api_tokens`, `control_logs`
- **app** (transversal): `app_configuracion`, `app_prompts`, `app_colas_trabajos`
- **procesos**: `procesos`, `procesos_archivos`, `procesos_datos_ia`, `procesos_historial`
- **bots** (tablas de `bots/`): `ruaf_consultas`, `fosiga_consultas`, `rues_consultas`, `simpleco_consultas`, `suaporte_consultas`, `aportesenlinea_consultas`, `asopagos_consultas` + vista `consultas_consolidadas`

Prisma reflection (no crea tablas, apunta a las existentes vía `previewFeatures = ["mysql"]` + `map = ...`).

---

## 6. Fases del plan

> Cada fase termina con **verificación real** antes de pasar a la siguiente.

### Fase 0b — Fundamentos Node (semana 1)

Objetivo: cimientos sólidos en TS, los 3 servicios levantan, login + dashboard + roles funcionan.
- [ ] Repo reorganizado (PHP archivado en `php_version/`, `node_version/` con monorepo).
- [ ] Raíz: `package.json` con workspaces, `.env` único, `.gitignore`, scripts `dev`/`db:reset`/`build`.
- [ ] `backend/`: Fastify + Prisma + TS. Estructura por módulo. `core/` (auth, db, env, logger, queue, pythonInvoker, storageClient, roles).
- [ ] `frontend/`: Vite + React + TS + Bootstrap 5 + TanStack Query + React Router. Layouts, Login, ChangePassword, Dashboard.
- [ ] `botstorage/`: Fastify + Storage interfaz + LocalStorage + endpoints `/internal/store|read|delete` con `X-Internal-Token`.
- [ ] `botworker/`: carpeta placeholder con README y requirements para Fase 2.
- [ ] Prisma schema generado desde `ddl.sql` (mirror de tablas existentes).
- [ ] **Verificación F0b**: `npm install` en raíz → `npm run dev` levanta los 3 servicios → login `admin/admin123` (forzar cambio) → dashboard renderiza con paleta corporativa → roles aplican (operador 403 en usuarios) → `uploads/` inaccesible por URL directa.

### Fase 1 — Carga de archivos (semana 2)

Objetivo: módulo **procesos** permite crear procesos y subir archivos.
- [ ] Backend `modules/procesos/`: CRUD de procesos (crear con `codigo` autogenerado, listar, ver).
- [ ] Backend `modules/archivos/`: POST `/procesos/:id/archivos` (multipart → `botstorage.store`), GET `/archivos/:id` (stream desde `botstorage.read`), DELETE.
- [ ] Validación de tipo y tamaño con zod + MIME magic; límites por `.env`.
- [ ] Renombrado: `{tipo}_{codigoProceso}_{llaveUnica}{ext}` — el hash SHA-256 se calcula backend-side.
- [ ] Frontend `pages/Procesos.tsx`: listado, crear, ver, subir archivos (drag & drop).
- [ ] Vista previa inline de PDF/imágenes/HTML dentro del proceso.
- [ ] Plantilla Excel descargable en cada lugar donde se pida carga estructurada (regla §4.1).
- [ ] Historial (`procesos_historial`) por cada alta/baja/edición (audit service).
- [ ] **Verificación F1**: crear proceso, subir 3 tipos de archivo (PDF/JPG/XLSX), descargar vía API, ver que no son accesibles por URL directa, ver historial.

### Fase 2 — Análisis con IA (semana 3)

Objetivo: extraer datos estructurados por proceso, validables por el operador.
- [ ] `botworker/analizador.py`: migrar `bybot_app/n8n/scripts/analyzer/gemini_client.py` + `main.py` + `shared/` (sin n8n, sin callback HTTP; escribe directo a BD).
- [ ] `backend/core/pythonInvoker.ts` invoca `analizador.py --proceso_id X` y parsea JSON stdout.
- [ ] Tabla `app_prompts` + editor en `pages/Prompts.tsx` con versionado y activación.
- [ ] Backend `modules/analisis/`: POST `/procesos/:id/analizar` → encola trabajo → poll de estado → GET devuelve datos extraídos.
- [ ] Pantalla de **validación**: campos editables lado a lado (original IA vs. validado), marca qué datos quedan aprobados.
- [ ] Guardado de `procesos_datos_ia.datos_originales` (IA) y `datos_validados` (humano), con `version`.
- [ ] Manejo de reintentos (máx. `app_configuracion.max_intentos_analisis`), timeouts y errores visibles.
- [ ] **Verificación F2**: con 1 proceso real (estado de cuenta + anexos) → análisis exitoso → datos editados → estado `validado`.

### Fase 3 — Pulido, pruebas y deploy (semana 4)

- [ ] Suite de pruebas backend (Vitest).
- [ ] Suite de pruebas Python (`pytest`) para `botworker`.
- [ ] Logs estructurados en `control_logs` + archivos en `logs/` (Pino rotating).
- [ ] Backups `.sql` automáticos + `reset_db.sql` probado en limpio.
- [ ] `.env` de producción, hardening, rate-limit en API (`@fastify/rate-limit`).
- [ ] Documentación: README raíz, `backend/README.md`, `frontend/README.md`, `botstorage/README.md`, guía de deploy.
- [ ] *Móvil futuro*: API REST-first ya cubierto; solo añadir generación de API tokens.
- [ ] **Verificación F3**: tests pasan, `reset_db.sql` deja BD limpia, deploy documentado.

> **Futuro (no planificado)**: Fase 4 podría ejecutar `bots/` sobre el `numero_id` extraído por IA, y Fase 5 podría generar documentos (no necesariamente demandas). Se planificarán a detalle cuando se arranque.

---

## 7. Decisiones que aún hay que tomar

1. **Proveedor de IA**: ¿Gemini (como antes) o evaluar OpenAI / Claude / un proveedor local? Confirmar `GEMINI_API_KEY` vigente y modelo (`gemini-1.5-flash` o `gemini-2.5-flash` ya usado en bots).
2. **Almacenamiento remoto de archivos**: ¿S3-compatible? ¿B2? ¿Cloudflare R2? Mientras se decide, `STORAGE_DRIVER=local` en `botstorage`.
3. **Servidor de producción a futuro**: por definir (suficientemente potente para Node + Prisma + Python + Playwright). Por ahora XAMPP local es suficiente.
4. **API tokens de larga vida** para móvil/integraciones: implementar `control_api_tokens` en Fase 1+. Recomendado: endpoint `POST /auth/api-token` con scopes.
5. **Daemon Node para colas**: ¿process separado con `node backend/src/worker.ts`, o un worker dentro del mismo proceso backend? Recomendado: proceso separado (`npm run worker`) para no bloquear requests.
6. **Roles/perfiles exactos** y qué módulo ve cada uno.
7. **Migración de datos legacy**: arrancamos limpio (no hay datos previos relevantes en `php_version/`).

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
| Cambios de API de Gemini | `app_prompts` + config `gemini_model` editable; interfaz `IAProvider` deja swap a futuro |
| Bots rompen por cambios en sitios | `bots/` ya aísla; `pythonInvoker` captura errores → ejecución `error` recuperable |
| Archivos pesados saturan `uploads/` | Límites por `.env` + Storage remoto como evasión futura |
| Cola en BD se satura | Índices en `app_colas_trabajos.estado/prioridad`; worker Node con concurrencia configurable |
| Análisis IA falla por PDFs escaneados / ilegibles | Detección de calidad al subir + reintento configurable + log del error en `procesos_historial` |
| Curva TS/React si equipo no conoce | Pairing + revisión; empezar con SPA simple sin librerías complejas |

---

## 10. Próximos pasos inmediatos

1. **Confirmar decisiones de §7** (al menos 1, 2, 5, 6).
2. **Aprobar este plan** y bloquear el alcance de la Fase 0b.
3. Iniciar F0b (archivar PHP ya hecho por el usuario, crear monorepo + 3 servicios).
4. Ejecutar F0b y firmar la **Verificación F0b** con el cliente.

---

## 11. Apéndice: mapeo "código legacy → destino"

| Origen | Destino | Acción |
|---|---|---|
| `php_version/sql/ddl.sql` | `node_version/sql/ddl.sql` | Reutilizado sin cambios |
| `php_version/sql/reset_db.sql` | idem | Reutilizado |
| `php_version/bots2/` | `node_version/bots/` | Renombrado a `bots/`; intacto (Python, invocado por Node) |
| `php_version/bots/` | `node_version/bots/` | Intacto (legacy congelado) |
| `php_version/roles.json` | `node_version/roles.json` | Reutilizado; backend lo lee |
| `php_version/php_rules.md` | `node_version/project_rules.md` | Normativa actualizada a stack Node/TS (sec §3.6) |
| `php_version/app/core/Auth.php` (flujo un-solo-uso) | `backend/src/core/auth.ts` | Reescribir en TS (JWT + bcrypt) |
| `php_version/app/core/Storage/StorageInterface` | `botstorage/src/storage/Storage.ts` | Reescribir en TS |
| `php_version/app/core/roles.php` | `backend/src/core/roles.ts` | Reescribir en TS |
| `php_version/app/admin/views/layouts/*` (header/sidebar/login) | `frontend/src/layouts/*`, `pages/Login.tsx` | Reescribir en React + mismo CSS |
| `php_version/assets/css/variables.css + common.css` | `frontend/src/styles/` | Reutilizar CSS literal (sin cambios) |
| `php_version/.env.example` | `node_version/.env.example` | Reescribir con prefijos por servicio |
| `bybot_app/n8n/scripts/analyzer/gemini_client.py` (Fase 2) | `botworker/analizador.py` + `shared/` | Migrar; quitar n8n/callback HTTP |
| `bybot_app/n8n/scripts/filler/` | — **descartar** | Diligenciamiento de PDFs fuera de alcance |
| `config/templates/crearcoop/posiciones.json` | — **descartar** | Sin generación de documentos por ahora |
| Prompts embebidos en `gemini_client.py` | `app_prompts` (tabla) + `pages/Prompts.tsx` | Externalizar y versionar |
