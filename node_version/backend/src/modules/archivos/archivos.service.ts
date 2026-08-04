import { createHash, randomUUID } from 'node:crypto';
import { extname } from 'node:path';
import { prisma } from '../../core/db.js';
import { store, read, remove } from '../../core/storageClient.js';
import { auditProceso } from '../../core/audit.js';
import { env } from '../../config/env.js';
import { badRequest, notFound } from '../../core/errors.js';
import type { ArchivoTipo } from './archivos.schema.js';

class HttpConflictError extends Error {
  status = 409;
  constructor(msg: string) { super(msg); }
}

// MIME -> extensión por defecto si no viene nombre original
const MIME_TO_EXT: Record<string, string> = {
  'application/pdf': '.pdf',
  'image/jpeg': '.jpg',
  'image/png': '.png',
  'text/html': '.html',
  'application/vnd.ms-excel': '.xls',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '.xlsx',
};

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

  // 2. Validar mime
  const mime = file.mimetype.toLowerCase();
  if (!isMimeAllowed(mime)) throw badRequest(`Tipo de archivo no permitido: ${mime}`);

  // 3. Validar tamaño
  const limit = limitForMime(mime);
  if (!limit) throw badRequest(`Tipo no gestionado: ${mime}`);
  if (file.size > limit) {
    const mb = (limit / 1024 / 1024).toFixed(1);
    throw badRequest(`El archivo excede el tamaño máximo (${mb} MB) para ${mime}.`);
  }

  // 4. Calcular hash
  const hash = createHash('sha256').update(file.data).digest('hex');

  // 5. Dedup por hash dentro del proceso
  const dup = await prisma.procesoArchivo.findFirst({ where: { proceso_id: procesoId, hash_sha256: hash } });
  if (dup) throw new HttpConflictError(`El archivo "${file.filename}" ya fue subido a este proceso (mismo contenido, hash SHA-256 idéntico).`);

  // 6. Renombrar: {tipo}_{codigoProceso}_{uuid}.{ext}
  const ext = extname(file.filename) || MIME_TO_EXT[mime] || '';
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