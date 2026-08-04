import { z } from 'zod';

export const PROCESO_TIPOS = ['cobranza', 'demanda', 'otro'] as const;
export const PROCESO_ESTADOS = [
  'creado',
  'archivos_cargados',
  'en_analisis',
  'analizado',
  'validado',
  'completado',
  'error',
  'cancelado',
] as const;

export const createProcesoSchema = z.object({
  tipo: z.enum(PROCESO_TIPOS).default('cobranza'),
  prioridad: z.number().int().min(1).max(10).default(5),
  notas: z.string().max(5000).optional(),
  asignado_a: z.number().int().positive().optional(),
});

export const updateProcesoSchema = z.object({
  tipo: z.enum(PROCESO_TIPOS).optional(),
  estado: z.enum(PROCESO_ESTADOS).optional(),
  prioridad: z.number().int().min(1).max(10).optional(),
  asignado_a: z.number().int().positive().nullable().optional(),
  notas: z.string().max(5000).optional(),
});

export const listProcesosSchema = z.object({
  page: z.coerce.number().int().min(1).default(1),
  limit: z.coerce.number().int().min(1).max(100).default(20),
  estado: z.enum(PROCESO_ESTADOS).optional(),
  tipo: z.enum(PROCESO_TIPOS).optional(),
  q: z.string().max(100).optional(),
});

export type CreateProcesoInput = z.infer<typeof createProcesoSchema>;
export type UpdateProcesoInput = z.infer<typeof updateProcesoSchema>;
export type ListProcesosInput = z.infer<typeof listProcesosSchema>;