import { useState } from 'react';
import { useConfiguracion, usePatchConfig } from '../api/queries';
import { useAuth } from '../auth/useAuth';

export default function Configuracion() {
  const { user } = useAuth();
  const tokenValid = !!user;
  const canEdit = user?.rol === 'admin';
  const { data, isLoading, error } = useConfiguracion(tokenValid);
  const patchMut = usePatchConfig();

  const [editKey, setEditKey] = useState<string | null>(null);
  const [editValue, setEditValue] = useState('');

  const onEditar = (clave: string, valor: string) => {
    setEditKey(clave);
    setEditValue(valor);
  };

  const onGuardar = async () => {
    if (!editKey) return;
    try {
      await patchMut.mutateAsync({ clave: editKey, valor: editValue });
      setEditKey(null);
    } catch { /* ignore */ }
  };

  // Agrupar por categoría
  const categorias: Record<string, NonNullable<typeof data>> = {};
  data?.forEach((r) => {
    if (!categorias[r.categoria]) categorias[r.categoria] = [];
    categorias[r.categoria].push(r);
  });

  return (
    <div className="page-card">
      <h2 className="h5 mb-3" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
        <i className="bi bi-gear" /> Configuración del sistema
      </h2>
      <p className="text-muted small">Valores cargados en <code>app_configuracion</code>. {canEdit ? 'Edita los valores directamente.' : 'Solo lectura (requieres rol admin para editar).'}</p>

      {error && <div className="alert alert-danger">No se pudo cargar la configuración.</div>}
      {isLoading && <div className="text-center text-muted py-3">Cargando…</div>}

      {data && Object.entries(categorias).map(([cat, rows]) => rows && (
        <div key={cat} className="mb-4">
          <h3 className="h6 mb-2 text-muted" style={{ fontFamily: 'var(--by-fuente-titulo)', textTransform: 'uppercase', fontSize: '.75rem', letterSpacing: '1px' }}>{cat}</h3>
          <table className="table table-sm align-middle">
            <thead><tr><th style={{ width: '30%' }}>Clave</th><th>Valor</th><th style={{ width: 80 }}>Tipo</th><th>Descripción</th>{canEdit ? <th style={{ width: 80 }} /> : null}</tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.clave}>
                  <td><code>{r.clave}</code></td>
                  <td>
                    {editKey === r.clave ? (
                      <input className="form-control form-control-sm" value={editValue} onChange={(e) => setEditValue(e.target.value)} autoFocus />
                    ) : (
                      <span className="text-truncate d-inline-block" style={{ maxWidth: 400 }} title={r.valor}>{r.valor.length > 80 ? r.valor.slice(0, 80) + '…' : r.valor}</span>
                    )}
                  </td>
                  <td><span className="badge bg-light text-secondary">{r.tipo}</span></td>
                  <td className="small text-muted">{r.descripcion ?? ''}</td>
                  {canEdit && (
                    <td>
                      {editKey === r.clave ? (
                        <>
                          <button className="btn btn-sm btn-success me-1" onClick={onGuardar} disabled={patchMut.isPending}><i className="bi bi-check-lg" /></button>
                          <button className="btn btn-sm btn-outline-secondary" onClick={() => setEditKey(null)}><i className="bi bi-x-lg" /></button>
                        </>
                      ) : (
                        <button className="btn btn-sm btn-outline-primary" onClick={() => onEditar(r.clave, r.valor)}><i className="bi bi-pencil" /></button>
                      )}
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ))}
    </div>
  );
}