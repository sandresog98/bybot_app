import { useState } from 'react';
import { Menu } from 'lucide-react';
import { Outlet } from 'react-router-dom';
import { Sidebar } from './Sidebar';

export function AppShell() {
  const [menuOpen, setMenuOpen] = useState(false);
  return (
    <div className={`app-shell${menuOpen ? ' menu-open' : ''}`}>
      <div className="mobile-topbar">
        <button type="button" className="icon-btn" aria-label="Abrir menú" onClick={() => setMenuOpen(true)}>
          <Menu size={18} aria-hidden />
        </button>
        <img className="logo logo-light topbar-logo" src="/bybot-logo-light.png" alt="ByBot" />
        <img className="logo logo-dark topbar-logo" src="/bybot-logo-dark.png" alt="ByBot" />
      </div>
      <Sidebar onNavigate={() => setMenuOpen(false)} />
      <div className="sidebar-overlay" onClick={() => setMenuOpen(false)} />
      <div className="app-content">
        <Outlet />
      </div>
    </div>
  );
}
