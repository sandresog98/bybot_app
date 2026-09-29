import type { FastifyRequest } from 'fastify';
import { SignJWT, jwtVerify } from 'jose';
import { config } from './config.js';

const secret = new TextEncoder().encode(config.JWT_SECRET);
export type AuthUser = { id: number; role: string; username: string };

export async function signToken(user: AuthUser) {
  return new SignJWT({ role: user.role, username: user.username })
    .setProtectedHeader({ alg: 'HS256' }).setSubject(String(user.id)).setIssuedAt().setExpirationTime('8h').sign(secret);
}

export async function requireUser(request: FastifyRequest): Promise<AuthUser> {
  const token = request.cookies.session ?? request.headers.authorization?.replace(/^Bearer\s+/i, '');
  if (!token) throw Object.assign(new Error('No autenticado.'), { statusCode: 401 });
  try {
    const { payload } = await jwtVerify(token, secret);
    if (!payload.sub || typeof payload.role !== 'string' || typeof payload.username !== 'string') throw new Error();
    return { id: Number(payload.sub), role: payload.role, username: payload.username };
  } catch {
    throw Object.assign(new Error('Sesión inválida o expirada.'), { statusCode: 401 });
  }
}

export async function requireAdmin(request: FastifyRequest) {
  const user = await requireUser(request);
  if (user.role !== 'admin') throw Object.assign(new Error('Solo administradores.'), { statusCode: 403 });
  return user;
}
