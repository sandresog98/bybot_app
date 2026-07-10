import { z } from 'zod';

export const loginSchema = z.object({
  usuario: z.string().min(1).max(50),
  password: z.string().min(1).max(255),
});

export const changePasswordSchema = z.object({
  nueva: z.string().min(8).max(128),
  confirmacion: z.string().min(1),
});

export const refreshSchema = z.object({
  refresh: z.string().min(1),
});