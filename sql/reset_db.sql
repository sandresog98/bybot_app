-- =============================================
-- RESET DATABASE - BYBOT v2.0
-- ¡ADVERTENCIA! Este script elimina todos los datos
-- =============================================

USE bybot;

-- Deshabilitar verificación de FK temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- Truncar todas las tablas
TRUNCATE TABLE procesos_historial;
TRUNCATE TABLE procesos_datos_ia;
TRUNCATE TABLE procesos_anexos;
TRUNCATE TABLE procesos;
TRUNCATE TABLE colas_trabajos;
TRUNCATE TABLE control_logs;
TRUNCATE TABLE prompts;

-- NO truncar control_usuarios para mantener admin
-- NO truncar configuracion para mantener settings
-- NO truncar plantillas_pagare para mantener plantillas

-- Habilitar verificación de FK
SET FOREIGN_KEY_CHECKS = 1;

-- Reinsertar usuario admin si no existe
INSERT IGNORE INTO control_usuarios (usuario, password, nombre_completo, email, rol)
VALUES ('admin', '$2y$10$BPGSMwk9u8YeZI0U2gBJE.X7XqmESvbPBiYMCbGqjhNfsVLLGlPtK', 
        'Administrador ByBot', 'admin@bybot.com', 'admin');

-- Confirmar reset
SELECT 'Base de datos reseteada exitosamente' AS mensaje;
SELECT 
    (SELECT COUNT(*) FROM procesos) as procesos,
    (SELECT COUNT(*) FROM control_usuarios) as usuarios,
    (SELECT COUNT(*) FROM configuracion) as configuraciones;

