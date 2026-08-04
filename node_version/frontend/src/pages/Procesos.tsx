import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { useProcesos, useCreateProceso } from '../api/queries';
import { useAuth } from '../auth/useAuth';
import { estadoColor, formatDateShort } from '../components/format';
import Modal from '../components/Modal';

export default function Procesos() {
  const { user } = useAuth();
  const tokenValid = !!user;
  const [page, setPage] = useState(1);
  const [estado, setEstado] = useState('');
  const [tipo, setTipo] = useState('');
  const [q, setQ] = useState('');
  const [showNew, setShowNew] = useState(false);

  const params = { page, limit: 15, estado: estado || undefined, tipo: tipo || undefined, q: q || undefined };
  const { data, isLoading, error } = useProcesos(params, tokenValid);
  const createMut = useCreateProceso();
  const [newTipo, setNewTipo] = useState('cobranza');
  const [newPrioridad, setNewPrioridad] = useState(5);
  const [newNotas, setNewNotas] = useState('');

  const onCrear = async (e: FormEvent) => {
    e.preventDefault();
    try {
      await createMut.mutateAsync({ tipo: newTipo, prioridad: newPrioridad, notas: newNotas });
      setShowNew(false);
      setNewNotas('');
      setNewPrioridad(5);
      setNewTipo('cobranza');
    } catch { /* ignore */ }
  };

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 className="h4 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
            <i className="bi bi-folder" /> Procesos
          </h2>
          <p className="text-muted mb-0" style={{ fontSize: '.85rem' }}>Casos del estudio — carga, seguimiento y análisis.</p>
        </div>
        <button className="btn btn-primary" onClick={() => setShowNew(true)}>
          <i className="bi bi-plus-lg" /> Nuevo proceso
        </button>
      </div>

      <div className="page-card">
        <div className="row g-2 mb-3">
          <div className="col-md-5">
            <input className="form-control form-control-sm" placeholder="Buscar por código…" value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} />
          </div>
          <div className="col-md-3">
            <select className="form-select form-select-sm" value={estado} onChange={(e) => { setEstado(e.target.value); setPage(1); }}>
              <option value="">Todos los estados</option>
              <option value="creado">Creado</option>
              <option value="archivos_cargados">Archivos cargados</option>
              <option value="en_analisis">En análisis</option>
              <option value="analizado">Analizado</option>
              <option value="validado">Validado</option>
              <option value="completado">Completado</option>
              <option value="error">Error</option>
              <option value="cancelado">Cancelado</option>
            </select>
          </div>
          <div className="col-md-3">
            <select className="form-select form-select-sm" value={tipo} onChange={(e) => { setTipo(e.target.value); setPage(1); }}>
              <option value="">Todos los tipos</option>
              <option value="cobranza">Cobranza</option>
              <option value="demanda">Demanda</option>
              <option value="otro">Otro</option>
            </select>
          </div>
        </div>

        {error && <div className="alert alert-danger">Error al cargar procesos.</div>}

        <table className="table table-sm align-middle">
          <thead>
            <tr><th>Código</th><th>Estado</th><th>Tipo</th><th>Prioridad</th><th>Archivos</th><th>Asignado</th><th>Creado</th></tr>
          </thead>
          <tbody>
            {isLoading && <tr><td colSpan={7} className="text-center text-muted py-3">Cargando…</td></tr>}
            {!isLoading && data?.rows.length === 0 && <tr><td colSpan={7} className="text-center text-muted py-3">Sin resultados.</td></tr>}
            {data?.rows.map((p) => (
              <tr key={p.id}>
                <td><Link to={`/procesos/${p.id}`}><code>{p.codigo}</code></Link></td>
                <td><span className={`badge bg-${estadoColor(p.estado)}`}>{p.estado.replace(/_/g, ' ')}</span></td>
                <td className="text-muted">{p.tipo}</td>
                <td>{p.prioridad}</td>
                <td>{p.total_archivos}</td>
                <td className="text-muted small">{p.asignado_a ?? '—'}</td>
                <td className="text-muted small">{formatDateShort(p.created_at)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        {data && data.total > data.limit && (
          <div className="d-flex justify-content-between align-items-center">
            <span className="text-muted small">Página {data.page} de {Math.ceil(data.total / data.limit)} — Total {data.total} procesos.</span>
            <div>
              <button className="btn btn-sm btn-outline-secondary me-2" disabled={page <= 1} onClick={() => setPage(page - 1)}>Anterior</button>
              <button className="btn btn-sm btn-outline-secondary" disabled={page * data.limit >= data.total} onClick={() => setPage(page + 1)}>Siguiente</button>
            </div>
          </div>
        )}
      </div>

      <Modal
        open={showNew}
        onClose={() => setShowNew(false)}
        title="Nuevo proceso"
        footer={
          <>
            <button type="button" className="btn btn-outline-secondary" onClick={() => setShowNew(false)}>Cancelar</button>
            <button type="submit" form="form-new-proceso" className="btn btn-primary" disabled={createMut.isPending}>
              {createMut.isPending ? 'Creando…' : 'Crear proceso'}
            </button>
          </>
        }
      >
        <form id="form-new-proceso" onSubmit={onCrear}>
          <div className="mb-3">
            <label className="form-label">Tipo de proceso</label>
            <select className="form-select" value={newTipo} onChange={(e) => setNewTipo(e.target.value)}>
              <option value="cobranza">Cobranza</option>
              <option value="demanda">Demanda</option>
              <option value="otro">Otro</option>
            </select>
          </div>
          <div className="mb-3">
            <label className="form-label">Prioridad (1=máxima, 10=mínima)</label>
            <input type="number" min={1} max={10} className="form-control" value={newPrioridad} onChange={(e) => setNewPrioridad(Number(e.target.value))} />
          </div>
          <div className="mb-3">
            <label className="form-label">Notas (opcional)</label>
            <textarea className="form-control" rows={3} value={newNotas} onChange={(e) => setNewNotas(e.target.value)} />
          </div>
        </form>
      </Modal>
    </>
  );
}