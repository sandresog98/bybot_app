import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';
import { changePassword } from '../api/queries';

export default function ChangePassword() {
  const navigate = useNavigate();
  const [nueva, setNueva] = useState('');
  const [confirmacion, setConfirmacion] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const onSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    if (nueva.length < 8) { setError('La contraseña debe tener al menos 8 caracteres.'); return; }
    if (nueva !== confirmacion) { setError('Las contraseñas no coinciden.'); return; }
    setLoading(true);
    try {
      await changePassword(nueva, confirmacion);
      navigate('/', { replace: true });
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'No se pudo cambiar la contraseña.';
      setError(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-wrap">
      <form className="login-card" onSubmit={onSubmit}>
        <div className="login-logo"><i className="bi bi-key" /></div>
        <h1 className="h5 text-center mb-1" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Cambia tu contraseña</h1>
        <p className="text-center text-muted mb-4" style={{ fontSize: '.85rem' }}>Esta es una contraseña de un solo uso. Define una nueva para continuar.</p>

        {error && <div className="alert alert-danger py-2">{error}</div>}

        <div className="form-floating mb-3">
          <input type="password" className="form-control" id="nueva" value={nueva}
                 onChange={(e) => setNueva(e.target.value)} placeholder="Nueva contraseña" required minLength={8} autoFocus />
          <label htmlFor="nueva"><i className="bi bi-lock" /> Nueva (mín. 8)</label>
        </div>
        <div className="form-floating mb-3">
          <input type="password" className="form-control" id="confirmacion" value={confirmacion}
                 onChange={(e) => setConfirmacion(e.target.value)} placeholder="Confirmar" required />
          <label htmlFor="confirmacion"><i className="bi bi-lock" /> Repite la contraseña</label>
        </div>
        <button className="btn btn-primary w-100 py-2" type="submit" disabled={loading}>
          <i className="bi bi-check2-circle" /> {loading ? 'Actualizando…' : 'Actualizar y continuar'}
        </button>
      </form>
    </div>
  );
}