-- =============================================================================
-- f4_entidades.sql — Soporte multi-entidad de documentos (F4)
--
-- Introduce el concepto de ENTIDAD (cliente/cooperativa) para que cada una defina
-- su propio catálogo de documentos y sus prompts de extracción, sin tocar código.
-- Foco actual: CONFIAR (sembrada completa). Crearcoop/Somec quedan como plantilla.
--
-- Idempotente (IF NOT EXISTS / ON DUPLICATE KEY). MariaDB 10.11 / MySQL 8.
-- Uso: docker exec -i bybot-mariadb mariadb -uroot -pXXX bybot_consolidado < sql/migrations/f4_entidades.sql
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. Tabla entidades (cliente/cooperativa que remite procesos)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) NOT NULL UNIQUE,                    -- slug: confiar, crearcoop, somec
    nombre VARCHAR(150) NOT NULL,
    nit VARCHAR(30) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entidad_codigo (codigo),
    INDEX idx_entidad_activo (activo)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 2. Catálogo de tipos de documento esperados por entidad
--    categoria_logica = taxonomía canónica compartida (la que ve el analizador).
--    valores categoria_logica: pagare, estado_cuenta, amortizacion, vinculacion,
--                              poder, anexo, identificacion, otro
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entidades_tipos_doc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entidad_id INT NOT NULL,
    clave VARCHAR(50) NOT NULL,                            -- nombre del doc en la entidad (ej: extracto)
    label VARCHAR(120) NOT NULL,                           -- etiqueta legible en el front
    categoria_logica VARCHAR(50) NOT NULL,                 -- categoría canónica (ver arriba)
    obligatorio TINYINT(1) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_etd_entidad FOREIGN KEY (entidad_id)
        REFERENCES entidades(id) ON DELETE CASCADE,
    UNIQUE KEY uk_etd_entidad_categoria (entidad_id, categoria_logica),
    INDEX idx_etd_entidad (entidad_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------------
-- 3. procesos.entidad_id (nullable = retrocompatible con procesos existentes)
-- ---------------------------------------------------------------------------
ALTER TABLE procesos
    ADD COLUMN IF NOT EXISTS entidad_id INT NULL AFTER tipo;

-- FK (se ignora si ya existe; en primera corrida se crea)
ALTER TABLE procesos
    ADD CONSTRAINT fk_proc_entidad FOREIGN KEY (entidad_id)
        REFERENCES entidades(id) ON DELETE SET NULL;

ALTER TABLE procesos
    ADD INDEX IF NOT EXISTS idx_proc_entidad (entidad_id);

-- ---------------------------------------------------------------------------
-- 4. app_prompts.entidad_id (NULL = prompt global; el específico gana)
--    app_prompts.tipo pasa a interpretarse como categoria_logica.
-- ---------------------------------------------------------------------------
ALTER TABLE app_prompts
    ADD COLUMN IF NOT EXISTS entidad_id INT NULL AFTER tipo;

ALTER TABLE app_prompts
    ADD CONSTRAINT fk_prompt_entidad FOREIGN KEY (entidad_id)
        REFERENCES entidades(id) ON DELETE CASCADE;

ALTER TABLE app_prompts
    ADD INDEX IF NOT EXISTS idx_prompt_entidad (entidad_id);

-- ===========================================================================
-- SEEDS
-- ===========================================================================

-- Entidades (foco Confiar; las otras dos quedan disponibles para configurar)
INSERT INTO entidades (codigo, nombre, nit, activo) VALUES
    ('confiar',   'CONFIAR Cooperativa Financiera',          '890900841', 1),
    ('crearcoop', 'Cooperativa de Ahorro y Crédito CREAR',   '890981459', 1),
    ('somec',     'Cooperativa Multiactiva de Profesionales SOMEC', '860026153', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), nit = VALUES(nit);

-- Catálogo de documentos de CONFIAR
INSERT INTO entidades_tipos_doc (entidad_id, clave, label, categoria_logica, obligatorio, orden)
SELECT e.id, v.clave, v.label, v.categoria_logica, v.obligatorio, v.orden
FROM entidades e
JOIN (
    SELECT 'extracto'    AS clave, 'Extracto de crédito'         AS label, 'estado_cuenta' AS categoria_logica, 1 AS obligatorio, 1 AS orden
    UNION ALL SELECT 'amortizacion', 'Tabla de amortización',    'amortizacion', 1, 2
    UNION ALL SELECT 'pagare',       'Pagaré',                   'pagare',       1, 3
    UNION ALL SELECT 'vinculacion',  'Solicitud de vinculación', 'vinculacion',  1, 4
) v
WHERE e.codigo = 'confiar'
ON DUPLICATE KEY UPDATE label = VALUES(label), clave = VALUES(clave),
    obligatorio = VALUES(obligatorio), orden = VALUES(orden);

-- Prompts específicos de CONFIAR (uno por categoría). Devuelven JSON canónico.
-- El analizador fusiona los objetos top-level de cada categoría en un solo resultado.
INSERT INTO app_prompts (nombre, version, tipo, entidad_id, contenido, activo, notas)
SELECT p.nombre, 'v1', p.tipo, e.id, p.contenido, 1, 'Prompt CONFIAR (F4)'
FROM entidades e
JOIN (
    SELECT 'confiar_estado_cuenta' AS nombre, 'estado_cuenta' AS tipo,
'Eres un extractor de datos de documentos financieros de CONFIAR Cooperativa Financiera. Este documento es un EXTRACTO DE CRÉDITO.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Usa null para lo que no encuentres. Montos como números sin símbolos ni separadores de miles. Tasas como decimal (14.75).

Estructura JSON:
{
  "credito": { "numero_pagare": "string o null", "producto": "string o null", "monto": "number o null", "cuota": "number o null", "plazo_meses": "number o null", "tasa_ea": "number o null", "fecha_desembolso": "YYYY-MM-DD o null", "cuotas_pagadas": "number o null", "cuotas_pendientes": "number o null", "proximo_pago": "YYYY-MM-DD o null" },
  "estado_cuenta": { "capital": "number o null", "intereses_corrientes": "number o null", "intereses_mora": "number o null", "total_deuda": "number o null", "fecha_corte": "YYYY-MM-DD o null" },
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "direccion": "string o null", "municipio": "string o null" },
  "entidad": { "nombre": "CONFIAR Cooperativa Financiera", "nit": "string o null" },
  "observaciones": "string o null"
}' AS contenido
    UNION ALL SELECT 'confiar_amortizacion', 'amortizacion',
'Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es una TABLA DE AMORTIZACIÓN / plan de pagos.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Montos como números sin símbolos. Usa null para lo que no encuentres.
- En "cuotas" incluye hasta las primeras 60 filas si hay muchas.

Estructura JSON:
{
  "amortizacion": {
    "valor_cuota": "number o null",
    "numero_cuotas": "number o null",
    "cuotas": [ { "numero": "number", "fecha": "YYYY-MM-DD o null", "cuota": "number o null", "abono_capital": "number o null", "abono_interes": "number o null", "saldo": "number o null" } ]
  },
  "observaciones": "string o null"
}'
    UNION ALL SELECT 'confiar_pagare', 'pagare',
'Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es un PAGARÉ (puede estar escaneado; usa OCR visual).

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Montos como números sin símbolos. Usa null para lo que no encuentres.

Estructura JSON:
{
  "pagare": { "numero": "string o null", "valor": "number o null", "fecha_suscripcion": "YYYY-MM-DD o null", "vencimiento": "YYYY-MM-DD o null", "tasa_interes": "number o null", "ciudad": "string o null" },
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },
  "observaciones": "string o null"
}'
    UNION ALL SELECT 'confiar_vinculacion', 'vinculacion',
'Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es una SOLICITUD DE VINCULACIÓN / formulario del asociado.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Usa null para lo que no encuentres.

Estructura JSON:
{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "ingresos_mensuales": "number o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "relacion_deudor": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}'
) p
WHERE e.codigo = 'confiar'
ON DUPLICATE KEY UPDATE contenido = VALUES(contenido), activo = 1;
