# Node2

Aplicación reducida para **gestión de usuarios**, **procesos**, **archivos** y **análisis con IA**. No contiene bots, automatizaciones web, `botworker` ni el microservicio `botstorage`.

## Arquitectura

- `backend/`: API Fastify, sesión en cookie HttpOnly, Prisma, worker IA y servicio local de archivos.
- `frontend/`: interfaz React/Vite para operar los cuatro módulos.
- MariaDB es la base de datos objetivo; los archivos se almacenan en un volumen persistente del backend.

## Requisitos

- Node.js 20 o superior.
- Un endpoint compatible con la API de chat de OpenAI si se habilitará IA.

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

El flujo inicial analiza `.txt`, `.csv` y `.json`, y registra el resultado, proveedor, modelo y errores. Se aceptan PDF e imágenes para gestión de archivos, pero requieren un extractor o flujo multimodal adicional antes de poder analizarlos.

## Operación

1. Inicia sesión con el usuario creado por `npm run db:seed`.
2. Un administrador puede crear usuarios mediante `POST /api/users`; la interfaz inicial se centra en procesos y archivos.
3. Crea un proceso, carga uno o más archivos y ejecuta **Analizar IA**.
4. Los resultados quedan asociados al proceso y al archivo.

## Límites y seguridad

- Tamaño máximo: `UPLOAD_MAX_MB` (25 MB por defecto).
- Formatos de carga: PDF, texto, CSV, JSON, JPG y PNG.
- Las contraseñas se almacenan con bcrypt; la sesión usa una cookie `HttpOnly`, `Secure` en producción y expira tras ocho horas.
- El acceso a procesos y archivos se limita a su creador, salvo administradores. Todas las acciones sensibles generan auditoría.
- Los archivos se escriben por streaming, se limitan por tamaño y validan la firma para PDF/JPG/PNG.
- No subas `backend/uploads/`, `.env` ni volcados de MariaDB al repositorio.

## Exclusiones deliberadas

No se migran `bots/`, `botworker/`, `botstorage/`, consultas externas, entidades de bot, prompts de bot ni sus tablas. Esto elimina los entornos Python/Playwright y el servicio de almacenamiento separado.
