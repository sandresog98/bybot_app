import { useEffect, useState } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import { api } from './api';
import { Login } from './components/Login';
import { Toasts } from './components/Toasts';
import { AppContext } from './context';
import { AppShell } from './layout/AppShell';
import { Home } from './pages/Home';
import { ProcessDetailPage } from './pages/ProcessDetailPage';
import { ProcessesPage } from './pages/ProcessesPage';
import { UsersPage } from './pages/UsersPage';
import type { Toast, ToastType, User } from './types';

let nextToastId = 0;

export function App() {
  const [user, setUser] = useState<User | null>(null);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | undefined>();
  const [checking, setChecking] = useState(true);

  const toast = (type: ToastType, msg: string) => {
    const id = ++nextToastId;
    setToasts(t => [...t, { id, type, message: msg }]);
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), 6000);
  };

  useEffect(() => {
    api.me().then(setUser).catch(() => setUser(null)).finally(() => setChecking(false));
  }, []);

  const login = async (username: string, password: string) => {
    setBusy(true); setMessage(undefined);
    try { const data = await api.login(username, password); setUser(data.user); }
    catch (error) { setMessage(error instanceof Error ? error.message : 'No se pudo ingresar.'); }
    finally { setBusy(false); }
  };

  const logout = () => { void api.logout().catch(() => undefined); setUser(null); };

  if (checking) return <main className="login"><p className="placeholder">Cargando…</p></main>;
  if (!user) return <Login onSubmit={login} busy={busy} message={message} />;

  return (
    <AppContext.Provider value={{ user, toast, logout }}>
      <Routes>
        <Route element={<AppShell />}>
          <Route index element={<Home />} />
          <Route path="procesos" element={<ProcessesPage />} />
          <Route path="procesos/:id" element={<ProcessDetailPage />} />
          {user.role === 'admin' && <Route path="usuarios" element={<UsersPage />} />}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Route>
      </Routes>
      <Toasts toasts={toasts} onDismiss={id => setToasts(t => t.filter(x => x.id !== id))} />
    </AppContext.Provider>
  );
}
