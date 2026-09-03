import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from './client';
import type { DashboardStats, User, Proceso, ProcesoDetalle, Usuario, UsuarioCreado, Prompt, AnalisisDatos, ConsumoIa, ProcesosConsulta, Entidad, EntidadAdmin, EntidadTipoDoc, EntidadTipoDocFull } from './types';

export async function login(usuario: string, password: string) {
  const r = await api.post('/auth/login', { usuario, password });
  return r.data.data;
}

export async function fetchMe(): Promise<User> {
  const r = await api.get('/auth/me');
  return r.data.data;
}

export async function changePassword(nueva: string, confirmacion: string) {
  const r = await api.post('/auth/change-password', { nueva, confirmacion });
  return r.data.data;
}

export async function logout() {
  const refresh = localStorage.getItem('bybot_refresh');
  if (refresh) {
    try { await api.post('/auth/logout', { refresh }); } catch { /* ignore */ }
  }
}

export function useMe(enabled = true) {
  return useQuery<User>({
    queryKey: ['me'],
    queryFn: fetchMe,
    enabled,
    retry: false,
  });
}

export function useDashboardStats(tokenValid: boolean) {
  return useQuery<DashboardStats>({
    queryKey: ['dashboard-stats'],
    queryFn: async () => (await api.get('/dashboard/stats')).data.data,
    enabled: tokenValid,
  });
}

// ============= Procesos =============

export interface ListProcesosParams {
  page?: number;
  limit?: number;
  estado?: string;
  tipo?: string;
  q?: string;
}

export function useProcesos(params: ListProcesosParams = {}, tokenValid: boolean) {
  return useQuery<{ rows: Proceso[]; total: number; page: number; limit: number }>({
    queryKey: ['procesos', params],
    queryFn: async () => (await api.get('/procesos', { params })).data.data,
    enabled: tokenValid,
    placeholderData: (prev) => prev,
  });
}

export function useProceso(id: number | null, tokenValid: boolean) {
  return useQuery<ProcesoDetalle>({
    queryKey: ['proceso', id],
    queryFn: async () => (await api.get(`/procesos/${id}`)).data.data,
    enabled: tokenValid && id != null,
  });
}

export function useCreateProceso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { tipo?: string; entidad_id?: number; prioridad?: number; notas?: string; asignado_a?: number }) =>
      (await api.post('/procesos', data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['procesos'] }),
  });
}

// ============= Entidades =============

export function useEntidades(tokenValid: boolean) {
  return useQuery<Entidad[]>({
    queryKey: ['entidades'],
    queryFn: async () => (await api.get('/entidades')).data.data,
    enabled: tokenValid,
    staleTime: 5 * 60 * 1000,
  });
}

export function useEntidadTiposDoc(entidadId: number | null, tokenValid: boolean) {
  return useQuery<EntidadTipoDoc[]>({
    queryKey: ['entidad-tipos-doc', entidadId],
    queryFn: async () => (await api.get(`/entidades/${entidadId}/tipos-doc`)).data.data,
    enabled: tokenValid && entidadId != null,
    staleTime: 5 * 60 * 1000,
  });
}

// --- Administración de entidades (admin) ---

export function useEntidadesAdmin(tokenValid: boolean) {
  return useQuery<EntidadAdmin[]>({
    queryKey: ['entidades-admin'],
    queryFn: async () => (await api.get('/entidades/admin')).data.data,
    enabled: tokenValid,
  });
}

function invalidateEntidades(qc: ReturnType<typeof useQueryClient>) {
  qc.invalidateQueries({ queryKey: ['entidades-admin'] });
  qc.invalidateQueries({ queryKey: ['entidades'] });
}

export function useCreateEntidad() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { codigo: string; nombre: string; nit?: string; activo?: boolean }) =>
      (await api.post('/entidades', data)).data.data as Entidad,
    onSuccess: () => invalidateEntidades(qc),
  });
}

export function useUpdateEntidad() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) =>
      (await api.patch(`/entidades/${id}`, data)).data.data as Entidad,
    onSuccess: () => invalidateEntidades(qc),
  });
}

export function useDeleteEntidad() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/entidades/${id}`)).data,
    onSuccess: () => invalidateEntidades(qc),
  });
}

export function useCatalogo(entidadId: number | null, tokenValid: boolean) {
  return useQuery<EntidadTipoDocFull[]>({
    queryKey: ['entidad-catalogo', entidadId],
    queryFn: async () => (await api.get(`/entidades/${entidadId}/catalogo`)).data.data,
    enabled: tokenValid && entidadId != null,
  });
}

function invalidateCatalogo(qc: ReturnType<typeof useQueryClient>, entidadId: number) {
  qc.invalidateQueries({ queryKey: ['entidad-catalogo', entidadId] });
  qc.invalidateQueries({ queryKey: ['entidad-tipos-doc', entidadId] });
  qc.invalidateQueries({ queryKey: ['entidades-admin'] });
}

export function useAddTipoDoc(entidadId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: Record<string, unknown>) =>
      (await api.post(`/entidades/${entidadId}/tipos-doc`, data)).data.data,
    onSuccess: () => invalidateCatalogo(qc, entidadId),
  });
}

export function useUpdateTipoDoc(entidadId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ tid, data }: { tid: number; data: Record<string, unknown> }) =>
      (await api.patch(`/entidades/tipos-doc/${tid}`, data)).data.data,
    onSuccess: () => invalidateCatalogo(qc, entidadId),
  });
}

export function useDeleteTipoDoc(entidadId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (tid: number) => (await api.delete(`/entidades/tipos-doc/${tid}`)).data,
    onSuccess: () => invalidateCatalogo(qc, entidadId),
  });
}

export function useUpdateProceso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) =>
      (await api.patch(`/procesos/${id}`, data)).data.data,
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['procesos'] });
      qc.invalidateQueries({ queryKey: ['proceso', vars.id] });
    },
  });
}

export function useDeleteProceso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/procesos/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['procesos'] }),
  });
}

export function useAsignables(tokenValid: boolean) {
  return useQuery<{ id: number; nombre_completo: string; rol: string }[]>({
    queryKey: ['asignables'],
    queryFn: async () => (await api.get('/procesos/asignables')).data.data,
    enabled: tokenValid,
    staleTime: 5 * 60 * 1000,
  });
}

// ============= Archivos =============

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

export async function uploadArchivo(procesoId: number, file: File, tipo: string, onProgress?: (pct: number) => void): Promise<UploadResult> {
  const form = new FormData();
  form.append('tipo', tipo);
  form.append('file', file);
  const r = await api.post(`/procesos/${procesoId}/archivos`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
    onUploadProgress: (e) => {
      if (e.total && onProgress) onProgress(Math.round((e.loaded * 100) / e.total));
    },
  });
  return r.data.data;
}

export function useUploadArchivo(procesoId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ file, tipo }: { file: File; tipo: string }) =>
      uploadArchivo(procesoId, file, tipo),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['proceso', procesoId] });
      qc.invalidateQueries({ queryKey: ['procesos'] });
    },
  });
}

export function useDeleteArchivo(procesoId: number) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/archivos/${id}`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['proceso', procesoId] });
      qc.invalidateQueries({ queryKey: ['procesos'] });
    },
  });
}

// ============= Análisis IA =============

export function useAnalizarProceso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (procesoId: number) => (await api.post(`/procesos/${procesoId}/analizar`)).data.data,
    onSuccess: (_d, procesoId) => {
      qc.invalidateQueries({ queryKey: ['proceso', procesoId] });
      qc.invalidateQueries({ queryKey: ['procesos'] });
    },
  });
}

export function useAnalisisEstado(procesoId: number | null, enabled: boolean) {
  return useQuery<{ estado: string; error_mensaje?: string | null; intentos: number; duracion_ms?: number | null } | null>({
    queryKey: ['analisis-estado', procesoId],
    queryFn: async () => (await api.get(`/procesos/${procesoId}/analisis/estado`)).data.data,
    enabled: enabled && procesoId != null,
    refetchInterval: (query) => {
      const d = query.state.data;
      if (d && (d.estado === 'pendiente' || d.estado === 'procesando')) return 2000;
      return false;
    },
  });
}

export function useAnalisisDatos(procesoId: number | null, enabled: boolean) {
  return useQuery<AnalisisDatos | null>({
    queryKey: ['analisis-datos', procesoId],
    queryFn: async () => (await api.get(`/procesos/${procesoId}/analisis/datos`)).data.data,
    enabled: enabled && procesoId != null,
  });
}

export function useConsumoProceso(procesoId: number | null, enabled: boolean) {
  return useQuery<ConsumoIa>({
    queryKey: ['analisis-consumo', procesoId],
    queryFn: async () => (await api.get(`/procesos/${procesoId}/analisis/consumo`)).data.data,
    enabled: enabled && procesoId != null,
  });
}

export function useValidarProceso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ procesoId, datos_validados }: { procesoId: number; datos_validados: Record<string, unknown> }) =>
      (await api.post(`/procesos/${procesoId}/validar`, { datos_validados })).data.data,
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['proceso', vars.procesoId] });
      qc.invalidateQueries({ queryKey: ['procesos'] });
      qc.invalidateQueries({ queryKey: ['analisis-datos', vars.procesoId] });
    },
  });
}

// URL para preview/descarga de archivo — DEPRECADA: usar fetchArchivoBlob() en su lugar.
// Se mantiene por compatibilidad pero NO expone el token en la URL.
export function archivoUrl(id: number, _preview = false): string {
  return `/archivos/${id}`;
}

/**
 * Descarga un archivo vía fetch con Authorization header y devuelve un Blob URL
 * seguro (sin token expuesto en la URL). Útil para <img src={blobUrl}>, <iframe>,
 * o <a download>.Recordar revocar con URL.revokeObjectURL(blobUrl) al desmontar.
 */
export async function fetchArchivoBlob(id: number): Promise<{ url: string; blob: Blob; mime: string; nombre: string }> {
  const resp = await api.get(`/archivos/${id}`, { responseType: 'blob' });
  const blob = resp.data as Blob;
  const mime = String(resp.headers['content-type'] ?? 'application/octet-stream');
  // Content-Disposition: attachment; filename="XXX" o inline; filename="XXX"
  const cd = String(resp.headers['content-disposition'] ?? '');
  const m = /filename="?([^";]+)"?/.exec(cd);
  const nombre = m ? m[1] : `archivo_${id}`;
  return { url: URL.createObjectURL(blob), blob, mime, nombre };
}

// ============= Usuarios =============

export function useUsuarios(tokenValid: boolean) {
  return useQuery<Usuario[]>({
    queryKey: ['usuarios'],
    queryFn: async () => (await api.get('/usuarios')).data.data,
    enabled: tokenValid,
  });
}

export function useCreateUsuario() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { usuario: string; nombre_completo: string; email?: string; rol: string }) =>
      (await api.post('/usuarios', data)).data.data as UsuarioCreado,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['usuarios'] }),
  });
}

export function useUpdateUsuario() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Record<string, unknown> }) =>
      (await api.patch(`/usuarios/${id}`, data)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['usuarios'] }),
  });
}

export function useResetPassword() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) =>
      (await api.post(`/usuarios/${id}/reset-password`)).data.data as { password_temporal: string },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['usuarios'] }),
  });
}

// ============= Configuración =============

export function useConfiguracion(tokenValid: boolean) {
  return useQuery<Array<{ clave: string; valor: string; tipo: string; categoria: string; descripcion?: string }>>({
    queryKey: ['configuracion'],
    queryFn: async () => (await api.get('/configuracion')).data.data,
    enabled: tokenValid,
  });
}

export function usePatchConfig() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ clave, valor }: { clave: string; valor: string }) =>
      (await api.patch(`/configuracion/${clave}`, { valor })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['configuracion'] }),
  });
}

// ============= Prompts =============

export function usePrompts() {
  return useQuery<Prompt[]>({
    queryKey: ['prompts'],
    queryFn: async () => (await api.get('/prompts')).data.data,
  });
}

export function useCreatePrompt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (data: { nombre: string; version: string; tipo: string; entidad_id?: number | null; contenido: string; notas?: string; activo?: boolean }) =>
      (await api.post('/prompts', data)).data.data as Prompt,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['prompts'] }),
  });
}

export function useUpdatePrompt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, data }: { id: number; data: Partial<{ nombre: string; version: string; tipo: string; entidad_id: number | null; contenido: string; notas: string; activo: boolean }> }) =>
      (await api.patch(`/prompts/${id}`, data)).data.data as Prompt,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['prompts'] }),
  });
}

export function useDeletePrompt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => (await api.delete(`/prompts/${id}`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['prompts'] }),
  });
}

export function useActivatePrompt() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/prompts/${id}/activar`)).data.data as Prompt,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['prompts'] }),
  });
}

// ============= Consultas de bots =============

export function useConsultarProceso() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ procesoId, bots }: { procesoId: number; bots?: string[] }) =>
      (await api.post(`/procesos/${procesoId}/consultar`, { bots })).data.data,
    onSuccess: (_d, vars) => {
      qc.invalidateQueries({ queryKey: ['proceso', vars.procesoId] });
      qc.invalidateQueries({ queryKey: ['consultas', vars.procesoId] });
      qc.invalidateQueries({ queryKey: ['procesos'] });
    },
  });
}

export function useConsultasProceso(procesoId: number | null, enabled: boolean) {
  return useQuery<ProcesosConsulta[]>({
    queryKey: ['consultas', procesoId],
    queryFn: async () => (await api.get(`/procesos/${procesoId}/consultas`)).data.data,
    enabled: enabled && procesoId != null,
    refetchInterval: (query) => {
      const d = query.state.data;
      if (d && d.some((c) => c.estado === 'pendiente' || c.estado === 'procesando')) return 3000;
      return false;
    },
  });
}