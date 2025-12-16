-- =============================================
-- DDL - BYBOT APP
-- Base de datos: 11.8.3-MariaDB-log
-- Sin llaves foráneas (relaciones a nivel de aplicación)
-- =============================================

CREATE DATABASE IF NOT EXISTS by_bot_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE by_bot_app;

-- =============================================
-- TABLA DE USUARIOS DEL SISTEMA
-- =============================================
CREATE TABLE IF NOT EXISTS control_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    rol VARCHAR(30) NOT NULL DEFAULT 'operador',
    estado_activo BOOLEAN DEFAULT TRUE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_usuario (usuario),
    INDEX idx_rol (rol),
    INDEX idx_estado (estado_activo)
);

-- Triggers para control_usuarios
DROP TRIGGER IF EXISTS control_usuarios_bu;
CREATE TRIGGER control_usuarios_bu BEFORE UPDATE ON control_usuarios
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- Usuario administrador por defecto (password: admin123)
INSERT IGNORE INTO control_usuarios (usuario, password, nombre_completo, email, rol)
VALUES ('admin', '$2y$10$BPGSMwk9u8YeZI0U2gBJE.X7XqmESvbPBiYMCbGqjhNfsVLLGlPtK', 'Administrador ByBot', 'admin@bybot.com', 'admin');

-- =============================================
-- TABLA DE LOGS DEL SISTEMA
-- =============================================
CREATE TABLE IF NOT EXISTS control_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accion VARCHAR(20) NOT NULL,
    modulo VARCHAR(50) NOT NULL,
    detalle TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    datos_anteriores LONGTEXT,
    datos_nuevos LONGTEXT,
    nivel VARCHAR(20) DEFAULT 'info',
    INDEX idx_accion (accion),
    INDEX idx_modulo (modulo),
    INDEX idx_usuario (id_usuario),
    INDEX idx_timestamp (timestamp)
);

-- =============================================
-- MÓDULO CREAR COOP - PROCESOS
-- =============================================
CREATE TABLE IF NOT EXISTS crear_coop_procesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    estado VARCHAR(30) DEFAULT 'creado' COMMENT 'Estados: creado, analizando_con_ia, analizado_con_ia, informacion_ia_validada, archivos_extraidos, llenar_pagare, error_analisis',
    -- Archivos generados
    archivo_pagare_original VARCHAR(255) NULL,
    archivo_estado_cuenta VARCHAR(255) NULL,
    archivo_anexos_original VARCHAR(255) NULL,
    archivo_anexos_extraidos VARCHAR(255) NULL,
    archivo_pagare_llenado VARCHAR(255) NULL,
    -- Metadata
    creado_por INT NULL,
    intentos_analisis INT DEFAULT 0,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_codigo (codigo),
    INDEX idx_estado (estado),
    INDEX idx_creado_por (creado_por),
    INDEX idx_fecha_creacion (fecha_creacion)
);

-- Triggers para crear_coop_procesos
DROP TRIGGER IF EXISTS crear_coop_procesos_bu;
CREATE TRIGGER crear_coop_procesos_bu BEFORE UPDATE ON crear_coop_procesos
FOR EACH ROW SET NEW.fecha_actualizacion = CURRENT_TIMESTAMP;

-- =============================================
-- MÓDULO CREAR COOP - ARCHIVOS ANEXOS
-- =============================================
CREATE TABLE IF NOT EXISTS crear_coop_anexos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tipo VARCHAR(20) DEFAULT 'anexo_original',
    tamanio_bytes INT NULL,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_proceso (proceso_id),
    INDEX idx_tipo (tipo)
);

-- =============================================
-- MÓDULO CREAR COOP - DATOS DE IA
-- =============================================
-- Tabla separada para datos extraídos por IA usando JSON
-- Esto permite escalabilidad sin modificar la estructura de la tabla principal
CREATE TABLE IF NOT EXISTS crear_coop_datos_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    datos_originales JSON NOT NULL COMMENT 'Datos extraídos originalmente por la IA',
    datos_validados JSON NULL COMMENT 'Datos validados/editados por el usuario (NULL si no se han validado)',
    metadata JSON NULL COMMENT 'Metadatos del análisis: tokens, modelo usado, etc.',
    fecha_analisis TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_validacion TIMESTAMP NULL DEFAULT NULL,
    validado_por INT NULL COMMENT 'Usuario que validó los datos',
    INDEX idx_proceso (proceso_id),
    INDEX idx_fecha_analisis (fecha_analisis),
    INDEX idx_fecha_validacion (fecha_validacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

