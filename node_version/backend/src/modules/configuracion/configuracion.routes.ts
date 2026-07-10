import type { FastifyInstance } from 'fastify';
import { prisma } from '../../core/db.js';
import { ok } from '../../core/errors.js';

export async function configuracionRoutes(app: FastifyInstance) {
  app.get('/', { preHandler: app.requireAuth }, async () => {
    const rows = await prisma.appConfiguracion.findMany({
      orderBy: [{ categoria: 'asc' }, { clave: 'asc' }],
    });
    return ok(rows.map((r) => ({
      clave: r.clave,
      valor: r.valor,
      tipo: r.tipo,
      categoria: r.categoria,
      descripcion: r.descripcion,
    })));
  });
}