# Node2

Aplicación para **gestión de usuarios, procesos, archivos y análisis con IA**, con **normalización de archivos**, **consolidación por proceso**, **almacenamiento estructurado** y **liquidación de demandas**. No contiene bots, automatizaciones web, `botworker` ni el microservicio `botstorage`.

## Arquitectura

- `backend/`: API Fastify, sesión en cookie HttpOnly, Prisma, worker IA y servicio local de archivos.
- `frontend/`: interfaz React/Vite para operar los cuatro módulos.
- MariaDB es la base de datos objetivo; los archivos se almacenan en un volumen persistente del backend.

## Requisitos

- Node.js 20 o superior.
- Una API key de **Google Gemini** (recomendado, análisis multimodal de PDF/imágenes) o un endpoint compatible con la API de chat de OpenAI (solo texto/JSON).

La guía completa, reproducible para desarrollo y servidor, está en [DEPLOYMENT.md](DEPLOYMENT.md). Incluye DDL, MariaDB, Docker, volúmenes, secretos y respaldos.

## Inicio local

```bash
cd node2
cp .env.example .env
# Cambia JWT_SECRET y ADMIN_PASSWORD antes de continuar.
npm install
npm run db:deploy
npm run db:seed
npm run dev
```

Abre `http://localhost:5173`. El backend queda disponible en `http://localhost:3001` y su comprobación de estado en `/health`.

`npm run dev` inicia API, interfaz y worker. En despliegue deben ejecutarse el servidor (`npm -w backend run start`) y el worker (`npm -w backend run worker:start`) como procesos separados.

## Configuración de IA

Completa estas variables en `.env`:

```dotenv
AI_API_URL="https://tu-proveedor/v1/chat/completions"
AI_API_KEY="tu-clave"
AI_MODEL="tu-modelo"
```

Con `AI_PROVIDER="gemini"` (recomendado) el análisis es multimodal: se envían PDF e imágenes directamente al modelo, además de `.txt`, `.csv` y `.json`. El prompt se selecciona por tipo de archivo (`tipo`) y por entidad, y se registra el resultado, proveedor, modelo, tokens, costo y errores. Con `AI_PROVIDER="openai"` solo se analizan archivos de texto o JSON.

## Pipeline de procesamiento

1. **Ingesta y normalización** (`backend/src/ingest/`): valida por firma y convierte formatos no analizables (TIFF/BMP/GIF/AVIF → PNG o PDF multipágina) antes de almacenar. Se guarda el archivo convertido.
2. **Análisis IA** (`backend/src/ai/`): extracción multimodal por archivo con Gemini (o texto con OpenAI), con prompts por tipo de archivo y por entidad. Se registran tokens, costo y errores.
3. **Consolidación** (`backend/src/consolidate.ts`): fusiona los resultados por archivo en un único resultado del proceso, con prioridad `pagaré > estado de cuenta > vinculación > poder > anexo > amortización`, deduplica arreglos y no sobrescribe con valores vacíos.
4. **Datos estructurados** (`backend/src/normalize.ts`): escribe el resultado consolidado en tablas consultables (`Parte`, `Credito`, `Movimiento`, `CuotaAmortizacion`, `ExtraccionCampo`).
5. **Liquidación** (`backend/src/demanda/liquidacion.ts`): calcula cuotas en mora, capital acelerado, cuantía y competencia (mínima/menor) para la demanda.

En la interfaz, el detalle del proceso ofrece: subir/reemplazar/eliminar/ver archivos, **Analizar todos los archivos IA**, **Consolidar análisis del proceso**, **Datos estructurados** y **Liquidación (borrador)**. Cada archivo conserva su **historial de ejecuciones** y se muestra por defecto la última ejecución exitosa.

## Parámetros de liquidación

```dotenv
SMLMV=1750905
UMBRAL_MINIMA_SMLMV=40
```

`SMLMV` es el salario mínimo vigente; `UMBRAL_MINIMA_SMLMV` define el tope de mínima cuantía (por defecto 40 SMLMV).

## Operación

1. Inicia sesión con el usuario creado por `npm run db:seed`.
2. Crea un proceso (con cliente/entidad) y carga sus documentos.
3. Pulsa **Analizar todos los archivos IA** (encola solo pendientes y fallidos).
4. Pulsa **Consolidar análisis del proceso** para unificar y llenar los datos estructurados.
5. Revisa/corrige los datos en el resultado y guarda la validación.
6. Usa **Liquidación (borrador)** indicando la cuota inicial en mora y la cuota de corte.

## Límites y seguridad

- Tamaño máximo: `UPLOAD_MAX_MB` (25 MB por defecto).
- Formatos de carga: PDF, texto, CSV, JSON, JPG, PNG, WEBP, TIFF, BMP, GIF y AVIF. Las imágenes no analizables por el modelo (TIFF, BMP, GIF, AVIF) se **normalizan al cargar**: una página se convierte a PNG y varias páginas se unen en un PDF; el archivo original en ese formato no se conserva.
- Las contraseñas se almacenan con bcrypt; la sesión usa una cookie `HttpOnly`, `Secure` en producción y expira tras ocho horas.
- El acceso a procesos y archivos se limita a su creador, salvo administradores. Todas las acciones sensibles generan auditoría.
- Los archivos se escriben por streaming, se limitan por tamaño y validan la firma para PDF/JPG/PNG.
- No subas `backend/uploads/`, `.env` ni volcados de MariaDB al repositorio.

## Exclusiones deliberadas

No se migran `bots/`, `botworker/`, `botstorage/`, consultas externas, entidades de bot, prompts de bot ni sus tablas. Esto elimina los entornos Python/Playwright y el servicio de almacenamiento separado.
