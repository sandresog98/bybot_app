# 📁 Índice de Archivos - ByBot v2.0

Este documento lista todos los archivos del proyecto con una breve descripción de cada uno.
Útil para entender rápidamente la estructura y propósito de cada componente.

---

## 📋 Documentación

| Archivo | Descripción |
|---------|-------------|
| `README.md` | Documentación principal del proyecto |
| `PLAN_DESARROLLO.md` | Plan de desarrollo por fases con arquitectura |
| `PLAN_PRUEBAS.md` | Plan detallado de pruebas para cada fase |
| `PLAN_REESTRUCTURACION.md` | Análisis inicial y opciones de arquitectura |
| `INDICE_ARCHIVOS.md` | Este archivo - índice de todos los archivos |
| `env_example.txt` | Template de variables de entorno para PHP |
| `roles.json` | Definición de roles y permisos |

---

## ⚙️ Configuración (`config/`)

| Archivo | Descripción |
|---------|-------------|
| `constants.php` | Constantes globales, clases de estado (EstadoProceso, TipoProceso, etc.) |
| `database.php` | Función `getConnection()` para conectar a MariaDB |
| `env_loader.php` | Carga variables desde `.env` usando vlucas/phpdotenv |
| `templates/crearcoop/posiciones.json` | Plantilla de posiciones para llenar pagaré (placeholder) |

---

## 🗄️ Base de Datos (`sql/`)

| Archivo | Descripción |
|---------|-------------|
| `ddl.sql` | Script para crear todas las tablas desde cero |
| `reset_db.sql` | Script para eliminar y recrear la base de datos |

### Tablas Definidas

| Tabla | Propósito |
|-------|-----------|
| `control_usuarios` | Usuarios del sistema |
| `control_logs` | Logs de acciones |
| `procesos` | Procesos principales (documentos a analizar) |
| `procesos_anexos` | Archivos adjuntos de cada proceso |
| `procesos_datos_ia` | Datos extraídos por la IA |
| `procesos_historial` | Historial de cambios de cada proceso |
| `cola_trabajos` | Cola de trabajos (legacy, no se usa con n8n) |
| `configuracion` | Configuraciones del sistema |
| `configuracion_prompts` | Prompts de IA versionados |
| `configuracion_plantillas` | Plantillas de pagaré |

---

## 🎨 Assets (`assets/`)

### CSS

| Archivo | Descripción |
|---------|-------------|
| `css/variables.css` | Variables CSS con colores corporativos |
| `css/common.css` | Estilos globales compartidos |
| `css/admin.css` | Estilos específicos del panel admin |

### JavaScript

| Archivo | Descripción |
|---------|-------------|
| `js/common.js` | Funciones JavaScript globales |
| `js/admin.js` | Objeto `ByBot` con utilidades para el admin |

---

## 🌐 Web - Core (`web/core/`)

| Archivo | Descripción |
|---------|-------------|
| `BaseModel.php` | Clase abstracta para modelos con CRUD genérico |
| `BaseService.php` | Clase abstracta para servicios de negocio |
| `Response.php` | Helper para respuestas JSON estandarizadas |
| `Validator.php` | Utilidades de validación de datos |
| `QueueManager.php` | Manejo de colas Redis (legacy, no se usa con n8n) |
| `N8nClient.php` | Cliente HTTP para disparar webhooks de n8n |

---

## 🖥️ Web - Panel Admin (`web/admin/`)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Router principal del admin, carga páginas según `?page=` |
| `login.php` | Página de login |
| `logout.php` | Destruye sesión y redirige a login |
| `config/paths.php` | Rutas y URLs específicas del admin |
| `utils/session.php` | Funciones de manejo de sesión |

### Layouts (`web/admin/views/layouts/`)

| Archivo | Descripción |
|---------|-------------|
| `header.php` | Cabecera HTML, meta tags, CSS, navbar |
| `sidebar.php` | Menú lateral basado en rol del usuario |
| `footer.php` | Scripts JS y cierre de HTML |

### Páginas (`web/admin/pages/`)

| Archivo | Descripción |
|---------|-------------|
| `dashboard.php` | Página principal con estadísticas |
| `access_denied.php` | Página de acceso denegado |
| `perfil.php` | Perfil del usuario |

### Módulo Procesos (`web/admin/pages/procesos/`)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Router del módulo (carga lista, crear, ver, validar) |
| `lista.php` | Lista de procesos con filtros |
| `crear.php` | Formulario para crear proceso |
| `ver.php` | Detalle de un proceso |
| `validar.php` | Validación de datos extraídos por IA |

### Módulo Usuarios (`web/admin/pages/usuarios/`)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Router del módulo |
| `lista.php` | Lista y gestión de usuarios |

### Módulo Configuración (`web/admin/pages/configuracion/`)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Router del módulo |
| `general.php` | Configuración general del sistema |
| `prompts.php` | Gestión de prompts de IA |
| `colas.php` | Estado de colas/n8n |
| `plantillas.php` | Gestión de plantillas de pagaré |

### Módulo Logs (`web/admin/pages/logs/`)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Router del módulo |
| `lista.php` | Visor de logs del sistema |

---

## 📡 Web - API REST (`web/api/`)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Entry point de la API, enruta según path |
| `.htaccess` | Rewrite rules para URLs limpias |

### Middleware (`web/api/middleware/`)

| Archivo | Descripción |
|---------|-------------|
| `cors.php` | Configura headers CORS |
| `auth.php` | Verifica sesión de usuario |
| `rate_limit.php` | Limita requests por IP/usuario |
| `api_token.php` | Valida token para workers/n8n |

### Routers v1 (`web/api/v1/`)

| Archivo | Descripción |
|---------|-------------|
| `auth/router.php` | Login, logout, me, change-password |
| `procesos/router.php` | CRUD de procesos, encolar análisis/llenado |
| `archivos/router.php` | Subir, descargar, eliminar archivos |
| `archivos/servir.php` | Endpoint para que n8n descargue archivos |
| `archivos/subir-externo.php` | Endpoint para que n8n suba archivos |
| `validacion/router.php` | Guardar, confirmar validación, re-analizar |
| `webhook/router.php` | Router para webhooks |
| `webhook/n8n.php` | Recibe callbacks de n8n (análisis, llenado, error) |
| `usuarios/router.php` | CRUD de usuarios |
| `colas/router.php` | Estado de colas (legacy) |
| `stats/router.php` | Estadísticas del dashboard |
| `config/router.php` | Configuración, prompts, plantillas |

---

## 📦 Web - Módulos (`web/modules/`)

### Procesos (`web/modules/procesos/`)

#### Models

| Archivo | Descripción |
|---------|-------------|
| `models/Proceso.php` | Modelo de procesos con estados y búsqueda |
| `models/Anexo.php` | Modelo de archivos adjuntos |
| `models/DatosIA.php` | Modelo de datos extraídos por IA |
| `models/Historial.php` | Modelo de historial de procesos |

#### Services

| Archivo | Descripción |
|---------|-------------|
| `services/ProcesoService.php` | Lógica de negocio para procesos, dispara n8n |
| `services/ArchivoService.php` | Manejo de archivos (subir, validar, eliminar) |
| `services/ValidacionService.php` | Lógica de validación de datos IA |

---

## 🤖 n8n - Flujos y Scripts (`n8n/`)

| Archivo | Descripción |
|---------|-------------|
| `SETUP_VPS.md` | Guía completa de configuración del VPS |

### Flujos (`n8n/flows/`)

| Archivo | Descripción |
|---------|-------------|
| `README.md` | Documentación de flujos n8n |
| `flujo_analisis.json` | Flujo para análisis con Gemini |
| `flujo_llenado.json` | Flujo para llenado de pagaré |

### Scripts Python (`n8n/scripts/`)

| Archivo | Descripción |
|---------|-------------|
| `requirements.txt` | Dependencias maestras de Python |
| `env_example.txt` | Template de .env para VPS |

#### Shared (`n8n/scripts/shared/`)

| Archivo | Descripción |
|---------|-------------|
| `__init__.py` | Exports del módulo |
| `config.py` | Configuración cargada desde .env |
| `utils.py` | download_file, upload_file, send_callback |

#### Analyzer (`n8n/scripts/analyzer/`)

| Archivo | Descripción |
|---------|-------------|
| `__init__.py` | Exports del módulo |
| `main.py` | Entry point para análisis |
| `gemini_client.py` | Cliente de Google Gemini AI |
| `requirements.txt` | Dependencias específicas |

#### Filler (`n8n/scripts/filler/`)

| Archivo | Descripción |
|---------|-------------|
| `__init__.py` | Exports del módulo |
| `main.py` | Entry point para llenado de PDF |
| `pdf_filler.py` | Llenado de PDF con PyMuPDF |
| `requirements.txt` | Dependencias específicas |

---

## 📂 Carpetas de Datos

| Carpeta | Descripción |
|---------|-------------|
| `uploads/` | Archivos subidos (PDFs, imágenes) |
| `logs/` | Logs de la aplicación |
| `assets/img/` | Imágenes del sistema |
| `assets/favicons/` | Favicons |

---

## 🔑 Archivos de Configuración Requeridos (No incluidos)

| Archivo | Descripción | Ubicación |
|---------|-------------|-----------|
| `.env` | Variables de entorno (PHP/Hostinger) | `/bybot/.env` |
| `.env` | Variables de entorno (Python/VPS) | `/opt/bybot/scripts/.env` |

**Nota:** Estos archivos contienen credenciales y NO se incluyen en el repositorio.
Usar los templates `env_example.txt` como base.

---

## 📊 Resumen de Conteo

| Tipo | Cantidad |
|------|----------|
| Archivos PHP | ~45 |
| Archivos Python | ~10 |
| Archivos SQL | 2 |
| Archivos CSS | 3 |
| Archivos JS | 2 |
| Archivos JSON | 4 |
| Archivos MD | 6 |

---

**Última actualización:** 2026-01-18

