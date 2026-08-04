import { z } from 'zod';

export const USUARIOS_ROLES = ['admin', 'supervisor', 'operador'] as const;

export const createUsuarioSchema = z.object({
  usuario: z.string().min(2).max(50),
  nombre_completo: z.string().min(2).max(100),
  email: z.string().email().optional(),
  rol: z.enum(USUARIOS_ROLES).default('operador'),
});

export const updateUsuarioSchema = z.object({
  nombre_completo: z.string().min(2).max(100).optional(),
  email: z.string().email().nullable().optional(),
  rol: z.enum(USUARIOS_ROLES).optional(),
  estado_activo: z.boolean().optional(),
});

export const patchConfigSchema = z.object({
  valor: z.string().max(10000),
});

export type CreateUsuarioInput = z.infer<typeof createUsuarioSchema>;
export type UpdateUsuarioInput = z.infer<typeof updateUsuarioSchema>;