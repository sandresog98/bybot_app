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

/** Marca un trabajo como completado **/
export async function markComplete(jobId: string, resultado: Record<string, unknown>) {
  await prisma.appColasTrabajo.update({
    where: { job_id: jobId },
    data: {
      estado: 'completado',
      resultado: resultado as never,
      finished_at: new Date(),
    },
  });
}

/** Marca un trabajo como fallido (o lo devuelve a pendiente si quedan intentos). **/
export async function markFailed(jobId: string, error: string) {
  const job = await prisma.appColasTrabajo.findFirst({ where: { job_id: jobId } });
  if (!job) return;
  const nuevosIntentos = job.intentos + 1;
  const nuevoEstado = nuevosIntentos >= job.max_intentos ? 'fallido' : 'pendiente';
  await prisma.appColasTrabajo.update({
    where: { job_id: jobId },
    data: {
      estado: nuevoEstado,
      error_mensaje: error,
      intentos: nuevosIntentos,
      finished_at: new Date(),
    },
  });
}