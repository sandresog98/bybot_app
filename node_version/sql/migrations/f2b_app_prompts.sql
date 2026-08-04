-- Migration: Crear tabla app_prompts para el editor de prompts (F2b)
-- Ejecutar: mysql -u root bybot_consolidado < sql/migrations/f2b_app_prompts.sql

CREATE TABLE IF NOT EXISTS app_prompts (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  nombre      VARCHAR(100) NOT NULL,
  version     VARCHAR(20) NOT NULL DEFAULT '1.0',
  tipo        VARCHAR(50) NOT NULL DEFAULT 'analisis',
  contenido   TEXT NOT NULL,
  activo      TINYINT NOT NULL DEFAULT 1,
  notas       TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO app_prompts (nombre, version, tipo, contenido, activo)
VALUES (
  'Análisis por defecto',
  '1.0',
  'analisis',
  'Eres un asistente legal experto en análisis de documentos de cobranza. Extrae la siguiente información en formato JSON:\n\n{\n  "estado_cuenta": {\n    "numero_credito": string | null,\n    "fecha_corte": string | null,\n    "capital": number | null,\n    "intereses_corrientes": number | null,\n    "intereses_mora": number | null,\n    "honorarios": number | null,\n    "gastos": number | null,\n    "seguros": number | null,\n    "otros_cobros": number | null,\n    "total_deuda": number | null,\n    "tasa_interes_corriente": number | null,\n    "tasa_interes_mora": number | null,\n    "dias_mora": number | null,\n    "fecha_ultimo_pago": string | null,\n    "valor_ultimo_pago": number | null\n  },\n  "entidad": {\n    "nombre": string | null,\n    "nit": string | null\n  },\n  "deudor": {\n    "nombre": string | null,\n    "numero_id": string | null,\n    "tipo_id": string | null\n  },\n  "codeudor": {\n    "nombre": string | null,\n    "numero_id": string | null,\n    "tipo_id": string | null\n  },\n  "referencias": [{\n    "tipo": string,\n    "nombre": string | null,\n    "telefono": string | null,\n    "direccion": string | null\n  }],\n  "solicitudes_vinculacion": string | null,\n  "observaciones": string | null\n}\n\nSi un documento no contiene información para un campo, devuelve null. Para arrays vacíos, devuelve []. Responde ÚNICAMENTE con el JSON, sin texto adicional.',
  1
);
