import fp from 'fastify-plugin';
import type { FastifyInstance, FastifyRequest } from 'fastify';
import { env } from '../config/env.js';

export default fp(async (app: FastifyInstance) => {
  app.addHook('onRequest', async (req: FastifyRequest, rep) => {
    // /health no requiere token
    if (req.url === '/health' || req.url.startsWith('/health')) return;
    const token = req.headers['x-internal-token'];
    if (!token || token !== env.BOTSTORAGE_INTERNAL_TOKEN) {
      return rep.code(401).send({ success: false, message: 'Token interno inválido o ausente.' });
    }
  });
});