import { useQuery } from '@tanstack/react-query';
import { api } from './client';
import type { DashboardStats, User } from './types';

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