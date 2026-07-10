import type { FastifyInstance } from 'fastify';
import { ok } from '../../core/errors.js';

export async function procesosRoutes(app: FastifyInstance) {
  // Fase 0b: stub. En Fase 1 se añadirán POST/GET/DELETE etc.
  app.get('/', { preHandler: app.requireAuth }, async () => ok([], 'Módulo en construcción (Fase 1)'));
}