import { prisma } from '../../core/db.js';
import { badRequest, notFound } from '../../core/errors.js';
import { audit } from '../../core/audit.js';
import type {
  CreateEntidadInput, UpdateEntidadInput, CreateTipoDocInput, UpdateTipoDocInput,
} from './entidades.schema.js';

/** Lista de entidades activas (para el selector al crear un proceso). */
export async function listEntidades() {
  return prisma.entidad.findMany({
    where: { activo: 1 },
    select: { id: true, codigo: true, nombre: true, nit: true },
    orderBy: { nombre: 'asc' },
  });
}

/** Lista completa (incluye inactivas) con conteos — para la administración. */
export async function listEntidadesAdmin() {
  const rows = await prisma.entidad.findMany({
    orderBy: { nombre: 'asc' },
    include: { _count: { select: { tipos_doc: true, procesos: true, prompts: true } } },
  });
  return rows.map((e) => ({
    id: e.id,
    codigo: e.codigo,
    nombre: e.nombre,
    nit: e.nit,
    activo: e.activo === 1,
    created_at: e.created_at,
    total_tipos_doc: e._count.tipos_doc,
    total_procesos: e._count.procesos,
    total_prompts: e._count.prompts,
  }));
}

/** ¿Existe la entidad y está activa? (validación al crear proceso). */
export async function entidadActivaExiste(id: number): Promise<boolean> {
  const e = await prisma.entidad.findFirst({ where: { id, activo: 1 }, select: { id: true } });
  return e !== null;
}

/**
 * Tipos de documento esperados por una entidad. Alimenta el desplegable dinámico
 * del front y el checklist de documentos. `value` es la categoría lógica canónica
 * (la que se guarda en procesos_archivos.tipo y usa el analizador).
 */
export async function getTiposDoc(entidadId: number) {
  const rows = await prisma.entidadTipoDoc.findMany({
    where: { entidad_id: entidadId, activo: 1 },
    select: { clave: true, label: true, categoria_logica: true, obligatorio: true, orden: true },
    orderBy: { orden: 'asc' },
  });
  return rows.map((r) => ({
    value: r.categoria_logica,
    label: r.label,
    clave: r.clave,
    obligatorio: r.obligatorio === 1,
    orden: r.orden,
  }));
}

/**
 * Conjunto de categorías lógicas válidas para la entidad de un proceso.
 * Se usa como whitelist al subir archivos. Devuelve null si el proceso no tiene entidad.
 */
export async function getCategoriasValidas(procesoId: number): Promise<Set<string> | null> {
  const proc = await prisma.proceso.findUnique({
    where: { id: procesoId },
    select: { entidad_id: true },
  });
  if (!proc?.entidad_id) return null;
  const rows = await prisma.entidadTipoDoc.findMany({
    where: { entidad_id: proc.entidad_id, activo: 1 },
    select: { categoria_logica: true },
  });
  return new Set(rows.map((r) => r.categoria_logica));
}

// ─────────────────────────── CRUD entidades (admin) ───────────────────────────

export async function createEntidad(data: CreateEntidadInput, usuarioId: number) {
  const dup = await prisma.entidad.findUnique({ where: { codigo: data.codigo }, select: { id: true } });
  if (dup) throw badRequest(`Ya existe una entidad con código "${data.codigo}".`);
  const e = await prisma.entidad.create({
    data: { codigo: data.codigo, nombre: data.nombre, nit: data.nit ?? null, activo: data.activo ? 1 : 0 },
  });
  await audit('entidades', 'crear', usuarioId, { entidad_tipo: 'entidad', entidad_id: e.id, detalle: `Entidad "${e.nombre}" creada.` });
  return e;
}

export async function updateEntidad(id: number, data: UpdateEntidadInput, usuarioId: number) {
  const cur = await prisma.entidad.findUnique({ where: { id } });
  if (!cur) throw notFound('Entidad no encontrada');
  if (data.codigo && data.codigo !== cur.codigo) {
    const dup = await prisma.entidad.findUnique({ where: { codigo: data.codigo }, select: { id: true } });
    if (dup) throw badRequest(`Ya existe una entidad con código "${data.codigo}".`);
  }
  const e = await prisma.entidad.update({
    where: { id },
    data: {
      ...(data.codigo !== undefined && { codigo: data.codigo }),
      ...(data.nombre !== undefined && { nombre: data.nombre }),
      ...(data.nit !== undefined && { nit: data.nit }),
      ...(data.activo !== undefined && { activo: data.activo ? 1 : 0 }),
    },
  });
  await audit('entidades', 'actualizar', usuarioId, { entidad_tipo: 'entidad', entidad_id: id, detalle: `Entidad "${e.nombre}" actualizada.` });
  return e;
}

export async function deleteEntidad(id: number, usuarioId: number) {
  const cur = await prisma.entidad.findUnique({ where: { id }, include: { _count: { select: { procesos: true } } } });
  if (!cur) throw notFound('Entidad no encontrada');
  if (cur._count.procesos > 0) {
    throw badRequest(`No se puede eliminar: la entidad tiene ${cur._count.procesos} proceso(s) asociados. Desactívala en su lugar.`);
  }
  // El catálogo de documentos y los prompts se borran en cascada (FK ON DELETE CASCADE).
  await prisma.entidad.delete({ where: { id } });
  await audit('entidades', 'eliminar', usuarioId, { entidad_tipo: 'entidad', entidad_id: id, detalle: `Entidad "${cur.nombre}" eliminada.` });
}

// ──────────────────── Catálogo de documentos por entidad (admin) ────────────────────

/** Catálogo completo (incluye inactivos) para administración. */
export async function listCatalogo(entidadId: number) {
  return prisma.entidadTipoDoc.findMany({
    where: { entidad_id: entidadId },
    orderBy: { orden: 'asc' },
  });
}

export async function addTipoDoc(entidadId: number, data: CreateTipoDocInput, usuarioId: number) {
  const ent = await prisma.entidad.findUnique({ where: { id: entidadId }, select: { id: true } });
  if (!ent) throw notFound('Entidad no encontrada');
  const dup = await prisma.entidadTipoDoc.findFirst({ where: { entidad_id: entidadId, categoria_logica: data.categoria_logica }, select: { id: true } });
  if (dup) throw badRequest(`La entidad ya tiene un documento con categoría "${data.categoria_logica}".`);
  const t = await prisma.entidadTipoDoc.create({
    data: {
      entidad_id: entidadId,
      clave: data.clave,
      label: data.label,
      categoria_logica: data.categoria_logica,
      obligatorio: data.obligatorio ? 1 : 0,
      orden: data.orden,
      activo: data.activo ? 1 : 0,
    },
  });
  await audit('entidades', 'crear_tipo_doc', usuarioId, { entidad_tipo: 'entidad_tipo_doc', entidad_id: t.id, detalle: `Documento "${t.label}" (${t.categoria_logica}) añadido a entidad ${entidadId}.` });
  return t;
}

export async function updateTipoDoc(tipoDocId: number, data: UpdateTipoDocInput, usuarioId: number) {
  const cur = await prisma.entidadTipoDoc.findUnique({ where: { id: tipoDocId } });
  if (!cur) throw notFound('Documento no encontrado');
  if (data.categoria_logica && data.categoria_logica !== cur.categoria_logica) {
    const dup = await prisma.entidadTipoDoc.findFirst({ where: { entidad_id: cur.entidad_id, categoria_logica: data.categoria_logica }, select: { id: true } });
    if (dup) throw badRequest(`La entidad ya tiene un documento con categoría "${data.categoria_logica}".`);
  }
  const t = await prisma.entidadTipoDoc.update({
    where: { id: tipoDocId },
    data: {
      ...(data.clave !== undefined && { clave: data.clave }),
      ...(data.label !== undefined && { label: data.label }),
      ...(data.categoria_logica !== undefined && { categoria_logica: data.categoria_logica }),
      ...(data.obligatorio !== undefined && { obligatorio: data.obligatorio ? 1 : 0 }),
      ...(data.orden !== undefined && { orden: data.orden }),
      ...(data.activo !== undefined && { activo: data.activo ? 1 : 0 }),
    },
  });
  await audit('entidades', 'actualizar_tipo_doc', usuarioId, { entidad_tipo: 'entidad_tipo_doc', entidad_id: tipoDocId, detalle: `Documento "${t.label}" actualizado.` });
  return t;
}

export async function deleteTipoDoc(tipoDocId: number, usuarioId: number) {
  const cur = await prisma.entidadTipoDoc.findUnique({ where: { id: tipoDocId } });
  if (!cur) throw notFound('Documento no encontrado');
  await prisma.entidadTipoDoc.delete({ where: { id: tipoDocId } });
  await audit('entidades', 'eliminar_tipo_doc', usuarioId, { entidad_tipo: 'entidad_tipo_doc', entidad_id: tipoDocId, detalle: `Documento "${cur.label}" eliminado.` });
}
