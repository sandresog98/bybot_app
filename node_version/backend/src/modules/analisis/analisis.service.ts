import { prisma } from '../../core/db.js';
import { push } from '../../core/queue.js';
import { auditProceso } from '../../core/audit.js';
import { notFound, badRequest } from '../../core/errors.js';

/** Lee un valor de app_configuracion */
async function getConfig(clave: string): Promise<string | null> {
  const row = await prisma.appConfiguracion.findUnique({ where: { clave } });
  return row?.valor ?? null;
}

/** Precios IA (USD por 1M tokens) desde config, con defaults seguros. */
async function getPreciosIa(): Promise<{ entrada: number; salida: number }> {
  const [pin, pout] = await Promise.all([
    getConfig('precio_ia_entrada_usd_1m'),
    getConfig('precio_ia_salida_usd_1m'),
  ]);
  return { entrada: Number(pin ?? '0.30') || 0, salida: Number(pout ?? '2.50') || 0 };
}

function costoUsd(tokensEntrada: number, tokensSalida: number, precios: { entrada: number; salida: number }): number {
  const c = (tokensEntrada / 1_000_000) * precios.entrada + (tokensSalida / 1_000_000) * precios.salida;
  return Math.round(c * 1_000_000) / 1_000_000; // redondeo a 6 decimales
}

/** Encola un trabajo de análisis para el proceso. Devuelve { job_id }. */
export async function encolarAnalisis(procesoId: number, usuarioId: number) {
  const proc = await prisma.proceso.findUnique({ where: { id: procesoId }, select: { id: true, estado: true, codigo: true } });
  if (!proc) throw notFound('Proceso no encontrado');
  if (proc.estado === 'en_analisis') throw badRequest('El proceso ya está en análisis.');

  const archivosCount = await prisma.procesoArchivo.count({ where: { proceso_id: procesoId } });
  if (archivosCount === 0) throw badRequest('El proceso no tiene archivos para analizar.');

  const estadoAnterior = proc.estado;
  await prisma.proceso.update({ where: { id: procesoId }, data: { estado: 'en_analisis' } });
  await auditProceso(procesoId, usuarioId, 'encolar_analisis', {
    estado_anterior: estadoAnterior,
    estado_nuevo: 'en_analisis',
    descripcion: `Análisis encolado para ${proc.codigo}.`,
  });

  const { jobId } = await push('bybot:analizar', 'analizar_proceso', { proceso_id: procesoId }, procesoId, 5);
  return { job_id: jobId };
}

/** Encola análisis automático si la config auto_analizar está activa. */
export async function autoEncolarAnalisis(procesoId: number, usuarioId: number) {
  const val = await getConfig('auto_analizar');
  if (val !== 'true') return null;
  return encolarAnalisis(procesoId, usuarioId);
}

/** Estado del último trabajo de análisis para un proceso. */
export async function getEstadoTrabajo(procesoId: number) {
  const job = await prisma.appColasTrabajo.findFirst({
    where: { proceso_id: procesoId, cola: 'bybot:analizar' },
    orderBy: { created_at: 'desc' },
    select: { job_id: true, estado: true, error_mensaje: true, intentos: true, max_intentos: true, duracion_ms: true, started_at: true, finished_at: true },
  });
  return job;
}

/** Último resultados_datos_ia del proceso, con costo estimado de esa corrida. */
export async function getResultados(procesoId: number) {
  const row = await prisma.procesosDatosIa.findFirst({
    where: { proceso_id: procesoId },
    orderBy: { fecha_analisis: 'desc' },
  });
  if (!row) return null;
  const precios = await getPreciosIa();
  return {
    ...row,
    costo_estimado_usd: costoUsd(row.tokens_entrada ?? 0, row.tokens_salida ?? 0, precios),
  };
}

/**
 * Consumo IA agregado del proceso: suma de TODAS sus corridas de análisis
 * (re-analizar crea filas nuevas y cada llamada a Gemini cuesta).
 */
export async function getConsumoProceso(procesoId: number) {
  const agg = await prisma.procesosDatosIa.aggregate({
    where: { proceso_id: procesoId },
    _sum: { tokens_entrada: true, tokens_salida: true, tokens_total: true },
    _count: true,
  });
  const tokens_entrada = agg._sum.tokens_entrada ?? 0;
  const tokens_salida = agg._sum.tokens_salida ?? 0;
  const tokens_total = agg._sum.tokens_total ?? 0;
  const precios = await getPreciosIa();
  return {
    analisis_count: agg._count,
    tokens_entrada,
    tokens_salida,
    tokens_total,
    costo_estimado_usd: costoUsd(tokens_entrada, tokens_salida, precios),
    precios,
  };
}

/** Valida los datos del análisis IA y actualiza el proceso. */
export async function validarProceso(procesoId: number, usuarioId: number, datosValidados: Record<string, unknown>) {
  const proc = await prisma.proceso.findUnique({ where: { id: procesoId }, select: { id: true, estado: true, codigo: true } });
  if (!proc) throw notFound('Proceso no encontrado');
  if (proc.estado !== 'analizado') throw badRequest('El proceso debe estar en estado "analizado" para validar.');

  const datosIa = await prisma.procesosDatosIa.findFirst({
    where: { proceso_id: procesoId },
    orderBy: { version: 'desc' },
  });
  if (!datosIa) throw badRequest('No hay datos de IA para este proceso.');

  const estadoAnterior = proc.estado;
  const now = new Date();

  await prisma.$transaction([
    prisma.procesosDatosIa.update({
      where: { id: datosIa.id },
      data: {
        datos_validados: datosValidados as never,
        validado_por: usuarioId,
        fecha_validacion: now,
      },
    }),
    prisma.proceso.update({
      where: { id: procesoId },
      data: { estado: 'validado', fecha_validacion: now },
    }),
  ]);

  await auditProceso(procesoId, usuarioId, 'validar', {
    estado_anterior: estadoAnterior,
    estado_nuevo: 'validado',
    descripcion: `Datos validados para ${proc.codigo}.`,
    datos_cambio: { datos_validados: datosValidados },
  });

  // Auto-consultar si está habilitado
  try {
    const autoConsultar = await getConfig('auto_consultar');
    if (autoConsultar === 'true') {
      const { encolarConsultas } = await import('../consultas/consultas.service.js');
      await encolarConsultas(procesoId, usuarioId);
    }
  } catch { /* fallo silencioso de auto-consultar */ }

  return { proceso_id: procesoId, estado: 'validado', fecha_validacion: now };
}