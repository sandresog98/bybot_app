import { z } from 'zod';

export const ARCHIVO_TIPOS = [
  'estado_cuenta',
  'anexo',
  'solicitud_deudor',
  'solicitud_codeudor',
  'identificacion',
  'otro',
] as const;

export const archivoTipoSchema = z.enum(ARCHIVO_TIPOS);

export const idSchema = z.object({
  id: z.coerce.number().int().positive(),
});

export type ArchivoTipo = z.infer<typeof archivoTipoSchema>;