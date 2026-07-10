import { createContext, useCallback, useState, type ReactNode } from 'react';
import { setTokens, clearTokens, getAccess } from '../api/client';
import { login as apiLogin, logout as apiLogout, fetchMe } from '../api/queries';
import type { User } from '../api/types';

interface AuthCtx {
  user: User | null;
  loading: boolean;
  login: (usuario: string, password: string) => Promise<{ must_change_password: boolean }>;
  logout: () => Promise<void>;
  refreshUser: () => Promise<void>;
}

export const AuthContext = createContext<AuthCtx | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState<boolean>(!!getAccess());

  const refreshUser = useCallback(async () => {
    if (!getAccess()) {
      setUser(null);
      return;
    }
    try {
      const me = await fetchMe();
      setUser(me);
    } catch {
      clearTokens();
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  const login = useCallback(async (usuario: string, password: string) => {
    const data = await apiLogin(usuario, password);
    setTokens(data.access_token, data.refresh_token);
    setUser(data.user);
    return { must_change_password: data.must_change_password };
  }, []);

  const logout = useCallback(async () => {
    try { await apiLogout(); } catch { /* ignore */ }
    clearTokens();
    setUser(null);
  }, []);

  return (
    <AuthContext.Provider value={{ user, loading, login, logout, refreshUser }}>
      {children}
    </AuthContext.Provider>
  );
}