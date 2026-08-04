import { randomBytes } from 'node:crypto';
import bcrypt from 'bcryptjs';
import { prisma } from '../../core/db.js';
import { audit } from '../../core/audit.js';
import { badRequest, notFound } from '../../core/errors.js';
import type { CreateUsuarioInput, UpdateUsuarioInput } from './usuarios.schema.js';

export async function listUsuarios() {
  const rows = await prisma.controlUsuario.findMany({
    select: {
      id: true, usuario: true, nombre_completo: true, email: true, rol: true,
      clave_un_solo_uso: true, estado_activo: true, ultimo_acceso: true, created_at: true,
    },
    orderBy: { id: 'asc' },
  });
  return rows.map((r) => ({
    ...r,
    clave_un_solo_uso: r.clave_un_solo_uso === 1,
    estado_activo: r.estado_activo === 1,
  }));
}

export async function createUsuario(data: CreateUsuarioInput, creadorId: number) {
  // Check usuario único
  const exists = await prisma.controlUsuario.findUnique({ where: { usuario: data.usuario } });
  if (exists) throw badRequest('Ya existe un usuario con ese nombre.');

  const temp = randomBytes(6).toString('hex'); // 12 chars
  const hash = await bcrypt.hash(temp, 10);

  const u = await prisma.controlUsuario.create({
    data: {
      usuario: data.usuario,
      password: hash,
      nombre_completo: data.nombre_completo,
      email: data.email ?? null,
      rol: data.rol,
      clave_un_solo_uso: 1,
      estado_activo: 1,
    },
    select: { id: true, usuario: true, nombre_completo: true, email: true, rol: true },
  });

  await audit('usuarios', 'crear', creadorId, {
    entidad_tipo: 'usuario', entidad_id: u.id,
    detalle: `Usuario '${u.usuario}' creado (rol=${u.rol}).`,
  });

  return { ...u, password_temporal: temp };
}

export async function updateUsuario(id: number, data: UpdateUsuarioInput, editorId: number) {
  const u = await prisma.controlUsuario.findUnique({ where: { id } });
  if (!u) throw notFound('Usuario no encontrado');

  const updated = await prisma.controlUsuario.update({
    where: { id },
    data: {
      nombre_completo: data.nombre_completo,
      email: data.email === null ? null : (data.email ?? undefined),
      rol: data.rol,
      estado_activo: data.estado_activo === undefined ? undefined : (data.estado_activo ? 1 : 0),
    },
    select: { id: true, usuario: true, nombre_completo: true, email: true, rol: true, estado_activo: true, clave_un_solo_uso: true },
  });

  await audit('usuarios', 'actualizar', editorId, {
    entidad_tipo: 'usuario', entidad_id: id,
    detalle: `Usuario '${u.usuario}' actualizado.`,
    datos_nuevos: data as Record<string, unknown>,
  });

  return {
    ...updated,
    estado_activo: updated.estado_activo === 1,
    clave_un_solo_uso: updated.clave_un_solo_uso === 1,
  };
}

export async function resetPassword(id: number, editorId: number) {
  const u = await prisma.controlUsuario.findUnique({ where: { id } });
  if (!u) throw notFound('Usuario no encontrado');

  const temp = randomBytes(6).toString('hex');
  const hash = await bcrypt.hash(temp, 10);
  await prisma.controlUsuario.update({
    where: { id },
    data: { password: hash, clave_un_solo_uso: 1 },
  });

  // Invalidar sesiones activas del usuario
  await prisma.controlSesion.deleteMany({ where: { usuario_id: id } });

  await audit('usuarios', 'reset_password', editorId, {
    entidad_tipo: 'usuario', entidad_id: id,
    detalle: `Contraseña de '${u.usuario}' reseteada (un solo uso).`,
  });

  return { password_temporal: temp };
}