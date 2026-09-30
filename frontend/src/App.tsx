import { useEffect, useState } from 'react';
import { api } from './api';
import { Login } from './components/Login';
import { ProcessDetail } from './components/ProcessDetail';
import { ProcessList } from './components/ProcessList';
import { Toasts } from './components/Toasts';
import { UsersPanel } from './components/UsersPanel';
import type { Detail, NewUserPayload, Process, Toast, User } from './types';

let nextToastId = 0;

export function App() {
  const [user, setUser] = useState<User | null>(null);
  const [users, setUsers] = useState<User[]>([]);
  const [processes, setProcesses] = useState<Process[]>([]);
  const [selected, setSelected] = useState<Detail | null>(null);
  const [toasts, setToasts] = useState<Toast[]>([]);
  const [busy, setBusy] = useState<Record<string, boolean>>({});

  const toast = (type: Toast['type'], message: string) => {
    const id = ++nextToastId;
    setToasts(t => [...t, { id, type, message }]);
    setTimeout(() => setToasts(t => t.filter(x => x.id !== id)), 6000);
  };

  const dismissToast = (id: number) => setToasts(t => t.filter(x => x.id !== id));

  const run = async (key: string, fn: () => Promise<void>) => {
    setBusy(b => ({ ...b, [key]: true }));
    try {
      await fn();
    } catch (error) {
      toast('error', error instanceof Error ? error.message : 'Ocurrió un error.');
    } finally {
      setBusy(b => ({ ...b, [key]: false }));
    }
  };

  const refresh = async () => {
    const currentUser = await api.me();
    setUser(currentUser);
    const page = await api.listProcesses();
    setProcesses(page.items);
    if (currentUser.role === 'admin') {
      const userPage = await api.listUsers();
      setUsers(userPage.items);
    }
  };

  useEffect(() => {
    refresh().catch(() => { setUser(null); setSelected(null); });
  }, []);

  const login = (username: string, password: string) =>
    run('login', async () => { const data = await api.login(username, password); setUser(data.user); await refresh(); toast('success', 'Bienvenido.'); });

  const logout = () =>
    run('logout', async () => { await api.logout(); setUser(null); setSelected(null); setProcesses([]); });

  const openProcess = (id: number) => void run('open', async () => { setSelected(await api.getProcess(id)); });

  const createProcess = (title: string) =>
    run('createProcess', async () => { const p = await api.createProcess(title); toast('success', 'Proceso creado.'); await refresh(); setSelected(await api.getProcess(p.id)); });

  const createUser = (payload: NewUserPayload) =>
    run('createUser', async () => { await api.createUser(payload); toast('success', 'Usuario creado.'); await refresh(); });

  const upload = (form: FormData) =>
    run('upload', async () => {
      if (!selected) return;
      await api.uploadFile(selected.id, form);
      toast('success', 'Archivo subido.');
      setSelected(await api.getProcess(selected.id));
      await refresh();
    });

  const analyze = (fileId?: number) =>
    run('analyze', async () => {
      if (!selected) return;
      const analysis = await api.analyze(selected.id, fileId);
      toast(analysis.status === 'completed' ? 'success' : 'error', analysis.status === 'completed' ? 'Análisis completado.' : `Análisis falló: ${analysis.error}`);
      setSelected(await api.getProcess(selected.id));
      await refresh();
    });

  const download = (fileId: number, name: string) =>
    void run('download', async () => {
      const response = await fetch(api.downloadUrl(fileId), { credentials: 'include' });
      if (!response.ok) throw new Error('No se pudo descargar el archivo.');
      const url = URL.createObjectURL(await response.blob());
      const link = document.createElement('a');
      link.href = url;
      link.download = name;
      link.click();
      URL.revokeObjectURL(url);
    });

  const validar = (analysisId: number, datos: unknown) =>
    run('validar', async () => {
      await api.validarAnalysis(analysisId, datos);
      toast('success', 'Validación guardada.');
      if (selected) setSelected(await api.getProcess(selected.id));
    });

  if (!user) return <Login onSubmit={login} busy={busy['login'] ?? false} />;

  return (
    <main>
      <header>
        <div className="brand">
          <div className="brand-mark">N2</div>
          <div>
            <div className="brand-name">Node2</div>
            <div className="hint">Procesos y análisis IA</div>
          </div>
        </div>
        <div className="header-right">
          <span className="user-chip"><span className="dot" />{user.name} · {user.role}</span>
          <button className="btn-ghost" onClick={() => void logout()}>Salir</button>
        </div>
      </header>
      <Toasts toasts={toasts} onDismiss={dismissToast} />
      {user.role === 'admin' && (
        <UsersPanel users={users} busy={busy['createUser'] ?? false} onSubmit={createUser} />
      )}
      <section className="grid">
        <ProcessList processes={processes} busy={busy['createProcess'] ?? false} onSelect={openProcess} onCreate={createProcess} />
        <ProcessDetail
          selected={selected}
          busyUpload={busy['upload'] ?? false}
          busyAnalyze={busy['analyze'] ?? false}
          onUpload={upload}
          onAnalyze={analyze}
          onDownload={download}
          onValidar={validar}
        />
      </section>
    </main>
  );
}