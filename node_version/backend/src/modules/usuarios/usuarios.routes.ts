import type { FastifyInstance } from 'fastify';
import { ok, err, badRequest } from '../../core/errors.js';
import { createUsuarioSchema, updateUsuarioSchema } from './usuarios.schema.js';
import * as svc from './usuarios.service.js';

export async function usuariosRoutes(app: FastifyInstance) {
  // GET /usuarios (requireModule('usuarios') ya forzado por mount del router -- pongo aquí de nuevo)
  app.get('/', { preHandler: app.requireModule('usuarios') }, async () => ok(await svc.listUsuarios()));

  // POST /usuarios
  app.post('/', { preHandler: app.requireModule('usuarios') }, async (req, rep) => {
    const parsed = createUsuarioSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    try {
      const u = await svc.createUsuario(parsed.data, Number(req.user!.sub));
      return ok(u, 'Usuario creado.');
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });

  // PATCH /usuarios/:id
  app.patch('/:id', { preHandler: app.requireModule('usuarios') }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    const parsed = updateUsuarioSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    try {
      const u = await svc.updateUsuario(id, parsed.data, Number(req.user!.sub));
      return ok(u, 'Usuario actualizado.');
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });

  // POST /usuarios/:id/reset-password
  app.post('/:id/reset-password', { preHandler: app.requireModule('usuarios') }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    try {
      const r = await svc.resetPassword(id, Number(req.user!.sub));
      return ok(r, 'Contraseña reseteada (mostrar al usuario una sola vez).');
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });
}