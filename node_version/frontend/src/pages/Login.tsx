import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [usuario, setUsuario] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setLoading(true);
    try {
      const { must_change_password } = await login(usuario, password);
      if (must_change_password) {
        navigate('/change-password', { replace: true });
      } else {
        navigate('/', { replace: true });
      }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'No se pudo iniciar sesión.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={onSubmit}>
        <div className="login-logo">By</div>
        <h1 className="h4 text-center mb-1" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>ByBot App</h1>
        <p className="text-center text-muted mb-4" style={{ fontSize: '.85rem' }}>Ingresa con tu usuario y contraseña.</p>

        {error && <div className="alert alert-danger py-2">{error}</div>}

        <div className="form-floating mb-3">
          <input
            type="text"
            className="form-control"
            id="usuario"
            value={usuario}
            onChange={(e) => setUsuario(e.target.value)}
            placeholder="usuario"
            required
            autoFocus
          />
          <label htmlFor="usuario"><i className="bi bi-person" /> Usuario</label>
        </div>
        <div className="form-floating mb-3">
          <input
            type="password"
            className="form-control"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Contraseña"
            required
          />
          <label htmlFor="password"><i className="bi bi-lock" /> Contraseña</label>
        </div>
        <button className="btn btn-primary w-100 py-2" type="submit" disabled={loading}>
          <i className="bi bi-box-arrow-in-right" /> {loading ? 'Entrando…' : 'Entrar'}
        </button>
        <p className="text-center text-muted mt-3 mb-0" style={{ fontSize: '.75rem' }}>ByBot · {new Date().getFullYear()}</p>
      </form>
    </div>
  );
}