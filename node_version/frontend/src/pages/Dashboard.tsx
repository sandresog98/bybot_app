import { useDashboardStats } from '../api/queries';
import { useAuth } from '../auth/useAuth';
import { Link } from 'react-router-dom';

export default function Dashboard() {
  const { user } = useAuth();
  const { data, isLoading, error } = useDashboardStats(!!user);

  const stats = data
    ? [
        { label: 'Procesos', value: data.counts.procesos, icon: 'folder', hint: 'Total creados' },
        { label: 'Archivos', value: data.counts.archivos, icon: 'files', hint: 'Subidos a storage' },
        { label: 'Analizados', value: data.counts.analizados, icon: 'robot', hint: 'Estado analizado / validado' },
        { label: 'Cola pendiente', value: data.counts.cola_pendientes, icon: 'hourglass-split', hint: 'Trabajos en cola' },
        { label: 'Usuarios', value: data.counts.usuarios, icon: 'people', hint: 'Cuentas activas' },
        { label: 'Prompts IA', value: data.counts.prompts, icon: 'chat-left-text', hint: 'Prompts activos' },
      ]
    : [];

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 className="h4 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Bienvenido</h2>
          <p className="text-muted mb-0" style={{ fontSize: '.875rem' }}>Panel general del sistema de casos.</p>
        </div>
        <Link to="/procesos" className="btn btn-primary"><i className="bi bi-folder-plus" /> Ir a Procesos</Link>
      </div>

      {error && <div className="alert alert-danger">No se pudo cargar el dashboard. Verifica sesión.</div>}

      <div className="row g-3 mb-4">
        {isLoading
          ? Array.from({ length: 6 }).map((_, i) => (
              <div key={i} className="col-12 col-md-6 col-xl-2">
                <div className="by-stat-card">
                  <div className="stat-label"><span className="placeholder col-6" /></div>
                  <div className="stat-value"><span className="placeholder col-4" /></div>
                  <div className="stat-hint"><span className="placeholder col-8" /></div>
                </div>
              </div>
            ))
          : stats.map((s) => (
              <div key={s.label} className="col-12 col-md-6 col-xl-2">
                <div className="by-stat-card">
                  <div className="stat-label"><i className={`bi bi-${s.icon}`} /> {s.label}</div>
                  <div className="stat-value">{s.value}</div>
                  <div className="stat-hint">{s.hint}</div>
                </div>
              </div>
            ))}
      </div>

      <div className="row g-3">
        <div className="col-12 col-xl-7">
          <div className="page-card">
            <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Últimos procesos</h3>
            {!data || data.ultimos.length === 0 ? (
              <div className="text-muted py-4 text-center">
                <i className="bi bi-inbox fs-1 d-block mb-2" style={{ color: 'var(--by-gris-claro)' }} />
                Aún no has creado procesos. La carga se habilita en la Fase 1.
              </div>
            ) : (
              <table className="table table-sm align-middle">
                <thead><tr><th>Código</th><th>Estado</th><th>Prioridad</th><th>Creado</th></tr></thead>
                <tbody>
                  {data.ultimos.map((p) => (
                    <tr key={p.id}>
                      <td><Link to="/procesos"><code>{p.codigo}</code></Link></td>
                      <td><span className={`badge badge-estado ${p.estado}`}>{p.estado}</span></td>
                      <td>{p.prioridad}</td>
                      <td className="text-muted small">{new Date(p.created_at).toLocaleString()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        </div>
        <div className="col-12 col-xl-5">
          <div className="page-card">
            <h3 className="h6 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Procesos por estado</h3>
            {!data || data.por_estado.length === 0 ? (
              <div className="text-muted py-4 text-center">
                <i className="bi bi-bar-chart-line fs-1 d-block mb-2" style={{ color: 'var(--by-gris-claro)' }} />
                Sin datos todavía.
              </div>
            ) : (
              <ul className="list-group list-group-flush">
                {data.por_estado.map((row) => (
                  <li key={row.estado} className="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span className={`badge badge-estado ${row.estado}`}>{row.estado}</span>
                    <strong>{row.n}</strong>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>

      <div className="page-card mt-4">
        <h3 className="h6 mb-2" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>Estado de la implementación</h3>
        <p className="text-muted small mb-2">Fase 0b (Fundamentos Node) — disponible:</p>
        <ul className="small">
          <li><i className="bi bi-check-circle-fill text-success" /> Monorepo + 3 servicios (backend / frontend / botstorage) + BD unificada + Prisma</li>
          <li><i className="bi bi-check-circle-fill text-success" /> Login con contraseña de un solo uso + JWT + RBAC</li>
          <li><i className="bi bi-hourglass-split text-warning" /> <strong>Fase 1</strong> (Carga de archivos) — pendiente</li>
          <li><i className="bi bi-hourglass-split text-warning" /> <strong>Fase 2</strong> (Análisis IA) — pendiente</li>
        </ul>
      </div>
    </>
  );
}