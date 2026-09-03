-- =============================================================================
-- f4c_tokens.sql — Contabilidad de tokens (entrada/salida) y precios IA
--
-- Guarda el desglose de tokens por análisis para estimar costo, y define precios
-- editables por millón de tokens.
--
-- Idempotente. MariaDB 10.11 / MySQL 8.
-- =============================================================================

ALTER TABLE procesos_datos_ia
    ADD COLUMN IF NOT EXISTS tokens_entrada INT NULL AFTER tokens_total,
    ADD COLUMN IF NOT EXISTS tokens_salida  INT NULL AFTER tokens_entrada;

-- Precios de gemini-2.5-flash (USD por 1.000.000 de tokens). Editables en Configuración.
INSERT INTO app_configuracion (clave, valor, tipo, categoria, descripcion) VALUES
    ('precio_ia_entrada_usd_1m', '0.30', 'float', 'ia', 'Precio USD por 1M tokens de entrada (prompt)'),
    ('precio_ia_salida_usd_1m',  '2.50', 'float', 'ia', 'Precio USD por 1M tokens de salida (respuesta)'),
    ('gemini_max_tokens',        '26000','int',   'ia', 'Máximo de tokens de salida del modelo'),
    ('gemini_thinking_budget',   '0',    'int',   'ia', 'Presupuesto de thinking de Gemini 2.5 (0=off, -1=auto, >0=fijo)')
ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion);
