# HANDOFF — ByBot (ByB Jurídicos) · Estado del proyecto y siguientes pasos

> Documento de traspaso para continuar el desarrollo en otra consola/sesión.
> Última actualización: 2026-10-08.

## 1. Qué es este proyecto

Centraliza el tratamiento de documentos de procesos jurídicos (clientes/entidades como CONFIAR, CREARCOOP, SOMEC):

1. **Cargar** archivos de distintos tipos (con normalización automática de formatos no analizables).
2. **Analizar** los archivos con IA (Gemini multimodal / OpenAI texto).
3. **Almacenar toda la información** extraída (JSON + tablas estructuradas).
4. **Generar la demanda** (en curso — Fase 4).

Se trabajó sobre `bybot_app` (el repo más reciente), descartando bots, automatización web y el microservicio `botstorage`.

## 2. Stack y estructura

- Repo: `C:\xampp\htdocs\projects\bybot\bybot_app` — GitHub `https://github.com/sandresog98/bybot_app.git` (rama `main`).
- Backend: TypeScript + Fastify + Prisma + **MariaDB** (XAMPP).
- Frontend: React 18 + Vite + `react-router-dom`.
- IA: Gemini (`gemini-2.5-flash`) recomendado; OpenAI para texto.
- Worker: proceso separado que procesa la cola de análisis.

```
backend/src/
  ingest/normalize.ts        # ingesta + conversión (TIFF/BMP/GIF/AVIF)
  ai/                        # proveedores y prompts por tipo/entidad
  consolidate.ts             # fusión por prioridad y dedupe
  normalize.ts               # JSON consolidado -> tablas estructuradas
  demanda/liquidacion.ts     # motor de liquidación y reglas (Fase 4.2)
  server.ts / worker.ts
frontend/src/
  layout/ (AppShell, Sidebar) pages/ (Home, Processes, ProcessDetail, Users)
  components/ (ProcessDetail, StructuredData, LiquidacionPanel, ResultadoIA, ...)
```

## 3. Cómo levantarlo (Windows / PowerShell)

`npm` (el `.ps1`) está bloqueado por políticas de ejecución: **usa `npm.cmd`**.

```powershell
cd C:\xampp\htdocs\projects\bybot\bybot_app
npm.cmd install            # si hace falta
npm.cmd run db:deploy      # DDL (crea/actualiza tablas)
npm.cmd run db:seed        # admin + entidades
npm.cmd run dev            # API :3001, worker, front :5173
```

- UI: `http://localhost:5173` · API: `http://localhost:3001` (`/health`).
- Base: MariaDB de XAMPP, base `node2` (usuario `root`, sin clave). `.env` en la raíz.
- Nota: `backend/.env` es un **hardlink** a `../.env` (los scripts corren con cwd `backend/`).
- Secretos en `.env` (no versionado). **No** subir `.env` al repo.

## 4. Estado por fases

| Fase | Descripción | Estado |
|---|---|---|
| 0 | Prompts de dominio (causación, TEA, deudor/codeudor) | ✅ |
| 1 | Ingesta + normalización (TIFF→PDF/PNG; rechazo de no soportados) | ✅ |
| 2 | Análisis por archivo + **consolidado por proceso** | ✅ |
| 3 | Persistencia estructurada (Parte, Credito, Movimiento, CuotaAmortizacion, ExtraccionCampo) | ✅ |
| — | Rediseño UI (sidebar, Inicio, acciones de archivo, "Ver", historial, solo última exitosa) | ✅ |
| 4.0 | Fundaciones (catálogo CONFIAR, parámetros, mapping producto→plantilla) | ⚠️ parcial (parámetros SMLMV y mapping ya) |
| 4.1 | Extractores de escritura/hipoteca, mandamiento, certificado cámara de comercio | ⏳ |
| 4.2 | Motor de liquidación y reglas (mora, capital acelerado, cuantía/competencia) | ✅ base implementada |
| 4.3 | Generación de `.docx` desde plantillas | ⏳ **bloqueada** (faltan plantillas) |
| 4.4 | Memoriales (medidas cautelares, subsanaciones, títulos) | ⏳ |
| 4.5 | UI/flujo de generación, estados (borrador/revisado/radicado) | ⏳ |

## 5. Endpoints clave

- `POST /api/processes/:id/files` · `PUT .../files/:fileId` (reemplazar) · `DELETE /api/files/:id` · `GET /api/files/:id/view` (inline) · `.../download`.
- `POST /api/processes/:id/analyze` · `POST .../analyze-all` (pendientes y fallidos).
- `POST /api/processes/:id/consolidate` (encola consolidación; idempotente).
- `GET /api/processes/:id/structured` (partes, crédito, movimientos, cuotas, campos).
- `POST /api/processes/:id/liquidacion` — body `{ cuotaInicial, cuotaCorte, interesesMora?, overridesCapital? }`.

## 6. Datos de prueba cargados

Tres procesos (los PDFs de ejemplo de los clientes): CONFIAR, CREARCOOP, SOMEC. Los 11 archivos están analizados y los 3 procesos consolidados. La liquidación se probó (p. ej. cuotas 34-36 de CONFIAR).

## 7. Lo que falta en la Fase 4 (¡importante!)

**Materiales del cliente (bloquean 4.3):** las plantillas `.docx` reales (`MODELO DEMANDA CONSUMO RECUPERADO`, `MODELO DEMANDA HIPOTECARIO`, `MEDIDAS CAUTELARES SALARIOS`, `Subsanación`, `NUEVA SOLICITUD DE MEDIDAS CAUTELARES`, memorial de títulos).

**Datos:** expediente completo de ejemplo por tipo (consumo e hipotecario) con pagaré, extracto, proyectado, formulario, poder, escritura (hipotecario) **y la DEMANDA final**; datos legales de CONFIAR (NIT, razón social, rep. legal, notificación, apoderada + T.P., poder); certificado de cámara de comercio.

**Decisiones:** valor del SMLMV vigente y umbral (ya en `.env`), tasa de interés de mora y su base, y regla de cuota de corte / capital acelerado.

**Subfases a implementar:**
- **4.1**: extractores nuevos (escritura/hipoteca, mandamiento de pago/autos, certificado de cámara de comercio) + formulario de datos aportados por el usuario (empleador, correos, "valores indicados").
- **4.2**: refinar reglas de **intereses de mora** (hoy se acepta como valor manual) y conciliación extracto vs proyectado (dos tablas FINDETER VIS).
- **4.3**: motor de plantillas `.docx` (p. ej. `docxtemplater` + `pizzip`) preservando estilos, membrete, firma y numeración de 2 niveles.
- **4.4/4.5**: memoriales y UI de generación con estados.

## 8. Deuda técnica / notas

- **Caché de Vite**: a veces sirve módulos viejos; reiniciar `npm run dev` o borrar `node_modules/.vite`.
- **Timeout IA**: `AI_REQUEST_TIMEOUT_MS=180000` (documentos pesados tardaban >60 s).
- **Calidad de datos**: el motor de liquidación advierte `capital + interés ≠ cuota` (columnas intercaladas del proyectado) y `totalDeuda < saldoCapital` (caso CONFIAR). Alinear extractor del proyectado.
- **Sin plantillas** todavía: no se puede generar el `.docx`.
- El repo no incluye `backend/uploads/` ni `.env` (ignorados).

## 9. Scripts de prueba (temporales, fuera del repo)

En `C:\Users\USUARIO\AppData\Local\Temp\opencode\`: `seed-files.mjs`, `reupload-tiff.mjs`, `smoke-analyses.mjs`, `structured-check.mjs`, `consolidate-check.mjs`, `liquidacion-check.mjs`, `inspect.mjs`, `check-modules.mjs`.

## 10. Próximo paso sugerido

1. Recibir **plantillas y expedientes** de CONFIAR.
2. Implementar **4.1** (extractores faltantes + formulario de datos).
3. Implementar **4.3** (generación `.docx`) y **4.5** (UI de generación).
4. Refinar **4.2** (intereses de mora y conciliación de proyectados).
