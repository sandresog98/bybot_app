import { NavLink } from 'react-router-dom';
import { useApp } from '../context';

const NAV = [
  { to: '/', label: 'Inicio', ico: '⌂', end: true, adminOnly: false },
  { to: '/procesos', label: 'Procesos', ico: '▤', end: false, adminOnly: false },
  { to: '/usuarios', label: 'Usuarios', ico: '☺', end: false, adminOnly: true },
];

export function Sidebar() {
  const { user, logout } = useApp();
  return (
    <aside className="sidebar">
      <div className="brand">
        <div className="brand-mark">N2</div>
        <div>
          <div className="brand-name">Node2</div>
          <div className="hint">Procesos y análisis IA</div>
        </div>
      </div>
      <nav className="nav">
        {NAV.filter(item => !item.adminOnly || user.role === 'admin').map(item => (
          <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`}>
            <span className="nav-ico" aria-hidden>{item.ico}</span>
            {item.label}
          </NavLink>
        ))}
      </nav>
      <div className="sidebar-foot">
        <span className="user-chip"><span className="dot" />{user.name} · {user.role}</span>
        <button className="btn-ghost btn-small" onClick={logout}>Salir</button>
      </div>
    </aside>
  );
}
