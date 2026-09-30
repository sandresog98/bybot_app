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
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updatedAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY process_code_key (code),
  CONSTRAINT process_created_by_fkey FOREIGN KEY (createdBy) REFERENCES `User` (id),
  CONSTRAINT process_entidad_id_fkey FOREIGN KEY (entidadId) REFERENCES `Entidad` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `File` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  originalName VARCHAR(255) NOT NULL,
  storageKey VARCHAR(300) NOT NULL,
  mimeType VARCHAR(120) NOT NULL,
  sizeBytes INT NOT NULL,
  sha256 CHAR(64) NOT NULL,
  tipo VARCHAR(40) NULL,
  uploadedBy INT NOT NULL,
  createdAt DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id), UNIQUE KEY file_storage_key_key (storageKey), KEY file_process_id_idx (processId),
  CONSTRAINT file_process_id_fkey FOREIGN KEY (processId) REFERENCES `Process` (id) ON DELETE CASCADE,
  CONSTRAINT file_uploaded_by_fkey FOREIGN KEY (uploadedBy) REFERENCES `User` (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Analysis` (
  id INT NOT NULL AUTO_INCREMENT,
  processId INT NOT NULL,
  fileId INT NULL,
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
