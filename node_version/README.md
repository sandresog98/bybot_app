# ByBot App — Monorepo Node/TS

App de **carga de archivos + análisis con IA** para procesos del estudio jurídico/cobranza.

## Stack
- **Backend**: Node + TypeScript + Fastify + Prisma (puerto `3001`)
- **Frontend**: React + Vite + TypeScript + Bootstrap 5 (puerto `5173`)
- **Botstorage**: microservicio de archivos Node + TS (puerto `3002`)
- **Botworker**: Python IA Gemini (Fase 2, placeholder)
- **bots/**: bots Python de registros públicos (intacto, invocado por backend via child_process)
- **BD**: MariaDB `bybot_consolidado` (DDL en `sql/ddl.sql`)

## Requisitos
- Node 18+
- XAMPP (MariaDB) arrancado — `/opt/lampp/bin/mysql`
- Python 3.10+ (para bots y botworker en Fase 2)

## Puesta en marcha rápida
```bash
# 1. Variables de entorno
cp .env.example .env

# 2. Instalar dependencias (todos los workspaces)
npm install

# 3. Crear/reiniciar la BD
npm run db:reset

# 4. Generar cliente Prisma
npm run db:generate

# 5. Levantar los 3 servicios en paralelo
npm run dev
```

Luego abrir:
- Frontend SPA: http://localhost:5173
- Backend API: http://localhost:3001/api/v1/health
- Botstorage: http://localhost:3002/health

## Login por defecto
- Usuario: `admin`
- Contraseña: `admin123`
- **Importante**: la contraseña es de un solo uso → te pedirá cambiarla al primer ingreso.

## Documentación
- [`docs/plan_app/PLAN_DESARROLLO.md`](docs/plan_app/PLAN_DESARROLLO.md) — plan detallado Fase 0b → Fase 3.
- [`project_rules.md`](project_rules.md) — normativa del proyecto (Bootstrap, colores, FKs, sin ENUM, JWT...).
- [`roles.json`](roles.json) — definición de roles → módulos.

## Estructura
Ver `docs/plan_app/PLAN_DESARROLLO.md` §4.