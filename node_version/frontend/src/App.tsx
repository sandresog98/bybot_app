import { useEffect, type ReactNode } from 'react';
import { Navigate, Route, Routes, useLocation } from 'react-router-dom';
import { useAuth } from './auth/useAuth';
import AdminLayout from './layouts/AdminLayout';
import Login from './pages/Login';
import ChangePassword from './pages/ChangePassword';
import Dashboard from './pages/Dashboard';
import Procesos from './pages/Procesos';
import Analisis from './pages/Analisis';
import Prompts from './pages/Prompts';
import Usuarios from './pages/Usuarios';
import Configuracion from './pages/Configuracion';

function RequireAuth({ children }: { children: ReactNode }) {
  const { user, loading } = useAuth();
  const loc = useLocation();
  if (loading) {
    return <div className="d-flex vh-100 align-items-center justify-content-center text-muted">Cargando sesión…</div>;
  }
  if (!user) return <Navigate to="/login" state={{ from: loc.pathname }} replace />;
  return <AdminLayout>{children}</AdminLayout>;
}

function RequireModule({ mod, children }: { mod: string; children: ReactNode }) {
  const { user } = useAuth();
  if (!user?.modulos?.includes(mod)) {
    return <Navigate to="/" replace />;
  }
  return <>{children}</>;
}

export default function App() {
  const { refreshUser } = useAuth();

  useEffect(() => {
    void refreshUser();
  }, [refreshUser]);

  return (
    <Routes>
      <Route path="/login" element={<Login />} />
      <Route path="/change-password" element={<ChangePassword />} />

      <Route path="/" element={<RequireAuth><Dashboard /></RequireAuth>} />
      <Route path="/procesos" element={<RequireAuth><Procesos /></RequireAuth>} />
      <Route path="/analisis" element={<RequireAuth><RequireModule mod="analisis"><Analisis /></RequireModule></RequireAuth>} />
      <Route path="/prompts" element={<RequireAuth><RequireModule mod="prompts"><Prompts /></RequireModule></RequireAuth>} />
      <Route path="/usuarios" element={<RequireAuth><RequireModule mod="usuarios"><Usuarios /></RequireModule></RequireAuth>} />
      <Route path="/configuracion" element={<RequireAuth><RequireModule mod="configuracion"><Configuracion /></RequireModule></RequireAuth>} />

      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}