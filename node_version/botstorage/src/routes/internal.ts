import type { FastifyInstance } from 'fastify';
import { getStorage } from '../storage/StorageFactory.js';

export async function internalRoutes(app: FastifyInstance) {
  // Aceptar octet-stream crudo para /store. Fastify por defecto solo parsea JSON.
  // `addContentTypeParser` con body vacío deja el stream en `req.raw`.
  app.addContentTypeParser('application/octet-stream', { parseAs: 'buffer' }, (_req, body, done) => {
    done(null, body);
  });

  // POST /internal/store
  // Body: bytes del archivo (octet-stream). Headers: X-File-Name (nombre con que guardar),
  //      X-File-Mime (opcional), X-File-Size (opcional, validación).
  app.post('/store', async (req, rep) => {
    const nameHeader = req.headers['x-file-name'];
    if (!nameHeader || typeof nameHeader !== 'string') {
      return rep.code(400).send({ success: false, message: 'Falta header X-File-Name' });
    }
    const storedName = decodeURIComponent(nameHeader);
    const mime = (req.headers['x-file-mime'] as string) || 'application/octet-stream';
    const body = req.body;
    if (!Buffer.isBuffer(body)) {
      return rep.code(400).send({ success: false, message: 'Body no soportado (esperado octet-stream buffer).' });
    }
    const storage = getStorage();
    const res = await storage.store(body, storedName, mime);
    return { success: true, data: res };
  });

  // GET /internal/read/:key  (devuelve bytes del archivo)
  app.get('/read/:key', async (req, rep) => {
    const { key } = req.params as { key: string };
    const storage = getStorage();
    if (!(await storage.exists(key))) {
      return rep.code(404).send({ success: false, message: 'No encontrado' });
    }
    const r = await storage.read(key);
    rep.header('Content-Type', r.mime);
    rep.header('Content-Length', String(r.size));
    return rep.send(r.data);
  });

  // DELETE /internal/delete/:key
  app.delete('/delete/:key', async (req, rep) => {
    const { key } = req.params as { key: string };
    const storage = getStorage();
    if (!(await storage.exists(key))) {
      return rep.code(404).send({ success: false, message: 'No encontrado' });
    }
    await storage.delete(key);
    return rep.code(204).send();
  });
}