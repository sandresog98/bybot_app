import { useEffect, useState } from 'react';
import { api } from '../api';
import { UsersPanel } from '../components/UsersPanel';
import { useApp } from '../context';
import type { NewUserPayload, User } from '../types';

export function UsersPage() {
  const { toast } = useApp();
  const [users, setUsers] = useState<User[]>([]);
  const [busy, setBusy] = useState(false);

  const load = async () => { const page = await api.listUsers(); setUsers(page.items); };

  useEffect(() => {
    load().catch(() => toast('error', 'No se pudieron cargar los usuarios.'));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const create = async (payload: NewUserPayload) => {
    setBusy(true);
    try { await api.createUser(payload); toast('success', 'Usuario creado.'); await load(); }
    catch (error) { toast('error', error instanceof Error ? error.message : 'No se pudo crear el usuario.'); }
    finally { setBusy(false); }
  };

  const update = async (id: number, payload: Partial<{ name: string; role: 'admin' | 'operator'; active: boolean; password: string }>) => {
    try { await api.updateUser(id, payload); toast('success', 'Usuario actualizado.'); await load(); }
    catch (error) { toast('error', error instanceof Error ? error.message : 'No se pudo actualizar el usuario.'); }
  };

  return (
    <section className="page">
      <header className="page-head">
        <div>
          <h1>Usuarios</h1>
          <p className="hint">Crea y administra los usuarios del sistema.</p>
        </div>
      </header>
      <UsersPanel users={users} busy={busy} onSubmit={create} onUpdate={update} />
    </section>
  );
}
