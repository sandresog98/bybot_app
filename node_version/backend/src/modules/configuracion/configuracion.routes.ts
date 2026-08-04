import type { FastifyInstance } from 'fastify';
import { prisma } from '../../core/db.js';
import { ok, err, notFound } from '../../core/errors.js';
import { patchConfigSchema } from '../usuarios/usuarios.schema.js';

export async function configuracionRoutes(app: FastifyInstance) {
  // GET /configuracion
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

  // PATCH /configuracion/:clave   (solo admin)
  app.patch('/:clave', { preHandler: app.requireAuth }, async (req, rep) => {
    const rol = req.user!.rol;
    if (rol !== 'admin') {
      return rep.code(403).send(err('Solo admin puede editar configuración.'));
    }
    const clave = (req.params as { clave: string }).clave;
    const parsed = patchConfigSchema.safeParse(req.body);
    if (!parsed.success) return rep.code(400).send(err('Datos inválidos', parsed.error.flatten().fieldErrors));

    const cur = await prisma.appConfiguracion.findUnique({ where: { clave } });
    if (!cur) return rep.code(404).send(notFound('Clave no encontrada'));

    await prisma.appConfiguracion.update({
      where: { clave },
      data: { valor: parsed.data.valor },
    });

    return ok({ clave, valor: parsed.data.valor }, 'Configuración actualizada.');
  });
}