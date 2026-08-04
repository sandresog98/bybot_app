import type { FastifyInstance } from 'fastify';
import { prisma } from '../../core/db.js';
import { ok, err, notFound, badRequest } from '../../core/errors.js';
import { createProcesoSchema, updateProcesoSchema, listProcesosSchema } from './procesos.schema.js';
import * as svc from './procesos.service.js';

export async function procesosRoutes(app: FastifyInstance) {
  // GET /procesos/asignables — lista de usuarios activos para asignar (id, nombre, rol)
  app.get('/asignables', { preHandler: app.requireAuth }, async () => {
    const users = await prisma.controlUsuario.findMany({
      where: { estado_activo: 1 },
      select: { id: true, nombre_completo: true, rol: true },
      orderBy: { nombre_completo: 'asc' },
    });
    return ok(users);
  });

  // GET /procesos?page&limit&estado&tipo&q
  app.get('/', { preHandler: app.requireAuth }, async (req, rep) => {
    const parsed = listProcesosSchema.safeParse(req.query);
    if (!parsed.success) return rep.code(400).send(err('Parámetros inválidos', parsed.error.flatten().fieldErrors));
    const { rows, total, page, limit } = await svc.listProcesos(parsed.data);
    return ok({ rows: rows.map(formatProceso), total, page, limit });
  });

  // POST /procesos
  app.post('/', { preHandler: app.requireAuth }, async (req, rep) => {
    const parsed = createProcesoSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    const proc = await svc.createProceso(parsed.data, Number(req.user!.sub));
    return ok(formatProceso(proc), 'Proceso creado.');
  });

  // GET /procesos/:id
  app.get('/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    const proc = await svc.getProceso(id);
    if (!proc) return rep.code(404).send(notFound('Proceso no encontrado'));
    return ok(formatProcesoDetalle(proc));
  });

  // PATCH /procesos/:id
  app.patch('/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    const parsed = updateProcesoSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    const proc = await svc.updateProceso(id, parsed.data, Number(req.user!.sub));
    if (!proc) return rep.code(404).send(notFound('Proceso no encontrado'));
    return ok(formatProceso(proc), 'Proceso actualizado.');
  });

  // DELETE /procesos/:id (solo admin/supervisor)
  app.delete('/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    const rol = req.user!.rol;
    if (rol !== 'admin' && rol !== 'supervisor') {
      return rep.code(403).send(err('Solo admin/supervisor pueden eliminar procesos.'));
    }
    const proc = await svc.deleteProceso(id, Number(req.user!.sub));
    if (!proc) return rep.code(404).send(notFound('Proceso no encontrado'));
    return ok(null, 'Proceso eliminado.');
  });
}

// Formateadores para emitir datos consistentes
function formatProceso(p: any) {
  return {
    id: p.id,
    codigo: p.codigo,
    tipo: p.tipo,
    estado: p.estado,
    prioridad: p.prioridad,
    creado_por: p.creado_por_user?.nombre_completo ?? null,
    asignado_a: p.asignado_a_user?.nombre_completo ?? null,
    total_archivos: p._count?.archivos ?? 0,
    notas: p.notas ?? null,
    created_at: p.created_at,
    updated_at: p.updated_at,
  };
}

function formatProcesoDetalle(p: any) {
  return {
    ...formatProceso(p),
    creado_por_id: p.creado_por,
    asignado_a_id: p.asignado_a,
    archivos: (p.archivos ?? []).map((a: any) => ({
      id: a.id,
      nombre_original: a.nombre_original,
      nombre_archivo: a.nombre_archivo,
      tipo: a.tipo,
      mime_type: a.mime_type,
      tamanio_bytes: a.tamanio_bytes,
      hash_sha256: a.hash_sha256 ? a.hash_sha256.slice(0, 12) + '…' : null,
      created_at: a.created_at,
    })),
    historial: (p.historial ?? []).map((h: any) => ({
      id: h.id,
      accion: h.accion,
      estado_anterior: h.estado_anterior,
      estado_nuevo: h.estado_nuevo,
      descripcion: h.descripcion,
      fecha: h.fecha,
      usuario: h.usuario?.nombre_completo ?? null,
    })),
  };
}