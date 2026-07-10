import type { FastifyInstance } from 'fastify';
import { ok } from '../../core/errors.js';

export async function analisisRoutes(app: FastifyInstance) {
  app.get('/', { preHandler: app.requireAuth }, async () => ok(null, 'Módulo en construcción (Fase 2)'));
}