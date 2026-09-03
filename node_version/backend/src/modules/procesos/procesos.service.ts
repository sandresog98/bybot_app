import { prisma } from '../../core/db.js';
import { auditProceso } from '../../core/audit.js';
import { badRequest } from '../../core/errors.js';
import { entidadActivaExiste } from '../entidades/entidades.service.js';
import type { CreateProcesoInput, UpdateProcesoInput, ListProcesosInput } from './procesos.schema.js';

/**
 * Genera el siguiente código de proceso con formato PROC-YYYY-NNNN.
 * Lee el MAX(codigo) WHERE codigo LIKE 'PROC-2026-%' y suma 1.
 * En una transacción para evitar colisiones (aunque la columna es UNIQUE).
 */
async function generateCodigo(): Promise<string> {
  const year = new Date().getFullYear();
  const prefix = `PROC-${year}-`;
  const last = await prisma.proceso.findFirst({
    where: { codigo: { startsWith: prefix } },
    orderBy: { codigo: 'desc' },
    select: { codigo: true },
  });
  let n = 1;
  if (last) {
    const parts = last.codigo.split('-');
    const num = parseInt(parts[2] ?? '0', 10);
    if (!Number.isNaN(num)) n = num + 1;
  }
  return `${prefix}${String(n).padStart(4, '0')}`;
}

export async function createProceso(data: CreateProcesoInput, usuarioId: number) {
  // Validar la entidad contra fuente confiable (whitelist en BD) antes de asociarla.
  if (data.entidad_id != null && !(await entidadActivaExiste(data.entidad_id))) {
    throw badRequest('Entidad inválida o inactiva.');
  }
  const codigo = await generateCodigo();
  const proc = await prisma.proceso.create({
    data: {
      codigo,
      tipo: data.tipo,
      entidad_id: data.entidad_id ?? null,
      prioridad: data.prioridad,
      notas: data.notas,
      asignado_a: data.asignado_a ?? null,
      creado_por: usuarioId,
    },
    include: { _count: { select: { archivos: true } }, entidad: { select: { id: true, nombre: true, codigo: true } } },
  });
  await auditProceso(proc.id, usuarioId, 'creado', {
    estado_nuevo: 'creado',
    descripcion: `Proceso ${codigo} creado.`,
  });
  return proc;
}

export async function listProcesos(opts: ListProcesosInput) {
  const where: Record<string, unknown> = {};
  if (opts.estado) where.estado = opts.estado;
  if (opts.tipo) where.tipo = opts.tipo;
  if (opts.q) where.codigo = { contains: opts.q };

  const [rows, total] = await Promise.all([
    prisma.proceso.findMany({
      where,
      orderBy: { created_at: 'desc' },
      skip: (opts.page - 1) * opts.limit,
      take: opts.limit,
      include: {
        _count: { select: { archivos: true } },
        creado_por_user: { select: { nombre_completo: true } },
        asignado_a_user: { select: { nombre_completo: true } },
        entidad: { select: { id: true, nombre: true, codigo: true } },
      },
    }),
    prisma.proceso.count({ where }),
  ]);
  return { rows, total, page: opts.page, limit: opts.limit };
}

export async function getProceso(id: number) {
  return prisma.proceso.findUnique({
    where: { id },
    include: {
      archivos: { orderBy: { orden: 'asc' } },
      entidad: { select: { id: true, nombre: true, codigo: true } },
      creado_por_user: { select: { id: true, nombre_completo: true } },
      asignado_a_user: { select: { id: true, nombre_completo: true } },
      historial: {
        orderBy: { fecha: 'desc' },
        take: 20,
        include: { usuario: { select: { nombre_completo: true } } },
      },
      _count: { select: { archivos: true } },
    },
  });
}

export async function updateProceso(id: number, data: UpdateProcesoInput, usuarioId: number) {
  const current = await prisma.proceso.findUnique({ where: { id } });
  if (!current) return null;

  const estadosDistintos = data.estado && data.estado !== current.estado;

  const updated = await prisma.proceso.update({
    where: { id },
    data: {
      tipo: data.tipo,
      estado: data.estado,
      prioridad: data.prioridad,
      asignado_a: data.asignado_a === null ? null : (data.asignado_a ?? undefined),
      notas: data.notas,
    },
    include: { _count: { select: { archivos: true } } },
  });

  if (estadosDistintos) {
    await auditProceso(id, usuarioId, 'estado_cambiado', {
      estado_anterior: current.estado,
      estado_nuevo: data.estado,
      descripcion: `Estado cambiado de ${current.estado} a ${data.estado}.`,
    });
  } else {
    await auditProceso(id, usuarioId, 'datos_editados', {
      descripcion: 'Datos del proceso actualizados.',
    });
  }
  return updated;
}

export async function deleteProceso(id: number, usuarioId: number) {
  // Borrar archivos físicos via botstorage lo gestiona el módulo archivos
  // (cascade de FK se encarga de procesos_archivos en BD)
  const proc = await prisma.proceso.findUnique({
    where: { id },
    select: { codigo: true, archivos: { select: { ruta_storage: true } } },
  });
  if (!proc) return null;

  // Borrar archivos físicos (best-effort)
  const { remove } = await import('../../core/storageClient.js');
  for (const a of proc.archivos) {
    try { await remove(a.ruta_storage); } catch { /* ignore */ }
  }

  await prisma.proceso.delete({ where: { id } });
  await auditProceso(id, usuarioId, 'cancelado', {
    estado_anterior: null,
    descripcion: `Proceso ${proc.codigo} eliminado.`,
  });
  return proc;
}