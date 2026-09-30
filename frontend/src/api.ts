import type { Analysis, Detail, Entidad, FileItem, NewUserPayload, Process, User } from './types';

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

  listProcesses: () => request<{ items: Process[] }>('/processes'),
  getProcess: (id: number) => request<Detail>(`/processes/${id}`),
  createProcess: (title: string, entidadId?: number) =>
    request<Process>('/processes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, entidadId }),
    }),

  listEntidades: () => request<Entidad[]>('/entidades'),
  createEntidad: (payload: { codigo: string; nombre: string; nit?: string }) =>
    request<Entidad>('/entidades', {
      method: 'POST',
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

  validarAnalysis: (analysisId: number, datos: unknown) =>
    request<{ ok: boolean }>(`/analyses/${analysisId}/validar`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ datos }),
    }),

  downloadUrl: (fileId: number) => `${apiUrl}/files/${fileId}/download`,
};