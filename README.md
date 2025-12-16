# ByBot - Sistema de Gestión Jurídica

Sistema especializado para automatizar procesos manuales relacionados con la creación de pagarés y demandas, específicamente el módulo CoreCoop.

## Características

- **Interfaz Administrativa**: Gestión completa de usuarios, logs y procesos
- **Módulo Crear Coop**: Procesamiento de documentos (pagarés, estados de cuenta, anexos)
- **Análisis con IA**: Integración con Gemini para extracción de información de documentos
- **Validación de Datos**: Interfaz para validar y editar datos extraídos por IA
- **Gestión de Estados**: Seguimiento del estado de cada proceso (creado, analizando, analizado, etc.)
- **Visualización de Archivos**: Ver y descargar documentos PDF directamente desde la interfaz

## Estructura del Proyecto

```
by_bot_app/
├── admin/                    # Interfaz administrativa (PHP)
│   ├── controllers/          # Controladores de autenticación
│   ├── models/               # Modelos de datos
│   ├── modules/              # Módulos funcionales
│   │   ├── usuarios/         # Gestión de usuarios
│   │   ├── logs/             # Visualización de logs
│   │   └── crear_coop/       # Módulo principal CoreCoop
│   │       ├── api/          # Endpoints API
│   │       ├── models/       # Modelos del módulo
│   │       └── pages/        # Páginas del módulo
│   ├── pages/                # Páginas principales
│   ├── views/                # Layouts y vistas
│   └── utils/                # Utilidades
├── bot/                      # Bot de análisis con Gemini (Python)
│   ├── config/               # Configuración
│   ├── core/                 # Módulos core
│   │   ├── database.py       # Gestor de BD
│   │   └── gemini_client.py  # Cliente Gemini
│   ├── processors/           # Procesadores
│   │   └── crear_coop_processor.py
│   ├── logs/                 # Logs del bot
│   ├── main.py               # Punto de entrada
│   └── requirements.txt      # Dependencias Python
├── assets/                   # Recursos estáticos
│   ├── css/                  # Estilos
│   ├── images/               # Imágenes (logo, etc.)
│   └── favicons/             # Iconos
├── config/                   # Configuración compartida
├── sql/                      # Scripts SQL
└── uploads/                   # Archivos subidos
```

## Requisitos

- PHP 8.2.28+
- MariaDB 11.8.3+
- Python 3.12+
- Bootstrap 5
- Servidor web (Apache/Nginx)

## Instalación

1. Clonar o copiar el proyecto en el directorio web
2. Crear el archivo `.env` en la raíz del proyecto:
   ```env
   DB_HOST=localhost
   DB_USER=tu_usuario
   DB_PASS=tu_contraseña
   DB_NAME=by_bot_app
   APP_ENV=development
   GEMINI_API_KEY=tu_api_key
   ```
3. Ejecutar el script SQL para crear la base de datos:
   ```bash
   mysql -u usuario -p < sql/ddl.sql
   ```
4. Configurar permisos de escritura en la carpeta `uploads/`
5. Acceder a la aplicación: `http://localhost/by_bot_app/admin/`

## Credenciales por Defecto

- Usuario: `admin`
- Contraseña: `admin123`

**IMPORTANTE**: Cambiar la contraseña después del primer acceso.

## Módulo Crear Coop

### Estados del Proceso

1. **creado**: Proceso recién creado, archivos cargados
2. **analizando_con_ia**: El proceso Python está analizando los documentos
3. **analizado_con_ia**: Análisis completado, datos extraídos
4. **informacion_ia_validada**: Datos validados y editados por el usuario
5. **archivos_extraidos**: Archivos relevantes extraídos de los anexos
6. **llenar_pagare**: Listo para llenar el pagaré

### Archivos Requeridos

- **Pagaré**: PDF del pagaré original (máx. 10MB)
- **Estado de Cuenta**: PDF del estado de cuenta (máx. 10MB)
- **Anexos**: Mínimo 1, máximo 5 archivos PDF (máx. 10MB cada uno)

### Información Extraída por IA

**Del Estado de Cuenta:**
- Fecha causación (última fecha de pago)
- Saldo capital
- Saldo interés
- Saldo mora
- Tasa interés efectiva anual (TEA)

**De los Anexos:**
- **Deudor/Solicitante**: Tipo y número de identificación, nombres, apellidos, fechas, teléfono, dirección, correo
- **Codeudor**: Misma información que deudor

### Validación de Datos

El sistema permite:
- Ver los valores originales extraídos por IA
- Editar campos individuales por sección (Estado de Cuenta, Deudor, Codeudor)
- Ver qué campos fueron editados
- Guardar cambios sin cambiar el estado
- Marcar como "Información IA Validada" cuando todos los datos estén correctos

## Integración con Python (Bot)

El sistema incluye un bot Python (`bot/`) que:
- Consulta procesos en estado "creado" en la base de datos cada 30 segundos
- Utiliza Gemini API para analizar documentos PDF
- Extrae información estructurada de estado de cuenta y anexos
- Guarda los datos en formato JSON en la tabla `crear_coop_datos_ia`
- Actualiza el estado del proceso automáticamente
- Se ejecuta como servicio continuo

### Configuración del Bot

1. Instalar dependencias:
```bash
cd bot/
pip install -r requirements.txt
```

2. Configurar API Key de Gemini en `.env`:
```env
GEMINI_API_KEY=tu_api_key_aqui
```

3. Probar conexión:
```bash
python3 test_connection.py
```

4. Ejecutar bot:
```bash
python3 main.py
# o
./start.sh
```

### Flujo de Trabajo del Bot

```
1. Usuario crea proceso en admin/
   └─> Estado: "creado"
   
2. Bot consulta procesos en estado "creado"
   └─> Cambia estado a: "analizando_con_ia"
   
3. Bot analiza documentos con Gemini:
   ├─> Estado de Cuenta → Extrae datos financieros
   └─> Anexos → Extrae datos de deudor y codeudor
   
4. Bot guarda datos en crear_coop_datos_ia (JSON)
   └─> Cambia estado a: "analizado_con_ia"
```

## Arquitectura de Datos de IA

### Estructura de Tablas

#### `crear_coop_procesos` (Tabla Principal)
- Solo campos esenciales del proceso
- Código, estado, archivos
- Metadata básica (fechas, creado_por, intentos)
- **NO contiene datos extraídos por IA**

#### `crear_coop_datos_ia` (Tabla de Datos de IA)
- Almacena todos los datos de IA en formato JSON
- **Estructura**:
  - `id`: ID único
  - `proceso_id`: Referencia al proceso
  - `datos_originales`: JSON con datos extraídos por IA
  - `datos_validados`: JSON con datos validados/editados (NULL si no se validaron)
  - `metadata`: JSON con tokens, modelo usado, etc.
  - `fecha_analisis`: Cuándo se analizó
  - `fecha_validacion`: Cuándo se validó
  - `validado_por`: Usuario que validó

### Ventajas de esta Arquitectura

1. **Escalabilidad**: Agregar nuevos campos no requiere modificar el esquema
2. **Flexibilidad**: Estructura JSON permite diferentes formatos por proceso
3. **Historial**: Mantiene datos originales y validados separados
4. **Performance**: Tabla principal más ligera y rápida
5. **Mantenibilidad**: Cambios en extracción de IA no afectan estructura

### Estructura del JSON

**`datos_originales`** (lo que extrae la IA):
```json
{
  "estado_cuenta": {
    "fecha_causacion": "2024-08-31",
    "saldo_capital": 3448419.0,
    "saldo_interes": 716732.0,
    "saldo_mora": 311307.0,
    "tasa_interes_efectiva_anual": 15.5
  },
  "deudor": {
    "tipo_identificacion": "CC",
    "numero_identificacion": "1234567890",
    "nombres": "Juan",
    "apellidos": "Pérez",
    ...
  },
  "codeudor": {
    ...
  }
}
```

**`datos_validados`** (lo que el usuario valida/edita):
- Misma estructura que `datos_originales`
- Solo contiene los campos que fueron editados
- Si un campo no está presente, se usa el valor original

**`metadata`** (información del análisis):
```json
{
  "tokens_entrada": 1500,
  "tokens_salida": 800,
  "tokens_total": 2300,
  "modelo": "gemini-2.5-flash-lite",
  "fecha_analisis": "2025-12-13 18:43:16"
}
```

## Seguridad

- Los archivos en `uploads/` no son accesibles directamente por URL
- Se utiliza `descargar_archivo.php` para servir archivos de forma segura
- Validación de roles y permisos mediante `roles.json`
- Contraseñas hasheadas con `password_hash()`
- Autenticación requerida para todas las operaciones

## Colores Corporativos

- **Azul Principal**: #003168
- **Gris Secundario**: #7D7D7D

## Troubleshooting

### El bot no encuentra procesos
- Verificar que hay procesos en estado "creado"
- Verificar conexión a BD: `python3 bot/test_connection.py`

### Error con Gemini API
- Verificar que `GEMINI_API_KEY` está configurada en `.env`
- Verificar que la API key es válida
- Revisar límites de cuota en Google AI Studio

### Archivos no encontrados
- Verificar rutas en `config/settings.py` (bot)
- Verificar que los archivos existen en `uploads/crear_coop/`
- Verificar permisos de lectura

### Logs
- **Bot**: `bot/logs/bot.log`
- **PHP**: Verificar configuración de errores en PHP

## Licencia

Proyecto privado - Uso interno
