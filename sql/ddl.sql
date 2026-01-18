-- =============================================
-- DDL - BYBOT v2.0
-- Base de datos: MariaDB 11.8.3
-- Sin llaves foráneas (relaciones a nivel de aplicación)
-- Fecha: 2026-01-16
-- =============================================

CREATE DATABASE IF NOT EXISTS bybot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bybot;

-- =============================================
-- TABLA: control_usuarios
-- Usuarios del sistema
-- =============================================
CREATE OR REPLACE TABLE control_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    rol VARCHAR(30) NOT NULL DEFAULT 'operador',
    -- Valores rol: admin, supervisor, operador
    estado_activo TINYINT(1) DEFAULT 1,
    -- 1=activo, 0=inactivo
    ultimo_acceso TIMESTAMP NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_usuario (usuario),
    INDEX idx_rol (rol),
    INDEX idx_estado (estado_activo)
);

-- Trigger para fecha_actualizacion
DROP TRIGGER IF EXISTS control_usuarios_bu;
CREATE TRIGGER control_usuarios_bu BEFORE UPDATE ON control_usuarios
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- Usuario admin por defecto (password: admin123)
INSERT IGNORE INTO control_usuarios (usuario, password, nombre_completo, email, rol)
VALUES ('admin', '$2y$10$BPGSMwk9u8YeZI0U2gBJE.X7XqmESvbPBiYMCbGqjhNfsVLLGlPtK', 
        'Administrador ByBot', 'admin@bybot.com', 'admin');

-- =============================================
-- TABLA: control_logs
-- Logs de auditoría del sistema
-- =============================================
CREATE OR REPLACE TABLE control_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accion VARCHAR(50) NOT NULL,
    -- Valores: login, logout, crear, actualizar, eliminar, validar, encolar, etc.
    modulo VARCHAR(50) NOT NULL,
    entidad_tipo VARCHAR(50) NULL,
    entidad_id INT NULL,
    detalle TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    datos_anteriores JSON,
    datos_nuevos JSON,
    nivel VARCHAR(20) DEFAULT 'info',
    -- Valores: debug, info, warning, error, critical
    INDEX idx_accion (accion),
    INDEX idx_modulo (modulo),
    INDEX idx_usuario (id_usuario),
    INDEX idx_timestamp (timestamp),
    INDEX idx_entidad (entidad_tipo, entidad_id)
);

-- =============================================
-- TABLA: procesos
-- Tabla principal de procesos de cobranza
-- =============================================
CREATE OR REPLACE TABLE procesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    tipo VARCHAR(30) DEFAULT 'cobranza',
    -- Valores tipo: cobranza, demanda, otro
    estado VARCHAR(30) DEFAULT 'creado',
    -- Valores estado: creado, en_cola_analisis, analizando, analizado,
    --                 validado, en_cola_llenado, llenando, completado,
    --                 error_analisis, error_llenado, cancelado
    prioridad INT DEFAULT 5,
    -- 1=máxima, 10=mínima
    
    -- Archivos principales
    archivo_pagare_original VARCHAR(255) NULL,
    archivo_estado_cuenta VARCHAR(255) NULL,
    archivo_pagare_llenado VARCHAR(255) NULL,
    
    -- Metadata
    creado_por INT NULL,
    asignado_a INT NULL,
    intentos_analisis INT DEFAULT 0,
    intentos_llenado INT DEFAULT 0,
    max_intentos INT DEFAULT 3,
    
    -- Control de procesamiento
    job_id_analisis VARCHAR(100) NULL,
    job_id_llenado VARCHAR(100) NULL,
    
    -- Timestamps
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL DEFAULT NULL,
    fecha_analisis TIMESTAMP NULL,
    fecha_validacion TIMESTAMP NULL,
    fecha_llenado TIMESTAMP NULL,
    fecha_completado TIMESTAMP NULL,
    
    -- Notas y observaciones
    notas TEXT,
    
    INDEX idx_codigo (codigo),
    INDEX idx_estado (estado),
    INDEX idx_tipo (tipo),
    INDEX idx_prioridad (prioridad),
    INDEX idx_creado_por (creado_por),
    INDEX idx_asignado_a (asignado_a),
    INDEX idx_fecha_creacion (fecha_creacion)
);

DROP TRIGGER IF EXISTS procesos_bu;
CREATE TRIGGER procesos_bu BEFORE UPDATE ON procesos
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- =============================================
-- TABLA: procesos_anexos
-- Archivos anexos de cada proceso
-- =============================================
CREATE OR REPLACE TABLE procesos_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo VARCHAR(50) DEFAULT 'anexo',
    -- Valores tipo: anexo, solicitud_deudor, solicitud_codeudor, otro
    tamanio_bytes INT NULL,
    mime_type VARCHAR(100),
    orden INT DEFAULT 0,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_proceso (proceso_id),
    INDEX idx_tipo (tipo)
);

-- =============================================
-- TABLA: procesos_datos_ia
-- Datos extraídos por IA (JSON flexible)
-- =============================================
CREATE OR REPLACE TABLE procesos_datos_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    version INT DEFAULT 1,
    
    -- Datos extraídos
    datos_originales JSON NOT NULL COMMENT 'Datos extraídos originalmente por la IA',
    datos_validados JSON NULL COMMENT 'Datos validados/editados por el usuario',
    
    -- Metadata del análisis
    metadata JSON NULL COMMENT 'Contiene: tokens_entrada, tokens_salida, tokens_total, modelo, prompts_usados, tiempos',
    
    -- Control
    fecha_analisis TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_validacion TIMESTAMP NULL,
    validado_por INT NULL,
    
    INDEX idx_proceso (proceso_id),
    INDEX idx_version (version),
    INDEX idx_fecha_analisis (fecha_analisis)
);

-- =============================================
-- TABLA: procesos_historial
-- Historial de cambios de cada proceso
-- =============================================
CREATE OR REPLACE TABLE procesos_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    usuario_id INT NULL,
    
    accion VARCHAR(50) NOT NULL,
    -- Valores: creado, estado_cambiado, archivos_subidos, analizado,
    --          datos_editados, validado, llenado, error, nota_agregada, cancelado, reintentado
    
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NULL,
    
    descripcion TEXT,
    datos_cambio JSON NULL COMMENT 'Detalles específicos del cambio',
    
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_proceso (proceso_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha (fecha)
);

-- =============================================
-- TABLA: colas_trabajos
-- Registro de trabajos en cola (auditoría)
-- =============================================
CREATE OR REPLACE TABLE colas_trabajos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(100) UNIQUE NOT NULL,
    cola VARCHAR(50) NOT NULL,
    -- Valores cola: bybot:analyze, bybot:fill, bybot:notify
    
    proceso_id INT NULL,
    tipo_trabajo VARCHAR(50) NOT NULL,
    -- Valores: analizar_documentos, llenar_pagare, notificar
    
    estado VARCHAR(30) DEFAULT 'pendiente',
    -- Valores: pendiente, procesando, completado, fallido, cancelado
    
    payload JSON NOT NULL,
    resultado JSON NULL,
    error_mensaje TEXT NULL,
    
    intentos INT DEFAULT 0,
    max_intentos INT DEFAULT 3,
    
    prioridad INT DEFAULT 5,
    
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_inicio TIMESTAMP NULL,
    fecha_fin TIMESTAMP NULL,
    duracion_ms INT NULL,
    
    worker_id VARCHAR(100) NULL,
    
    INDEX idx_job_id (job_id),
    INDEX idx_cola (cola),
    INDEX idx_proceso (proceso_id),
    INDEX idx_estado (estado),
    INDEX idx_fecha_creacion (fecha_creacion)
);

-- =============================================
-- TABLA: configuracion
-- Configuraciones del sistema
-- =============================================
CREATE OR REPLACE TABLE configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    tipo VARCHAR(20) DEFAULT 'string',
    -- Valores tipo: string, int, float, bool, json
    categoria VARCHAR(50) DEFAULT 'general',
    descripcion TEXT,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_clave (clave),
    INDEX idx_categoria (categoria)
);

-- Configuraciones iniciales
INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion) VALUES
('gemini_model', 'gemini-1.5-flash', 'string', 'ia', 'Modelo de Gemini a utilizar'),
('gemini_temperature', '0.1', 'float', 'ia', 'Temperatura para respuestas de IA'),
('gemini_max_tokens', '4000', 'int', 'ia', 'Máximo de tokens de salida'),
('max_file_size_image', '5242880', 'int', 'archivos', 'Tamaño máximo imágenes (5MB)'),
('max_file_size_pdf', '10485760', 'int', 'archivos', 'Tamaño máximo PDFs (10MB)'),
('max_intentos_analisis', '3', 'int', 'procesos', 'Intentos máximos de análisis'),
('max_intentos_llenado', '3', 'int', 'procesos', 'Intentos máximos de llenado'),
('websocket_enabled', 'true', 'bool', 'notificaciones', 'WebSockets habilitados'),
('prompt_version_estado_cuenta', 'v1', 'string', 'prompts', 'Versión prompt estado cuenta'),
('prompt_version_anexos', 'v1', 'string', 'prompts', 'Versión prompt anexos'),
('prompt_version_vinculacion', 'v1', 'string', 'prompts', 'Versión prompt vinculación')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- =============================================
-- TABLA: prompts
-- Prompts de IA almacenados
-- =============================================
CREATE OR REPLACE TABLE prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    -- Valores tipo: estado_cuenta, anexos, vinculacion
    contenido TEXT NOT NULL,
    activo TINYINT(1) DEFAULT 0,
    notas TEXT,
    creado_por INT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL,
    
    UNIQUE KEY uk_nombre_version (nombre, version),
    INDEX idx_tipo (tipo),
    INDEX idx_activo (activo)
);

DROP TRIGGER IF EXISTS prompts_bu;
CREATE TRIGGER prompts_bu BEFORE UPDATE ON prompts
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- =============================================
-- TABLA: plantillas_pagare
-- Plantillas para llenado de pagarés
-- =============================================
CREATE OR REPLACE TABLE plantillas_pagare (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    descripcion TEXT,
    configuracion JSON NOT NULL COMMENT 'Contiene: campos con posiciones, formatos, etc.',
    activa TINYINT(1) DEFAULT 0,
    creado_por INT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL,
    
    UNIQUE KEY uk_nombre_version (nombre, version),
    INDEX idx_activa (activa)
);

DROP TRIGGER IF EXISTS plantillas_pagare_bu;
CREATE TRIGGER plantillas_pagare_bu BEFORE UPDATE ON plantillas_pagare
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- Insertar plantilla por defecto para CrearCoop
INSERT INTO plantillas_pagare (nombre, version, descripcion, configuracion, activa) VALUES
('CrearCoop', 'v1', 'Plantilla para pagarés de CrearCoop', '{
    "nombre": "CrearCoop Pagaré v1",
    "version": "1.0",
    "paginas": 2,
    "campos": {
        "capital": {
            "x_percent": 0.42,
            "y_percent": 0.18,
            "page": 0,
            "fontsize": 11,
            "format": "currency"
        },
        "interes_plazo": {
            "x_percent": 0.42,
            "y_percent": 0.21,
            "page": 0,
            "fontsize": 11,
            "format": "currency"
        },
        "tasa_interes": {
            "x_percent": 0.42,
            "y_percent": 0.24,
            "page": 0,
            "fontsize": 11,
            "format": "percent"
        },
        "fecha_vencimiento": {
            "x_percent": 0.42,
            "y_percent": 0.27,
            "page": 0,
            "fontsize": 11,
            "format": "date"
        },
        "deudor_nombre": {
            "x_percent": 0.30,
            "y_percent": 0.32,
            "page": 0,
            "fontsize": 11,
            "format": "text"
        },
        "codeudor_nombre": {
            "x_percent": 0.30,
            "y_percent": 0.36,
            "page": 0,
            "fontsize": 11,
            "format": "text"
        },
        "deudor_cedula": {
            "x_percent": 0.30,
            "y_percent": 0.40,
            "page": 0,
            "fontsize": 11,
            "format": "text"
        },
        "codeudor_cedula": {
            "x_percent": 0.30,
            "y_percent": 0.44,
            "page": 0,
            "fontsize": 11,
            "format": "text"
        },
        "endoso": {
            "x_percent": 0.20,
            "y_percent": 0.85,
            "page": 1,
            "fontsize": 10,
            "format": "multiline",
            "texto_fijo": "Endoso en procuración a favor de:\\nAndrés Bello Arias T.P. 378.676"
        }
    }
}', 1)
ON DUPLICATE KEY UPDATE configuracion = VALUES(configuracion);

-- =============================================
-- VISTAS ÚTILES
-- =============================================

-- Vista: Resumen de procesos por estado
CREATE OR REPLACE VIEW v_procesos_por_estado AS
SELECT 
    estado,
    COUNT(*) as cantidad,
    DATE(fecha_creacion) as fecha
FROM procesos
GROUP BY estado, DATE(fecha_creacion)
ORDER BY fecha DESC, estado;

-- Vista: Procesos con datos completos
CREATE OR REPLACE VIEW v_procesos_detalle AS
SELECT 
    p.*,
    u_creador.nombre_completo as creador_nombre,
    u_asignado.nombre_completo as asignado_nombre,
    (SELECT COUNT(*) FROM procesos_anexos WHERE proceso_id = p.id) as total_anexos,
    (SELECT MAX(fecha) FROM procesos_historial WHERE proceso_id = p.id) as ultima_actividad
FROM procesos p
LEFT JOIN control_usuarios u_creador ON p.creado_por = u_creador.id
LEFT JOIN control_usuarios u_asignado ON p.asignado_a = u_asignado.id;

-- Vista: Estadísticas de colas
CREATE OR REPLACE VIEW v_colas_estadisticas AS
SELECT 
    cola,
    estado,
    COUNT(*) as cantidad,
    AVG(duracion_ms) as duracion_promedio_ms,
    MAX(fecha_creacion) as ultimo_trabajo
FROM colas_trabajos
GROUP BY cola, estado;

