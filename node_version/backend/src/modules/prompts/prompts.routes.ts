import type { FastifyInstance } from 'fastify';
import { ok, err, badRequest, notFound, forbidden } from '../../core/errors.js';
import * as svc from './prompts.service.js';
import { createPromptSchema, updatePromptSchema } from './prompts.schema.js';

async function requireAdmin(req: { user?: { rol?: string } }): Promise<void> {
  if (!req.user || req.user.rol !== 'admin') throw forbidden('Solo administradores pueden gestionar prompts.');
}

export async function promptsRoutes(app: FastifyInstance) {
  // GET / — lista
  app.get('/', { preHandler: app.requireAuth }, async () => {
    return ok(await svc.listPrompts());
  });

  // GET /:id — detalle
  app.get('/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      return ok(await svc.getPrompt(id));
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });

  // POST / — crear (admin)
  app.post('/', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const parse = createPromptSchema.safeParse(req.body);
    if (!parse.success) return rep.code(400).send(badRequest(parse.error.issues.map((i) => i.message).join('; ')));
    try {
      const p = await svc.createPrompt(parse.data, Number((req as { user: { sub: string } }).user.sub));
      return ok(p, 'Prompt creado.');
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });

  // PATCH /:id — actualizar (admin)
  app.patch('/:id', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    const parse = updatePromptSchema.safeParse(req.body);
    if (!parse.success) return rep.code(400).send(badRequest(parse.error.issues.map((i) => i.message).join('; ')));
    try {
      const p = await svc.updatePrompt(id, parse.data, Number((req as { user: { sub: string } }).user.sub));
      return ok(p, 'Prompt actualizado.');
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });

  // DELETE /:id — eliminar (admin)
  app.delete('/:id', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      await svc.deletePrompt(id, Number((req as { user: { sub: string } }).user.sub));
      return ok(null, 'Prompt eliminado.');
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });

  // POST /:id/activar — activar y desactivar otros del mismo tipo (admin)
  app.post('/:id/activar', { preHandler: [app.requireAuth, requireAdmin] }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      const p = await svc.activatePrompt(id, Number((req as { user: { sub: string } }).user.sub));
      return ok(p, 'Prompt activado.');
    } catch (e) {
      rep.code((e as { status?: number }).status ?? 500).send(err((e as Error).message));
    }
  });
}
