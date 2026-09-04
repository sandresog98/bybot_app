# RUNBOOK — ByBot App (`node_version`)

Guía para poner en marcha el monorepo y sus daemons en **cualquier PC**
(Linux/XAMPP, macOS/Docker-Colima o Windows). Pasos genéricos + notas por SO.

> Preliminar: lee también `README.md` y `handoff.md`. Este documento es el *cómo
> arrancar de cero*.

---

## 1. Requisitos

| Dependencia | Versión | Notas |
|---|---|---|
| Node.js + npm | 18+ / 20 | monorepo (workspaces) |
| Python | 3.10+ | daemon IA (Gemini) y bots |
| MariaDB / MySQL | 8 / 10.x | BD `bybot_consolidado` |
| Google‑GenAI API key | — | clave de Gemini, en `.env` (`GEMINI_API_KEY`) |

Opcional (solo para documentos TIFF): conversión TIFF→PDF vía `Pillow` (ya incluido).

---

## 2. Variables de entorno (`.env`)

```bash
cd node_version
cp .env.example .env
```

Edita como mínimo:
- **Base de datos** — `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`
  y `DATABASE_URL` (formato `mysql://USER:PASS@HOST:PORT/DB`). Deben coincidir
  con tu MariaDB.
- **Gemini** — `GEMINI_API_KEY` (obligatoria para análisis IA).
  `GEMINI_MODEL=gemini-2.5-flash`, `GEMINI_MAX_TOKENS=26000`,
  `GEMINI_THINKING_BUDGET=0`.
- **Storage** — `STORAGE_DRIVER=local`, `STORAGE_LOCAL_DIR=uploads`.
- **Tokens internos** — `BACKEND_BOTSTORAGE_TOKEN` == `BOTSTORAGE_INTERNAL_TOKEN`.
- `WORKER_PY_BIN` (ruta al venv Python, usada por backend para invocar Python).

> En `shared/config.py` el daemon Python lee el `.env` de la **raíz del monorepo**
> (`node_version/.env`), así que las claves `GEMINI_*`, `DB_*`, `STORAGE_LOCAL_DIR`
> y `COLA_POLL_INTERVAL_SEG` se toman de ahí.

---

## 3. Base de datos

### Linux / XAMPP (MySQL a mano):

```bash
# 1. Arrancar MariaDB (como root):
sudo /opt/lampp/lampp startmysql

# 2. Verificar credenciales root (XAMPP suele ser SIN password):
/opt/lampp/bin/mysql -u root -e "SELECT 1"
#   → si tu `.env` usa root con password (p.ej. bybot_root), ajusta para que cuadre:
mysql -u root <<'SQL'
ALTER USER 'root'@'localhost' IDENTIFIED BY 'bybot_root';
CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY 'bybot_root';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

# 3. Crear/reiniciar la BD con seeds (DESTRUYE datos existentes de bybot_consolidado).
#    IMPORTANTE: ejecutar DESDE node_version/ porque reset_db.sql hace SOURCE de sql/ddl.sql
#    con rutas relativas al directorio de trabajo.
cd node_version
/opt/lampp/bin/mysql -u root -pbybot_root < sql/reset_db.sql
```

> Si no quieres resetear (p.ej. conservar datos) y la BD ya existe, solo aplica las
> migraciones pendientes en orden: `sql/migrations/*.sql`.

### macOS / Docker (Colima, el entorno original del proyecto):

```bash
docker run -d --name bybot-mariadb -e MARIADB_ROOT_PASSWORD=bybot_root \
  -e MARIADB_DATABASE=bybot_consolidado -p 3306:3306 mariadb:10.11
# Cargar esquema (no uses reset_db.sql con path XAMPP):
docker exec -i bybot-mariadb mariadb -uroot -pbybot_root bybot_consolidado < sql/ddl.sql
# Y luego las migraciones pendientes:
for f in sql/migrations/*.sql; do docker exec -i bybot-mariadb mariadb -uroot -pbybot_root bybot_consolidado < "$f"; done
```

### Windows (recomendado WSL2/Linux o contenedor Docker de MariaDB):
Sigue el flujo genérico con `mysql` disponible en PATH y las mismas queries.
El `STORAGE_LOCAL_DIR=uploads` y las rutas relativas funcionan igual.

---

## 4. Instalar dependencias

```bash
# Nodo (monorepo con workspaces). Se instalan backend + frontend + botstorage.
cd node_version
npm install

# Prisma client (espejo de la BD, NO migraciones):
npm run db:generate

# Python (daemon IA + bots). crea el venv y las dependencias:
cd node_version/botworker
python3 -m venv .venv
.venv/bin/pip install --upgrade pip
.venv/bin/pip install -r requirements.txt           # pillow ya incluido para TIFF
```

> **Si `npm install` o `pip install` dan 403** (registro corporativo tipo Fury) en macOS,
> autentica: `fury registry login` y reintenta. En redes normales es transparente.

---

## 5. Arrancar los 3 servicios Node

```bash
cd node_version
npm run dev        # sube backend (:3001), frontend (:5173) y botstorage (:3002)
```

Verifica:
```bash
curl http://127.0.0.1:3001/api/v1/health    # {"db":"ok"}
curl http://127.0.0.1:3002/health            # botstorage
curl -I http://127.0.0.1:5173/               # 200
```

Para ejecutarlos desligados del terminal (no se matan al cerrar la sesión) usa
`setsid` (Linux) o `nohup` (macOS):
```bash
setsid nohup npm run dev > /tmp/bybot_dev.log 2>&1 < /dev/null &
```

---

## 6. Daemons Python (análisis IA + consultas bot)

Dependen de la BD y de `.env`. Desde `node_version/`:

```bash
# Análisis IA (Gemini). Processa la cola 'bybot:analizar'.
setsid nohup botworker/.venv/bin/python botworker/daemon.py     > /tmp/bydaemon.log    2>&1 < /dev/null &
# Consultas bot. Processa la cola 'bybot:consultar'.
setsid nohup botworker/.venv/bin/python botworker/bot_runner.py > /tmp/bybotrunner.log 2>&1 < /dev/null &
```

(macOS usa `nohup ... &` directamente porque no existen `setsid`.)

Logs: `tail -f /tmp/bydaemon.log` y `tail -f /tmp/bybotrunner.log`.

> Tras cambiar `analizador.py`, la `GEMINI_API_KEY` o la config de `.env`: **reinicia el daemon**
> (mata el proceso y relánzalo).

---

## 7. Flujo de prueba end-to-end (API)

```bash
B=http://127.0.0.1:3001/api/v1
# 1) login (admin/admin123 es de único uso → cambia la clave en el primer ingreso)
curl -s -X POST $B/auth/login -H 'Content-Type: application/json' \
  -d '{"usuario":"admin","password":"admin123"}'

# 2) crear proceso por entidad (confiar=1, crearcoop=2, somec=3)
curl -s -X POST $B/procesos -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -d '{"tipo":"cobranza","entidad_id":1}'

# 3) subir archivo (PDF). ⚠️ El campo 'tipo' debe ir ANTES que 'file' en el multipart.
curl -s -X POST $B/procesos/1/archivos -H "Authorization: Bearer $TOKEN" \
  -F "tipo=estado_cuenta" -F "file=@archivos/condiar/extracto.pdf"

# 4) encolar análisis y vigilar estado
curl -s -X POST $B/procesos/1/analizar -H "Authorization: Bearer $TOKEN"
curl -s $B/procesos/1/analisis/estado -H "Authorization: Bearer $TOKEN"
curl -s $B/procesos/1/analisis/datos  -H "Authorization: Bearer $TOKEN"
curl -s $B/procesos/1/analisis/consumo -H "Authorization: Bearer $TOKEN"
```

**Truco del multipart (importante):** `@fastify/multipart` solo captura los campos de texto
que están **antes** de la parte del archivo. Si envías `tipo` después de `file`, se ignora y
cae al default `anexo`. Usa `-F "tipo=..." -F "file=@..."`.

---

## 8. Notas de la instancia (este entorno Linux de desarrollo)

- MariaDB corriendo con **datadir propio** en `/tmp/opencode/bybot_new` (root `root`/`bybot_root`)
  porque el `lampp` de XAMPP exigía root y su datadir era de `mysql`. El socket está en
  `/tmp/opencode/bybot_new/run/mysql.sock` y escucha en `127.0.0.1:3306`.
- Para reiniciar la BD desde cero aquí:
  ```bash
  /opt/lampp/bin/mysql -h 127.0.0.1 -u root -pbybot_root -e "DROP DATABASE IF EXISTS bybot_consolidado" && \
  /opt/lampp/bin/mysql -h 127.0.0.1 -u root -pbybot_root < sql/ddl.sql
  ```
- Credenciales default (seed): usuario `admin` / `admin123` (único uso) → en esta sesión se
  cambió a `admin555`. Entidades sembradas: `confiar`, `crearcoop`, `somec`.
- Archivos de ejemplo: en `../archivos/{condiar,crearcoop,somec}/` (la carpeta `condiar`
  contiene los documentos de **CONFIAR**).
- Free-tier de Gemini: ~5 req/min y se agota con uso intensivo (errores `429`/`503`);
  el daemon reintenta con backoff. Para cargas reales usa una key con facturación.