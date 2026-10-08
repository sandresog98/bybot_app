# Despliegue: MariaDB, archivos locales y Docker

Esta es la arquitectura inicial de Node2. La API y el worker son contenedores independientes; MariaDB es externo y los archivos viven en un volumen Docker persistente compartido.

```text
Navegador → Frontend (Nginx) → API ─→ MariaDB
                                  └→ volumen `node2_files_data`
Worker IA ────────────────────────────┘
```

## 1. Requisitos

- Docker Engine y Docker Compose v2 en el servidor de aplicación.
- Node.js 20+ solo si se ejecutará sin Docker, se instalarán dependencias o se correrán pruebas.
- Un servidor MariaDB 10.0+ accesible desde los contenedores.
- Dominio y TLS mediante Nginx, Caddy u otro proxy inverso para producción.

## 2. Crear la base MariaDB

Ejecuta esto como administrador de MariaDB; sustituye la contraseña por una aleatoria y fuerte. No uses `root` desde la aplicación.

```sql
CREATE DATABASE node2
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER 'node2_app'@'10.%' IDENTIFIED BY 'CAMBIA_POR_UN_SECRETO_LARGO';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES
  ON node2.* TO 'node2_app'@'10.%';
FLUSH PRIVILEGES;
```

Usa una restricción de host acorde a tu red (`10.%` es solo un ejemplo). Si API, worker y MariaDB comparten host, puede usarse `localhost`; si están en redes distintas, restringe al rango/host real del servidor de aplicación.

El DDL completo y versionado está en `backend/prisma/ddl.sql`. Es el baseline para una base nueva; futuros cambios deben añadirse como migraciones versionadas, no alterando una base en producción manualmente. Para crearlo manualmente:

```bash
mysql --host=<HOST_MARIADB> --user=node2_app --password node2 < backend/prisma/ddl.sql
```

El comando Docker de migración usa exactamente ese DDL. Prisma usa el conector `mysql`, que también es el conector soportado para MariaDB. [Prisma: MySQL/MariaDB](https://docs.prisma.io/docs/orm/v6/overview/databases/mysql)

## 3. Servicio de archivos del backend

El backend crea `UPLOAD_DIR` al arrancar. En Docker, API y worker montan el volumen nombrado `node2_files_data` en `/app/uploads`; por eso ambos ven exactamente los mismos archivos.

Los archivos nunca se sirven públicamente desde Nginx: se descargan a través de la API, que valida sesión y permisos. El volumen persiste al recrear los contenedores. No uses `docker compose down -v`, ya que eliminaría el volumen y los archivos. [Docker Compose volumes](https://docs.docker.com/reference/compose-file/volumes/)

Para inspección o backups a nivel de host, se puede sustituir el volumen por un bind mount controlado, por ejemplo `/srv/node2/files:/app/uploads`. La ruta debe tener permisos de lectura/escritura para el proceso del contenedor.

**Evolución escalable:** cuando API y worker vivan en hosts diferentes, reemplaza este volumen por MinIO (S3 compatible) o almacenamiento de objetos gestionado. No compartas esta carpeta por NFS como solución de escalado.

## 4. Configurar secretos y variables

```bash
cp .env.example .env
openssl rand -base64 48  # JWT_SECRET
openssl rand -base64 24  # ADMIN_PASSWORD y contraseña MariaDB
```

Ejemplo para producción con API en Docker y MariaDB externo:

```dotenv
DATABASE_URL="mysql://node2_app:CLAVE_CODIFICADA@10.0.0.20:3306/node2"
JWT_SECRET="secreto-unico-de-al-menos-32-caracteres"
ADMIN_USERNAME="admin"
ADMIN_PASSWORD="contrasena-inicial-segura"
BACKEND_PORT=3001
FRONTEND_ORIGIN="https://app.ejemplo.com"
UPLOAD_MAX_MB=25
AI_API_URL=""
AI_API_KEY=""
AI_MODEL=""
```

Codifica en URL los caracteres especiales de la contraseña MariaDB (por ejemplo, `@` como `%40`). `.env` nunca se versiona. En producción usa Docker secrets o un gestor de secretos, no valores visibles en un `compose.yaml`; Docker recomienda secretos para credenciales. [Docker secrets](https://docs.docker.com/compose/how-tos/use-secrets/)

## 5. Instalar y generar lockfile

La primera vez, en una máquina con acceso al registro npm:

```bash
npm install
git add package-lock.json
```

Versiona `package-lock.json`. Desde entonces, CI, Docker y nuevos equipos deben usar `npm ci` para instalaciones reproducibles.

## 6. Ejecutar con Docker

Con `.env` configurado y MariaDB ya disponible:

```bash
docker compose build
docker compose up -d
docker compose ps
curl http://localhost:3001/health
```

`migrate` ejecuta DDL y crea el administrador semilla; después se inician `api`, `worker` y `frontend`. La interfaz queda en `http://localhost:8080` para pruebas locales. En producción, publica el puerto 80 del frontend detrás de un proxy TLS y configura `FRONTEND_ORIGIN` con la URL pública exacta.

Comandos operativos:

```bash
docker compose logs -f api worker
docker compose restart worker
docker compose down
```

No escales `worker` a más de una réplica hasta contar con una cola distribuida o un mecanismo de bloqueo robusto para múltiples consumidores.

## 7. Desarrollo sin Docker

1. Configura MariaDB y `.env` como se indicó.
2. Ejecuta `npm ci`, `npm run db:deploy`, `npm run db:seed` y `npm run dev`.
3. Para almacenamiento local fuera de Docker, define `UPLOAD_DIR=uploads`; sus archivos se guardan en `backend/uploads/` y no se envían a Git.

## 8. Validación y pruebas

Después de instalar dependencias:

```bash
npm run typecheck
npm run build
```

El flujo obligatorio es `npm run typecheck`, `npm run test` y `npm run build`. Las pruebas no deben usar un token IA real: usan proveedor simulado y datos sintéticos.

## 9. Estructura de datos

Además del JSON de cada análisis, la aplicación normaliza la información del proceso en tablas consultables:

- `Parte` (deudor, codeudor, referencias, apoderado, otorgante).
- `Credito` (una por proceso: números, montos, tasas, fechas y saldos).
- `Movimiento` (filas del estado de cuenta).
- `CuotaAmortizacion` (plan de pagos).
- `ExtraccionCampo` (todo campo extraído, por `ruta`).

Se generan al **consolidar** el análisis del proceso (`backend/src/normalize.ts`) y se reescriben en cada consolidación.

## 10. Parámetros de liquidación

```dotenv
SMLMV=1750905
UMBRAL_MINIMA_SMLMV=40
```

Usados por `backend/src/demanda/liquidacion.ts` para calcular la cuantía y la competencia (mínima/menor). Ajusta `SMLMV` al valor vigente del año.

## 11. Respaldos y recuperación

- Base: `mariadb-dump --single-transaction node2 > node2.sql`.
- Archivos: respaldar el volumen `node2_files_data` o el directorio del bind mount.
- Restaurar BD y volumen como una misma unidad lógica: las tablas almacenan las claves de los archivos.
- Revisa periódicamente los análisis fallidos y `AuditEvent`.
