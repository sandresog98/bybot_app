import bcrypt from 'bcryptjs';
import { SignJWT, jwtVerify, type JWTPayload } from 'jose';
import { randomBytes } from 'node:crypto';
import { prisma } from './db.js';
import { env } from '../config/env.js';

const secret = new TextEncoder().encode(env.BACKEND_JWT_SECRET);

export interface AccessTokenPayload extends JWTPayload {
  sub: string;        // usuario id (string)
  usuario: string;
  rol: string;
  nombre: string;
  modulos: string[];
}

export interface LoginResult {
  ok: boolean;
  error?: string;
  user?: { id: number; usuario: string; rol: string; nombre: string; clave_un_solo_uso: boolean };
  access?: string;
  refresh?: string;
}

export async function attemptLogin(usuario: string, password: string, ip?: string, ua?: string): Promise<LoginResult> {
  const u = await prisma.controlUsuario.findFirst({
    where: { usuario, estado_activo: 1 },
  });
  if (!u) return { ok: false, error: 'Usuario o contraseña incorrectos.' };
  const ok = await bcrypt.compare(password, u.password);
  if (!ok) return { ok: false, error: 'Usuario o contraseña incorrectos.' };

  // Generar tokens
  const modulos = (await import('./roles.js')).modulesFor(u.rol);
  const access = await new SignJWT({ usuario: u.usuario, rol: u.rol, nombre: u.nombre_completo, modulos })
    .setProtectedHeader({ alg: 'HS256' })
    .setSubject(String(u.id))
    .setIssuedAt()
    .setExpirationTime(env.BACKEND_JWT_ACCESS_TTL)
    .sign(secret);

  const refreshToken = randomBytes(32).toString('hex');
  const refreshHash = await bcrypt.hash(refreshToken, 10);
  const refreshMs = parseTtlMs(env.BACKEND_JWT_REFRESH_TTL);
  await prisma.controlSesion.create({
    data: {
      usuario_id: u.id,
      token: refreshHash,
      ip: ip ?? null,
      user_agent: ua ? ua.slice(0, 255) : null,
      expires_at: new Date(Date.now() + refreshMs),
    },
  });

  await prisma.controlUsuario.update({
    where: { id: u.id },
    data: { ultimo_acceso: new Date() },
  });

  return {
    ok: true,
    user: { id: u.id, usuario: u.usuario, rol: u.rol, nombre: u.nombre_completo, clave_un_solo_uso: u.clave_un_solo_uso === 1 },
    access,
    refresh: refreshToken, // se envía al cliente en claro; BD guarda el hash
  };
}

export async function verifyAccessToken(token: string): Promise<AccessTokenPayload | null> {
  try {
    const { payload } = await jwtVerify(token, secret, { algorithms: ['HS256'] });
    return payload as AccessTokenPayload;
  } catch {
    return null;
  }
}

export async function rotateRefresh(refreshToken: string): Promise<LoginResult> {
  // Buscar sesión por hash (no podemos hacer query directa con bcrypt; iteramos las sesiones activas)
  const sesiones = await prisma.controlSesion.findMany({
    where: { expires_at: { gt: new Date() } },
    take: 200,
  });
  let matched: { id: number; usuario_id: number } | null = null;
  for (const s of sesiones) {
    if (await bcrypt.compare(refreshToken, s.token)) {
      matched = { id: s.id, usuario_id: s.usuario_id };
      break;
    }
  }
  if (!matched) return { ok: false, error: 'Refresh token inválido o expirado.' };

  // Borrar sesión usada, crear una nueva
  await prisma.controlSesion.delete({ where: { id: matched.id } });
  const u = await prisma.controlUsuario.findUnique({ where: { id: matched.usuario_id } });
  if (!u) return { ok: false, error: 'Usuario inexistente.' };

  const modulos = (await import('./roles.js')).modulesFor(u.rol);
  const access = await new SignJWT({ usuario: u.usuario, rol: u.rol, nombre: u.nombre_completo, modulos })
    .setProtectedHeader({ alg: 'HS256' })
    .setSubject(String(u.id))
    .setIssuedAt()
    .setExpirationTime(env.BACKEND_JWT_ACCESS_TTL)
    .sign(secret);

  const newRefresh = randomBytes(32).toString('hex');
  const newHash = await bcrypt.hash(newRefresh, 10);
  const refreshMs = parseTtlMs(env.BACKEND_JWT_REFRESH_TTL);
  await prisma.controlSesion.create({
    data: {
      usuario_id: u.id,
      token: newHash,
      expires_at: new Date(Date.now() + refreshMs),
    },
  });

  return {
    ok: true,
    user: { id: u.id, usuario: u.usuario, rol: u.rol, nombre: u.nombre_completo, clave_un_solo_uso: u.clave_un_solo_uso === 1 },
    access,
    refresh: newRefresh,
  };
}

export async function logoutSession(refreshToken: string): Promise<void> {
  // Lo mismo: iterar y comparar
  const sesiones = await prisma.controlSesion.findMany({ take: 200 });
  for (const s of sesiones) {
    if (await bcrypt.compare(refreshToken, s.token)) {
      await prisma.controlSesion.delete({ where: { id: s.id } });
      return;
    }
  }
}

export async function changeOwnPassword(userId: number, nueva: string, confirmacion: string): Promise<{ ok: boolean; error?: string }> {
  if (nueva.length < 8) return { ok: false, error: 'La contraseña debe tener al menos 8 caracteres.' };
  if (nueva !== confirmacion) return { ok: false, error: 'Las contraseñas no coinciden.' };
  const hash = await bcrypt.hash(nueva, 10);
  await prisma.controlUsuario.update({
    where: { id: userId },
    data: { password: hash, clave_un_solo_uso: 0 },
  });
  return { ok: true };
}

export async function resetPassword(userId: number): Promise<string> {
  const temp = randomBytes(6).toString('hex'); // 12 chars
  const hash = await bcrypt.hash(temp, 10);
  await prisma.controlUsuario.update({
    where: { id: userId },
    data: { password: hash, clave_un_solo_uso: 1 },
  });
  return temp;
}

function parseTtlMs(ttl: string): number {
  const m = /^(\d+)([smhd])$/.exec(ttl.trim());
  if (!m) return 7 * 24 * 3600 * 1000;
  const n = Number(m[1]);
  const unit = m[2];
  const mult = unit === 's' ? 1000 : unit === 'm' ? 60_000 : unit === 'h' ? 3_600_000 : 86_400_000;
  return n * mult;
}