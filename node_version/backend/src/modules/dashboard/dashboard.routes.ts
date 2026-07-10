import type { FastifyInstance } from 'fastify';
import { prisma } from '../../core/db.js';
import { ok } from '../../core/errors.js';

export async function dashboardRoutes(app: FastifyInstance) {
  app.get('/stats', { preHandler: app.requireAuth }, async () => {
    const [procesos, archivos, analizados, colaPendientes, usuarios, prompts] = await Promise.all([
      prisma.proceso.count(),
      prisma.procesoArchivo.count(),
      prisma.proceso.count({ where: { estado: { in: ['analizado', 'validado', 'completado'] } } }),
      prisma.appColasTrabajo.count({ where: { estado: 'pendiente' } }),
      prisma.controlUsuario.count(),
      prisma.appPrompt.count({ where: { activo: 1 } }),
    ]);

    const porEstado = await prisma.proceso.groupBy({
      by: ['estado'],
      _count: { _all: true },
      orderBy: { _count: { estado: 'desc' } },
    });

    const ultimos = await prisma.proceso.findMany({
      orderBy: { created_at: 'desc' },
      take: 5,
      select: { id: true, codigo: true, estado: true, prioridad: true, created_at: true },
    });

    return ok({
      counts: { procesos, archivos, analizados, cola_pendientes: colaPendientes, usuarios, prompts },
      por_estado: porEstado.map((p) => ({ estado: p.estado, n: p._count._all })),
      ultimos,
    });
  });
}