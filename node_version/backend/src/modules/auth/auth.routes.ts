import type { FastifyInstance } from 'fastify';
import { attemptLogin, rotateRefresh, logoutSession, changeOwnPassword } from '../../core/auth.js';
import { prisma } from '../../core/db.js';
import { loginSchema, changePasswordSchema, refreshSchema } from './auth.schema.js';
import { ok, err } from '../../core/errors.js';
import { logger } from '../../core/logger.js';

export async function authRoutes(app: FastifyInstance) {
  // POST /auth/login
  app.post('/login', async (req, rep) => {
    const parsed = loginSchema.safeParse(req.body);
    if (!parsed.success) {
      return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    }
    const ip = (req.ip ?? '').toString();
    const ua = (req.headers['user-agent'] ?? '').toString();
    const res = await attemptLogin(parsed.data.usuario, parsed.data.password, ip, ua);
    if (!res.ok || !res.user || !res.access || !res.refresh) {
      logger.info({ usuario: parsed.data.usuario, ok: false }, 'login fallido');
      return rep.code(401).send(err(res.error ?? 'Login fallido'));
    }
    logger.info({ usuario: parsed.data.usuario, rol: res.user.rol }, 'login ok');
    return ok({
      user: res.user,
      access_token: res.access,
      refresh_token: res.refresh,
      must_change_password: res.user.clave_un_solo_uso,
    });
  });

  // POST /auth/refresh
  app.post('/refresh', async (req, rep) => {
    const parsed = refreshSchema.safeParse(req.body);
    if (!parsed.success) {
      return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    }
    const res = await rotateRefresh(parsed.data.refresh);
    if (!res.ok || !res.user || !res.access || !res.refresh) {
      return rep.code(401).send(err(res.error ?? 'Refresh fallido'));
    }
    return ok({
      user: res.user,
      access_token: res.access,
      refresh_token: res.refresh,
    });
  });

  // POST /auth/logout
  app.post('/logout', async (req, rep) => {
    const parsed = refreshSchema.safeParse(req.body);
    if (parsed.success) {
      await logoutSession(parsed.data.refresh);
    }
    return ok(null, 'Sesión cerrada.');
  });

  // POST /auth/change-password (requiere auth)
  app.post('/change-password', { preHandler: app.requireAuth }, async (req, rep) => {
    const parsed = changePasswordSchema.safeParse(req.body);
    if (!parsed.success) {
      return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));
    }
    const u = await prisma.controlUsuario.findUnique({ where: { id: Number(req.user!.sub) } });
    if (!u) return rep.code(404).send(err('Usuario no encontrado'));
    const r = await changeOwnPassword(u.id, parsed.data.nueva, parsed.data.confirmacion);
    if (!r.ok) return rep.code(400).send(err(r.error ?? 'No se pudo cambiar la contraseña'));
    return ok({ must_change_password: false }, 'Contraseña actualizada.');
  });

  // GET /auth/me
  app.get('/me', { preHandler: app.requireAuth }, async (req) => {
    const u = await prisma.controlUsuario.findUnique({
      where: { id: Number(req.user!.sub) },
      select: { id: true, usuario: true, nombre_completo: true, email: true, rol: true, clave_un_solo_uso: true, ultimo_acceso: true },
    });
    return ok({
      ...u,
      modulos: req.user!.modulos,
      clave_un_solo_uso: (u?.clave_un_solo_uso ?? 0) === 1,
    });
  });
}