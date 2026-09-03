import type { FastifyInstance } from 'fastify';
import { ok, err, badRequest } from '../../core/errors.js';
import * as svc from './analisis.service.js';
import { validarProcesoSchema } from './analisis.schema.js';

async function requireEdit(req: { user?: { rol?: string } }): Promise<void> {
  if (!req.user || (req.user.rol !== 'admin' && req.user.rol !== 'supervisor')) {
    throw { status: 403, message: 'Solo admin/supervisor pueden modificar procesos.' };
  }
}

export async function analisisRoutes(app: FastifyInstance) {
  // POST /procesos/:id/analizar
  app.post('/procesos/:id/analizar', { preHandler: [app.requireAuth, requireEdit] }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      const r = await svc.encolarAnalisis(procesoId, Number(req.user!.sub));
      return ok(r, 'Análisis encolado.');
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });

  // GET /procesos/:id/analisis/estado
  app.get('/procesos/:id/analisis/estado', { preHandler: app.requireAuth }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    const estado = await svc.getEstadoTrabajo(procesoId);
    return ok(estado ?? { estado: 'sin_trabajo', error_mensaje: null });
  });

  // GET /procesos/:id/analisis/datos
  app.get('/procesos/:id/analisis/datos', { preHandler: app.requireAuth }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    const datos = await svc.getResultados(procesoId);
    return ok(datos);
  });

  // GET /procesos/:id/analisis/consumo — tokens y costo estimado agregado del proceso
  app.get('/procesos/:id/analisis/consumo', { preHandler: app.requireAuth }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    return ok(await svc.getConsumoProceso(procesoId));
  });

  // POST /procesos/:id/validar — validar datos IA
  app.post('/procesos/:id/validar', { preHandler: [app.requireAuth, requireEdit] }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));
    const parse = validarProcesoSchema.safeParse(req.body);
    if (!parse.success) return rep.code(400).send(badRequest(parse.error.issues.map((i) => i.message).join('; ')));
    try {
      const r = await svc.validarProceso(procesoId, Number(req.user!.sub), parse.data.datos_validados);
      return ok(r, 'Datos validados correctamente.');
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });
}