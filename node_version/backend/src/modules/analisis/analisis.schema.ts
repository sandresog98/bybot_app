import { z } from 'zod';

export const estadoTrabajoSchema = z.object({
  estado: z.enum(['pendiente', 'procesando', 'completado', 'fallido', 'cancelado']),
});

export const validarProcesoSchema = z.object({
  datos_validados: z.record(z.unknown()),
});