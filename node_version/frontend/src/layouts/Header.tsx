import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/useAuth';

interface Props { onToggleSidebar: () => void; }

export default function Header({ onToggleSidebar }: Props) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout();
    navigate('/login', { replace: true });
  };

  return (
    <header className="app-header">
      <button className="btn btn-link d-md-none text-dark p-0 me-2" onClick={onToggleSidebar}>
        <i className="bi bi-list fs-4" />
      </button>
      <h1 className="h5 mb-0">ByBot App</h1>
      <div className="ms-auto d-flex align-items-center gap-2">
        <span className="badge bg-light text-secondary fw-normal">
          <i className="bi bi-person-circle" /> {user?.nombre_completo ?? user?.nombre ?? user?.usuario} · {user?.rol}
        </span>
        <button className="btn btn-sm btn-outline-danger" onClick={handleLogout}>
          <i className="bi bi-box-arrow-right" /> Salir
        </button>
      </div>
    </header>
  );
}
