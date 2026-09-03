-- =============================================================================
-- f4b_prompts_globales_y_catalogos.sql
--
-- Completa el soporte multi-entidad:
--  (1) Prompts GLOBALES por categoría lógica (entidad_id NULL) → fallback para
--      cualquier entidad sin prompt específico.
--  (2) Catálogo de documentos de CREARCOOP y SOMEC.
--
-- Idempotente. MariaDB 10.11 / MySQL 8.
-- =============================================================================

-- ---------------------------------------------------------------------------
-- (1) Prompts globales por categoría (los ya existentes estado_cuenta/anexos se conservan)
-- ---------------------------------------------------------------------------
INSERT INTO app_prompts (nombre, version, tipo, entidad_id, contenido, activo, notas)
SELECT nombre, 'v1', tipo, NULL, contenido, 1, 'Prompt global (F4b)' FROM (
    SELECT 'global_pagare' AS nombre, 'pagare' AS tipo,
'Extrae los datos de este PAGARÉ (puede estar escaneado; usa OCR visual).\n\nResponde SOLO con JSON válido, sin markdown. Montos como números sin símbolos ni separadores. Usa null si no encuentras.\n\n{\n  "pagare": { "numero": "string o null", "valor": "number o null", "fecha_suscripcion": "YYYY-MM-DD o null", "vencimiento": "YYYY-MM-DD o null", "tasa_interes": "number o null", "ciudad": "string o null" },\n  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null" },\n  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },\n  "entidad": { "nombre": "string o null", "nit": "string o null" },\n  "observaciones": "string o null"\n}' AS contenido
    UNION ALL SELECT 'global_amortizacion', 'amortizacion',
'Extrae la TABLA DE AMORTIZACIÓN / plan de pagos.\n\nResponde SOLO con JSON válido, sin markdown. Montos como números sin símbolos. Incluye hasta 60 cuotas. Usa null si no encuentras.\n\n{\n  "amortizacion": { "valor_cuota": "number o null", "numero_cuotas": "number o null", "cuotas": [ { "numero": "number", "fecha": "YYYY-MM-DD o null", "cuota": "number o null", "abono_capital": "number o null", "abono_interes": "number o null", "saldo": "number o null" } ] },\n  "credito": { "numero_credito": "string o null", "monto": "number o null", "tasa_ea": "number o null", "fecha_desembolso": "YYYY-MM-DD o null" },\n  "deudor": { "nombre_completo": "string o null", "numero_documento": "string o null" },\n  "observaciones": "string o null"\n}'
    UNION ALL SELECT 'global_vinculacion', 'vinculacion',
'Extrae los datos del FORMULARIO DE VINCULACIÓN / solicitud del asociado (puede estar escaneado).\n\nResponde SOLO con JSON válido, sin markdown. Usa null si no encuentras.\n\n{\n  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "ingresos_mensuales": "number o null" },\n  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "relacion_deudor": "string o null" },\n  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],\n  "observaciones": "string o null"\n}'
    UNION ALL SELECT 'global_poder', 'poder',
'Extrae los datos de este PODER (documento legal de representación).\n\nResponde SOLO con JSON válido, sin markdown. Usa null si no encuentras.\n\n{\n  "poder": { "otorgante": "string o null", "tipo_documento_otorgante": "CC/CE/NIT/PA o null", "numero_documento_otorgante": "string o null", "apoderado": "string o null", "facultades": "string o null", "ciudad": "string o null", "fecha": "YYYY-MM-DD o null" },\n  "deudor": { "nombre_completo": "string o null", "numero_documento": "string o null" },\n  "observaciones": "string o null"\n}'
    UNION ALL SELECT 'global_anexo', 'anexo',
'Analiza estos documentos anexos y extrae la información del deudor y codeudor.\n\nResponde SOLO con JSON válido, sin markdown. Usa null si no encuentras.\n\n{\n  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null" },\n  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },\n  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],\n  "observaciones": "string o null"\n}'
) g
ON DUPLICATE KEY UPDATE contenido = VALUES(contenido), activo = 1;

-- ---------------------------------------------------------------------------
-- (2) Catálogo de documentos de CREARCOOP
-- ---------------------------------------------------------------------------
INSERT INTO entidades_tipos_doc (entidad_id, clave, label, categoria_logica, obligatorio, orden)
SELECT e.id, v.clave, v.label, v.categoria_logica, v.obligatorio, v.orden
FROM entidades e JOIN (
    SELECT 'estado_cuenta' AS clave, 'Detalle de movimientos' AS label, 'estado_cuenta' AS categoria_logica, 1 AS obligatorio, 1 AS orden
    UNION ALL SELECT 'anexos', 'Anexos',  'anexo',  0, 2
    UNION ALL SELECT 'pagare', 'Pagaré',  'pagare', 1, 3
) v WHERE e.codigo = 'crearcoop'
ON DUPLICATE KEY UPDATE label = VALUES(label), clave = VALUES(clave), obligatorio = VALUES(obligatorio), orden = VALUES(orden);

-- ---------------------------------------------------------------------------
-- (3) Catálogo de documentos de SOMEC (incluye formulario TIFF → vinculacion)
-- ---------------------------------------------------------------------------
INSERT INTO entidades_tipos_doc (entidad_id, clave, label, categoria_logica, obligatorio, orden)
SELECT e.id, v.clave, v.label, v.categoria_logica, v.obligatorio, v.orden
FROM entidades e JOIN (
    SELECT 'plan_pagos' AS clave, 'Plan de pagos' AS label, 'amortizacion' AS categoria_logica, 1 AS obligatorio, 1 AS orden
    UNION ALL SELECT 'pagare',     'Pagaré',                     'pagare',      1, 2
    UNION ALL SELECT 'poder',      'Poder',                      'poder',       0, 3
    UNION ALL SELECT 'formulario', 'Formulario de vinculación',  'vinculacion', 1, 4
) v WHERE e.codigo = 'somec'
ON DUPLICATE KEY UPDATE label = VALUES(label), clave = VALUES(clave), obligatorio = VALUES(obligatorio), orden = VALUES(orden);
