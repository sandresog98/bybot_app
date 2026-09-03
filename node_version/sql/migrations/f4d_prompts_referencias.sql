-- f4d_prompts_referencias.sql — evita bucles de repetición en 'referencias'.
-- Idempotente (vuelve a fijar el contenido). MariaDB/MySQL.

UPDATE app_prompts SET contenido='Eres un extractor de datos de CONFIAR Cooperativa Financiera. Este documento es una SOLICITUD DE VINCULACIÓN / formulario del asociado.

IMPORTANTE:
- Responde SOLO con JSON válido, sin texto adicional ni markdown.
- Usa null para lo que no encuentres.
- En "referencias": incluye SOLO las personas que aparezcan explícitamente como referencia en el documento (normalmente 1 a 5, máximo 8). NUNCA repitas una referencia, no inventes y no rellenes con duplicados. Si no hay, usa [].

Estructura JSON:
{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "ingresos_mensuales": "number o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "relacion_deudor": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}' WHERE nombre='confiar_vinculacion';
UPDATE app_prompts SET contenido='Analiza estos documentos anexos y extrae la información del deudor y codeudor.

Responde SOLO con JSON válido, sin markdown. Usa null si no encuentras.
- En "referencias": incluye SOLO las personas que aparezcan explícitamente como referencia en el documento (normalmente 1 a 5, máximo 8). NUNCA repitas una referencia, no inventes y no rellenes con duplicados. Si no hay, usa [].

{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}' WHERE nombre='global_anexo';
UPDATE app_prompts SET contenido='Extrae los datos del FORMULARIO DE VINCULACIÓN / solicitud del asociado (puede estar escaneado).

Responde SOLO con JSON válido, sin markdown. Usa null si no encuentras.
- En "referencias": incluye SOLO las personas que aparezcan explícitamente como referencia en el documento (normalmente 1 a 5, máximo 8). NUNCA repitas una referencia, no inventes y no rellenes con duplicados. Si no hay, usa [].

{
  "deudor": { "nombre_completo": "string o null", "tipo_documento": "CC/CE/NIT/PA o null", "numero_documento": "string o null", "fecha_expedicion": "YYYY-MM-DD o null", "lugar_expedicion": "string o null", "fecha_nacimiento": "YYYY-MM-DD o null", "direccion": "string o null", "ciudad": "string o null", "departamento": "string o null", "telefono": "string o null", "celular": "string o null", "email": "string o null", "ocupacion": "string o null", "empresa": "string o null", "ingresos_mensuales": "number o null" },
  "codeudor": { "existe": "boolean", "nombre_completo": "string o null", "tipo_documento": "string o null", "numero_documento": "string o null", "direccion": "string o null", "ciudad": "string o null", "telefono": "string o null", "celular": "string o null", "relacion_deudor": "string o null" },
  "referencias": [ { "nombre": "string", "telefono": "string", "relacion": "string" } ],
  "observaciones": "string o null"
}' WHERE nombre='global_vinculacion';
