import type { Analysis, Detail, Entidad, FileItem, Liquidacion, NewUserPayload, Process, Structured, User } from './types';

const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:3001/api';

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${apiUrl}${path}`, {
    ...options,
    credentials: 'include',
    headers: { ...(options.headers ?? {}) },
  });
  const payload = await response.json().catch(() => null);
  if (!response.ok) throw new Error(payload?.message ?? 'La solicitud falló.');
  return payload as T;
}

export const api = {
  me: () => request<User>('/auth/me'),
  login: (username: string, password: string) =>
    request<{ user: User }>('/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    }),
  logout: () => request<{ ok: boolean }>('/auth/logout', { method: 'POST' }),

  listProcesses: (filters: { q?: string; entidadId?: number; status?: string } = {}) => {
    const params = new URLSearchParams();
    if (filters.q) params.set('q', filters.q);
    if (filters.entidadId) params.set('entidadId', String(filters.entidadId));
    if (filters.status) params.set('status', filters.status);
    const query = params.toString();
    return request<{ items: Process[] }>(`/processes${query ? `?${query}` : ''}`);
  },
  getProcess: (id: number) => request<Detail>(`/processes/${id}`),
  getStructured: (id: number) => request<Structured>(`/processes/${id}/structured`),
  liquidar: (id: number, payload: { cuotaInicial: number; cuotaCorte: number; interesesMora?: number; overridesCapital?: Record<string, number> }) =>
    request<Liquidacion>(`/processes/${id}/liquidacion`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }),
  createProcess: (title: string, entidadId?: number) =>
    request<Process>('/processes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, entidadId }),
    }),

  listEntidades: (options: { all?: boolean } = {}) => request<Entidad[]>(`/entidades${options.all ? '?all=1' : ''}`),
  createEntidad: (payload: { codigo: string; nombre: string; nit?: string }) =>
    request<Entidad>('/entidades', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }),
  updateEntidad: (id: number, payload: Partial<{ codigo: string; nombre: string; nit: string | null; active: boolean }>) =>
    request<Entidad>(`/entidades/${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }),

  listUsers: () => request<{ items: User[] }>('/users'),
  createUser: (payload: NewUserPayload) =>
    request<User>('/users', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    }),

  uploadFile: (processId: number, form: FormData, tipo?: string) =>
    request<FileItem>(`/processes/${processId}/files${tipo ? `?tipo=${encodeURIComponent(tipo)}` : ''}`, { method: 'POST', body: form }),

  analyze: (processId: number, fileId?: number) =>
    request<Analysis>(`/processes/${processId}/analyze`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fileId }),
    }),

  analyzeAll: (processId: number) =>
    request<{ queued: number; skipped: number; total: number }>(`/processes/${processId}/analyze-all`, { method: 'POST' }),

  consolidate: (processId: number) =>
    request<Analysis>(`/processes/${processId}/consolidate`, { method: 'POST' }),

  deleteFile: (fileId: number) => request<{ ok: boolean }>(`/files/${fileId}`, { method: 'DELETE' }),

  replaceFile: (processId: number, fileId: number, form: FormData, tipo?: string) =>
    request<FileItem>(`/processes/${processId}/files/${fileId}${tipo ? `?tipo=${encodeURIComponent(tipo)}` : ''}`, { method: 'PUT', body: form }),

  updateUser: (id: number, payload: Partial<{ name: string; role: 'admin' | 'operator'; active: boolean; password: string }>) =>
    request<User>(`/users/${id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }),

  validarAnalysis: (analysisId: number, datos: unknown) =>
    request<{ ok: boolean }>(`/analyses/${analysisId}/validar`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ datos }),
    }),

  downloadUrl: (fileId: number) => `${apiUrl}/files/${fileId}/download`,
  viewUrl: (fileId: number) => `${apiUrl}/files/${fileId}/view`,
};