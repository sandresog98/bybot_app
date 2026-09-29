import type { FormEvent } from 'react';
import type { NewUserPayload, User } from '../types';

type Props = {
  users: User[];
  busy: boolean;
  onSubmit: (payload: NewUserPayload) => Promise<void>;
};

export function UsersPanel({ users, busy, onSubmit }: Props) {
  const handle = (event: FormEvent<HTMLFormElement>) => {
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

  return (
    <section className="users section-card">
      <h2>Usuarios</h2>
      <form onSubmit={handle} className="inline">
        <input name="name" placeholder="Nombre" required />
        <input name="username" placeholder="Usuario" required />
        <input name="password" type="password" minLength={10} placeholder="Contraseña" required />
        <select name="role" defaultValue="operator">
          <option value="operator">Operador</option>
          <option value="admin">Administrador</option>
        </select>
        <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear usuario'}</button>
      </form>
      <div className="hint users-list">{users.map(item => `${item.name} (${item.username}, ${item.role})`).join(' · ')}</div>
    </section>
  );
}