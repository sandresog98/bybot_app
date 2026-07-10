import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';

export default function Prompts() {
  const { data, isLoading, error } = useQuery<Array<{ id: number; nombre: string; version: string; tipo: string; activo: boolean; updated_at: string }>>({
    queryKey: ['prompts'],
    queryFn: async () => (await api.get('/prompts')).data.data,
  });

  return (
    <div className="page-card">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="h5 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
          <i className="bi bi-chat-left-text" /> Prompts de IA
        </h2>
        <button className="btn btn-sm btn-outline-secondary" disabled title="Disponible en Fase 2"><i className="bi bi-plus-lg" /> Nuevo</button>
      </div>
      <p className="text-muted small">Versión semilla de prompts migrada del legado. Editor usable en Fase 2.</p>
      {error && <div className="alert alert-danger">No se pudo cargar.</div>}
      <table className="table table-sm align-middle">
        <thead><tr><th>Nombre</th><th>Versión</th><th>Tipo</th><th>Activo</th><th>Actualizado</th></tr></thead>
        <tbody>
          {isLoading
            ? <tr><td colSpan={5} className="text-center text-muted">Cargando…</td></tr>
            : data?.map((p) => (
              <tr key={p.id}>
                <td><code>{p.nombre}</code></td>
                <td>{p.version}</td>
                <td>{p.tipo}</td>
                <td>{p.activo ? <span className="badge bg-success"><i className="bi bi-check2" /> Activo</span> : <span className="badge bg-light text-secondary">Inactivo</span>}</td>
                <td className="text-muted small">{new Date(p.updated_at).toLocaleString()}</td>
              </tr>
            ))}
        </tbody>
      </table>
    </div>
  );
}