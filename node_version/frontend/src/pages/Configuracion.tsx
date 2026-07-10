import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';

export default function Configuracion() {
  const { data, isLoading, error } = useQuery<Array<{ clave: string; valor: string; tipo: string; categoria: string; descripcion?: string }>>({
    queryKey: ['configuracion'],
    queryFn: async () => (await api.get('/configuracion')).data.data,
  });

  return (
    <div className="page-card">
      <h2 className="h5 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-gear" /> Configuración del sistema
      </h2>
      <p className="text-muted small">Valores cargados en <code>app_configuracion</code>.</p>
      {error && <div className="alert alert-danger">No se pudo cargar la configuración.</div>}
      <table className="table table-sm align-middle">
        <thead><tr><th>Categoría</th><th>Clave</th><th>Valor</th><th>Tipo</th><th>Descripción</th></tr></thead>
        <tbody>
          {isLoading
            ? <tr><td colSpan={5} className="text-center text-muted">Cargando…</td></tr>
            : data?.map((r) => (
              <tr key={r.clave}>
                <td><span className="badge bg-light text-secondary">{r.categoria}</span></td>
                <td><code>{r.clave}</code></td>
                <td className="small text-truncate" style={{ maxWidth: 320 }} title={r.valor}>{r.valor.length > 80 ? r.valor.slice(0, 80) + '…' : r.valor}</td>
                <td><span className="badge bg-light text-secondary">{r.tipo}</span></td>
                <td className="small text-muted">{r.descripcion ?? ''}</td>
              </tr>
            ))}
        </tbody>
      </table>
    </div>
  );
}