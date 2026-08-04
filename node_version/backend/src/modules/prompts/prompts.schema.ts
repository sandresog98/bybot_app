import { z } from 'zod';

export const createPromptSchema = z.object({
  nombre: z.string().min(1).max(100),
  version: z.string().min(1).max(20),
  tipo: z.string().min(1).max(50),
  contenido: z.string().min(1),
  notas: z.string().optional(),
  activo: z.boolean().optional().default(false),
});

export const updatePromptSchema = z.object({
  nombre: z.string().min(1).max(100).optional(),
  version: z.string().min(1).max(20).optional(),
  tipo: z.string().min(1).max(50).optional(),
  contenido: z.string().min(1).optional(),
  notas: z.string().optional(),
  activo: z.boolean().optional(),
});
