-- f4e_prompts_observaciones.sql — observaciones = resumen breve (evita transcribir cláusulas).
-- Idempotente. MariaDB/MySQL.

UPDATE app_prompts SET contenido='Analiza este estado de cuenta bancario/financiero y extrae la siguiente información en formato JSON.

IMPORTANTE:
- Responde SOLO con el JSON, sin texto adicional
- Usa null para campos que no encuentres
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.
- Los valores monetarios deben ser números (sin símbolos de moneda)
- Las tasas de interés deben ser números decimales (ej: 24.5 para 24.5%)

Estructura JSON requerida:
{
  "estado_cuenta": {
    "numero_credito": "string o null",
    "fecha_corte": "YYYY-MM-DD o null",
    "capital": "number o null",
    "intereses_corrientes": "number o null",
    "intereses_mora": "number o null",
    "honorarios": "number o null",
    "gastos": "number o null",
    "seguros": "number o null",
    "otros_cobros": "number o null",
    "total_deuda": "number o null",
    "tasa_interes_corriente": "number o null",
    "tasa_interes_mora": "number o null",
    "dias_mora": "number o null",
    "fecha_ultimo_pago": "YYYY-MM-DD o null",
    "valor_ultimo_pago": "number o null"
  },
  "entidad": { "nombre": "string o null", "nit": "string o null" },
  "observaciones": "string con notas adicionales relevantes"
}' WHERE nombre='estado_cuenta';
UPDATE app_prompts SET contenido='Eres un asistente legal experto en análisis de documentos de cobranza. Extrae la siguiente información en formato JSON:

{
  "estado_cuenta": {
    "numero_credito": string | null,
    "fecha_corte": string | null,
    "capital": number | null,
    "intereses_corrientes": number | null,
    "intereses_mora": number | null,
    "honorarios": number | null,
    "gastos": number | null,
    "seguros": number | null,
    "otros_cobros": number | null,
    "total_deuda": number | null,
    "tasa_interes_corriente": number | null,
    "tasa_interes_mora": number | null,
    "dias_mora": number | null,
    "fecha_ultimo_pago": string | null,
    "valor_ultimo_pago": number | null
  },
  "entidad": {
    "nombre": string | null,
    "nit": string | null
  },
  "deudor": {
    "nombre": string | null,
    "numero_id": string | null,
    "tipo_id": string | null
  },
  "codeudor": {
    "nombre": string | null,
    "numero_id": string | null,
    "tipo_id": string | null
  },
  "referencias": [{
    "tipo": string,
    "nombre": string | null,
    "telefono": string | null,
    "direccion": string | null
  }],
  "solicitudes_vinculacion": string | null,
  "observaciones": string | null
}

Si un documento no contiene información para un campo, devuelve null. Para arrays vacíos, devuelve []. Responde ÚNICAMENTE con el JSON, sin texto adicional.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.' WHERE nombre='Análisis por defecto';
UPDATE app_prompts SET contenido='Eres un extractor de datos de documentos financieros de CONFIAR Cooperativa Financiera. Este documento es un EXTRACTO DE CRÉDITO.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Usa null para lo que no encuentres.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento. Montos como números sin símbolos ni separadores de miles. Tasas como decimal (14.75).

Estructura JSON:
{
  "credito": { "numero_pagare": "string o null", "producto": "string o null", "monto": "number o null", "cuota": "number o null", "plazo_meses": "number o null", "tasa_ea": "number o null", "fecha_desembolso": "YYYY-MM-DD o null", "cuotas_pagadas": "number o null", "cuotas_pendientes": "number o null", "proximo_pago": "YYYY-MM-DD o null" },
  "estado_cuenta": { "capital": "number o null", "intereses_corrientes": "number o null", "intereses_mora": "number o null", "total_deuda": "number o null", "fecha_corte": "YYYY-MM-DD o null" },
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "direccion": "string o null", "municipio": "string o null" },
  "entidad": { "nombre": "CONFIAR Cooperativa Financiera", "nit": "string o null" },
  "observaciones": "string o null"
}' WHERE nombre='confiar_estado_cuenta';
UPDATE app_prompts SET contenido='Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es una TABLA DE AMORTIZACIÓN / plan de pagos.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Montos como números sin símbolos. Usa null para lo que no encuentres.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.
- En "cuotas" incluye hasta las primeras 60 filas si hay muchas.

Estructura JSON:
{
  "amortizacion": {
    "valor_cuota": "number o null",
    "numero_cuotas": "number o null",
    "cuotas": [ { "numero": "number", "fecha": "YYYY-MM-DD o null", "cuota": "number o null", "abono_capital": "number o null", "abono_interes": "number o null", "saldo": "number o null" } ]
  },
  "observaciones": "string o null"
}' WHERE nombre='confiar_amortizacion';
UPDATE app_prompts SET contenido='Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es un PAGARÉ (puede estar escaneado; usa OCR visual).

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Montos como números sin símbolos. Usa null para lo que no encuentres.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.

Estructura JSON:
{
  "pagare": { "numero": "string o null", "valor": "number o null", "fecha_suscripcion": "YYYY-MM-DD o null", "vencimiento": "YYYY-MM-DD o null", "tasa_interes": "number o null", "ciudad": "string o null" },
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },
  "observaciones": "string o null"
}' WHERE nombre='confiar_pagare';
UPDATE app_prompts SET contenido='Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es una SOLICITUD DE VINCULACIÓN / formulario del asociado.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Usa null para lo que no encuentres.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.
- En "referencias": incluye SOLO las personas que aparezcan explícitamente como referencia en el documento (normalmente 1 a 5, máximo 8). NUNCA repitas una referencia, no inventes y no rellenes con duplicados. Si no hay, usa [].

Estructura JSON:
{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "ingresos_mensuales": "number o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "relacion_deudor": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}' WHERE nombre='confiar_vinculacion';
UPDATE app_prompts SET contenido='Extrae los datos de este PAGARÉ (puede estar escaneado; usa OCR visual).

Responde SOLO con JSON válido, sin markdown. Montos como números sin símbolos ni separadores. Usa null si no encuentras.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.

{
  "pagare": { "numero": "string o null", "valor": "number o null", "fecha_suscripcion": "YYYY-MM-DD o null", "vencimiento": "YYYY-MM-DD o null", "tasa_interes": "number o null", "ciudad": "string o null" },
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },
  "entidad": { "nombre": "string o null", "nit": "string o null" },
  "observaciones": "string o null"
}' WHERE nombre='global_pagare';
UPDATE app_prompts SET contenido='Extrae la TABLA DE AMORTIZACIÓN / plan de pagos.

Responde SOLO con JSON válido, sin markdown. Montos como números sin símbolos. Incluye hasta 60 cuotas. Usa null si no encuentras.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.

{
  "amortizacion": { "valor_cuota": "number o null", "numero_cuotas": "number o null", "cuotas": [ { "numero": "number", "fecha": "YYYY-MM-DD o null", "cuota": "number o null", "abono_capital": "number o null", "abono_interes": "number o null", "saldo": "number o null" } ] },
  "credito": { "numero_credito": "string o null", "monto": "number o null", "tasa_ea": "number o null", "fecha_desembolso": "YYYY-MM-DD o null" },
  "deudor": { "nombre_completo": "string o null", "numero_documento": "string o null" },
  "observaciones": "string o null"
}' WHERE nombre='global_amortizacion';
UPDATE app_prompts SET contenido='Extrae los datos del FORMULARIO DE VINCULACIÓN / solicitud del asociado (puede estar escaneado).

Responde SOLO con JSON válido, sin markdown. Usa null si no encuentras.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.
- En "referencias": incluye SOLO las personas que aparezcan explícitamente como referencia en el documento (normalmente 1 a 5, máximo 8). NUNCA repitas una referencia, no inventes y no rellenes con duplicados. Si no hay, usa [].

{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "ingresos_mensuales": "number o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "relacion_deudor": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}' WHERE nombre='global_vinculacion';
UPDATE app_prompts SET contenido='Extrae los datos de este PODER (documento legal de representación).

Responde SOLO con JSON válido, sin markdown. Usa null si no encuentras.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.

{
  "poder": { "otorgante": "string o null", "tipo_documento_otorgante": "CC/CE/NIT/PA o null", "numero_documento_otorgante": "string o null", "apoderado": "string o null", "facultades": "string o null", "ciudad": "string o null", "fecha": "YYYY-MM-DD o null" },
  "deudor": { "nombre_completo": "string o null", "numero_documento": "string o null" },
  "observaciones": "string o null"
}' WHERE nombre='global_poder';
UPDATE app_prompts SET contenido='Analiza estos documentos anexos y extrae la información del deudor y codeudor.

Responde SOLO con JSON válido, sin markdown. Usa null si no encuentras.
- En "observaciones": SOLO un resumen breve (máximo 1-2 frases) con notas realmente relevantes (mora, garantías, inconsistencias). NO transcribas cláusulas, NO copies texto largo ni párrafos del documento.
- En "referencias": incluye SOLO las personas que aparezcan explícitamente como referencia en el documento (normalmente 1 a 5, máximo 8). NUNCA repitas una referencia, no inventes y no rellenes con duplicados. Si no hay, usa [].

{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}' WHERE nombre='global_anexo';
