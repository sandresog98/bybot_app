-- =============================================================================
-- ByBot Consolidado — Esquema de base de datos unificado
-- Motor: MariaDB (XAMPP) / MySQL 8
-- Ejecutar:  mysql -u root < sql/ddl.sql
--            (/opt/lampp/bin/mysql -u root < sql/ddl.sql)
--
-- Normas php_rules.md:
--   * Llaves foráneas reales (ON DELETE / ON UPDATE explícitos)
--   * Sin ENUM -> VARCHAR + comentario con valores válidos
--   * Prefijo por módulo (casos_*, control_*, app_*, bots_*_consultas)
--   * utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS bybot_consolidado
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE bybot_consolidado;

SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- Módulo: control (autenticación, auditoría, sesiones)
-- =============================================================================

CREATE TABLE IF NOT EXISTS control_usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,                       -- bcrypt hash
    nombre_completo VARCHAR(100) NOT NULL,
    email VARCHAR(100) NULL,
    rol VARCHAR(30) NOT NULL DEFAULT 'operador',           -- valores: admin, supervisor, operador
    clave_un_solo_uso TINYINT(1) NOT NULL DEFAULT 1,      -- 1=forzar cambio al primer login
    estado_activo TINYINT(1) NOT NULL DEFAULT 1,           -- 1=activo, 0=inactivo
    ultimo_acceso DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ctrl_usu_rol (rol),
    INDEX idx_ctrl_usu_estado (estado_activo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS control_sesiones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,                    -- token de sesión (random)
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ses_usu FOREIGN KEY (usuario_id)
        REFERENCES control_usuarios(id) ON DELETE CASCADE,
    INDEX idx_ses_token (token),
    INDEX idx_ses_usuario (usuario_id),
    INDEX idx_ses_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS control_api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,                          -- etiqueta del token (móvil, integración, ...)
    token VARCHAR(128) NOT NULL UNIQUE,
    scopes VARCHAR(255) NULL,                              -- csv de scopes; null = todos
    expires_at DATETIME NULL,                              -- null = sin expiración
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_apitok_usu FOREIGN KEY (usuario_id)
        REFERENCES control_usuarios(id) ON DELETE CASCADE,
    INDEX idx_apitok_token (token)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS control_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accion VARCHAR(50) NOT NULL,                           -- valores: login, logout, crear, actualizar, eliminar, validar, encolar, ...
    modulo VARCHAR(50) NOT NULL,
    entidad_tipo VARCHAR(50) NULL,
    entidad_id INT NULL,
    detalle TEXT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    nivel VARCHAR(20) NOT NULL DEFAULT 'info',             -- valores: debug, info, warning, error, critical
    CONSTRAINT fk_log_usu FOREIGN KEY (usuario_id)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    INDEX idx_log_accion (accion),
    INDEX idx_log_modulo (modulo),
    INDEX idx_log_usuario (usuario_id),
    INDEX idx_log_timestamp (timestamp),
    INDEX idx_log_entidad (entidad_tipo, entidad_id)
) ENGINE=InnoDB;

-- =============================================================================
-- Módulo: app (colas, configuración, prompts IA) — transversal
-- =============================================================================

CREATE TABLE IF NOT EXISTS app_configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    tipo VARCHAR(20) NOT NULL DEFAULT 'string',            -- valores: string, int, float, bool, json
    categoria VARCHAR(50) NOT NULL DEFAULT 'general',
    descripcion TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_appcfg_clave (clave),
    INDEX idx_appcfg_categoria (categoria)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    version VARCHAR(20) NOT NULL,
    tipo VARCHAR(50) NOT NULL,                             -- valores: estado_cuenta, anexos, vinculacion, ...
    contenido MEDIUMTEXT NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    notas TEXT NULL,
    creado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prompt_creadopor FOREIGN KEY (creado_por)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_prompt_nombre_version (nombre, version),
    INDEX idx_prompt_tipo (tipo),
    INDEX idx_prompt_activo (activo)
) ENGINE=InnoDB;

-- =============================================================================
-- Módulo: procesos (carga de archivos + análisis IA) — Fases 1 y 2
-- =============================================================================

CREATE TABLE IF NOT EXISTS procesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    tipo VARCHAR(30) NOT NULL DEFAULT 'cobranza',          -- valores: cobranza, demanda, otro
    estado VARCHAR(30) NOT NULL DEFAULT 'creado',          -- valores: creado, archivos_cargados, en_analisis, analizado, validado, completado, error, cancelado
    prioridad INT NOT NULL DEFAULT 5,                      -- 1=máxima, 10=mínima
    creado_por INT NULL,
    asignado_a INT NULL,
    intentos_analisis INT NOT NULL DEFAULT 0,
    max_intentos INT NOT NULL DEFAULT 3,
    notas TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_analisis DATETIME NULL,
    fecha_validacion DATETIME NULL,
    fecha_completado DATETIME NULL,
    CONSTRAINT fk_proc_creadopor FOREIGN KEY (creado_por)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_proc_asignado FOREIGN KEY (asignado_a)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    INDEX idx_proc_codigo (codigo),
    INDEX idx_proc_estado (estado),
    INDEX idx_proc_tipo (tipo),
    INDEX idx_proc_prioridad (prioridad),
    INDEX idx_proc_creado (creado_por),
    INDEX idx_proc_fecha_creado (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS procesos_archivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,                 -- nombre con que se guarda en storage
    ruta_storage VARCHAR(500) NOT NULL,                   -- ruta relativa dentro del driver
    driver VARCHAR(20) NOT NULL DEFAULT 'local',          -- valores: local, remote
    tipo VARCHAR(50) NOT NULL DEFAULT 'anexo',            -- valores: estado_cuenta, anexo, solicitud_deudor, solicitud_codeudor, identificacion, otro
    mime_type VARCHAR(100) NULL,
    tamanio_bytes INT NULL,
    hash_sha256 CHAR(64) NULL,
    orden INT NOT NULL DEFAULT 0,
    subido_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_arch_proc FOREIGN KEY (proceso_id)
        REFERENCES procesos(id) ON DELETE CASCADE,
    CONSTRAINT fk_arch_subido FOREIGN KEY (subido_por)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    INDEX idx_arch_proceso (proceso_id),
    INDEX idx_arch_tipo (tipo),
    INDEX idx_arch_hash (hash_sha256)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS procesos_datos_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    datos_originales JSON NOT NULL COMMENT 'Datos crudos extraídos por la IA',
    datos_validados JSON NULL COMMENT 'Datos editados/aprobados por el operador',
    metadata JSON NULL COMMENT 'tokens_entrada, tokens_salida, modelo, prompts_usados, tiempos',
    modelo VARCHAR(50) NULL,
    tokens_total INT NULL,
    fecha_analisis TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    validado_por INT NULL,
    fecha_validacion DATETIME NULL,
    CONSTRAINT fk_diag_proc FOREIGN KEY (proceso_id)
        REFERENCES procesos(id) ON DELETE CASCADE,
    CONSTRAINT fk_diag_validador FOREIGN KEY (validado_por)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    INDEX idx_diag_proceso (proceso_id),
    INDEX idx_diag_version (version),
    INDEX idx_diag_fecha (fecha_analisis)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS procesos_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    usuario_id INT NULL,
    accion VARCHAR(50) NOT NULL,                           -- valores: creado, estado_cambiado, archivos_subidos, analizado, datos_editados, validado, error, nota_agregada, cancelado, reintentado
    estado_anterior VARCHAR(30) NULL,
    estado_nuevo VARCHAR(30) NULL,
    descripcion TEXT NULL,
    datos_cambio JSON NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hist_proc FOREIGN KEY (proceso_id)
        REFERENCES procesos(id) ON DELETE CASCADE,
    CONSTRAINT fk_hist_usu FOREIGN KEY (usuario_id)
        REFERENCES control_usuarios(id) ON DELETE SET NULL,
    INDEX idx_hist_proceso (proceso_id),
    INDEX idx_hist_usuario (usuario_id),
    INDEX idx_hist_accion (accion),
    INDEX idx_hist_fecha (fecha)
) ENGINE=InnoDB;

-- =============================================================================
-- Módulo: bots (tablas de consultas de bots2 — esquema ya existente, mantenido)
-- No se integran con la app en este plan, pero se conservan en la misma BD.
-- =============================================================================

CREATE TABLE IF NOT EXISTS ruaf_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    tipo_doc VARCHAR(60) NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    eps_afiliado VARCHAR(200) NULL,
    regimen VARCHAR(60) NULL,
    estado_afiliacion VARCHAR(60) NULL,
    fecha_afiliacion_eps VARCHAR(30) NULL,
    novedad VARCHAR(500) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ruaf_numero (numero_id),
    INDEX idx_ruaf_fecha (fecha_consulta),
    INDEX idx_ruaf_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fosiga_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    url_final VARCHAR(600) NULL,
    tipo_identificacion VARCHAR(10) NULL,
    nombres VARCHAR(100) NULL,
    apellidos VARCHAR(100) NULL,
    fecha_nacimiento VARCHAR(20) NULL,
    departamento VARCHAR(60) NULL,
    municipio VARCHAR(60) NULL,
    entidad VARCHAR(200) NULL,
    regimen VARCHAR(60) NULL,
    fecha_afiliacion_efectiva VARCHAR(30) NULL,
    fecha_finalizacion_afiliacion VARCHAR(30) NULL,
    tipo_afiliado VARCHAR(60) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fosiga_numero (numero_id),
    INDEX idx_fosiga_fecha (fecha_consulta),
    INDEX idx_fosiga_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rues_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    url_final VARCHAR(600) NULL,
    razon_social VARCHAR(300) NULL,
    nit VARCHAR(30) NULL,
    matricula_mercantil VARCHAR(30) NULL,
    estado_matricula VARCHAR(60) NULL,
    fecha_renovacion VARCHAR(30) NULL,
    direccion VARCHAR(400) NULL,
    departamento_rues VARCHAR(60) NULL,
    municipio_rues VARCHAR(60) NULL,
    categoria_matricula VARCHAR(100) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_rues_numero (numero_id),
    INDEX idx_rues_fecha (fecha_consulta),
    INDEX idx_rues_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS simpleco_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    periodo_mes INT NULL,
    periodo_anio INT NULL,
    tipo_planilla VARCHAR(10) NULL,
    numero_planilla VARCHAR(30) NULL,
    periodo_cotizacion VARCHAR(10) NULL,
    periodo_servicio VARCHAR(10) NULL,
    fecha_comprobante VARCHAR(20) NULL,
    empresa VARCHAR(200) NULL,
    documento_identificacion VARCHAR(30) NULL,
    empleado VARCHAR(150) NULL,
    cedula VARCHAR(20) NULL,
    tipo_admin VARCHAR(80) NULL,
    nit_entidad VARCHAR(30) NULL,
    codigo_entidad VARCHAR(30) NULL,
    nombre_entidad VARCHAR(150) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_simpleco_numero (numero_id),
    INDEX idx_simpleco_fecha (fecha_consulta),
    INDEX idx_simpleco_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suaporte_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    url_final VARCHAR(600) NULL,
    tipo_planilla VARCHAR(10) NULL,
    numero_planilla VARCHAR(30) NULL,
    periodo_cotizacion VARCHAR(10) NULL,
    periodo_servicio VARCHAR(10) NULL,
    fecha_comprobante VARCHAR(20) NULL,
    empresa VARCHAR(200) NULL,
    documento_identificacion VARCHAR(30) NULL,
    empleado VARCHAR(150) NULL,
    cedula VARCHAR(20) NULL,
    tipo_admin_arl VARCHAR(20) NULL,
    nit_arl VARCHAR(30) NULL,
    codigo_arl VARCHAR(30) NULL,
    nombre_arl VARCHAR(100) NULL,
    tipo_admin_eps VARCHAR(20) NULL,
    nit_eps VARCHAR(30) NULL,
    codigo_eps VARCHAR(30) NULL,
    nombre_eps VARCHAR(100) NULL,
    tipo_admin_afp VARCHAR(20) NULL,
    nit_afp VARCHAR(30) NULL,
    codigo_afp VARCHAR(30) NULL,
    nombre_afp VARCHAR(100) NULL,
    tipo_admin_ccf VARCHAR(20) NULL,
    nit_ccf VARCHAR(30) NULL,
    codigo_ccf VARCHAR(30) NULL,
    nombre_ccf VARCHAR(100) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_suaporte_numero (numero_id),
    INDEX idx_suaporte_fecha (fecha_consulta),
    INDEX idx_suaporte_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aportesenlinea_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    nombre_certifica VARCHAR(200) NULL,
    cedula_certifica VARCHAR(20) NULL,
    aportes VARCHAR(300) NULL,
    aportante VARCHAR(200) NULL,
    nit_aportante VARCHAR(30) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aportesl_numero (numero_id),
    INDEX idx_aportesl_fecha (fecha_consulta),
    INDEX idx_aportesl_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asopagos_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_id VARCHAR(20) NOT NULL,
    fecha_consulta DATETIME NOT NULL,
    estado VARCHAR(30) NOT NULL,
    motivo VARCHAR(500) NULL,
    archivo_original VARCHAR(600) NULL,
    metadata_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_asopagos_numero (numero_id),
    INDEX idx_asopagos_fecha (fecha_consulta),
    INDEX idx_asopagos_estado (estado)
) ENGINE=InnoDB;

-- =============================================================================
-- Vista consolidada de bots (igual que bots2)
-- =============================================================================
CREATE OR REPLACE VIEW consultas_consolidadas AS
SELECT
    'ruaf'   AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    eps_afiliado AS entidad_1, regimen AS categoria_1, estado_afiliacion AS estado_detalle,
    NULL AS razon_social, NULL AS nit, NULL AS matricula, NULL AS empresa, NULL AS empleado,
    NULL AS periodo_cotizacion, metadata_json
FROM ruaf_consultas
UNION ALL
SELECT
    'fosiga' AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    entidad AS entidad_1, regimen AS categoria_1, estado AS estado_detalle,
    NULL, NULL, NULL, NULL, CONCAT(nombres, ' ', apellidos), NULL, metadata_json
FROM fosiga_consultas
UNION ALL
SELECT
    'rues' AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    NULL, NULL, estado_matricula,
    razon_social, nit, matricula_mercantil, NULL, NULL, NULL, metadata_json
FROM rues_consultas
UNION ALL
SELECT
    'simpleco' AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    nombre_entidad, tipo_admin, NULL,
    NULL, nit_entidad, NULL, empresa, empleado, periodo_cotizacion, metadata_json
FROM simpleco_consultas
UNION ALL
SELECT
    'suaporte' AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    nombre_eps, tipo_admin_eps, NULL,
    NULL, nit_eps, NULL, empresa, empleado, periodo_cotizacion, metadata_json
FROM suaporte_consultas
UNION ALL
SELECT
    'aportesenlinea' AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    aportante, aportes, NULL,
    NULL, nit_aportante, NULL, aportante, nombre_certifica, NULL, metadata_json
FROM aportesenlinea_consultas
UNION ALL
SELECT
    'asopagos' AS fuente, id, numero_id, fecha_consulta, estado, motivo, archivo_original,
    NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, metadata_json
FROM asopagos_consultas;

-- =============================================================================
-- Vistas útiles de la app
-- =============================================================================

-- Módulo: app/colas (definido tras procesos por la FK)
CREATE TABLE IF NOT EXISTS app_colas_trabajos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(100) NOT NULL UNIQUE,
    cola VARCHAR(50) NOT NULL,
    proceso_id INT NULL,
    tipo_trabajo VARCHAR(50) NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    payload JSON NOT NULL,
    resultado JSON NULL,
    error_mensaje TEXT NULL,
    intentos INT NOT NULL DEFAULT 0,
    max_intentos INT NOT NULL DEFAULT 3,
    prioridad INT NOT NULL DEFAULT 5,
    worker_id VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    duracion_ms INT NULL,
    CONSTRAINT fk_cola_proc FOREIGN KEY (proceso_id)
        REFERENCES procesos(id) ON DELETE CASCADE,
    INDEX idx_cola_job (job_id),
    INDEX idx_cola_cola (cola),
    INDEX idx_cola_proceso (proceso_id),
    INDEX idx_cola_estado (estado),
    INDEX idx_cola_pendientes (estado, prioridad, created_at)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW v_procesos_detalle AS
SELECT
    p.*,
    u_creador.nombre_completo AS creador_nombre,
    u_asig.nombre_completo    AS asignado_nombre,
    (SELECT COUNT(*) FROM procesos_archivos WHERE proceso_id = p.id) AS total_archivos,
    (SELECT MAX(fecha) FROM procesos_historial WHERE proceso_id = p.id) AS ultima_actividad
FROM procesos p
LEFT JOIN control_usuarios u_creador ON p.creado_por = u_creador.id
LEFT JOIN control_usuarios u_asig    ON p.asignado_a = u_asig.id;

CREATE OR REPLACE VIEW v_procesos_por_estado AS
SELECT estado, COUNT(*) AS cantidad, DATE(created_at) AS fecha
FROM procesos
GROUP BY estado, DATE(created_at)
ORDER BY fecha DESC, estado;

CREATE OR REPLACE VIEW v_colas_estadisticas AS
SELECT
    cola, estado, COUNT(*) AS cantidad,
    AVG(duracion_ms) AS duracion_promedio_ms,
    MAX(created_at) AS ultimo_trabajo
FROM app_colas_trabajos
GROUP BY cola, estado;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Datos semilla
-- =============================================================================

INSERT INTO control_usuarios (usuario, password, nombre_completo, email, rol, clave_un_solo_uso) VALUES
('admin', '$2y$10$Z2EH9VptiMq2iw/rwYHhIuGDFUt3mGxP9M6us32Rg3N3E58ZWVq5S', 'Administrador ByBot', 'admin@bybot.local', 'admin', 1)
ON DUPLICATE KEY UPDATE usuario = usuario;

INSERT INTO app_configuracion (clave, valor, tipo, categoria, descripcion) VALUES
('gemini_model',              'gemini-1.5-flash', 'string', 'ia',       'Modelo de Gemini a utilizar'),
('gemini_temperature',        '0.1',              'float',  'ia',       'Temperatura para respuestas de IA'),
('gemini_max_tokens',         '4000',             'int',    'ia',       'Máximo de tokens de salida'),
('max_file_size_image',       '5242880',          'int',    'archivos', 'Tamaño máximo imágenes (5MB)'),
('max_file_size_pdf',         '10485760',         'int',    'archivos', 'Tamaño máximo PDFs (10MB)'),
('max_file_size_html',        '2097152',          'int',    'archivos', 'Tamaño máximo HTML (2MB)'),
('max_file_size_excel',       '10485760',         'int',    'archivos', 'Tamaño máximo Excel (10MB)'),
('max_intentos_analisis',     '3',                'int',    'procesos', 'Intentos máximos de análisis IA'),
('cola_poll_interval_seg',     '5',               'int',    'colas',    'Intervalo de polling del daemon en segundos'),
('upload_allowed_mimes',      'application/pdf,image/jpeg,image/png,text/html,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'string', 'archivos', 'MIMEs permitidos en carga')
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

INSERT INTO app_prompts (nombre, version, tipo, contenido, activo, notas) VALUES
('estado_cuenta', 'v1', 'estado_cuenta',
'Analiza este estado de cuenta bancario/financiero y extrae la siguiente información en formato JSON.\n\nIMPORTANTE:\n- Responde SOLO con el JSON, sin texto adicional\n- Usa null para campos que no encuentres\n- Los valores monetarios deben ser números (sin símbolos de moneda)\n- Las tasas de interés deben ser números decimales (ej: 24.5 para 24.5%)\n\nEstructura JSON requerida:\n{\n  "estado_cuenta": {\n    "numero_credito": "string o null",\n    "fecha_corte": "YYYY-MM-DD o null",\n    "capital": "number o null",\n    "intereses_corrientes": "number o null",\n    "intereses_mora": "number o null",\n    "honorarios": "number o null",\n    "gastos": "number o null",\n    "seguros": "number o null",\n    "otros_cobros": "number o null",\n    "total_deuda": "number o null",\n    "tasa_interes_corriente": "number o null",\n    "tasa_interes_mora": "number o null",\n    "dias_mora": "number o null",\n    "fecha_ultimo_pago": "YYYY-MM-DD o null",\n    "valor_ultimo_pago": "number o null"\n  },\n  "entidad": { "nombre": "string o null", "nit": "string o null" },\n  "observaciones": "string con notas adicionales relevantes"\n}',
1, 'Prompt inicial migrado de bybot_app/legacy gemini_client.py'),
('anexos', 'v1', 'anexos',
'Analiza estos documentos anexos y extrae la información del deudor y codeudor (si existe).\n\nIMPORTANTE:\n- Responde SOLO con el JSON, sin texto adicional\n- Usa null para campos que no encuentres\n- Identifica si hay información de codeudor/garante\n\nEstructura JSON requerida:\n{\n  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "cargo": "string o null", "ingresos_mensuales": "number o null" },\n  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "fecha_expedicion": "string o null", "lugar_expedicion": "string o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "relacion_deudor": "string o null" },\n  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],\n  "solicitudes_vinculacion": { "detectadas": "boolean", "paginas": ["number"] }\n}',
1, 'Prompt inicial migrado de bybot_app/legacy gemini_client.py')
ON DUPLICATE KEY UPDATE contenido = VALUES(contenido);