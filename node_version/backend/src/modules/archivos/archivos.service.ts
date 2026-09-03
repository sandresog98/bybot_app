import { createHash, randomUUID } from 'node:crypto';
import { prisma } from '../../core/db.js';
import { store, read, remove } from '../../core/storageClient.js';
import { auditProceso } from '../../core/audit.js';
import { env } from '../../config/env.js';
import { badRequest, notFound } from '../../core/errors.js';
import { getCategoriasValidas } from '../entidades/entidades.service.js';
import { ARCHIVO_TIPOS } from './archivos.schema.js';
import type { ArchivoTipo } from './archivos.schema.js';

class HttpConflictError extends Error {
  status = 409;
  constructor(msg: string) { super(msg); }
}

// MIME -> extensión canónica. La extensión SIEMPRE se deriva del MIME validado
// (nunca del nombre de archivo del usuario) para blindar contra path traversal.
const MIME_TO_EXT: Record<string, string> = {
  'application/pdf': '.pdf',
  'image/jpeg': '.jpg',
  'image/png': '.png',
  'image/tiff': '.tif',
  'text/html': '.html',
  'application/vnd.ms-excel': '.xls',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '.xlsx',
};

/**
 * Detecta el MIME real por firma (magic bytes) para los formatos binarios que
 * manejamos. No se confía en el Content-Type del cliente (anti-spoofing).
 * Devuelve null si no hay firma reconocible (html/excel se validan por MIME declarado).
 */
function sniffMime(buf: Buffer): string | null {
  if (buf.length >= 4 && buf[0] === 0x25 && buf[1] === 0x50 && buf[2] === 0x44 && buf[3] === 0x46) return 'application/pdf'; // %PDF
  if (buf.length >= 8 && buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47) return 'image/png';
  if (buf.length >= 3 && buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'image/jpeg';
  if (buf.length >= 4 && ((buf[0] === 0x49 && buf[1] === 0x49 && buf[2] === 0x2a && buf[3] === 0x00) ||
                          (buf[0] === 0x4d && buf[1] === 0x4d && buf[2] === 0x00 && buf[3] === 0x2a))) return 'image/tiff';
  return null;
}

// MIME -> límite de tamaño correcto
function limitForMime(mime: string): number | null {
  const m = mime.toLowerCase();
  if (m.startsWith('image/')) return env.UPLOAD_MAX_SIZE_IMAGE;
  if (m === 'application/pdf') return env.UPLOAD_MAX_SIZE_PDF;
  if (m === 'text/html') return env.UPLOAD_MAX_SIZE_HTML;
  if (m.includes('excel') || m.includes('spreadsheet')) return env.UPLOAD_MAX_SIZE_EXCEL;
  return null;
}

// MIME permitido
function isMimeAllowed(mime: string): boolean {
  const allowed = env.UPLOAD_ALLOWED_MIMES.split(',').map((s) => s.trim().toLowerCase());
  return allowed.includes(mime.toLowerCase());
}

/**
 * Valida que `tipo` sea una categoría lógica permitida para el proceso:
 * - Si el proceso tiene entidad → contra el catálogo de esa entidad.
 * - Si no → contra la whitelist global ARCHIVO_TIPOS.
 */
async function assertTipoValido(procesoId: number, tipo: string): Promise<void> {
  const catalogo = await getCategoriasValidas(procesoId);
  const permitidos = catalogo ?? new Set<string>(ARCHIVO_TIPOS);
  if (!permitidos.has(tipo)) {
    throw badRequest(`Tipo de documento no válido para este proceso: ${tipo}`);
  }
}

export interface UploadResult {
  id: number;
  nombre_original: string;
  nombre_archivo: string;
  tipo: string;
  mime_type: string;
  tamanio_bytes: number;
  hash_sha256: string;
  estado_proceso: string;
}

export async function uploadArchivo(
  procesoId: number,
  file: { filename: string; mimetype: string; data: Buffer; size: number },
  tipo: ArchivoTipo,
  usuarioId: number,
): Promise<UploadResult> {
  // 1. Validar existencia del proceso
  const proc = await prisma.proceso.findUnique({ where: { id: procesoId }, select: { id: true, codigo: true, estado: true } });
  if (!proc) throw notFound('Proceso no encontrado');

  // 2. Validar que el tipo pertenezca al proceso (catálogo de la entidad o whitelist global)
  await assertTipoValido(procesoId, tipo);

  // 3. Determinar el MIME real por firma (anti-spoofing). Para binarios manda la firma;
  //    si no hay firma reconocible (html/excel) se usa el MIME declarado.
  const declared = file.mimetype.toLowerCase();
  const sniffed = sniffMime(file.data);
  const mime = sniffed ?? declared;
  if (!isMimeAllowed(mime)) throw badRequest(`Tipo de archivo no permitido: ${mime}`);
  // Anti-spoofing: solo se rechaza cuando el cliente DECLARA un tipo concreto que
  // contradice la firma real. Un Content-Type genérico/desconocido (octet-stream o
  // vacío) no es una afirmación falsa → manda la firma.
  const declaredGeneric = !declared || declared === 'application/octet-stream';
  if (sniffed && !declaredGeneric && declared !== sniffed && !(declared.startsWith('image/') && sniffed.startsWith('image/'))) {
    throw badRequest(`El contenido del archivo (${sniffed}) no coincide con el tipo declarado (${declared}).`);
  }

  // 4. Validar tamaño
  const limit = limitForMime(mime);
  if (!limit) throw badRequest(`Tipo no gestionado: ${mime}`);
  if (file.size > limit) {
    const mb = (limit / 1024 / 1024).toFixed(1);
    throw badRequest(`El archivo excede el tamaño máximo (${mb} MB) para ${mime}.`);
  }

  // 5. Calcular hash
  const hash = createHash('sha256').update(file.data).digest('hex');

  // 6. Dedup por hash dentro del proceso
  const dup = await prisma.procesoArchivo.findFirst({ where: { proceso_id: procesoId, hash_sha256: hash } });
  if (dup) throw new HttpConflictError(`El archivo "${file.filename}" ya fue subido a este proceso (mismo contenido, hash SHA-256 idéntico).`);

  // 7. Renombrar: {tipo}_{codigoProceso}_{uuid}.{ext}. La extensión se deriva del
  //    MIME validado (no del nombre de usuario) → sin path traversal en la clave.
  const ext = MIME_TO_EXT[mime] ?? '.bin';
  const nombreArchivo = `${tipo}_${proc.codigo}_${randomUUID()}${ext}`;

  // 7. Guardar en botstorage
  const stored = await store(
    { filename: nombreArchivo, mimetype: mime, size: file.size, data: file.data },
    nombreArchivo,
  );

  // 8. Insertar registro + historial + cambiar estado si es el primero
  const ordenActual = await prisma.procesoArchivo.count({ where: { proceso_id: procesoId } });
  const archivo = await prisma.procesoArchivo.create({
    data: {
      proceso_id: procesoId,
      nombre_original: file.filename,
      nombre_archivo: nombreArchivo,
      ruta_storage: stored.key,
      driver: 'local',
      tipo,
      mime_type: mime,
      tamanio_bytes: file.size,
      hash_sha256: hash,
      orden: ordenActual,
      subido_por: usuarioId,
    },
  });

  // 9. Cambiar estado del proceso si era 'creado'
  let estadoProceso = proc.estado;
  if (proc.estado === 'creado') {
    await prisma.proceso.update({ where: { id: procesoId }, data: { estado: 'archivos_cargados' } });
    estadoProceso = 'archivos_cargados';
  }

  await auditProceso(procesoId, usuarioId, 'archivos_subidos', {
    estado_anterior: proc.estado,
    estado_nuevo: estadoProceso,
    descripcion: `Archivo '${file.filename}' subido (${tipo}).`,
  });

  return {
    id: archivo.id,
    nombre_original: archivo.nombre_original,
    nombre_archivo: archivo.nombre_archivo,
    tipo: archivo.tipo,
    mime_type: archivo.mime_type ?? mime,
    tamanio_bytes: archivo.tamanio_bytes ?? file.size,
    hash_sha256: hash,
    estado_proceso: estadoProceso,
  };
}

export async function archivoStream(archivoId: number, usuarioId: number) {
  const a = await prisma.procesoArchivo.findUnique({ where: { id: archivoId } });
  if (!a) throw notFound('Archivo no encontrado');
  const body = await read(a.ruta_storage);
  // Log de descarga
  await auditProceso(a.proceso_id, usuarioId, 'descarga_archivo', {
    descripcion: `Descarga de '${a.nombre_original}'.`,
  });
  return { stream: body, mime: a.mime_type ?? 'application/octet-stream', size: a.tamanio_bytes ?? 0, nombre: a.nombre_original };
}

export async function deleteArchivo(archivoId: number, usuarioId: number) {
  const a = await prisma.procesoArchivo.findUnique({ where: { id: archivoId } });
  if (!a) throw notFound('Archivo no encontrado');

  // Borrar en botstorage
  try { await remove(a.ruta_storage); } catch { /* ignore */ }

  await prisma.procesoArchivo.delete({ where: { id: archivoId } });
  await auditProceso(a.proceso_id, usuarioId, 'archivo_eliminado', {
    descripcion: `Archivo '${a.nombre_original}' eliminado.`,
  });
  return a;
}