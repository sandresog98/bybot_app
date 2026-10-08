export const STATUS_LABELS: Record<string, string> = {
  created: 'Creado',
  files_uploaded: 'Archivos cargados',
  analysis_queued: 'Análisis en cola',
  analyzed: 'Analizado',
  pending: 'Pendiente',
  queued: 'En cola',
  running: 'En proceso',
  completed: 'Completado',
  failed: 'Fallido',
};

export const statusLabel = (code?: string | null): string => (code ? STATUS_LABELS[code] ?? code : '');

// Estados posibles de un proceso, para el filtro del listado.
export const PROCESS_STATUS_OPTIONS = ['created', 'files_uploaded', 'analysis_queued', 'analyzed'];
