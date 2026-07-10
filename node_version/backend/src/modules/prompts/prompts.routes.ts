import type { FastifyInstance } from 'fastify';
import { prisma } from '../../core/db.js';
import { ok } from '../../core/errors.js';

export async function promptsRoutes(app: FastifyInstance) {
  app.get('/', { preHandler: app.requireAuth }, async () => {
    const rows = await prisma.appPrompt.findMany({
      orderBy: [{ activo: 'desc' }, { nombre: 'asc' }],
      select: { id: true, nombre: true, version: true, tipo: true, activo: true, updated_at: true },
    });
    return ok(rows.map((r) => ({
      ...r,
      activo: (r as { activo: number }).activo === 1,
    })));
  });
}