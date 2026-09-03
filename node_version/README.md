# ByBot App — Monorepo Node/TS

App de **carga de archivos + análisis con IA + consultas bot** para procesos del estudio
jurídico/cobranza. Fases F0b → F3 funcionales **+ F4: ingesta multi-entidad**.

## Stack
- **Backend**: Node + TypeScript + Fastify + Prisma (puerto `3001`)
- **Frontend**: React + Vite + TypeScript + Bootstrap 5 (puerto `5173`)
- **Botstorage**: microservicio de archivos Node + TS (puerto `3002`)
- **Botworker**: Python — daemon de análisis IA (Gemini) y daemon de consultas bot
  (`botworker/analizador.py`, `botworker/bot_runner.py`)
- **bots/**: bots Python de registros públicos, invocados por `bot_runner.py` vía cola
- **BD**: MariaDB `bybot_consolidado` (DDL en `sql/ddl.sql`)

## F4 — Multi-entidad (resumen)
Cada **entidad** (cliente/cooperativa) define su catálogo de documentos y sus prompts; el
análisis mapea todo a categorías canónicas. Módulo **Entidades** (admin) para gestionarlo.
Soporta PDF, imágenes, **TIFF** (convertido a PDF), HTML y Excel; valida el tipo por *magic-bytes*.
Guarda **tokens de entrada/salida y costo estimado** por proceso (tarjeta "Consumo IA").
Detalle en [`handoff.md`](handoff.md) §1.b.

## Requisitos
- Node 18+ · Python 3.10+ (bots y botworker) · MariaDB/MySQL
- **macOS (este entorno)**: Docker vía **Colima** + registro de paquetes **Fury** — ver
  [`handoff.md`](handoff.md) §1.a (la BD corre en contenedor, no XAMPP).

## Puesta en marcha rápida
```bash
# 1. Variables de entorno
cp .env.example .env            # ajustar DATABASE_URL, GEMINI_API_KEY, tokens internos

# 2. Instalar dependencias (todos los workspaces)
npm install                     # en Fury: `fury registry login` si da 403

# 3. Crear/reiniciar la BD
npm run db:reset                # Linux/XAMPP. En macOS/Docker: ver handoff.md §1.a

# 4. Generar cliente Prisma
npm run db:generate

# 5. Levantar los 3 servicios en paralelo
npm run dev
```

Luego abrir:
- Frontend SPA: http://localhost:5173
- Backend API: http://localhost:3001/api/v1/health
- Botstorage: http://localhost:3002/health

Daemons Python (análisis y consultas bot):
```bash
# desde node_version/botworker con el venv activo
.venv/bin/python daemon.py         # análisis IA (Gemini) — log /tmp/bydaemon.log
.venv/bin/python bot_runner.py     # consultas bot  — log /tmp/bybotrunner.log
```

## Login por defecto
- Usuario: `admin`
- Contraseña: `admin123` (de un solo uso → te pedirá cambiarla al primer ingreso).
- En la BD de desarrollo de este entorno ya fue cambiada a `admin555`.

## Documentación
- [`docs/plan_app/PLAN_DESARROLLO.md`](docs/plan_app/PLAN_DESARROLLO.md) — plan detallado Fase 0b → Fase 3.
- [`handoff.md`](handoff.md) — estado actual, dashboard de bots, fixes recientes y pendientes.
- [`project_rules.md`](project_rules.md) — normativa del proyecto (Bootstrap, colores, FKs, sin ENUM, JWT...).
- [`roles.json`](roles.json) — definición de roles → módulos.

## Estructura
Ver `docs/plan_app/PLAN_DESARROLLO.md` §4.