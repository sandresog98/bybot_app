import fp from 'fastify-plugin';
import type { FastifyInstance, FastifyRequest } from 'fastify';
import { verifyAccessToken, type AccessTokenPayload } from '../core/auth.js';
import { canModule } from '../core/roles.js';

declare module 'fastify' {
  interface FastifyInstance {
    requireAuth: (req: FastifyRequest, _rep?: FastifyReply) => Promise<AccessTokenPayload>;
    requireModule: (module: string) => (req: FastifyRequest, rep: FastifyReply) => Promise<AccessTokenPayload>;
  }

  interface FastifyRequest {
    user?: AccessTokenPayload;
  }
}

export default fp(async (app: FastifyInstance) => {
  app.decorate('requireAuth', async (req: FastifyRequest) => {
    const auth = req.headers.authorization;
    if (!auth || !auth.startsWith('Bearer ')) {
      throw app.httpErrors.unauthorized('Falta token de autenticación.');
    }
    const token = auth.slice(7).trim();
    const payload = await verifyAccessToken(token);
    if (!payload) {
      throw app.httpErrors.unauthorized('Token inválido o expirado.');
    }
    req.user = payload;
    return payload;
  });

  app.decorate('requireModule', (module: string) => async (req: FastifyRequest) => {
    const payload = await app.requireAuth(req);
    if (!canModule(payload.rol, module)) {
      throw app.httpErrors.forbidden('No tienes permiso para este módulo.');
    }
    return payload;
  });
});