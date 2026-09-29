import { randomUUID } from 'node:crypto';
import Fastify, { type FastifyRequest } from 'fastify';
import cookie from '@fastify/cookie';
import cors from '@fastify/cors';
import multipart from '@fastify/multipart';
import bcrypt from 'bcryptjs';
import { z } from 'zod';
import { audit } from './audit.js';
import { requireAdmin, requireUser, signToken, type AuthUser } from './auth.js';
import { config } from './config.js';
import { prisma } from './db.js';
import { initializeStorage, readStoredFile, removeStoredFile, saveUpload } from './storage.js';

const app = Fastify({ bodyLimit: config.UPLOAD_MAX_MB * 1024 * 1024 });
await app.register(cookie);
await app.register(cors, { origin: config.FRONTEND_ORIGIN, credentials: true });
await app.register(multipart, { limits: { fileSize: config.UPLOAD_MAX_MB * 1024 * 1024, files: 1 } });

const loginAttempts = new Map<string, { count: number; resetAt: number }>();
const publicUser = ({ id, username, name, role, active }: { id: number; username: string; name: string; role: string; active: boolean }) => ({ id, username, name, role, active });
const allowedMimes = new Set(['application/pdf', 'text/plain', 'text/csv', 'application/json', 'image/jpeg', 'image/png']);
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

app.get('/api/processes', async (request) => {
  const user = await requireUser(request); const { page, limit } = paginationSchema.parse(request.query); const where = user.role === 'admin' ? {} : { createdBy: user.id };
  const [items, total] = await prisma.$transaction([prisma.process.findMany({ where, orderBy: { createdAt: 'desc' }, skip: (page - 1) * limit, take: limit, include: { creator: { select: { name: true } }, _count: { select: { files: true, analyses: true } } } }), prisma.process.count({ where })]);
  return { items, total, page, limit };
});
app.post('/api/processes', async (request) => {
  const user = await requireUser(request); const body = z.object({ title: z.string().trim().min(3).max(120) }).parse(request.body);
  const process = await prisma.process.create({ data: { code: processCode(), title: body.title, createdBy: user.id } }); await audit(user.id, 'create', 'process', process.id, process.code); return process;
});
app.get('/api/processes/:id', async (request) => {
  const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(id, user);
  return prisma.process.findUniqueOrThrow({ where: { id }, include: { files: { orderBy: { createdAt: 'desc' } }, analyses: { orderBy: { createdAt: 'desc' } }, creator: { select: { name: true } } } });
});
app.post('/api/processes/:id/files', async (request) => {
  const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
  const upload = await request.file(); if (!upload) throw fail(400, 'Debes adjuntar un archivo.');
  const saved = await saveUpload(upload.filename, upload.file);
  const detectedMime = sniffMime(saved.header); const mimeType = detectedMime ?? upload.mimetype.toLowerCase();
  if (upload.file.truncated || saved.sizeBytes === 0 || !allowedMimes.has(mimeType) || (detectedMime && upload.mimetype !== 'application/octet-stream' && upload.mimetype !== detectedMime)) { await removeStoredFile(saved.storageKey); throw fail(400, 'Archivo inválido, vacío, demasiado grande o de formato no permitido.'); }
  try {
    const file = await prisma.file.create({ data: { processId, originalName: upload.filename, storageKey: saved.storageKey, mimeType, sizeBytes: saved.sizeBytes, sha256: saved.sha256, uploadedBy: user.id } });
    await prisma.process.update({ where: { id: processId }, data: { status: 'files_uploaded' } }); await audit(user.id, 'upload', 'file', file.id, file.originalName); return file;
  } catch (error) { await removeStoredFile(saved.storageKey); throw error; }
});
app.get('/api/files/:id/download', async (request, reply) => {
  const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const file = await fileWithAccess(id, user); const content = await readStoredFile(file.storageKey);
  await audit(user.id, 'download', 'file', file.id); return reply.type(file.mimeType).header('Content-Disposition', `attachment; filename="${file.originalName.replace(/"/g, '')}"`).send(content);
});
app.delete('/api/files/:id', async (request) => {
  const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const file = await fileWithAccess(id, user);
  await prisma.file.delete({ where: { id } }); try { await removeStoredFile(file.storageKey); } catch { await audit(user.id, 'storage_cleanup_failed', 'file', file.id); } await audit(user.id, 'delete', 'file', file.id); return { ok: true };
});
app.post('/api/processes/:id/analyze', async (request) => {
  const user = await requireUser(request); const processId = z.coerce.number().int().parse((request.params as { id: string }).id); await assertProcessAccess(processId, user);
  const body = z.object({ fileId: z.number().int().optional() }).parse(request.body ?? {}); const file = body.fileId ? await prisma.file.findFirst({ where: { id: body.fileId, processId } }) : await prisma.file.findFirst({ where: { processId }, orderBy: { createdAt: 'desc' } });
  if (!file) throw fail(400, 'El proceso no tiene archivos para analizar.');
  const analysis = await prisma.analysis.create({ data: { processId, fileId: file.id, status: 'queued', provider: config.AI_API_URL || null, model: config.AI_MODEL || null } }); await prisma.process.update({ where: { id: processId }, data: { status: 'analysis_queued' } }); await audit(user.id, 'queue_analysis', 'analysis', analysis.id); return analysis;
});
app.post('/api/analyses/:id/retry', async (request) => { const user = await requireUser(request); const id = z.coerce.number().int().parse((request.params as { id: string }).id); const analysis = await prisma.analysis.findUnique({ where: { id } }); if (!analysis) throw fail(404, 'Análisis no encontrado.'); await assertProcessAccess(analysis.processId, user); if (analysis.status !== 'failed' || analysis.attempts >= analysis.maxAttempts) throw fail(409, 'El análisis no se puede reintentar.'); return prisma.analysis.update({ where: { id }, data: { status: 'queued', error: null } }); });

app.setErrorHandler((error, _request, reply) => reply.code((error as { statusCode?: number }).statusCode ?? 500).send({ message: error.message || 'Error interno.' }));
await initializeStorage();
await app.listen({ host: '0.0.0.0', port: config.BACKEND_PORT });
