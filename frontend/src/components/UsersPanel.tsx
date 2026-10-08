import { useState } from 'react';
import type { FormEvent } from 'react';
import { Pencil, Plus } from 'lucide-react';
import { Modal } from './Modal';
import type { NewUserPayload, User } from '../types';

type UpdatePayload = Partial<{ name: string; role: 'admin' | 'operator'; active: boolean; password: string }>;
type Props = {
  users: User[];
  busy: boolean;
  onSubmit: (payload: NewUserPayload) => Promise<void>;
  onUpdate?: (id: number, payload: UpdatePayload) => Promise<void>;
};

export function UsersPanel({ users, busy, onSubmit, onUpdate }: Props) {
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<User | null>(null);

  const handleCreate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    void onSubmit({
      name: String(form.get('name') ?? ''),
      username: String(form.get('username') ?? ''),
      password: String(form.get('password') ?? ''),
      role: String(form.get('role') ?? 'operator') as NewUserPayload['role'],
    }).then(() => setCreating(false));
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
    }).then(() => setEditing(null));
  };

  return (
    <section className="section-card">
      <div className="section-head">
        <h3>Listado de usuarios</h3>
        <button type="button" className="btn-small" onClick={() => setCreating(true)}>
          <Plus size={16} aria-hidden />
          Nuevo usuario
        </button>
      </div>

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
              <td className="row-actions">
                {onUpdate && (
                  <button type="button" className="icon-btn" title="Editar" onClick={() => setEditing(user)}>
                    <Pencil size={15} aria-hidden />
                  </button>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      {creating && (
        <Modal title="Nuevo usuario" onClose={() => setCreating(false)}>
          <form onSubmit={handleCreate} className="modal-form">
            <label className="field-inline">Nombre
              <input name="name" placeholder="Nombre" required />
            </label>
            <label className="field-inline">Usuario
              <input name="username" placeholder="Usuario" required />
            </label>
            <label className="field-inline">Contraseña
              <input name="password" type="password" minLength={10} placeholder="Contraseña" required />
            </label>
            <label className="field-inline">Rol
              <select name="role" defaultValue="operator">
                <option value="operator">Operador</option>
                <option value="admin">Administrador</option>
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn-ghost" onClick={() => setCreating(false)}>Cancelar</button>
              <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear usuario'}</button>
            </div>
          </form>
        </Modal>
      )}

      {editing && (
        <Modal title={`Editar usuario: ${editing.username}`} onClose={() => setEditing(null)}>
          <form onSubmit={handleUpdate} className="modal-form">
            <label className="field-inline">Nombre
              <input name="name" defaultValue={editing.name} placeholder="Nombre" required />
            </label>
            <label className="field-inline">Rol
              <select name="role" defaultValue={editing.role}>
                <option value="operator">Operador</option>
                <option value="admin">Administrador</option>
              </select>
            </label>
            <label className="field-inline">Nueva contraseña (opcional)
              <input name="password" type="password" minLength={12} placeholder="Nueva contraseña (opcional)" />
            </label>
            <label className="check"><input type="checkbox" name="active" defaultChecked={editing.active} /> Activo</label>
            <div className="modal-actions">
              <button type="button" className="btn-ghost" onClick={() => setEditing(null)}>Cancelar</button>
              <button type="submit">Guardar</button>
            </div>
          </form>
        </Modal>
      )}
    </section>
  );
}
