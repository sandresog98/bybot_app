import { useState, type ReactNode } from 'react';
import { useAuth } from '../auth/useAuth';
import Sidebar from './Sidebar';
import Header from './Header';

export default function AdminLayout({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const modulos = user?.modulos ?? [];
  const [sidebarOpen, setSidebarOpen] = useState(false);

  return (
    <div className="app-shell" id="app-shell">
      <Sidebar modulos={modulos} open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
      <div className="app-main">
        <Header onToggleSidebar={() => setSidebarOpen((v) => !v)} />
        <main className="app-content">{children}</main>
      </div>
      {sidebarOpen && <div className="sidebar-overlay" onClick={() => setSidebarOpen(false)} />}
    </div>
  );
}
