import { z } from 'zod';

/** Taxonomía canónica de categorías lógicas de documento (whitelist). */
export const CATEGORIAS_LOGICAS = [
  'pagare',
  'estado_cuenta',
  'amortizacion',
  'vinculacion',
  'poder',
  'anexo',
  'identificacion',
  'otro',
] as const;

export const categoriaSchema = z.enum(CATEGORIAS_LOGICAS);

export const createEntidadSchema = z.object({
  codigo: z.string().regex(/^[a-z0-9_]{1,50}$/, 'Código: minúsculas, números y _ (1-50)'),
  nombre: z.string().min(1).max(150),
  nit: z.string().max(30).optional(),
  activo: z.boolean().optional().default(true),
});

export const updateEntidadSchema = z.object({
  codigo: z.string().regex(/^[a-z0-9_]{1,50}$/).optional(),
  nombre: z.string().min(1).max(150).optional(),
  nit: z.string().max(30).nullable().optional(),
  activo: z.boolean().optional(),
});

export const createTipoDocSchema = z.object({
  clave: z.string().min(1).max(50),
  label: z.string().min(1).max(120),
  categoria_logica: categoriaSchema,
  obligatorio: z.boolean().optional().default(false),
  orden: z.number().int().min(0).max(999).optional().default(0),
  activo: z.boolean().optional().default(true),
});

export const updateTipoDocSchema = z.object({
  clave: z.string().min(1).max(50).optional(),
  label: z.string().min(1).max(120).optional(),
  categoria_logica: categoriaSchema.optional(),
  obligatorio: z.boolean().optional(),
  orden: z.number().int().min(0).max(999).optional(),
  activo: z.boolean().optional(),
});

export type CreateEntidadInput = z.infer<typeof createEntidadSchema>;
export type UpdateEntidadInput = z.infer<typeof updateEntidadSchema>;
export type CreateTipoDocInput = z.infer<typeof createTipoDocSchema>;
export type UpdateTipoDocInput = z.infer<typeof updateTipoDocSchema>;
