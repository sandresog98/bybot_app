import { z } from 'zod';

export const consultarProcesoSchema = z.object({
  bots: z.array(z.string()).optional(),
});

export const consultaResumenSchema = z.object({
  id: z.number(),
  proceso_id: z.number(),
  persona_tipo: z.string(),
  bot: z.string(),
  numero_id: z.string(),
  estado: z.string(),
  resultado_resumen: z.unknown().nullable(),
  orden_ejecucion: z.number(),
  created_at: z.string(),
});

export const personaSchema = z.object({
  tipo: z.string(),
  numero_id: z.string(),
  nombre: z.string().optional(),
});
