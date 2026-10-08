import { randomUUID } from 'node:crypto';
import { pathToFileURL } from 'node:url';
import Fastify, { type FastifyInstance, type FastifyRequest } from 'fastify';
import cookie from '@fastify/cookie';
import cors from '@fastify/cors';
import multipart from '@fastify/multipart';
import bcrypt from 'bcryptjs';
import { z } from 'zod';
import { audit } from './audit.js';
import { requireAdmin, requireUser, signToken, type AuthUser } from './auth.js';
import { Prisma } from '@prisma/client';
import { config } from './config.js';
import { prisma } from './db.js';
import { initializeStorage, readStoredFile, removeStoredFile, saveBuffer } from './storage.js';
import { normalizeUpload, UnsupportedFileError } from './ingest/normalize.js';
import { normalizeProcess } from './normalize.js';
import { liquidarDemanda, tipoDemandaPorProducto } from './demanda/liquidacion.js';
import { FILE_TIPOS } from './ai/prompts.js';

const loginAttempts = new Map<string, { count: number; resetAt: number }>();
const publicUser = ({ id, username, name, role, active }: { id: number; username: string; name: string; role: string; active: boolean }) => ({ id, username, name, role, active });
const fileTipos = new Set<string>(FILE_TIPOS);
const paginationSchema = z.object({ page: z.coerce.number().int().positive().default(1), limit: z.coerce.number().int().min(1).max(100).default(25) });

function fail(statusCode: number, message: string) { return Object.assign(new Error(message), { statusCode }); }
function processCode() { return `PR-${new Date().getFullYear()}-${randomUUID().slice(0, 8).toUpperCase()}`; }
function checkLoginRateLimit(ip: string) {
  const now = Date.now(); const entry = loginAttempts.get(ip);
  if (!entry || entry.resetAt < now) { loginAttempts.set(ip, { count: 1, resetAt: now + 15 * 60_000 }); return; }
  if (entry.count >= 10) throw fail(429, 'Demasiados intentos. Intenta de nuevo en unos minutos.');
  entry.count += 1;
}
function sniffMime(header: Buffer) {
  if (header.subarray(0, 4).equals(Buffer.from('%PDF'))) return 'application/pdf';
  if (header.subarray(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) return 'image/png';
  if (header[0] === 0xff && header[1] === 0xd8 && header[2] === 0xff) return 'image/jpeg';
  if (header[0] === 0x42 && header[1] === 0x4d) return 'image/bmp';
  if (header[0] === 0x47 && header[1] === 0x49 && header[2] === 0x46) return 'image/gif';
  if (header[0] === 0x49 && header[1] === 0x49 && header[2] === 0x2a && header[3] === 0x00) return 'image/tiff';
  if (header[0] === 0x4d && header[1] === 0x4d && header[2] === 0x00 && header[3] === 0x2a) return 'image/tiff';
  return null;
}
async function assertProcessAccess(processId: number, user: AuthUser) {
  const process = await prisma.process.findUnique({ where: { id: processId } });
  if (!process) throw fail(404, 'Proceso no encontrado.');
  if (user.role !== 'admin' && process.createdBy !== user.id) throw fail(403, 'No tienes acceso a este proceso.');
  return process;
}
async function fileWithAccess(fileId: number, user: AuthUser) {
  const file = await prisma.file.findUnique({ where: { id: fileId } });
  if (!file) throw fail(404, 'Archivo no encontrado.');
  await assertProcessAccess(file.processId, user);
  return file;
}
async function readUpload(request: FastifyRequest) {
  const upload = await request.file();
  if (!upload) throw fail(400, 'Debes adjuntar un archivo.');
  const chunks: Buffer[] = [];
  for await (const chunk of upload.file) chunks.push(Buffer.from(chunk));
  const original = Buffer.concat(chunks);
  if (upload.file.truncated || original.length === 0) throw fail(400, 'Archivo inválido, vacío o demasiado grande.');
  const declaredMime = upload.mimetype.toLowerCase();
  const detectedMime = sniffMime(original.subarray(0, 4_100));
  let normalized;
  try {
    normalized = await normalizeUpload({ originalName: upload.filename, declaredMime, detectedMime, content: original });
  } catch (error) {
    if (error instanceof UnsupportedFileError) throw fail(422, error.message);
    throw error;
  }
  const dot = upload.filename.lastIndexOf('.');
  const base = dot > 0 ? upload.filename.slice(0, dot) : upload.filename;
  return { originalName: upload.filename, storedName: `${base}.${normalized.extension}`, normalized, originalMime: detectedMime ?? declaredMime };
}

export async function buildApp(): Promise<FastifyInstance> {
  const app = Fastify({ bodyLimit: config.UPLOAD_MAX_MB * 1024 * 1024 });
  await app.register(cookie);
  await app.register(cors, { origin: config.FRONTEND_ORIGIN, credentials: true });
  await app.register(multipart, { limits: { fileSize: config.UPLOAD_MAX_MB * 1024 * 1024, files: 1 } });

  app.get('/health', async () => ({ ok: true }));
  app.post('/api/auth/login', async (request, reply) => {
    checkLoginRateLimit(request.ip);
    const body = z.object({ username: z.string().min(1).max(80), password: z.string().min(1).max(256) }).parse(request.body);
    const user = await prisma.user.findUnique({ where: { username: body.username } });
    if (!user || !user.active || !(await bcrypt.compare(body.password, user.passwordHash))) throw fail(401, 'Credenciales inválidas.');
    loginAttempts.delete(request.ip);
    reply.setCookie('session', await signToken(user), { httpOnly: true, secure: process.env.NODE_ENV === 'production', sameSite: 'lax', path: '/', maxAge: 8 * 60 * 60 });
    await audit(user.id, 'login', 'session', user.id);
    return { user: publicUser(user) };
  });
  app.post('/api/auth/logout', async (request, reply) => { const user = await requireUser(request); reply.clearCookie('session', { path: '/' }); await audit(user.id, 'logout', 'session', user.id); return { ok: true }; });
  app.get('/api/auth/me', async (request) => {
    const auth = await requireUser(request); const user = await prisma.user.findUnique({ where: { id: auth.id } });
    if (!user || !user.active) throw fail(401, 'Usuario no disponible.'); return publicUser(user);
  });

  app.get('/api/users', async (request) => {
    await requireAdmin(request); const { page, limit } = paginationSchema.parse(request.query);
    const [items, total] = await prisma.$transaction([prisma.user.findMany({ select: { id: true, username: true, name: true, role: true, active: true, createdAt: true }, orderBy: { name: 'asc' }, skip: (page - 1) * limit, take: limit }), prisma.user.count()]);
    return { items, total, page, limit };
  });
  app.post('/api/users', async (request) => {
    const admin = await requireAdmin(request);
    const body = z.object({ username: z.string().trim().min(3).max(80).regex(/^[a-zA-Z0-9._-]+$/), name: z.string().trim().min(2).max(120), password: z.string().min(12).max(256), role: z.enum(['admin', 'operator']).default('operator') }).parse(request.body);
    const exists = await prisma.user.findUnique({ where: { username: body.username } }); if (exists) throw fail(409, 'El usuario ya existe.');
    const { password, ...userData } = body; const user = await prisma.user.create({ data: { ...userData, passwordHash: await bcrypt.hash(password, 12) } });
    await audit(admin.id, 'create', 'user', user.id, user.username); return publicUser(user);
  });
  app.patch('/api/users/:id', async (request) => {
    const admin = await requireAdmin(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id);
    const body = z.object({ name: z.string().trim().min(2).max(120).optional(), role: z.enum(['admin', 'operator']).optional(), active: z.boolean().optional(), password: z.string().min(12).max(256).optional() }).parse(request.body);
    const target = await prisma.user.findUnique({ where: { id } }); if (!target) throw fail(404, 'Usuario no encontrado.');
    if (target.role === 'admin' && (body.role === 'operator' || body.active === false)) { const admins = await prisma.user.count({ where: { role: 'admin', active: true } }); if (admins <= 1) throw fail(409, 'Debe existir al menos un administrador activo.'); }
    const { password, ...data } = body; const user = await prisma.user.update({ where: { id }, data: { ...data, ...(password ? { passwordHash: await bcrypt.hash(password, 12) } : {}) } });
    await audit(admin.id, 'update', 'user', user.id); return publicUser(user);
  });

  app.get('/api/entidades', async (request) => {
    await requireUser(request);
    return prisma.entidad.findMany({ orderBy: { nombre: 'asc' }, select: { id: true, codigo: true, nombre: true, nit: true } });
  });
  app.post('/api/entidades', async (request) => {
    const admin = await requireAdmin(request);
    const body = z.object({ codigo: z.string().trim().min(2).max(60).regex(/^[a-zA-Z0-9_.-]+$/), nombre: z.string().trim().min(2).max(160), nit: z.string().trim().max(40).optional() }).parse(request.body);
    const exists = await prisma.entidad.findUnique({ where: { codigo: body.codigo } }); if (exists) throw fail(409, 'La entidad ya existe.');
    const entidad = await prisma.entidad.create({ data: body }); await audit(admin.id, 'create', 'entidad', entidad.id, entidad.codigo); return entidad;
  });

  app.get('/api/processes', async (request) => {
    const user = await requireUser(request); const { page, limit } = paginationSchema.parse(request.query);
    const filters = z.object({ q: z.string().trim().max(120).optional(), entidadId: z.coerce.number().int().positive().optional(), status: z.string().trim().max(40).optional() }).parse(request.query);
    const where: Prisma.ProcessWhereInput = user.role === 'admin' ? {} : { createdBy: user.id };
    if (filters.entidadId) where.entidadId = filters.entidadId;
    if (filters.status) where.status = filters.status;
    if (filters.q) {
      where.OR = [
        { deudorNombre: { contains: filters.q } },
        { deudorDocumento: { contains: filters.q } },
        { code: { contains: filters.q } },
        { title: { contains: filters.q } },
      ];
    }
    const [items, total] = await prisma.$transaction([prisma.process.findMany({ where, orderBy: { createdAt: 'desc' }, skip: (page - 1) * limit, take: limit, include: { creator: { select: { name: true } }, entidad: { select: { codigo: true, nombre: true } }, _count: { select: { files: true, analyses: true } } } }), prisma.process.count({ where })]);
    return { items, total, page, limit };
  });
  app.post('/api/processes', async (request) => {
    const user = await requireUser(request); const body = z.object({ title: z.string().trim().min(3).max(120), entidadId: z.number().int().positive().optional() }).parse(request.body);
    if (body.entidadId) { const ent = await prisma.entidad.findUnique({ where: { id: body.entidadId } }); if (!ent) throw fail(400, 'Entidad no válida.'); }
    const process = await prisma.process.create({ data: { code: processCode(), title: body.title, createdBy: user.id, entidadId: body.entidadId ?? null } }); await audit(user.id, 'create', 'process', process.id, process.code); return process;
  });
  app.get('/api/processes/:id', async (request) => {
    const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(id, user);
    return prisma.process.findUniqueOrThrow({ where: { id }, include: { files: { orderBy: { createdAt: 'desc' } }, analyses: { orderBy: { createdAt: 'desc' } }, creator: { select: { name: true } }, entidad: { select: { codigo: true, nombre: true } } } });
  });
  app.get('/api/processes/:id/structured', async (request) => {
    const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(id, user);
    const [partes, credito, campos] = await prisma.$transaction([
      prisma.parte.findMany({ where: { processId: id }, orderBy: { id: 'asc' } }),
      prisma.credito.findFirst({ where: { processId: id } }),
      prisma.extraccionCampo.findMany({ where: { processId: id }, orderBy: { id: 'asc' } }),
    ]);
    const [movimientos, cuotas] = await prisma.$transaction([
      prisma.movimiento.findMany({ where: { processId: id }, orderBy: { orden: 'asc' } }),
      prisma.cuotaAmortizacion.findMany({ where: { processId: id }, orderBy: { id: 'asc' } }),
    ]);
    return { partes, credito, movimientos, cuotas, campos };
  });
  app.post('/api/processes/:id/files', async (request) => {
    const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
    const { tipo } = z.object({ tipo: z.string().optional() }).parse(request.query); if (tipo && !fileTipos.has(tipo)) throw fail(400, 'Tipo de archivo no válido.');
    const { storedName, normalized, originalMime } = await readUpload(request);
    const saved = await saveBuffer(storedName, normalized.buffer, normalized.extension);
    try {
      const file = await prisma.file.create({ data: { processId, originalName: storedName, storageKey: saved.storageKey, mimeType: normalized.mimeType, sizeBytes: saved.sizeBytes, sha256: saved.sha256, originalMimeType: originalMime, converted: normalized.converted, tipo: tipo ?? null, uploadedBy: user.id } });
      await prisma.process.update({ where: { id: processId }, data: { status: 'files_uploaded' } }); await audit(user.id, 'upload', 'file', file.id, file.originalName); return file;
    } catch (error) { await removeStoredFile(saved.storageKey); throw error; }
  });
  app.get('/api/files/:id/download', async (request, reply) => {
    const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const file = await fileWithAccess(id, user); const content = await readStoredFile(file.storageKey);
    await audit(user.id, 'download', 'file', file.id); return reply.type(file.mimeType).header('Content-Disposition', `attachment; filename="${file.originalName.replace(/"/g, '')}"`).send(content);
  });
  app.get('/api/files/:id/view', async (request, reply) => {
    const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const file = await fileWithAccess(id, user); const content = await readStoredFile(file.storageKey);
    await audit(user.id, 'view', 'file', file.id); return reply.type(file.mimeType).header('Content-Disposition', `inline; filename="${file.originalName.replace(/"/g, '')}"`).send(content);
  });
  app.delete('/api/files/:id', async (request) => {
    const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const file = await fileWithAccess(id, user);
    await prisma.file.delete({ where: { id } }); try { await removeStoredFile(file.storageKey); } catch { await audit(user.id, 'storage_cleanup_failed', 'file', file.id); } await audit(user.id, 'delete', 'file', file.id); return { ok: true };
  });
  app.put('/api/processes/:id/files/:fileId', async (request) => {
    const user = await requireUser(request);
    const processId = z.coerce.number().int().parse((request.params as { id: string }).id);
    const fileId = z.coerce.number().int().parse((request.params as { fileId: string }).fileId);
    await assertProcessAccess(processId, user);
    const existing = await prisma.file.findFirst({ where: { id: fileId, processId } });
    if (!existing) throw fail(404, 'Archivo no encontrado en este proceso.');
    const { tipo } = z.object({ tipo: z.string().optional() }).parse(request.query); if (tipo && !fileTipos.has(tipo)) throw fail(400, 'Tipo de archivo no válido.');
    const { storedName, normalized, originalMime } = await readUpload(request);
    const saved = await saveBuffer(storedName, normalized.buffer, normalized.extension);
    try {
      const file = await prisma.file.update({ where: { id: existing.id }, data: { originalName: storedName, storageKey: saved.storageKey, mimeType: normalized.mimeType, sizeBytes: saved.sizeBytes, sha256: saved.sha256, originalMimeType: originalMime, converted: normalized.converted, tipo: tipo ?? existing.tipo } });
      try { await removeStoredFile(existing.storageKey); } catch { await audit(user.id, 'storage_cleanup_failed', 'file', existing.id); }
      await audit(user.id, 'replace', 'file', file.id, file.originalName);
      return file;
    } catch (error) { await removeStoredFile(saved.storageKey); throw error; }
  });
  app.post('/api/processes/:id/analyze', async (request) => {
    const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
    const body = z.object({ fileId: z.number().int().optional() }).parse(request.body ?? {}); const file = body.fileId ? await prisma.file.findFirst({ where: { id: body.fileId, processId } }) : await prisma.file.findFirst({ where: { processId }, orderBy: { createdAt: 'desc' } });
    if (!file) throw fail(400, 'El proceso no tiene archivos para analizar.');
    const analysis = await prisma.analysis.create({ data: { processId, fileId: file.id, status: 'queued', provider: config.AI_PROVIDER, model: config.AI_PROVIDER === 'gemini' ? config.GEMINI_MODEL : config.AI_MODEL || null } }); await prisma.process.update({ where: { id: processId }, data: { status: 'analysis_queued' } }); await audit(user.id, 'queue_analysis', 'analysis', analysis.id); return analysis;
  });
  app.post('/api/processes/:id/analyze-all', async (request) => {
    const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
    const files = await prisma.file.findMany({ where: { processId }, orderBy: { createdAt: 'asc' } });
    if (files.length === 0) throw fail(400, 'El proceso no tiene archivos para analizar.');
    const provider = config.AI_PROVIDER; const model = provider === 'gemini' ? config.GEMINI_MODEL : config.AI_MODEL || null;
    let queued = 0; let skipped = 0;
    for (const file of files) {
      const latest = await prisma.analysis.findFirst({ where: { fileId: file.id }, orderBy: { createdAt: 'desc' } });
      if (latest && ['queued', 'running', 'completed'].includes(latest.status)) { skipped += 1; continue; }
      await prisma.analysis.create({ data: { processId, fileId: file.id, status: 'queued', provider, model } });
      queued += 1;
    }
    if (queued > 0) await prisma.process.update({ where: { id: processId }, data: { status: 'analysis_queued' } });
    await audit(user.id, 'queue_analysis_bulk', 'process', processId, `queued=${queued} skipped=${skipped}`);
    return { queued, skipped, total: files.length };
  });
  app.post('/api/processes/:id/consolidate', async (request) => {
    const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
    const pending = await prisma.analysis.findFirst({ where: { processId, scope: 'process', status: { in: ['queued', 'running'] } }, orderBy: { createdAt: 'desc' } });
    if (pending) return pending;
    const completed = await prisma.analysis.count({ where: { processId, scope: 'file', status: 'completed' } });
    if (completed === 0) throw fail(400, 'Analiza primero al menos un archivo antes de consolidar.');
    const analysis = await prisma.analysis.create({ data: { processId, fileId: null, scope: 'process', status: 'queued', provider: 'consolidator', model: null } });
    await prisma.process.update({ where: { id: processId }, data: { status: 'analysis_queued' } });
    await audit(user.id, 'queue_consolidation', 'process', processId);
    return analysis;
  });
  app.post('/api/processes/:id/liquidacion', async (request) => {
    const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
    const body = z.object({
      cuotaInicial: z.number().int().positive(),
      cuotaCorte: z.number().int().positive(),
      overridesCapital: z.record(z.string(), z.number()).optional(),
      interesesMora: z.number().optional(),
    }).parse(request.body ?? {});
    const credito = await prisma.credito.findFirst({ where: { processId } });
    const cuotas = await prisma.cuotaAmortizacion.findMany({ where: { processId }, orderBy: { numero: 'asc' } });
    if (cuotas.length === 0) throw fail(400, 'No hay cuotas de amortización para el proceso. Consolida el análisis primero.');
    const overridesCapital: Record<number, number> = {};
    for (const [key, value] of Object.entries(body.overridesCapital ?? {})) overridesCapital[Number(key)] = value;
    const result = liquidarDemanda({
      cuotas: cuotas.map(cuota => ({
        numero: cuota.numero, fecha: cuota.fecha,
        cuota: cuota.cuota?.toNumber() ?? null,
        abonoCapital: cuota.abonoCapital?.toNumber() ?? null,
        abonoInteres: cuota.abonoInteres?.toNumber() ?? null,
        saldo: cuota.saldo?.toNumber() ?? null,
      })),
      cuotaInicial: body.cuotaInicial,
      cuotaCorte: body.cuotaCorte,
      overridesCapital,
      interesesMora: body.interesesMora ?? null,
      saldoCapitalExtracto: credito?.saldoCapital?.toNumber() ?? null,
      totalDeudaExtracto: credito?.totalDeuda?.toNumber() ?? null,
      smlmv: config.SMLMV,
      umbralMinimaSmlmv: config.UMBRAL_MINIMA_SMLMV,
    });
    await audit(user.id, 'liquidar', 'process', processId);
    return { ...result, tipoDemanda: tipoDemandaPorProducto(credito?.producto) };
  });
  app.post('/api/analyses/:id/retry', async (request) => { const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const analysis = await prisma.analysis.findUnique({ where: { id } }); if (!analysis) throw fail(404, 'Análisis no encontrado.'); await assertProcessAccess(analysis.processId, user); if (analysis.status !== 'failed' || analysis.attempts >= analysis.maxAttempts) throw fail(409, 'El análisis no se puede reintentar.'); return prisma.analysis.update({ where: { id }, data: { status: 'queued', error: null } }); });
  app.post('/api/analyses/:id/validar', async (request) => {
    const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id);
    const analysis = await prisma.analysis.findUnique({ where: { id } }); if (!analysis) throw fail(404, 'Análisis no encontrado.'); await assertProcessAccess(analysis.processId, user);
    const body = z.object({ datos: z.record(z.string(), z.unknown()) }).parse(request.body);
    await prisma.analysis.update({ where: { id }, data: { validated: body.datos as Prisma.InputJsonValue } });
    if (analysis.scope === 'process') {
      try { await normalizeProcess(prisma, analysis.processId, analysis.id, body.datos); }
      catch (error) { await audit(user.id, 'normalize_failed', 'process', analysis.processId, error instanceof Error ? error.message : undefined); }
    }
    await audit(user.id, 'validate', 'analysis', id); return { ok: true };
  });

  app.setErrorHandler((error, _request, reply) => {
    const status = error instanceof z.ZodError ? 400 : (error as { statusCode?: number }).statusCode ?? 500;
    reply.code(status).send({ message: error.message || 'Error interno.' });
  });
  return app;
}

const isMain = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
if (isMain) {
  await initializeStorage();
  const app = await buildApp();
  await app.listen({ host: '0.0.0.0', port: config.BACKEND_PORT });
}