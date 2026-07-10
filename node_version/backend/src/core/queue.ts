import { prisma } from './db.js';
import { randomBytes } from 'node:crypto';

export interface PushResult {
  jobId: string;
}

/** Inserta un trabajo pendiente en app_colas_trabajos. Devuelve job_id. */
export async function push(
  cola: string,
  tipoTrabajo: string,
  payload: Record<string, unknown>,
  procesoId?: number,
  prioridad = 5,
): Promise<PushResult> {
  const jobId = `${cola}-${randomBytes(16).toString('hex')}`;
  await prisma.appColasTrabajo.create({
    data: {
      job_id: jobId,
      cola,
      proceso_id: procesoId ?? null,
      tipo_trabajo: tipoTrabajo,
      estado: 'pendiente',
      payload: payload as never,
      prioridad,
    },
  });
  return { jobId };
}

export async function getStatus(jobId: string) {
  return prisma.appColasTrabajo.findFirst({
    where: { job_id: jobId },
    select: {
      job_id: true,
      estado: true,
      resultado: true,
      error_mensaje: true,
      intentos: true,
      started_at: true,
      finished_at: true,
      duracion_ms: true,
    },
  });
}