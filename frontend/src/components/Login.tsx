import type { FormEvent } from 'react';

type Props = {
  onSubmit: (username: string, password: string) => Promise<void>;
  busy: boolean;
  message?: string;
};

export function Login({ onSubmit, busy, message }: Props) {
  const handle = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const username = String(form.get('username') ?? '');
    const password = String(form.get('password') ?? '');
    void onSubmit(username, password);
  };

  return (
    <main className="login">
      <form onSubmit={handle} className="login-form">
        <div className="login-brand">
          <div className="app-logo">N2</div>
          <h1>Node2</h1>
          <p>Procesos y análisis IA</p>
        </div>
        <label className="login-field">
          Usuario
          <input name="username" placeholder="Usuario" required autoComplete="username" />
        </label>
        <label className="login-field">
          Contraseña
          <input name="password" type="password" placeholder="Contraseña" required autoComplete="current-password" />
        </label>
        <button type="submit" disabled={busy}>{busy ? 'Ingresando…' : 'Ingresar'}</button>
        {message && <p className="notice">{message}</p>}
      </form>
    </main>
  );
}