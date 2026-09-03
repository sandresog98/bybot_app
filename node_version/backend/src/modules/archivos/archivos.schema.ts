import { z } from 'zod';

/**
 * Whitelist global de categorías lógicas de documento (fallback cuando el proceso
 * no tiene entidad). Cuando el proceso SÍ tiene entidad, el conjunto válido lo
 * define el catálogo de la entidad (entidades_tipos_doc.categoria_logica).
 */
export const ARCHIVO_TIPOS = [
  'pagare',
  'estado_cuenta',
  'amortizacion',
  'vinculacion',
  'poder',
  'anexo',
  'identificacion',
  'solicitud_deudor',
  'solicitud_codeudor',
  'otro',
] as const;

export const archivoTipoSchema = z.enum(ARCHIVO_TIPOS);

/**
 * Validación de FORMATO del tipo (defensa contra path traversal / resource injection):
 * solo minúsculas y guion bajo, 1-50 chars. La validación de negocio (que el tipo
 * pertenezca a la entidad del proceso) se hace en el service contra fuente confiable.
 */
export const tipoFormatSchema = z.string().regex(/^[a-z_]{1,50}$/);

export const idSchema = z.object({
  id: z.coerce.number().int().positive(),
});

export type ArchivoTipo = string;