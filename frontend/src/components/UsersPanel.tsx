import { useState } from 'react';
import type { FormEvent } from 'react';
import type { NewUserPayload, User } from '../types';

type UpdatePayload = Partial<{ name: string; role: 'admin' | 'operator'; active: boolean; password: string }>;
type Props = {
  users: User[];
  busy: boolean;
  onSubmit: (payload: NewUserPayload) => Promise<void>;
  onUpdate?: (id: number, payload: UpdatePayload) => Promise<void>;
};

export function UsersPanel({ users, busy, onSubmit, onUpdate }: Props) {
  const [editing, setEditing] = useState<User | null>(null);

  const handleCreate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    onSubmit({
      name: String(form.get('name') ?? ''),
      username: String(form.get('username') ?? ''),
      password: String(form.get('password') ?? ''),
      role: String(form.get('role') ?? 'operator') as NewUserPayload['role'],
    });
    event.currentTarget.reset();
  };

  const handleUpdate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!editing || !onUpdate) return;
    const form = new FormData(event.currentTarget);
    const password = String(form.get('password') ?? '');
    void onUpdate(editing.id, {
      name: String(form.get('name') ?? ''),
      role: String(form.get('role') ?? 'operator') as UpdatePayload['role'],
      active: form.get('active') === 'on',
      ...(password ? { password } : {}),
    });
    setEditing(null);
  };

  return (
    <section className="section-card">
      <form onSubmit={handleCreate} className="inline">
        <input name="name" placeholder="Nombre" required />
        <input name="username" placeholder="Usuario" required />
        <input name="password" type="password" minLength={10} placeholder="Contraseña" required />
        <select name="role" defaultValue="operator">
          <option value="operator">Operador</option>
          <option value="admin">Administrador</option>
        </select>
        <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear usuario'}</button>
      </form>

      <table className="data-table">
        <thead>
          <tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th /></tr>
        </thead>
        <tbody>
          {users.length === 0 && <tr><td colSpan={5} className="placeholder">Aún no hay usuarios.</td></tr>}
          {users.map(user => (
            <tr key={user.id}>
              <td>{user.name}</td>
              <td>{user.username}</td>
              <td>{user.role}</td>
              <td>{user.active ? 'Activo' : 'Inactivo'}</td>
              <td>
                {onUpdate && <button type="button" className="btn-ghost btn-small" onClick={() => setEditing(user)}>Editar</button>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {editing && (
        <form onSubmit={handleUpdate} className="inline edit-form">
          <span className="edit-title">Editando: {editing.username}</span>
          <input name="name" defaultValue={editing.name} placeholder="Nombre" required />
          <select name="role" defaultValue={editing.role}>
            <option value="operator">Operador</option>
            <option value="admin">Administrador</option>
          </select>
          <label className="check"><input type="checkbox" name="active" defaultChecked={editing.active} /> Activo</label>
          <input name="password" type="password" minLength={12} placeholder="Nueva contraseña (opcional)" />
          <button type="submit">Guardar</button>
          <button type="button" className="btn-ghost" onClick={() => setEditing(null)}>Cancelar</button>
        </form>
      )}
    </section>
  );
}
