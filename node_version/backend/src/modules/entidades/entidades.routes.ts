import type { FastifyInstance } from 'fastify';
import { ok, err, badRequest, forbidden } from '../../core/errors.js';
import * as svc from './entidades.service.js';
import {
  createEntidadSchema, updateEntidadSchema, createTipoDocSchema, updateTipoDocSchema,
} from './entidades.schema.js';

async function requireAdmin(req: { user?: { rol?: string } }): Promise<void> {
  if (!req.user || req.user.rol !== 'admin') throw forbidden('Solo administradores pueden gestionar entidades.');
}

function intParam(v: string): number | null {
  const n = Number(v);
  return Number.isInteger(n) && n > 0 ? n : null;
}

export async function entidadesRoutes(app: FastifyInstance) {
  // GET /entidades — entidades activas (selector al crear proceso; cualquier autenticado)
  app.get('/', { preHandler: app.requireAuth }, async () => ok(await svc.listEntidades()));

  // GET /entidades/admin — listado completo con conteos (admin)
  app.get('/admin', { preHandler: [app.requireAuth, requireAdmin] }, async () => ok(await svc.listEntidadesAdmin()));

  // POST /entidades — crear (admin)
  app.post('/', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const parsed = createEntidadSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    try {
      return ok(await svc.createEntidad(parsed.data, Number(req.user!.sub)), 'Entidad creada.');
    } catch (e) { return rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message)); }
  });

  // PATCH /entidades/:id — actualizar (admin)
  app.patch('/:id', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = intParam((req.params as { id: string }).id);
    if (id === null) return rep.code(400).send(badRequest('ID inválido'));
    const parsed = updateEntidadSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    try {
      return ok(await svc.updateEntidad(id, parsed.data, Number(req.user!.sub)), 'Entidad actualizada.');
    } catch (e) { return rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message)); }
  });

  // DELETE /entidades/:id — eliminar (admin)
  app.delete('/:id', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = intParam((req.params as { id: string }).id);
    if (id === null) return rep.code(400).send(badRequest('ID inválido'));
    try {
      await svc.deleteEntidad(id, Number(req.user!.sub));
      return ok(null, 'Entidad eliminada.');
    } catch (e) { return rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message)); }
  });

  // GET /entidades/:id/tipos-doc — catálogo activo mapeado (para el selector de subida)
  app.get('/:id/tipos-doc', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = intParam((req.params as { id: string }).id);
    if (id === null) return rep.code(400).send(badRequest('ID inválido'));
    return ok(await svc.getTiposDoc(id));
  });

  // GET /entidades/:id/catalogo — catálogo completo incl. inactivos (admin)
  app.get('/:id/catalogo', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = intParam((req.params as { id: string }).id);
    if (id === null) return rep.code(400).send(badRequest('ID inválido'));
    return ok(await svc.listCatalogo(id));
  });

  // POST /entidades/:id/tipos-doc — añadir documento al catálogo (admin)
  app.post('/:id/tipos-doc', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = intParam((req.params as { id: string }).id);
    if (id === null) return rep.code(400).send(badRequest('ID inválido'));
    const parsed = createTipoDocSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    try {
      return ok(await svc.addTipoDoc(id, parsed.data, Number(req.user!.sub)), 'Documento añadido.');
    } catch (e) { return rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message)); }
  });

  // PATCH /entidades/tipos-doc/:tid — actualizar documento del catálogo (admin)
  app.patch('/tipos-doc/:tid', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const tid = intParam((req.params as { tid: string }).tid);
    if (tid === null) return rep.code(400).send(badRequest('ID inválido'));
    const parsed = updateTipoDocSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    try {
      return ok(await svc.updateTipoDoc(tid, parsed.data, Number(req.user!.sub)), 'Documento actualizado.');
    } catch (e) { return rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message)); }
  });

  // DELETE /entidades/tipos-doc/:tid — eliminar documento del catálogo (admin)
  app.delete('/tipos-doc/:tid', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const tid = intParam((req.params as { tid: string }).tid);
    if (tid === null) return rep.code(400).send(badRequest('ID inválido'));
    try {
      await svc.deleteTipoDoc(tid, Number(req.user!.sub));
      return ok(null, 'Documento eliminado.');
    } catch (e) { return rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message)); }
  });
}
