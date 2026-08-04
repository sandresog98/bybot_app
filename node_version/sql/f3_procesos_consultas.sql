-- F3: Tabla de vínculo entre procesos y consultas de bots
CREATE TABLE IF NOT EXISTS procesos_consultas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proceso_id INT NOT NULL,
    persona_tipo VARCHAR(20) NOT NULL COMMENT 'deudor | codeudor',
    bot VARCHAR(50) NOT NULL COMMENT 'fosiga | ruaf | rues',
    numero_id VARCHAR(50) NOT NULL,
    consulta_tabla VARCHAR(50) DEFAULT NULL COMMENT 'fosiga_consultas | ruaf_consultas | rues_consultas',
    consulta_id INT DEFAULT NULL COMMENT 'id en la tabla específica',
    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente' COMMENT 'pendiente | procesando | exitoso | fallido | saltado',
    resultado_resumen JSON DEFAULT NULL,
    orden_ejecucion INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (proceso_id) REFERENCES procesos(id) ON DELETE CASCADE,
    INDEX idx_pc_proceso (proceso_id),
    INDEX idx_pc_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
