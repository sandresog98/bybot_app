import { prisma } from './db.js';

/**
 * Registra una entrada en `procesos_historial` y un log de auditoría.
 */
export async function auditProceso(
  procesoId: number,
  usuarioId: number | null,
  accion: string,
  opts: {
    estado_anterior?: string | null;
    estado_nuevo?: string | null;
    descripcion?: string;
    datos_cambio?: Record<string, unknown>;
  } = {},
): Promise<void> {
  await prisma.procesosHistorial.create({
    data: {
      proceso_id: procesoId,
      usuario_id: usuarioId,
      accion,
      estado_anterior: opts.estado_anterior ?? null,
      estado_nuevo: opts.estado_nuevo ?? null,
      descripcion: opts.descripcion ?? null,
      datos_cambio: (opts.datos_cambio as never) ?? undefined,
    },
  });

  await prisma.controlLog.create({
    data: {
      usuario_id: usuarioId,
      accion,
      modulo: 'procesos',
      entidad_tipo: 'proceso',
      entidad_id: procesoId,
      detalle: opts.descripcion ?? null,
      nivel: 'info',
    },
  });
}

/**
 * Log de auditoría general (no atado a un proceso).
 */
export async function audit(
  modulo: string,
  accion: string,
  usuarioId: number | null,
  opts: {
    entidad_tipo?: string;
    entidad_id?: number;
    detalle?: string;
    nivel?: string;
    datos_anteriores?: Record<string, unknown>;
    datos_nuevos?: Record<string, unknown>;
  } = {},
): Promise<void> {
  await prisma.controlLog.create({
    data: {
      usuario_id: usuarioId,
      accion,
      modulo,
      entidad_tipo: opts.entidad_tipo ?? null,
      entidad_id: opts.entidad_id ?? null,
      detalle: opts.detalle ?? null,
      datos_anteriores: (opts.datos_anteriores as never) ?? undefined,
      datos_nuevos: (opts.datos_nuevos as never) ?? undefined,
      nivel: opts.nivel ?? 'info',
    },
  });
}