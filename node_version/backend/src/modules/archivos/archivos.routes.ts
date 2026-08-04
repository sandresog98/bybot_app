import type { FastifyInstance } from 'fastify';
import { ok, err, badRequest, notFound } from '../../core/errors.js';
import { archivoTipoSchema } from './archivos.schema.js';
import * as svc from './archivos.service.js';

export async function archivosRoutes(app: FastifyInstance) {
  // POST /procesos/:id/archivos  (multipart, campo: file + tipo)
  app.post('/procesos/:id/archivos', { preHandler: app.requireAuth }, async (req, rep) => {
    const procesoId = Number((req.params as { id: string }).id);
    if (!Number.isInteger(procesoId)) return rep.code(400).send(badRequest('ID inválido'));

    const data = await req.file();
    if (!data) return rep.code(400).send(badRequest('Falta el archivo en el body multipart.'));

    const fields = data.fields as Record<string, { value?: string }>;
    const tipoRaw = fields?.tipo?.value ?? 'anexo';
    const parsedTipo = archivoTipoSchema.safeParse(tipoRaw);
    if (!parsedTipo.success) return rep.code(400).send(err('Tipo inválido', parsedTipo.error.flatten()));

    const buf = await data.toBuffer();
    try {
      const r = await svc.uploadArchivo(
        procesoId,
        { filename: data.filename, mimetype: data.mimetype, data: buf, size: buf.length },
        parsedTipo.data,
        Number(req.user!.sub),
      );
      // Auto-análisis si la config está activa
      try {
        const { autoEncolarAnalisis } = await import('../analisis/analisis.service.js');
        autoEncolarAnalisis(procesoId, Number(req.user!.sub)).catch(() => {});
      } catch { /* fallo silencioso auto-análisis */ }
      return ok(r, 'Archivo subido.');
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });

  // GET /archivos/:id  (?preview=1 → Content-Disposition inline; else attachment)
  app.get('/archivos/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));

    try {
      const r = await svc.archivoStream(id, Number(req.user!.sub));
      const preview = (req.query as { preview?: string }).preview === '1';
      rep.header('Content-Type', r.mime);
      rep.header('Content-Length', String(r.size));
      rep.header(
        'Content-Disposition',
        (preview ? 'inline' : 'attachment') + `; filename="${encodeURIComponent(r.nombre)}"`,
      );
      // r.stream es un IncomingResponse.body (Readable)
      return rep.send(r.stream);
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });

  // DELETE /archivos/:id
  app.delete('/archivos/:id', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));

    try {
      const a = await svc.deleteArchivo(id, Number(req.user!.sub));
      return ok({ id: a.id }, 'Archivo eliminado.');
    } catch (e) {
      const status = (e as { status?: number }).status ?? 500;
      rep.code(status).send(err((e as Error).message));
      return;
    }
  });

  // GET /archivos/:id/meta  (para previeworía sin descargar todo)
  app.get('/archivos/:id/meta', { preHandler: app.requireAuth }, async (req, rep) => {
    const id = Number((req.params as { id: string }).id);
    if (!Number.isInteger(id)) return rep.code(400).send(badRequest('ID inválido'));
    const { prisma } = await import('../../core/db.js');
    const a = await prisma.procesoArchivo.findUnique({ where: { id } });
    if (!a) return rep.code(404).send(notFound('Archivo no encontrado'));
    return ok({
      id: a.id,
      proceso_id: a.proceso_id,
      nombre_original: a.nombre_original,
      tipo: a.tipo,
      mime_type: a.mime_type,
      tamanio_bytes: a.tamanio_bytes,
      created_at: a.created_at,
    });
  });
}