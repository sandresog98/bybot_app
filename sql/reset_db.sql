-- =============================================
-- RESET DATABASE - BYBOT APP
-- Este script elimina todas las tablas y las recrea
-- =============================================

-- Eliminar tablas en orden inverso de dependencias
DROP TABLE IF EXISTS crear_coop_anexos;
DROP TABLE IF EXISTS crear_coop_procesos;
DROP TABLE IF EXISTS control_logs;
DROP TABLE IF EXISTS control_usuarios;
