import { prisma } from '../../core/db.js';
import { notFound, badRequest } from '../../core/errors.js';
import { audit } from '../../core/audit.js';
import type { createPromptSchema, updatePromptSchema } from './prompts.schema.js';
import type { z } from 'zod';

export async function listPrompts() {
  const rows = await prisma.appPrompt.findMany({
    orderBy: [{ activo: 'desc' }, { nombre: 'asc' }],
    include: { entidad: { select: { id: true, nombre: true, codigo: true } } },
  });
  return rows.map((r) => ({
    ...r,
    activo: r.activo === 1,
    entidad: r.entidad?.nombre ?? null,
    entidad_codigo: r.entidad?.codigo ?? null,
  }));
}

export async function getPrompt(id: number) {
  const p = await prisma.appPrompt.findUnique({ where: { id } });
  if (!p) throw notFound('Prompt no encontrado');
  return { ...p, activo: p.activo === 1 };
}

export async function createPrompt(data: z.infer<typeof createPromptSchema>, usuarioId: number) {
  const existing = await prisma.appPrompt.findUnique({
    where: { uk_prompt_nombre_version: { nombre: data.nombre, version: data.version } },
  });
  if (existing) throw badRequest(`Ya existe un prompt "${data.nombre}" versión "${data.version}"`);

  const p = await prisma.appPrompt.create({
    data: {
      nombre: data.nombre,
      version: data.version,
      tipo: data.tipo,
      entidad_id: data.entidad_id ?? null,
      contenido: data.contenido,
      notas: data.notas ?? null,
      activo: data.activo ? 1 : 0,
      creado_por: usuarioId,
    },
  });

  await audit('prompts', 'crear', usuarioId, {
    entidad_tipo: 'prompt',
    entidad_id: p.id,
    detalle: `Prompt "${p.nombre}" v${p.version} creado.`,
  });

  return { ...p, activo: p.activo === 1 };
}

export async function updatePrompt(id: number, data: z.infer<typeof updatePromptSchema>, usuarioId: number) {
  const existing = await prisma.appPrompt.findUnique({ where: { id } });
  if (!existing) throw notFound('Prompt no encontrado');

  const p = await prisma.appPrompt.update({
    where: { id },
    data: {
      ...(data.nombre !== undefined && { nombre: data.nombre }),
      ...(data.version !== undefined && { version: data.version }),
      ...(data.tipo !== undefined && { tipo: data.tipo }),
      ...(data.entidad_id !== undefined && { entidad_id: data.entidad_id }),
      ...(data.contenido !== undefined && { contenido: data.contenido }),
      ...(data.notas !== undefined && { notas: data.notas }),
      ...(data.activo !== undefined && { activo: data.activo ? 1 : 0 }),
    },
  });

  await audit('prompts', 'actualizar', usuarioId, {
    entidad_tipo: 'prompt',
    entidad_id: p.id,
    detalle: `Prompt "${p.nombre}" v${p.version} actualizado.`,
  });

  return { ...p, activo: p.activo === 1 };
}

export async function deletePrompt(id: number, usuarioId: number) {
  const existing = await prisma.appPrompt.findUnique({ where: { id } });
  if (!existing) throw notFound('Prompt no encontrado');

  await prisma.appPrompt.delete({ where: { id } });

  await audit('prompts', 'eliminar', usuarioId, {
    entidad_tipo: 'prompt',
    entidad_id: id,
    detalle: `Prompt "${existing.nombre}" v${existing.version} eliminado.`,
  });
}

export async function activatePrompt(id: number, usuarioId: number) {
  const p = await prisma.appPrompt.findUnique({ where: { id } });
  if (!p) throw notFound('Prompt no encontrado');

  // Solo un prompt activo por combinación (tipo, entidad). El global (entidad_id NULL)
  // y el específico de cada entidad son mutuamente independientes.
  await prisma.$transaction([
    prisma.appPrompt.updateMany({
      where: { tipo: p.tipo, entidad_id: p.entidad_id, activo: 1 },
      data: { activo: 0 },
    }),
    prisma.appPrompt.update({
      where: { id },
      data: { activo: 1 },
    }),
  ]);

  await audit('prompts', 'activar', usuarioId, {
    entidad_tipo: 'prompt',
    entidad_id: id,
    detalle: `Prompt "${p.nombre}" v${p.version} activado (tipo ${p.tipo}, entidad ${p.entidad_id ?? 'global'}).`,
  });

  const updated = await prisma.appPrompt.findUnique({ where: { id } });
  return { ...updated!, activo: true };
}
