import type { FastifyInstance } from 'fastify';
import { prisma } from '../../core/db.js';
import { ok } from '../../core/errors.js';

export async function usuariosRoutes(app: FastifyInstance) {
  app.get('/', { preHandler: app.requireModule('usuarios') }, async () => {
    const rows = await prisma.controlUsuario.findMany({
      select: { id: true, usuario: true, nombre_completo: true, email: true, rol: true, clave_un_solo_uso: true, estado_activo: true, ultimo_acceso: true },
      orderBy: { id: 'asc' },
    });
    return ok(rows.map((r) => ({
      ...r,
      clave_un_solo_uso: (r as { clave_un_solo_uso: number }).clave_un_solo_uso === 1,
      estado_activo: (r as { estado_activo: number }).estado_activo === 1,
    })));
  });
}