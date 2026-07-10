import { NavLink } from 'react-router-dom';

const CATALOG: Record<string, { icon: string; label: string; path: string }> = {
  dashboard:     { icon: 'speedometer2', label: 'Dashboard',     path: '/' },
  procesos:      { icon: 'folder',       label: 'Procesos',      path: '/procesos' },
  analisis:      { icon: 'robot',        label: 'Análisis IA',    path: '/analisis' },
  prompts:       { icon: 'chat-left-text',label: 'Prompts IA',    path: '/prompts' },
  usuarios:      { icon: 'people',       label: 'Usuarios',      path: '/usuarios' },
  configuracion: { icon: 'gear',         label: 'Configuración', path: '/configuracion' },
};

interface Props { modulos: string[] }

export default function Sidebar({ modulos }: Props) {
  return (
    <aside className="app-sidebar">
      <div className="logo">
        <span style={{ visibility: 'visible' }}>ByBot</span>
        <small>App de casos</small>
      </div>
      <nav className="nav flex-column mt-2">
        {modulos.map((m) => {
          const c = CATALOG[m];
          if (!c) return null;
          return (
            <NavLink
              key={m}
              to={c.path}
              className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
              data-page={m}
              end={m === 'dashboard'}
            >
              <i className={`bi bi-${c.icon}`} />
              <span>{c.label}</span>
            </NavLink>
          );
        })}
      </nav>
    </aside>
  );
}