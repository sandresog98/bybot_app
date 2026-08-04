import type { ProcesoEstado } from '../api/types';

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function estadoColor(estado: ProcesoEstado | string): string {
  const colors: Record<string, string> = {
    creado: 'secondary',
    archivos_cargados: 'primary',
    en_analisis: 'warning',
    analizado: 'info',
    validado: 'success',
    completado: 'success',
    error: 'danger',
    cancelado: 'secondary',
  };
  return colors[estado] ?? 'secondary';
}

export function formatDate(iso: string): string {
  try {
    return new Date(iso).toLocaleString('es-CO', { dateStyle: 'short', timeStyle: 'short' } as Intl.DateTimeFormatOptions);
  } catch {
    return iso;
  }
}

export function formatDateShort(iso: string): string {
  try {
    return new Date(iso).toLocaleDateString('es-CO');
  } catch {
    return iso;
  }
}

export function iconForMime(mime: string): string {
  if (mime === 'application/pdf') return 'file-earmark-pdf';
  if (mime.startsWith('image/')) return 'file-earmark-image';
  if (mime === 'text/html') return 'file-earmark-code';
  if (mime.includes('excel') || mime.includes('spreadsheet')) return 'file-earmark-spreadsheet';
  return 'file-earmark';
}