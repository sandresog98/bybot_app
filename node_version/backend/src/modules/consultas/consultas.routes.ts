import type { FastifyInstance } from 'fastify';
import { ok, err, badRequest } from '../../core/errors.js';
import * as svc from './consultas.service.js';
import { consultarProcesoSchema } from './consultas.schema.js';

async function requireEdit(req: { user?: { rol?: string } }): Promise<void> {
  if (!req.user || (req.user.rol !== 'admin' && req.user.rol !== 'supervisor')) {
    throw { status: 403, message: 'Solo admin/supervisor pueden ejecutar consultas.' };
  }
}

export async function consultasRoutes(app: FastifyInstance) {
  // POST /procesos/:id/consultar — encola consultas de bots
  app.post('/procesos/:id/consultar', { preHandler: [app.requireAuth, requireEdit] }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    const parse = consultarProcesoSchema.safeParse(req.body);
    if (!parse.success) return rep.code(400).send(badRequest(parse.error.issues.map((i) => i.message).join('; ')));
    try {
      const r = await svc.encolarConsultas(procesoId, Number(req.user!.sub), parse.data.bots);
      return ok(r, 'Consultas encoladas.');
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });

  // GET /procesos/:id/consultas — lista resultados de consultas
  app.get('/procesos/:id/consultas', { preHandler: app.requireAuth }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      const r = await svc.getConsultas(procesoId);
      return ok(r);
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });

  // GET /consultas/:id — detalle de una consulta (con datos del bot)
  app.get('/consultas/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      const r = await svc.getConsultaDetalle(id);
      return ok(r);
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });
}
