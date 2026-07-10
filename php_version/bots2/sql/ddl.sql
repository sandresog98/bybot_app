-- =============================================================================
-- ByBot Consolidado — Esquema de base de datos
-- Motor: MySQL / MariaDB (via XAMPP)
-- Ejecutar: mysql -u root < sql/ddl.sql
-- =============================================================================

CREATE DATABASE IF NOT EXISTS bybot_consolidado
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bybot_consolidado;

-- =============================================================================
-- Tabla: ruaf_consultas
-- Fuente: HTML de SISPRO (RUAF) — ReportViewer ASP.NET
-- Bot: ruaf
-- =============================================================================
CREATE TABLE IF NOT EXISTS ruaf_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    tipo_doc VARCHAR(60),
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    eps_afiliado VARCHAR(200),
    regimen VARCHAR(60),
    estado_afiliacion VARCHAR(60),
    fecha_afiliacion_eps VARCHAR(30),
    novedad VARCHAR(500),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ruaf_numero (numero_id),
    INDEX idx_ruaf_fecha (fecha_consulta),
    INDEX idx_ruaf_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Tabla: fosiga_consultas
-- Fuente: HTML de ADRES (Consulte su EPS) — GridView ASP.NET
-- Bot: fosiga
-- =============================================================================
CREATE TABLE IF NOT EXISTS fosiga_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    url_final VARCHAR(600),
    tipo_identificacion VARCHAR(10),
    nombres VARCHAR(100),
    apellidos VARCHAR(100),
    fecha_nacimiento VARCHAR(20),
    departamento VARCHAR(60),
    municipio VARCHAR(60),
    entidad VARCHAR(200),
    regimen VARCHAR(60),
    fecha_afiliacion_efectiva VARCHAR(30),
    fecha_finalizacion_afiliacion VARCHAR(30),
    tipo_afiliado VARCHAR(60),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fosiga_numero (numero_id),
    INDEX idx_fosiga_fecha (fecha_consulta),
    INDEX idx_fosiga_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Tabla: rues_consultas
-- Fuente: HTML de RUES (Registro Mercantil) — Angular SPA
-- Bot: rues
-- =============================================================================
CREATE TABLE IF NOT EXISTS rues_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    url_final VARCHAR(600),
    razon_social VARCHAR(300),
    nit VARCHAR(30),
    matricula_mercantil VARCHAR(30),
    estado_matricula VARCHAR(60),
    fecha_renovacion VARCHAR(30),
    direccion VARCHAR(400),
    departamento_rues VARCHAR(60),
    municipio_rues VARCHAR(60),
    categoria_matricula VARCHAR(100),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rues_numero (numero_id),
    INDEX idx_rues_fecha (fecha_consulta),
    INDEX idx_rues_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Tabla: simpleco_consultas
-- Fuente: PDF de Simple.co (comprobante de pago)
-- Bot: simpleco
-- =============================================================================
CREATE TABLE IF NOT EXISTS simpleco_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    periodo_mes INT,
    periodo_anio INT,
    tipo_planilla VARCHAR(10),
    numero_planilla VARCHAR(30),
    periodo_cotizacion VARCHAR(10),
    periodo_servicio VARCHAR(10),
    fecha_comprobante VARCHAR(20),
    empresa VARCHAR(200),
    documento_identificacion VARCHAR(30),
    empleado VARCHAR(150),
    cedula VARCHAR(20),
    tipo_admin VARCHAR(80),
    nit_entidad VARCHAR(30),
    codigo_entidad VARCHAR(30),
    nombre_entidad VARCHAR(150),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_simpleco_numero (numero_id),
    INDEX idx_simpleco_fecha (fecha_consulta),
    INDEX idx_simpleco_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Tabla: suaporte_consultas
-- Fuente: PDF de SuAporte (comprobante de pago con desglose por administradora)
-- Bot: suaporte
-- =============================================================================
CREATE TABLE IF NOT EXISTS suaporte_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    url_final VARCHAR(600),
    tipo_planilla VARCHAR(10),
    numero_planilla VARCHAR(30),
    periodo_cotizacion VARCHAR(10),
    periodo_servicio VARCHAR(10),
    fecha_comprobante VARCHAR(20),
    empresa VARCHAR(200),
    documento_identificacion VARCHAR(30),
    empleado VARCHAR(150),
    cedula VARCHAR(20),
    tipo_admin_arl VARCHAR(20),
    nit_arl VARCHAR(30),
    codigo_arl VARCHAR(30),
    nombre_arl VARCHAR(100),
    tipo_admin_eps VARCHAR(20),
    nit_eps VARCHAR(30),
    codigo_eps VARCHAR(30),
    nombre_eps VARCHAR(100),
    tipo_admin_afp VARCHAR(20),
    nit_afp VARCHAR(30),
    codigo_afp VARCHAR(30),
    nombre_afp VARCHAR(100),
    tipo_admin_ccf VARCHAR(20),
    nit_ccf VARCHAR(30),
    codigo_ccf VARCHAR(30),
    nombre_ccf VARCHAR(100),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suaporte_numero (numero_id),
    INDEX idx_suaporte_fecha (fecha_consulta),
    INDEX idx_suaporte_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Tabla: aportesenlinea_consultas
-- Fuente: PDF de Aportes en Linea (certificado de aportes)
-- Bot: aportesenlinea
-- =============================================================================
CREATE TABLE IF NOT EXISTS aportesenlinea_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    nombre_certifica VARCHAR(200),
    cedula_certifica VARCHAR(20),
    aportes VARCHAR(300),
    aportante VARCHAR(200),
    nit_aportante VARCHAR(30),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aportesl_numero (numero_id),
    INDEX idx_aportesl_fecha (fecha_consulta),
    INDEX idx_aportesl_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Tabla: asopagos_consultas
-- Fuente: PDF de ASOPAGOS (certificado)
-- Bot: asopagos (PENDIENTE — sin bot.py)
-- =============================================================================
CREATE TABLE IF NOT EXISTS asopagos_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500),
    archivo_original VARCHAR(600),
    metadata_json JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_asopagos_numero (numero_id),
    INDEX idx_asopagos_fecha (fecha_consulta),
    INDEX idx_asopagos_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Vista consolidada: consultas_consolidadas
-- UNION ALL de todas las tablas de bots.
-- Una misma cedula puede aparecer en varios bots (JOIN natural por numero_id).
-- =============================================================================
CREATE OR REPLACE VIEW consultas_consolidadas AS
SELECT
    'ruaf'              AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    eps_afiliado        AS entidad_1,
    regimen             AS categoria_1,
    estado_afiliacion   AS estado_detalle,
    NULL                AS razon_social,
    NULL                AS nit,
    NULL                AS matricula,
    NULL                AS empresa,
    NULL                AS empleado,
    NULL                AS periodo_cotizacion,
    metadata_json
FROM ruaf_consultas

UNION ALL

SELECT
    'fosiga'            AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    entidad             AS entidad_1,
    regimen             AS categoria_1,
    estado              AS estado_detalle,
    NULL                AS razon_social,
    NULL                AS nit,
    NULL                AS matricula,
    NULL                AS empresa,
    CONCAT(nombres, ' ', apellidos) AS empleado,
    NULL                AS periodo_cotizacion,
    metadata_json
FROM fosiga_consultas

UNION ALL

SELECT
    'rues'              AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    NULL                AS entidad_1,
    NULL                AS categoria_1,
    estado_matricula    AS estado_detalle,
    razon_social,
    nit,
    matricula_mercantil AS matricula,
    NULL                AS empresa,
    NULL                AS empleado,
    NULL                AS periodo_cotizacion,
    metadata_json
FROM rues_consultas

UNION ALL

SELECT
    'simpleco'          AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    nombre_entidad      AS entidad_1,
    tipo_admin          AS categoria_1,
    NULL                AS estado_detalle,
    NULL                AS razon_social,
    nit_entidad         AS nit,
    NULL                AS matricula,
    empresa,
    empleado,
    periodo_cotizacion,
    metadata_json
FROM simpleco_consultas

UNION ALL

SELECT
    'suaporte'          AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    nombre_eps          AS entidad_1,
    tipo_admin_eps      AS categoria_1,
    NULL                AS estado_detalle,
    NULL                AS razon_social,
    nit_eps             AS nit,
    NULL                AS matricula,
    empresa,
    empleado,
    periodo_cotizacion,
    metadata_json
FROM suaporte_consultas

UNION ALL

SELECT
    'aportesenlinea'    AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    aportante           AS entidad_1,
    aportes             AS categoria_1,
    NULL                AS estado_detalle,
    NULL                AS razon_social,
    nit_aportante       AS nit,
    NULL                AS matricula,
    aportante           AS empresa,
    nombre_certifica    AS empleado,
    NULL                AS periodo_cotizacion,
    metadata_json
FROM aportesenlinea_consultas

UNION ALL

SELECT
    'asopagos'          AS fuente,
    id,
    numero_id,
    fecha_consulta,
    estado,
    motivo,
    archivo_original,
    NULL                AS entidad_1,
    NULL                AS categoria_1,
    NULL                AS estado_detalle,
    NULL                AS razon_social,
    NULL                AS nit,
    NULL                AS matricula,
    NULL                AS empresa,
    NULL                AS empleado,
    NULL                AS periodo_cotizacion,
    metadata_json
FROM asopagos_consultas;
