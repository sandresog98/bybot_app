import axios, { type AxiosInstance } from 'axios';

const API_BASE = (import.meta as { env: ImportMetaEnv }).env.VITE_API_BASE_URL || '/api/v1';

export const api: AxiosInstance = axios.create({
  baseURL: API_BASE,
  withCredentials: false,
  headers: { 'Content-Type': 'application/json' },
});

const ACCESS_KEY = 'bybot_access';
const REFRESH_KEY = 'bybot_refresh';

export function setTokens(access: string, refresh: string) {
  localStorage.setItem(ACCESS_KEY, access);
  localStorage.setItem(REFRESH_KEY, refresh);
}

export function clearTokens() {
  localStorage.removeItem(ACCESS_KEY);
  localStorage.removeItem(REFRESH_KEY);
}

export function getAccess(): string | null {
  return localStorage.getItem(ACCESS_KEY);
}
export function getRefresh(): string | null {
  return localStorage.getItem(REFRESH_KEY);
}

api.interceptors.request.use((config) => {
  const t = getAccess();
  if (t) config.headers.Authorization = `Bearer ${t}`;
  return config;
});

let refreshing: Promise<string | null> | null = null;
async function tryRefresh(): Promise<string | null> {
  if (refreshing) return refreshing;
  const r = getRefresh();
  if (!r) return null;
  refreshing = (async () => {
    try {
      const resp = await axios.post(`${API_BASE}/auth/refresh`, { refresh: r });
      const { access_token, refresh_token } = resp.data.data;
      setTokens(access_token, refresh_token);
      return access_token as string;
    } catch {
      clearTokens();
      return null;
    } finally {
      refreshing = null;
    }
  })();
  return refreshing;
}

api.interceptors.response.use(
  (r) => r,
  async (err) => {
    const original = err.config;
    if (err.response?.status === 401 && !original.__retry && !original.url?.includes('/auth/')) {
      original.__retry = true;
      const newTok = await tryRefresh();
      if (newTok) {
        original.headers.Authorization = `Bearer ${newTok}`;
        return api(original);
      }
    }
    return Promise.reject(err);
  },
);