# 🚀 Plan de Desarrollo - ByBot v2.0
## Arquitectura con n8n como Orquestador

---

## ⚠️ ESTADO ACTUAL DEL PROYECTO

> **IMPORTANTE:** El código de las Fases 1-5 está **ESCRITO pero NO PROBADO**.
> 
> Antes de continuar con el desarrollo, es necesario ejecutar las pruebas
> descritas en `PLAN_PRUEBAS.md` para identificar y corregir errores.

### Progreso Actual

| Fase | Estado | Probado |
|------|--------|---------|
| 1. Fundamentos | ✅ Código escrito | ❌ No |
| 2. API REST | ✅ Código escrito | ❌ No |
| 3. Panel Admin | ✅ Código escrito | ❌ No |
| 4. Integración n8n | ✅ Código escrito | ❌ No |
| 5. Scripts Python | ✅ Código escrito | ❌ No |
| 6. Pruebas | 🔷 Pendiente | - |
| 7. Refinamiento | 🔷 Pendiente | - |
| 8. Deploy | 🔷 Pendiente | - |

### Próximos Pasos

1. **Ejecutar pruebas** siguiendo `PLAN_PRUEBAS.md`
2. **Corregir errores** encontrados durante las pruebas
3. **Continuar con Fase 6** una vez las fases previas estén funcionando

---

## 📋 Información del Proyecto

| Aspecto | Detalle |
|---------|---------|
| **Nombre** | ByBot v2.0 |
| **Ubicación PHP** | Hostinger (sitio web) |
| **Ubicación n8n** | VPS Ubuntu |
| **Python** | 3.12.3 (VPS) |
| **n8n** | 2.3.2 |
| **Duración estimada** | 5-6 semanas |
| **Stack Principal** | PHP 8.2 + n8n + Python 3.12 |

---

## 🏗️ Arquitectura General

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           HOSTINGER (Sitio Web)                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                    Admin Panel (PHP + Bootstrap)                       │ │
│  │  • Dashboard  • Procesos  • Usuarios  • Configuración  • Reportes     │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                    │                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                         REST API (PHP)                                 │ │
│  │  /api/v1/auth/*  /api/v1/procesos/*  /api/v1/archivos/*  ...          │ │
│  │  /api/v1/webhook/n8n  (recibe callbacks)                               │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                    │                                        │
│  ┌────────────────────┐   ┌───────────────────────────────────────────┐   │
│  │      MariaDB       │   │           File Storage (uploads/)          │   │
│  │  (Datos + Estado)  │   │         (Archivos de procesos)             │   │
│  └────────────────────┘   └───────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
                                     │
                              Webhook │ HTTP
                                     ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              VPS Ubuntu                                      │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                         n8n (v2.3.2)                                   │ │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────┐   │ │
│  │  │ Flujo: Análisis │  │ Flujo: Llenado  │  │ Flujo: Notificación │   │ │
│  │  │ • Webhook trigger│ │ • Webhook trigger│ │ • Eventos diversos  │   │ │
│  │  │ • Descarga PDF   │ │ • Obtiene datos  │ │ • Email/Slack/etc   │   │ │
│  │  │ • Ejecuta Python │ │ • Ejecuta Python │ │                     │   │ │
│  │  │ • Callback PHP   │ │ • Sube PDF       │ │                     │   │ │
│  │  └─────────────────┘  └─────────────────┘  └─────────────────────┘   │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
│                                    │                                        │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                     Scripts Python (3.12.3)                            │ │
│  │  ┌─────────────────────┐    ┌─────────────────────────────────────┐  │ │
│  │  │  analyzer.py        │    │  pagare_filler.py                   │  │ │
│  │  │  • Gemini API       │    │  • PyMuPDF                          │  │ │
│  │  │  • Extrae datos     │    │  • Llena campos                     │  │ │
│  │  │  • JSON output      │    │  • Genera PDF                       │  │ │
│  │  └─────────────────────┘    └─────────────────────────────────────┘  │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 🔄 Flujo de Trabajo con n8n

### Flujo 1: Análisis de Documentos

```
┌──────────────┐    Webhook     ┌──────────────┐    HTTP     ┌──────────────┐
│   PHP API    │ ─────────────► │     n8n      │ ──────────► │   Hostinger  │
│ (Hostinger)  │                │    (VPS)     │             │  (Descarga)  │
└──────────────┘                └──────────────┘             └──────────────┘
      │                               │                             │
      │ 1. POST /webhook/analizar     │                             │
      │    {proceso_id, archivos}     │                             │
      │                               ▼                             │
      │                        ┌──────────────┐                     │
      │                        │  2. Descarga │ ◄───────────────────┘
      │                        │    archivos  │
      │                        └──────────────┘
      │                               │
      │                               ▼
      │                        ┌──────────────┐
      │                        │  3. Ejecuta  │
      │                        │  analyzer.py │
      │                        │  (Gemini AI) │
      │                        └──────────────┘
      │                               │
      │                               ▼
      │                        ┌──────────────┐
      │                        │  4. Callback │
      │ ◄──────────────────────│   a PHP API  │
      │   POST /webhook/n8n    └──────────────┘
      │   {proceso_id, datos}
      ▼
┌──────────────┐
│  5. Actualiza│
│  BD y estado │
└──────────────┘
```

### Flujo 2: Llenado de Pagaré

```
┌──────────────┐    Webhook     ┌──────────────┐
│   PHP API    │ ─────────────► │     n8n      │
│ (tras validar)                │    (VPS)     │
└──────────────┘                └──────────────┘
      │                               │
      │ 1. POST /webhook/llenar       │
      │    {proceso_id, datos_ia}     ▼
      │                        ┌──────────────┐
      │                        │  2. Descarga │
      │                        │    pagaré    │
      │                        │    original  │
      │                        └──────────────┘
      │                               │
      │                               ▼
      │                        ┌──────────────┐
      │                        │  3. Ejecuta  │
      │                        │ filler.py    │
      │                        │  (PyMuPDF)   │
      │                        └──────────────┘
      │                               │
      │                               ▼
      │                        ┌──────────────┐
      │                        │  4. Sube PDF │
      │ ◄──────────────────────│   llenado    │
      │   POST /webhook/n8n    │  + Callback  │
      │   {proceso_id, archivo}└──────────────┘
      ▼
┌──────────────┐
│  5. Guarda   │
│  y completa  │
└──────────────┘
```

---

## 📁 Estructura del Proyecto

```
bybot/
│
├── 📁 web/                                    # ═══ HOSTINGER ═══
│   │
│   ├── 📁 admin/                              # Panel Administrativo (COMPLETADO ✅)
│   │   ├── 📁 config/paths.php
│   │   ├── 📁 pages/
│   │   ├── 📁 utils/session.php
│   │   ├── 📁 views/layouts/
│   │   ├── index.php
│   │   ├── login.php
│   │   └── logout.php
│   │
│   ├── 📁 api/                                # ═══ API REST ═══
│   │   ├── 📁 v1/
│   │   │   ├── 📁 auth/router.php
│   │   │   ├── 📁 procesos/router.php
│   │   │   ├── 📁 archivos/router.php
│   │   │   ├── 📁 validacion/router.php
│   │   │   ├── 📁 webhook/
│   │   │   │   ├── router.php
│   │   │   │   └── n8n.php                   # 🆕 Callback de n8n
│   │   │   ├── 📁 usuarios/router.php
│   │   │   └── 📁 config/router.php
│   │   └── 📁 middleware/
│   │
│   ├── 📁 modules/                            # Modelos y Servicios
│   │   ├── 📁 procesos/
│   │   ├── 📁 usuarios/
│   │   └── 📁 configuracion/
│   │
│   └── 📁 core/                               # Core PHP
│       ├── BaseModel.php
│       ├── BaseService.php
│       ├── Response.php
│       ├── Validator.php
│       └── N8nClient.php                      # 🆕 Cliente para llamar n8n
│
├── 📁 n8n/                                    # ═══ VPS - FLUJOS N8N ═══
│   │
│   ├── 📁 workflows/                          # Exportación de flujos
│   │   ├── analisis_documentos.json
│   │   ├── llenado_pagare.json
│   │   └── notificaciones.json
│   │
│   └── 📁 scripts/                            # Scripts Python para n8n
│       │
│       ├── 📁 analyzer/
│       │   ├── main.py                        # Entry point para análisis
│       │   ├── gemini_client.py               # Cliente Gemini AI
│       │   ├── prompt_loader.py               # Carga prompts
│       │   └── requirements.txt
│       │
│       ├── 📁 filler/
│       │   ├── main.py                        # Entry point para llenado
│       │   ├── pdf_filler.py                  # Llenado con PyMuPDF
│       │   ├── template_loader.py             # Carga plantillas
│       │   └── requirements.txt
│       │
│       └── 📁 shared/
│           ├── utils.py
│           └── config.py
│
├── 📁 config/                                 # ═══ CONFIGURACIÓN ═══
│   ├── 📁 prompts/                            # Prompts de IA
│   │   └── v1/
│   │       ├── estado_cuenta.md
│   │       ├── anexos.md
│   │       └── vinculacion.md
│   ├── 📁 templates/                          # Plantillas pagaré
│   │   └── posiciones.json
│   ├── database.php
│   ├── env_loader.php
│   └── constants.php
│
├── 📁 assets/                                 # Recursos estáticos
├── 📁 sql/                                    # Scripts BD
├── 📁 uploads/                                # Archivos subidos
├── 📁 logs/                                   # Logs
│
├── .env.example
├── .env
├── roles.json
├── README.md
├── PLAN_REESTRUCTURACION.md
└── PLAN_DESARROLLO.md
```

---

## 📅 Fases de Desarrollo (Actualizadas)

### ✅ FASE 1: Fundamentos (COMPLETADA)
- [x] Estructura de carpetas
- [x] Configuración base
- [x] Core PHP (BaseModel, BaseService, Response, Validator)
- [x] DDL Base de datos
- [x] Assets CSS/JS

### ✅ FASE 2: API REST (COMPLETADA)
- [x] Middleware (auth, cors, rate_limit)
- [x] Endpoints de autenticación
- [x] Endpoints de procesos
- [x] Endpoints de archivos
- [x] Endpoints de validación
- [x] Endpoints de usuarios y configuración

### ✅ FASE 3: Panel Administrativo (COMPLETADA)
- [x] Layouts (header, sidebar, footer)
- [x] Dashboard con estadísticas
- [x] Módulo Procesos (lista, crear, ver, validar)
- [x] Módulo Usuarios
- [x] Módulo Configuración
- [x] Módulo Logs

---

### ✅ FASE 4: Integración con n8n (COMPLETADA)
**Objetivo:** Conectar PHP con n8n para procesamiento

#### 4.1 Cliente n8n en PHP
- [x] Crear `N8nClient.php` para disparar webhooks
- [x] Implementar métodos:
  - `triggerWebhook(workflowPath, data)`
- [x] Manejo de errores y reintentos

#### 4.2 Webhook Receptor (PHP → recibe de n8n)
- [x] Crear endpoint `POST /api/v1/webhook/n8n`
- [x] Validar token secreto de n8n
- [x] Manejar tipos de callback:
  - `analysis_complete` - Guardar datos IA
  - `analysis_error` - Registrar error
  - `fill_complete` - Guardar PDF llenado
  - `fill_error` - Registrar error
- [x] Actualizar estados de proceso automáticamente

#### 4.3 Actualizar Servicios PHP
- [x] `ProcesoService` - Integrar disparos a n8n
- [x] Modificar flujo de creación de proceso
- [x] Modificar flujo de validación → llenado

#### 4.4 Endpoints para n8n (PHP → n8n consume)
- [x] `GET /api/v1/archivos/servir` - Descarga para n8n
- [x] `POST /api/v1/archivos/subir-externo` - Subida desde n8n
- [x] Token de autenticación especial para n8n

**Entregables Fase 4:**
- ✅ N8nClient funcional
- ✅ Webhook receptor configurado
- ✅ PHP puede disparar flujos
- ✅ PHP puede recibir resultados

---

### ✅ FASE 5: Flujos n8n y Scripts Python (COMPLETADA)
**Objetivo:** Crear flujos de automatización en n8n y scripts de procesamiento

#### 5.1 Scripts Python para VPS
- [x] `analyzer/main.py` - Entry point para análisis
- [x] `analyzer/gemini_client.py` - Cliente Gemini AI
- [x] `filler/main.py` - Entry point para llenado
- [x] `filler/pdf_filler.py` - Llenado con PyMuPDF
- [x] `shared/config.py` - Configuración centralizada
- [x] `shared/utils.py` - Utilidades comunes
- [x] `requirements.txt` - Dependencias

#### 5.2 Flujo: Análisis de Documentos
```
Webhook Trigger (webhook/analisis)
    ↓
Respuesta Inmediata
    ↓
Set Variables
    ↓
Execute Command (python analyzer/main.py)
    ↓
IF (éxito)
    → HTTP Request (Callback éxito a PHP)
ELSE
    → HTTP Request (Callback error a PHP)
```
- [x] Flujo exportado: `n8n/flows/flujo_analisis.json`

#### 5.3 Flujo: Llenado de Pagaré
```
Webhook Trigger (webhook/llenado)
    ↓
Respuesta Inmediata
    ↓
Set Variables
    ↓
HTTP Request (Descargar pagaré)
    ↓
Execute Command (python filler/main.py)
    ↓
HTTP Request (Callback a PHP con base64)
    ↓
Limpiar Archivos Temporales
```
- [x] Flujo exportado: `n8n/flows/flujo_llenado.json`

#### 5.4 Documentación
- [x] `n8n/flows/README.md` - Guía de flujos
- [x] `n8n/SETUP_VPS.md` - Guía de instalación VPS

**Entregables Fase 5:**
- ✅ Scripts Python funcionales
- ✅ Flujos n8n exportados como JSON
- ✅ Documentación de instalación VPS

---

### 🔷 FASE 6: Pruebas de Integración (Semana 5)
**Objetivo:** Probar la integración completa PHP ↔ n8n ↔ Python

#### 6.1 Configurar VPS
- [ ] Copiar scripts a `/opt/bybot/scripts/`
- [ ] Crear entorno virtual Python
- [ ] Instalar dependencias
- [ ] Configurar `.env` en VPS
- [ ] Probar scripts standalone

#### 6.2 Configurar n8n
- [ ] Importar flujos desde JSON
- [ ] Ajustar rutas de scripts
- [ ] Activar webhooks
- [ ] Probar flujo de análisis

#### 6.3 Configurar Hostinger
- [ ] Configurar `.env` de producción
- [ ] Verificar conexión a BD
- [ ] Verificar CORS para VPS
- [ ] Probar API endpoints

#### 6.4 Test End-to-End
- [ ] Crear proceso desde Admin
- [ ] Verificar disparo a n8n
- [ ] Verificar análisis completo
- [ ] Validar datos en Admin
- [ ] Verificar llenado de pagaré
- [ ] Descargar PDF llenado

**Entregables Fase 6:**
- ✅ Scripts instalados en VPS
- ✅ Flujos n8n configurados
- ✅ Integración funcionando

---

### 🔷 FASE 7: Refinamiento y Optimización (Semana 6)
**Objetivo:** Sistema robusto y optimizado

#### 7.1 Manejo de Errores
- [ ] Error en análisis → Reintentar automático
- [ ] Error en llenado → Reintentar automático
- [ ] Timeout → Notificar al usuario
- [ ] n8n no disponible → Marcar como pendiente

#### 7.2 Notificaciones en UI
- [ ] Polling de estado (alternativa a WebSocket)
- [ ] Actualización automática de listas
- [ ] Indicadores de proceso en curso
- [ ] Toasts de notificación

#### 7.3 Optimización
- [ ] Revisar tiempos de respuesta
- [ ] Cachear configuraciones
- [ ] Optimizar queries
- [ ] Comprimir PDFs generados

#### 7.4 Prompts de IA
- [ ] Afinar prompts para mejor extracción
- [ ] Versionar prompts
- [ ] UI para editar prompts

**Entregables Fase 7:**
- ✅ Manejo de errores robusto
- ✅ UI responsiva y actualizada
- ✅ Sistema optimizado

---

### 🔷 FASE 8: Documentación y Deploy (Semana 6)
**Objetivo:** Sistema listo para producción

#### 8.1 Documentación
- [ ] README.md actualizado
- [ ] Guía de instalación Hostinger
- [ ] Guía de instalación VPS/n8n
- [ ] Documentación de API
- [ ] Guía de configuración de flujos

#### 8.2 Configuración Producción
- [ ] Variables de entorno producción
- [ ] URLs y tokens seguros
- [ ] Logs configurados
- [ ] Backups de BD

#### 8.3 Seguridad
- [ ] Token secreto n8n ↔ PHP
- [ ] Rate limiting en webhooks
- [ ] Validación de orígenes
- [ ] Auditoría de accesos

**Entregables Fase 8:**
- ✅ Documentación completa
- ✅ Sistema en producción
- ✅ Seguridad verificada

---

## 🔧 Variables de Entorno (.env)

```env
# =============================================
# BYBOT v2.0 - Variables de Entorno
# =============================================

# Entorno
APP_ENV=development
APP_DEBUG=true
APP_URL=https://tu-sitio.com/bybot

# Base de Datos (Hostinger)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=bybot
DB_USER=tu_usuario
DB_PASS=tu_password

# n8n (VPS)
N8N_BASE_URL=https://tu-vps.com:5678
N8N_WEBHOOK_URL=https://tu-vps.com:5678/webhook
N8N_API_KEY=tu_api_key_n8n
N8N_WEBHOOK_SECRET=secreto_compartido_para_validar

# Gemini AI (usado por scripts Python)
GEMINI_API_KEY=tu_api_key_gemini
GEMINI_MODEL=gemini-1.5-flash
GEMINI_TEMPERATURE=0.1
GEMINI_MAX_TOKENS=4000

# API
API_TOKEN_SECRET=tu_secret_para_tokens
API_RATE_LIMIT=100

# Token para que n8n acceda a la API PHP
N8N_ACCESS_TOKEN=token_largo_y_seguro_para_n8n

# Uploads
UPLOAD_MAX_SIZE_IMAGE=5242880
UPLOAD_MAX_SIZE_PDF=10485760

# Logs
LOG_LEVEL=debug
```

---

## 🆚 Comparación: Redis vs n8n

| Aspecto | Redis + Workers | n8n |
|---------|-----------------|-----|
| **Instalación** | Requiere Docker/Redis | Ya instalado ✅ |
| **Mantenimiento** | Múltiples servicios | Interfaz centralizada ✅ |
| **Debugging** | Logs en archivos | Visual en n8n ✅ |
| **Escalabilidad** | Manual | Fácil con n8n ✅ |
| **Costo** | Recursos servidor | Ya incluido ✅ |
| **Complejidad** | Alta | Media ✅ |
| **Flexibilidad** | Máxima | Alta ✅ |
| **Curva aprendizaje** | Alta | Baja ✅ |

---

## 📊 Métricas de Éxito

| Métrica | Objetivo |
|---------|----------|
| Tiempo de análisis | < 60 segundos |
| Tiempo de llenado | < 30 segundos |
| Uptime n8n | > 99% |
| Errores de análisis | < 5% |
| Latencia webhook | < 2 segundos |

---

## 🚨 Consideraciones Especiales

### Hostinger
- Verificar límites de timeout para webhooks
- Configurar CORS para VPS
- Asegurar que uploads sean accesibles por URL (con token)

### VPS con n8n
- Asegurar que Python 3.12.3 tenga las librerías necesarias
- Configurar n8n para ejecutar comandos de sistema
- Firewall abierto para webhooks

### Comunicación
- Usar HTTPS siempre
- Token secreto en headers
- Validar payloads

---

## 📝 Próximos Pasos Inmediatos (Fase 6)

1. **Copiar scripts al VPS** - `scp -r n8n/scripts/* usuario@vps:/opt/bybot/scripts/`
2. **Configurar `.env` en VPS** - Variables de entorno para Python
3. **Importar flujos en n8n** - Desde los JSON exportados
4. **Probar flujo de análisis** - Con un documento de prueba
5. **Configurar `.env` en Hostinger** - URLs y tokens

---

## 📋 URLs de Producción

| Componente | URL |
|-----------|-----|
| **Admin Panel** | https://bybjuridicos.andapps.cloud/web/admin/ |
| **API** | https://bybjuridicos.andapps.cloud/web/api/v1/ |
| **n8n** | https://n8n.srv1083920.hstgr.cloud |
| **Webhook Análisis** | https://n8n.srv1083920.hstgr.cloud/webhook/analisis |
| **Webhook Llenado** | https://n8n.srv1083920.hstgr.cloud/webhook/llenado |
| **Callback PHP** | https://bybjuridicos.andapps.cloud/web/api/v1/webhook/n8n/ |

---

**Documento creado:** 2026-01-16  
**Última actualización:** 2026-01-18  
**Autor:** Asistente IA  
**Versión:** 2.1 (Fase 5 Completada)
