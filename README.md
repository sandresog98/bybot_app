# 🤖 ByBot v2.0

Sistema de procesamiento automático de documentos con IA para análisis y llenado de pagarés.

---

## 📋 Tabla de Contenidos

1. [Descripción General](#descripción-general)
2. [Arquitectura](#arquitectura)
3. [Estructura del Proyecto](#estructura-del-proyecto)
4. [Requisitos](#requisitos)
5. [Instalación](#instalación)
6. [Configuración](#configuración)
7. [Uso](#uso)
8. [API Reference](#api-reference)
9. [Estado del Desarrollo](#estado-del-desarrollo)
10. [Documentación Adicional](#documentación-adicional)

---

## 📖 Descripción General

ByBot es un sistema que automatiza el procesamiento de documentos financieros:

1. **Recibe documentos** (estados de cuenta, anexos, solicitudes)
2. **Analiza con IA** (Google Gemini) para extraer datos
3. **Permite validación** humana de los datos extraídos
4. **Llena pagarés** automáticamente con los datos validados

### Características Principales

- ✅ Panel administrativo con Bootstrap
- ✅ API REST para integraciones
- ✅ Análisis de documentos con Gemini AI
- ✅ Llenado automático de PDFs con PyMuPDF
- ✅ Orquestación con n8n (en VPS separado)
- ✅ Sistema de roles y permisos
- ✅ Historial completo de procesos

---

## 🏗️ Arquitectura

```
┌─────────────────────────────────────────────────────────────┐
│                    HOSTINGER (PHP)                           │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────┐ │
│  │  Admin Panel    │  │    REST API     │  │   MariaDB   │ │
│  │  (Bootstrap)    │  │   (/api/v1/)    │  │  (Datos)    │ │
│  └────────┬────────┘  └────────┬────────┘  └─────────────┘ │
│           │                    │                            │
│           └────────────────────┼────────────────────────────│
└────────────────────────────────┼────────────────────────────┘
                                 │ Webhook
                                 ▼
┌─────────────────────────────────────────────────────────────┐
│                    VPS UBUNTU (n8n)                          │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                    n8n v2.3.2                        │   │
│  │  ┌──────────────┐  ┌──────────────┐                 │   │
│  │  │Flujo Análisis│  │Flujo Llenado │                 │   │
│  │  └──────┬───────┘  └──────┬───────┘                 │   │
│  └─────────┼─────────────────┼─────────────────────────┘   │
│            │                 │                              │
│            ▼                 ▼                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │              Python 3.12.3 Scripts                   │   │
│  │  ┌─────────────┐      ┌─────────────┐               │   │
│  │  │ analyzer.py │      │  filler.py  │               │   │
│  │  │ (Gemini AI) │      │  (PyMuPDF)  │               │   │
│  │  └─────────────┘      └─────────────┘               │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Flujo de Datos

```
1. Usuario crea proceso     →  PHP guarda en BD
2. Usuario sube archivos    →  PHP guarda en uploads/
3. Usuario inicia análisis  →  PHP dispara webhook a n8n
4. n8n ejecuta Python       →  Gemini analiza documentos
5. n8n envía callback       →  PHP guarda datos IA
6. Usuario valida datos     →  PHP actualiza BD
7. Usuario inicia llenado   →  PHP dispara webhook a n8n
8. n8n ejecuta Python       →  PyMuPDF llena pagaré
9. n8n envía callback       →  PHP guarda PDF llenado
10. Usuario descarga pagaré →  Proceso completado
```

---

## 📁 Estructura del Proyecto

```
bybot/
│
├── 📁 config/                    # Configuración central
│   ├── constants.php             # Constantes y clases de estado
│   ├── database.php              # Conexión PDO a MariaDB
│   ├── env_loader.php            # Carga de variables .env
│   └── templates/                # Plantillas de pagaré
│       └── crearcoop/
│           └── posiciones.json
│
├── 📁 web/                       # Aplicación web principal
│   │
│   ├── 📁 admin/                 # Panel administrativo
│   │   ├── config/paths.php      # Rutas del admin
│   │   ├── utils/session.php     # Manejo de sesión
│   │   ├── views/layouts/        # Header, sidebar, footer
│   │   ├── pages/                # Páginas del admin
│   │   │   ├── dashboard.php
│   │   │   ├── procesos/         # CRUD de procesos
│   │   │   ├── usuarios/         # Gestión de usuarios
│   │   │   ├── configuracion/    # Config del sistema
│   │   │   └── logs/             # Visor de logs
│   │   ├── index.php             # Router principal
│   │   ├── login.php             # Página de login
│   │   └── logout.php            # Cerrar sesión
│   │
│   ├── 📁 api/                   # API REST
│   │   ├── index.php             # Entry point de API
│   │   ├── .htaccess             # Rewrite rules
│   │   ├── middleware/           # Auth, CORS, rate limit
│   │   └── v1/                   # Versión 1 de API
│   │       ├── auth/router.php
│   │       ├── procesos/router.php
│   │       ├── archivos/router.php
│   │       ├── validacion/router.php
│   │       ├── webhook/          # Callbacks de n8n
│   │       │   ├── router.php
│   │       │   └── n8n.php
│   │       ├── usuarios/router.php
│   │       ├── colas/router.php
│   │       ├── stats/router.php
│   │       └── config/router.php
│   │
│   ├── 📁 modules/               # Módulos de negocio
│   │   └── procesos/
│   │       ├── models/           # Proceso, Anexo, DatosIA, Historial
│   │       └── services/         # ProcesoService, ArchivoService
│   │
│   └── 📁 core/                  # Clases base
│       ├── BaseModel.php         # CRUD genérico
│       ├── BaseService.php       # Lógica de negocio
│       ├── Response.php          # Respuestas JSON
│       ├── Validator.php         # Validación de datos
│       ├── QueueManager.php      # (Legacy) Colas Redis
│       └── N8nClient.php         # Cliente para webhooks n8n
│
├── 📁 n8n/                       # Scripts para VPS
│   ├── SETUP_VPS.md              # Guía de instalación VPS
│   ├── flows/                    # Flujos n8n (JSON)
│   │   ├── flujo_analisis.json
│   │   ├── flujo_llenado.json
│   │   └── README.md
│   └── scripts/                  # Scripts Python
│       ├── requirements.txt      # Dependencias maestras
│       ├── env_example.txt       # Template de .env
│       ├── shared/               # Utilidades comunes
│       │   ├── config.py
│       │   └── utils.py
│       ├── analyzer/             # Análisis con Gemini
│       │   ├── main.py
│       │   └── gemini_client.py
│       └── filler/               # Llenado de PDF
│           ├── main.py
│           └── pdf_filler.py
│
├── 📁 assets/                    # Recursos estáticos
│   ├── css/
│   │   ├── variables.css         # Colores corporativos
│   │   ├── common.css            # Estilos globales
│   │   └── admin.css             # Estilos del admin
│   └── js/
│       ├── common.js             # JS global
│       └── admin.js              # JS del admin
│
├── 📁 sql/                       # Scripts de BD
│   ├── ddl.sql                   # Crear tablas
│   └── reset_db.sql              # Reiniciar BD
│
├── 📁 uploads/                   # Archivos subidos
│   └── .gitkeep
│
├── 📁 logs/                      # Logs de aplicación
│   └── .gitkeep
│
├── env_example.txt               # Template de .env (Hostinger)
├── roles.json                    # Definición de roles
├── PLAN_DESARROLLO.md            # Plan de desarrollo por fases
├── PLAN_PRUEBAS.md               # Plan de pruebas detallado
├── PLAN_REESTRUCTURACION.md      # Análisis inicial del proyecto
└── README.md                     # Este archivo
```

---

## 📋 Requisitos

### Servidor PHP (Hostinger)
- PHP 8.2+
- MariaDB 11.8+
- Extensiones: pdo_mysql, curl, json, fileinfo
- mod_rewrite habilitado

### Servidor VPS (n8n)
- Ubuntu 20.04+
- n8n 2.3.2
- Python 3.12.3
- Librerías: google-generativeai, PyMuPDF, requests

### Desarrollo Local
- XAMPP/WAMP/MAMP con PHP 8.2+
- Composer (opcional)

---

## 🚀 Instalación

### 1. Clonar/Copiar el Proyecto

```bash
# En Hostinger o servidor local
cd /path/to/htdocs
git clone [repo-url] bybot
# O copiar archivos manualmente
```

### 2. Configurar Variables de Entorno

```bash
cd bybot
cp env_example.txt .env
nano .env  # Editar con tus valores
```

### 3. Crear Base de Datos

```sql
CREATE DATABASE bybot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
# Ejecutar DDL
mysql -u usuario -p bybot < sql/ddl.sql
```

### 4. Insertar Usuario Admin

```sql
INSERT INTO control_usuarios (nombre, email, password, rol, activo) 
VALUES (
    'Administrador',
    'admin@tudominio.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1
);
-- Password: admin123
```

### 5. Configurar VPS (Ver SETUP_VPS.md)

```bash
# En el VPS
mkdir -p /opt/bybot/scripts
# Copiar contenido de n8n/scripts/
# Instalar dependencias Python
# Importar flujos en n8n
```

---

## ⚙️ Configuración

### Variables de Entorno Principales (.env)

```env
# Aplicación
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bybjuridicos.andapps.cloud

# Base de Datos
DB_HOST=localhost
DB_NAME=bybot
DB_USER=usuario
DB_PASS=password

# n8n
N8N_WEBHOOK_URL=https://n8n.srv1083920.hstgr.cloud/webhook
WORKER_API_TOKEN=token_seguro_compartido

# Gemini (referencia, se usa en VPS)
GEMINI_API_KEY=tu_api_key
```

### Roles de Usuario (roles.json)

```json
{
    "admin": ["dashboard", "procesos", "usuarios", "configuracion", "logs"],
    "supervisor": ["dashboard", "procesos", "logs"],
    "operador": ["dashboard", "procesos"]
}
```

---

## 📖 Uso

### Acceso al Panel Administrativo

```
URL: https://tu-dominio.com/web/admin/
Usuario: admin@tudominio.com
Password: admin123 (cambiar después del primer login)
```

### Flujo de Trabajo Típico

1. **Login** → Acceder al panel
2. **Crear Proceso** → Subir documentos (estado de cuenta, anexos)
3. **Analizar** → El sistema extrae datos con IA
4. **Validar** → Revisar y corregir datos extraídos
5. **Llenar Pagaré** → Generar PDF con datos validados
6. **Descargar** → Obtener pagaré llenado

---

## 📡 API Reference

### Autenticación

```bash
# Login
POST /web/api/v1/auth/login
Body: { "email": "...", "password": "..." }

# Usuario actual
GET /web/api/v1/auth/me

# Logout
POST /web/api/v1/auth/logout
```

### Procesos

```bash
# Listar
GET /web/api/v1/procesos?page=1&estado=creado

# Crear
POST /web/api/v1/procesos
Body: { "tipo": "cobranza", "prioridad": 5 }

# Obtener
GET /web/api/v1/procesos/{id}

# Encolar análisis
POST /web/api/v1/procesos/{id}/encolar-analisis

# Encolar llenado
POST /web/api/v1/procesos/{id}/encolar-llenado
```

### Archivos

```bash
# Subir
POST /web/api/v1/archivos/subir
Form: proceso_id, tipo, archivo

# Descargar
GET /web/api/v1/archivos/{id}

# Servir (para n8n)
GET /web/api/v1/archivos/servir?id={id}
Header: X-N8N-Access-Token: {token}
```

### Webhooks (para n8n)

```bash
# Resultado de análisis
POST /web/api/v1/webhook/n8n/analisis
Header: X-N8N-Access-Token: {token}
Body: { "proceso_id": 1, "success": true, "datos": {...} }

# Resultado de llenado
POST /web/api/v1/webhook/n8n/llenado
Header: X-N8N-Access-Token: {token}
Body: { "proceso_id": 1, "success": true, "archivo_contenido_base64": "..." }
```

---

## 📊 Estado del Desarrollo

| Fase | Descripción | Estado |
|------|-------------|--------|
| 1 | Fundamentos (config, BD, core) | ✅ Completada |
| 2 | API REST completa | ✅ Completada |
| 3 | Panel Administrativo | ✅ Completada |
| 4 | Integración n8n (PHP) | ✅ Completada |
| 5 | Scripts Python y Flujos n8n | ✅ Completada |
| 6 | Pruebas de Integración | ⏳ Pendiente |
| 7 | Refinamiento y Optimización | ⏳ Pendiente |
| 8 | Documentación y Deploy | ⏳ Pendiente |

### ⚠️ Estado Actual

**El código está escrito pero NO ha sido probado.** Antes de usar en producción:

1. Seguir el `PLAN_PRUEBAS.md` paso a paso
2. Corregir errores encontrados
3. Probar integración completa

---

## 📚 Documentación Adicional

| Archivo | Descripción |
|---------|-------------|
| `PLAN_DESARROLLO.md` | Plan detallado de desarrollo por fases |
| `PLAN_PRUEBAS.md` | Plan de pruebas con tests específicos |
| `PLAN_REESTRUCTURACION.md` | Análisis inicial y opciones de arquitectura |
| `n8n/SETUP_VPS.md` | Guía de configuración del VPS |
| `n8n/flows/README.md` | Documentación de flujos n8n |

---

## 🎨 Colores Corporativos

| Color | Código | Uso |
|-------|--------|-----|
| Azul Primario | `#55A5C8` | Color principal |
| Verde Secundario | `#9AD082` | Acentos y éxito |
| Gris Terciario | `#B1BCBF` | Fondos y bordes |
| Azul Oscuro | `#35719E` | Encabezados |

---

## 🔐 Seguridad

- Autenticación basada en sesiones PHP
- Tokens para comunicación con n8n
- Archivos en `uploads/` protegidos (requieren autenticación)
- Validación de roles por módulo
- Rate limiting en API

---

## 📝 Licencia

Proyecto privado - Todos los derechos reservados.

---

## 👥 Contacto

Para soporte o consultas sobre el proyecto, contactar al administrador del sistema.

---

**Versión:** 2.0  
**Última actualización:** 2026-01-18
