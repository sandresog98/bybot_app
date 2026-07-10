-- =============================================================================
-- reset_db.sql — Reinicia la base de datos bybot_consolidado a estado limpio
--
-- Uso (desde la raíz del proyecto):
--   /opt/lampp/bin/mysql -u root < sql/reset_db.sql
--
-- El comando `SOURCE` se resuelve relativo al directorio de trabajo actual,
-- por lo que debe ejecutarse desde la raíz del proyecto (donde está sql/).
--
-- ADVERTENCIA: destruye todos los datos existentes. No ejecutar en producción
-- sin un respaldo previo.
-- =============================================================================

DROP DATABASE IF EXISTS bybot_consolidado;
SOURCE sql/ddl.sql;