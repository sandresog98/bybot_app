-- Ejecutar con un usuario con privilegios DDL sobre la base `node2`.
-- Motor requerido: InnoDB. Codificación: utf8mb4.

CREATE TABLE IF NOT EXISTS `User` (
  id INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  passwordHash VARCHAR(255) NOT NULL,
  name VARCHAR(120) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'operator',
  active TINYINT(1) NOT NULL DEFAULT 1,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY user_username_key (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Entidad` (
  id INT NOT NULL AUTO_INCREMENT,
  codigo VARCHAR(60) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  nit VARCHAR(40) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY entidad_codigo_key (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Process` (
  id INT NOT NULL AUTO_INCREMENT,
  code VARCHAR(50) NOT NULL,
  title VARCHAR(120) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'created',
  entidadId INT NULL,
  createdBy INT NOT NULL,
  deudorNombre VARCHAR(200) NULL,
  deudorDocumento VARCHAR(60) NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY process_code_key (code), KEY process_deudor_documento_idx (deudorDocumento),
  CONSTRAINT process_created_by_fkey FOREIGN KEY (createdBy) REFERENCES `User` (id),
  CONSTRAINT process_entidad_id_fkey FOREIGN KEY (entidadId) REFERENCES `Entidad` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `File` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  originalName VARCHAR(255) NOT NULL,
  storageKey VARCHAR(300) NOT NULL,
  mimeType VARCHAR(120) NOT NULL,
  originalMimeType VARCHAR(120) NULL,
  converted TINYINT(1) NOT NULL DEFAULT 0,
  sizeBytes INT NOT NULL,
  sha256 CHAR(64) NOT NULL,
  tipo VARCHAR(40) NULL,
  uploadedBy INT NOT NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY file_storage_key_key (storageKey), KEY file_process_id_idx (processId),
  CONSTRAINT file_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE,
  CONSTRAINT file_uploaded_by_fkey FOREIGN KEY (uploadedBy) REFERENCES `User` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migraciones idempotentes para bases ya creadas (MariaDB admite IF NOT EXISTS en ADD COLUMN).
ALTER TABLE `File` ADD COLUMN IF NOT EXISTS originalMimeType VARCHAR(120) NULL;
ALTER TABLE `File` ADD COLUMN IF NOT EXISTS converted TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `Analysis` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  fileId INT NULL,
  scope VARCHAR(20) NOT NULL DEFAULT 'file',
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  provider VARCHAR(500) NULL,
  model VARCHAR(120) NULL,
  result JSON NULL,
  validated JSON NULL,
  error TEXT NULL,
  inputTokens INT NULL,
  outputTokens INT NULL,
  costUsd DECIMAL(12,6) NULL,
  attempts INT NOT NULL DEFAULT 0,
  maxAttempts INT NOT NULL DEFAULT 3,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), KEY analysis_process_id_idx (processId), KEY analysis_status_created_at_idx (status, createdAt),
  CONSTRAINT analysis_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE,
  CONSTRAINT analysis_file_id_fkey FOREIGN KEY (fileId) REFERENCES `File` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AuditEvent` (
  id INT NOT NULL AUTO_INCREMENT,
  userId INT NULL,
  action VARCHAR(80) NOT NULL,
  resource VARCHAR(80) NOT NULL,
  resourceId INT NULL,
  detail TEXT NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), KEY audit_resource_resource_id_idx (resource, resourceId), KEY audit_user_id_created_at_idx (userId, createdAt),
  CONSTRAINT audit_user_id_fkey FOREIGN KEY (userId) REFERENCES `User` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migraciones idempotentes de columnas añadidas después de la creación inicial.
ALTER TABLE `Analysis` ADD COLUMN IF NOT EXISTS scope VARCHAR(20) NOT NULL DEFAULT 'file';
ALTER TABLE `Process` ADD COLUMN IF NOT EXISTS deudorNombre VARCHAR(200) NULL;
ALTER TABLE `Process` ADD COLUMN IF NOT EXISTS deudorDocumento VARCHAR(60) NULL;
ALTER TABLE `Process` ADD INDEX IF NOT EXISTS process_deudor_documento_idx (deudorDocumento);
ALTER TABLE `Entidad` ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1;

-- ---------- Fase 3: persistencia estructurada ----------
CREATE TABLE IF NOT EXISTS `Parte` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  rol VARCHAR(40) NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  tipoDocumento VARCHAR(40) NULL,
  numeroDocumento VARCHAR(60) NULL,
  nombreCompleto VARCHAR(200) NULL,
  fechaExpedicion DATETIME(3) NULL,
  lugarExpedicion VARCHAR(120) NULL,
  fechaNacimiento DATETIME(3) NULL,
  direccion VARCHAR(255) NULL,
  ciudad VARCHAR(120) NULL,
  departamento VARCHAR(120) NULL,
  telefono VARCHAR(60) NULL,
  celular VARCHAR(60) NULL,
  email VARCHAR(160) NULL,
  ocupacion VARCHAR(120) NULL,
  empresa VARCHAR(160) NULL,
  ingresosMensuales DECIMAL(18,2) NULL,
  relacionDeudor VARCHAR(120) NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), KEY parte_process_id_rol_idx (processId, rol), KEY parte_numero_documento_idx (numeroDocumento),
  CONSTRAINT parte_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Credito` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  entidadId INT NULL,
  numeroCredito VARCHAR(80) NULL,
  numeroPagare VARCHAR(80) NULL,
  producto VARCHAR(120) NULL,
  monto DECIMAL(18,2) NULL,
  plazoMeses INT NULL,
  tasaEa DECIMAL(9,4) NULL,
  tasaInteresCorriente DECIMAL(9,4) NULL,
  tasaInteresMora DECIMAL(9,4) NULL,
  fechaDesembolso DATETIME(3) NULL,
  fechaCorte DATETIME(3) NULL,
  fechaCausacion DATETIME(3) NULL,
  saldoCapital DECIMAL(18,2) NULL,
  totalInteresesCorrientes DECIMAL(18,2) NULL,
  totalInteresesMora DECIMAL(18,2) NULL,
  totalSeguroVida DECIMAL(18,2) NULL,
  totalDeuda DECIMAL(18,2) NULL,
  diasMora INT NULL,
  fechaUltimoPago DATETIME(3) NULL,
  valorUltimoPago DECIMAL(18,2) NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY credito_process_id_key (processId), KEY credito_numero_credito_idx (numeroCredito),
  CONSTRAINT credito_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Movimiento` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  creditoId INT NOT NULL,
  orden INT NOT NULL DEFAULT 0,
  documento VARCHAR(80) NULL,
  fecha DATETIME(3) NULL,
  descripcion VARCHAR(255) NULL,
  total DECIMAL(18,2) NULL,
  capital DECIMAL(18,2) NULL,
  interes DECIMAL(18,2) NULL,
  mora DECIMAL(18,2) NULL,
  seguroVida DECIMAL(18,2) NULL,
  otros DECIMAL(18,2) NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), KEY movimiento_process_id_idx (processId), KEY movimiento_credito_id_fecha_idx (creditoId, fecha),
  CONSTRAINT movimiento_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE,
  CONSTRAINT movimiento_credito_id_fkey FOREIGN KEY (creditoId) REFERENCES `Credito` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `CuotaAmortizacion` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  creditoId INT NOT NULL,
  numero INT NULL,
  fecha DATETIME(3) NULL,
  cuota DECIMAL(18,2) NULL,
  abonoCapital DECIMAL(18,2) NULL,
  abonoInteres DECIMAL(18,2) NULL,
  saldo DECIMAL(18,2) NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), KEY cuota_amortizacion_process_id_idx (processId), KEY cuota_amortizacion_credito_id_numero_idx (creditoId, numero),
  CONSTRAINT cuota_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE,
  CONSTRAINT cuota_credito_id_fkey FOREIGN KEY (creditoId) REFERENCES `Credito` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ExtraccionCampo` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  analysisId INT NULL,
  fileId INT NULL,
  ruta VARCHAR(255) NOT NULL,
  clave VARCHAR(120) NOT NULL,
  valorTexto TEXT NULL,
  valorNumero DECIMAL(18,4) NULL,
  valorFecha DATETIME(3) NULL,
  valorBool TINYINT(1) NULL,
  valorJson JSON NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), KEY extraccion_campo_process_id_ruta_idx (processId, ruta), KEY extraccion_campo_analysis_id_idx (analysisId),
  CONSTRAINT extraccion_campo_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
