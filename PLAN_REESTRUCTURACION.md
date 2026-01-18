# 📋 Plan de Reestructuración - ByBot

## 📊 Análisis del Proyecto Actual (bybot_app)

### Descripción General
**ByBot** es un sistema especializado para automatizar procesos jurídicos de cobranza, específicamente:
- Análisis de documentos PDF (pagarés, estados de cuenta, anexos) mediante IA (Gemini)
- Extracción automática de información (deudor, codeudor, saldos, tasas)
- Llenado automático de pagarés con datos extraídos
- Validación manual de datos por usuarios

### Componentes Actuales

| Componente | Tecnología | Descripción |
|------------|------------|-------------|
| Admin Web | PHP 8.2 + Bootstrap | Panel administrativo para gestión de procesos |
| Bot de Análisis | Python 3.12 | Servicio que procesa documentos con Gemini |
| Base de Datos | MariaDB 11.8 | Almacena procesos, datos de IA, usuarios, logs |

### Flujo de Trabajo Actual
```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  1. Crear       │────▶│  2. Analizar    │────▶│  3. Validar     │
│  Proceso (Web)  │     │  con IA (Bot)   │     │  Datos (Web)    │
└─────────────────┘     └─────────────────┘     └─────────────────┘
                                                        │
                                                        ▼
                                               ┌─────────────────┐
                                               │  4. Llenar      │
                                               │  Pagaré (Bot)   │
                                               └─────────────────┘
```

### Estructura de Tablas Actual
- `control_usuarios` - Usuarios del sistema
- `control_logs` - Logs de auditoría
- `crear_coop_procesos` - Procesos de creación de pagarés
- `crear_coop_anexos` - Archivos anexos de cada proceso
- `crear_coop_datos_ia` - Datos extraídos por IA (JSON)

---

## ⚠️ Problemas Identificados

### 1. Estructura del Código
- ❌ Módulo único (`crear_coop`) muy grande con lógica mezclada
- ❌ Archivos de vista (PHP) con lógica de negocio (500+ líneas en `ver_proceso.php`)
- ❌ Funciones helper definidas en archivos de vista
- ❌ Redundancia de código entre vistas

### 2. Arquitectura
- ❌ Comunicación Bot ↔ PHP mediante archivos (descarga/subida HTTP)
- ❌ El bot hace polling cada 30 segundos (ineficiente)
- ❌ No hay colas de trabajo (jobs queue)
- ❌ Estados del proceso codificados como strings literales

### 3. Escalabilidad
- ❌ Un solo módulo para todo el proceso jurídico
- ❌ No hay separación entre diferentes tipos de documentos
- ❌ Hardcoded: posiciones de campos en el pagaré (PyMuPDF)
- ❌ Prompts de IA embebidos en el código

### 4. Mantenibilidad
- ❌ CSS común mínimo, estilos inline
- ❌ JavaScript embebido en las vistas
- ❌ No hay tests automatizados
- ❌ Documentación limitada

### 5. UX/UI
- ❌ Solo un proceso a la vez
- ❌ Sin notificaciones en tiempo real del progreso
- ❌ Sin dashboard con métricas
- ❌ Sin historial de cambios

---

## 🎯 Opciones de Reestructuración

### Opción A: Refactorización Incremental
**Esfuerzo:** ⭐⭐☆☆☆ (Bajo) | **Tiempo estimado:** 1-2 semanas

**Descripción:** Mejorar la estructura actual sin reescribir desde cero.

**Cambios propuestos:**
1. Extraer funciones helper a archivos separados
2. Separar JavaScript a archivos `.js`
3. Crear clases de servicio para lógica de negocio
4. Agregar constantes para estados de proceso
5. Mejorar el CSS común

**Pros:**
- ✅ Menor riesgo
- ✅ Preserva funcionalidad existente
- ✅ Rápido de implementar

**Contras:**
- ❌ Mantiene problemas arquitectónicos fundamentales
- ❌ No resuelve la comunicación Bot ↔ PHP
- ❌ Limitado para agregar nuevas funcionalidades

---

### Opción B: Arquitectura por Capas (Recomendada para MVP)
**Esfuerzo:** ⭐⭐⭐☆☆ (Medio) | **Tiempo estimado:** 2-4 semanas

**Descripción:** Reestructurar con una arquitectura MVC limpia y componentes reutilizables.

```
bybot/
├── admin/                          # Interfaz Administrativa
│   ├── config/
│   │   └── paths.php
│   ├── controllers/
│   │   └── AuthController.php
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── pages/
│   │   └── dashboard.php
│   ├── utils/
│   │   └── session.php
│   └── views/
│       └── layouts/
│           ├── footer.php
│           ├── header.php
│           └── sidebar.php
│
├── api/                            # ★ NUEVO: API centralizada
│   ├── auth/
│   │   └── login.php
│   ├── procesos/
│   │   ├── crear.php
│   │   ├── listar.php
│   │   ├── obtener.php
│   │   └── actualizar.php
│   ├── archivos/
│   │   ├── subir.php
│   │   ├── descargar.php
│   │   └── servir_bot.php
│   ├── validacion/
│   │   └── guardar.php
│   └── middleware/
│       ├── auth.php
│       └── cors.php
│
├── modules/                        # Módulos funcionales
│   └── procesos/
│       ├── models/
│       │   ├── Proceso.php
│       │   ├── Anexo.php
│       │   └── DatosIA.php
│       ├── services/
│       │   ├── ProcesoService.php
│       │   ├── ArchivosService.php
│       │   └── ValidacionService.php
│       └── pages/
│           ├── lista.php
│           ├── crear.php
│           └── ver.php
│
├── bot/                            # Bot de Python
│   ├── config/
│   │   ├── __init__.py
│   │   ├── settings.py
│   │   └── logging_config.py
│   ├── core/
│   │   ├── __init__.py
│   │   ├── api_client.py          # ★ NUEVO: Cliente para API PHP
│   │   ├── database.py
│   │   ├── gemini_client.py
│   │   ├── pdf_extractor.py
│   │   └── pagare_filler.py
│   ├── processors/
│   │   ├── __init__.py
│   │   ├── base_processor.py      # ★ NUEVO: Clase base
│   │   ├── analisis_processor.py
│   │   └── pagare_processor.py
│   ├── prompts/                   # ★ NUEVO: Prompts externalizados
│   │   ├── estado_cuenta.txt
│   │   ├── anexos.txt
│   │   └── vinculacion.txt
│   ├── main.py
│   ├── worker.py                  # ★ NUEVO: Worker para colas
│   └── requirements.txt
│
├── assets/
│   ├── css/
│   │   ├── variables.css          # ★ Variables CSS corporativas
│   │   ├── common.css
│   │   └── modules/
│   │       └── procesos.css
│   ├── js/
│   │   ├── common.js
│   │   └── modules/
│   │       └── procesos.js
│   ├── img/
│   │   └── logo.png
│   └── favicons/
│       └── favicon.ico
│
├── config/
│   ├── constants.php              # ★ NUEVO: Constantes centralizadas
│   ├── database.php
│   └── env_loader.php
│
├── core/                          # ★ NUEVO: Clases base compartidas
│   ├── BaseModel.php
│   ├── BaseService.php
│   └── Response.php
│
├── sql/
│   ├── ddl.sql
│   └── reset_db.sql
│
├── uploads/
│   └── procesos/
│       └── [año]/[mes]/
│
├── logs/
│   ├── app.log
│   └── bot.log
│
├── roles.json
├── .env
└── README.md
```

**Cambios principales:**
1. **API Centralizada** - Todos los endpoints en `/api/`
2. **Servicios** - Lógica de negocio separada en clases Service
3. **Constantes** - Estados y configuraciones en archivos dedicados
4. **Assets modulares** - CSS/JS organizados por módulo
5. **Prompts externos** - Prompts de IA en archivos .txt editables
6. **Logs centralizados** - Carpeta dedicada para logs

**Pros:**
- ✅ Código más limpio y mantenible
- ✅ Fácil agregar nuevos módulos
- ✅ API reutilizable (futuras integraciones)
- ✅ Mejor separación de responsabilidades

**Contras:**
- ❌ Requiere reescribir parte del código
- ❌ Tiempo de desarrollo moderado
- ❌ Posibles regresiones si no hay tests

---

### Opción C: Arquitectura Moderna con Eventos y Colas
**Esfuerzo:** ⭐⭐⭐⭐⭐ (Alto) | **Tiempo estimado:** 4-8 semanas

**Descripción:** Sistema completo con colas de trabajo, eventos en tiempo real y arquitectura de microservicios.

```
bybot/
├── web/                           # Frontend PHP/Bootstrap
│   ├── admin/
│   │   └── [estructura similar a Opción B]
│   └── api/
│       └── [API REST completa]
│
├── services/                      # ★ Microservicios Python
│   ├── analyzer/                  # Servicio de análisis con IA
│   │   ├── config/
│   │   ├── handlers/
│   │   │   ├── estado_cuenta_handler.py
│   │   │   ├── anexos_handler.py
│   │   │   └── vinculacion_handler.py
│   │   ├── main.py
│   │   └── Dockerfile
│   │
│   ├── pagare_filler/            # Servicio de llenado de pagarés
│   │   ├── config/
│   │   ├── templates/            # ★ Plantillas de pagarés
│   │   │   └── crearcoop/
│   │   │       └── posiciones.json
│   │   ├── main.py
│   │   └── Dockerfile
│   │
│   └── notifier/                 # ★ Servicio de notificaciones
│       ├── websocket_server.py
│       └── Dockerfile
│
├── queue/                        # ★ Sistema de colas
│   └── redis/                    # O RabbitMQ
│       └── docker-compose.yml
│
├── workers/                      # ★ Workers para procesamiento
│   ├── analisis_worker.py
│   ├── pagare_worker.py
│   └── supervisor.conf
│
├── shared/                       # ★ Código compartido
│   ├── python/
│   │   ├── database/
│   │   ├── gemini/
│   │   └── utils/
│   └── php/
│       └── helpers/
│
├── config/
│   ├── prompts/                  # Prompts de IA versionados
│   │   ├── v1/
│   │   └── v2/
│   └── templates/                # Configuraciones de plantillas
│
├── sql/
├── uploads/
├── logs/
├── docker-compose.yml            # ★ Orquestación completa
└── README.md
```

**Características avanzadas:**
1. **Sistema de Colas (Redis/RabbitMQ)** - Procesamiento asíncrono
2. **WebSockets** - Notificaciones en tiempo real
3. **Microservicios** - Servicios independientes y escalables
4. **Docker** - Despliegue containerizado
5. **Plantillas configurables** - JSON para posiciones de campos
6. **Prompts versionados** - Control de versiones de prompts

**Pros:**
- ✅ Arquitectura profesional y escalable
- ✅ Procesamiento eficiente con colas
- ✅ Notificaciones en tiempo real
- ✅ Fácil de escalar horizontalmente
- ✅ Configuración sin código (plantillas JSON)

**Contras:**
- ❌ Mayor complejidad operacional
- ❌ Requiere Redis/RabbitMQ
- ❌ Curva de aprendizaje alta
- ❌ Tiempo de desarrollo largo
- ❌ Más recursos de servidor

---

## 🔄 Comparativa de Opciones

| Aspecto | Opción A | Opción B | Opción C |
|---------|----------|----------|----------|
| **Tiempo** | 1-2 semanas | 2-4 semanas | 4-8 semanas |
| **Complejidad** | Baja | Media | Alta |
| **Escalabilidad** | Limitada | Buena | Excelente |
| **Mantenibilidad** | Media | Alta | Alta |
| **Riesgo** | Bajo | Medio | Alto |
| **Costo** | $ | $$ | $$$ |
| **Futuro** | Limitado | Extensible | Muy extensible |

---

## 💡 Recomendación

### Para tu situación actual: **Opción B (Arquitectura por Capas)**

**Razones:**
1. **Balance perfecto** entre mejora y esfuerzo
2. **Resuelve los problemas principales** sin sobreingeniería
3. **Base sólida** para evolucionar a Opción C si es necesario
4. **Mantenible** con el equipo actual
5. **Tiempo razonable** de implementación

### Roadmap Sugerido

```
Fase 1 (Semana 1-2): Fundamentos
├── Configurar estructura de carpetas
├── Crear constantes y configuración centralizada
├── Implementar clases base (BaseModel, BaseService)
└── Migrar API a estructura centralizada

Fase 2 (Semana 2-3): Módulo Procesos
├── Crear modelos (Proceso, Anexo, DatosIA)
├── Implementar servicios (ProcesoService, etc.)
├── Migrar vistas con separación de lógica
└── Extraer JavaScript a archivos separados

Fase 3 (Semana 3-4): Bot y Refinamiento
├── Externalizar prompts a archivos .txt
├── Crear cliente API para comunicación
├── Implementar clase base para processors
└── Tests manuales y corrección de bugs
```

---

## 📝 Próximos Pasos

1. **Confirmar opción elegida**
2. **Definir prioridades** (qué funcionalidades son críticas)
3. **Crear el DDL actualizado** con mejoras de schema
4. **Comenzar implementación** fase por fase

---

## 🔧 Mejoras Adicionales Recomendadas

Independiente de la opción elegida, se sugiere:

### Base de Datos
- [ ] Agregar tabla `procesos_historial` para auditoría de cambios
- [ ] Agregar columna `version` en datos_ia para control de versiones
- [ ] Índices adicionales para búsquedas frecuentes

### Seguridad
- [ ] Rate limiting en API
- [ ] Tokens de API para el bot
- [ ] Logs de acceso detallados

### UX/UI
- [ ] Dashboard con estadísticas
- [ ] Búsqueda avanzada de procesos
- [ ] Exportación a Excel
- [ ] Procesamiento batch (múltiples archivos)

### Monitoreo
- [ ] Métricas de uso de tokens Gemini
- [ ] Alertas cuando hay errores en análisis
- [ ] Dashboard de estado del bot

---

**Documento generado:** 2026-01-16  
**Proyecto:** bybot → bybot (reestructuración)  
**Versión:** 1.0

