import { type ReactNode } from 'react';
import { useAuth } from '../auth/useAuth';
import Sidebar from './Sidebar';
import Header from './Header';

export default function AdminLayout({ children }: { children: ReactNode }) {
  const { user } = useAuth();
  const modulos = user?.modulos ?? [];

  return (
    <div className="app-shell" id="app-shell">
      <Sidebar modulos={modulos} />
      <div className="app-main">
        <Header />
        <main className="app-content">{children}</main>
      </div>
    </div>
  );
}