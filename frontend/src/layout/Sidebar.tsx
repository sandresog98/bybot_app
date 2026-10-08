import { NavLink } from 'react-router-dom';
import { Building2, Home, Layers, Moon, Sun, Users } from 'lucide-react';
import { useApp } from '../context';

const NAV = [
  { to: '/', label: 'Inicio', icon: Home, end: true, adminOnly: false },
  { to: '/procesos', label: 'Procesos', icon: Layers, end: false, adminOnly: false },
  { to: '/clientes', label: 'Clientes', icon: Building2, end: false, adminOnly: true },
  { to: '/usuarios', label: 'Usuarios', icon: Users, end: false, adminOnly: true },
];

export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { user, logout, theme, toggleTheme } = useApp();
  return (
    <aside className="sidebar">
      <div className="brand">
        <img className="logo logo-light brand-logo" src="/bybot-logo-light.png" alt="ByBot" />
        <img className="logo logo-dark brand-logo" src="/bybot-logo-dark.png" alt="ByBot" />
        <img className="sidebar-company-logo" src="/byb-logo.png" alt="ByB Jurídicos" />
      </div>
      <nav className="nav">
        {NAV.filter(item => !item.adminOnly || user.role === 'admin').map(item => (
          <NavLink key={item.to} to={item.to} end={item.end} className={({ isActive }) => `nav-item${isActive ? ' active' : ''}`} onClick={onNavigate}>
            <item.icon size={18} aria-hidden />
            {item.label}
          </NavLink>
        ))}
      </nav>
      <div className="sidebar-foot">
        <span className="user-chip"><span className="dot" />{user.name} · {user.role}</span>
        <div className="sidebar-actions">
          <button type="button" className="icon-btn" title={theme === 'dark' ? 'Tema claro' : 'Tema oscuro'} aria-label="Cambiar tema" onClick={toggleTheme}>
            {theme === 'dark' ? <Sun size={16} aria-hidden /> : <Moon size={16} aria-hidden />}
          </button>
          <button className="btn-ghost btn-small" onClick={logout}>Salir</button>
        </div>
      </div>
    </aside>
  );
}
