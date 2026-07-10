import type { FastifyInstance } from 'fastify';
import { prisma } from '../core/db.js';
import { env } from '../config/env.js';

export async function healthRoutes(app: FastifyInstance) {
  app.get('/health', async () => {
    let db = 'ok';
    try {
      await prisma.$queryRaw`SELECT 1`;
    } catch (e) {
      db = 'error: ' + (e as Error).message;
    }
    return {
      service: 'bybot-backend',
      env: env.APP_ENV,
      db,
      ts: new Date().toISOString(),
    };
  });
}