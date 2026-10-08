import { useState } from 'react';
import type { FormEvent } from 'react';
import { Plus, Search, X } from 'lucide-react';
import { PROCESS_STATUS_OPTIONS, statusLabel } from '../status';
import { Modal } from './Modal';
import type { Entidad, Process } from '../types';

export type ProcessFilters = { q: string; entidadId: number; status: string };

type Props = {
  processes: Process[];
  entidades: Entidad[];
  busy: boolean;
  onSelect: (id: number) => void;
  onCreate: (title: string, entidadId?: number) => Promise<void>;
  filters?: ProcessFilters;
  onFilterChange?: (patch: Partial<ProcessFilters>) => void;
};

export function ProcessList({ processes, entidades, busy, onSelect, onCreate, filters, onFilterChange }: Props) {
  const [creating, setCreating] = useState(false);
  const activeEntidades = entidades.filter(entidad => entidad.active !== false);

  const handleCreate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const entidadId = Number(form.get('entidadId') ?? '0');
    void onCreate(String(form.get('title') ?? ''), entidadId > 0 ? entidadId : undefined).then(() => setCreating(false));
  };

  return (
    <div className="section-card">
      <div className="section-head">
        <h3>Listado de procesos</h3>
        <button type="button" className="btn-small" onClick={() => setCreating(true)}>
          <Plus size={16} aria-hidden />
          Nuevo proceso
        </button>
      </div>

      {filters && onFilterChange && (
        <div className="filter-bar">
          <div className="search-field">
            <Search size={16} aria-hidden />
            <input
              value={filters.q}
              onChange={event => onFilterChange({ q: event.target.value })}
              placeholder="Buscar por deudor, cédula, código o título"
              aria-label="Buscar procesos"
            />
            {filters.q && (
              <button type="button" className="icon-btn" aria-label="Limpiar búsqueda" onClick={() => onFilterChange({ q: '' })}>
                <X size={14} aria-hidden />
              </button>
            )}
          </div>
          <select value={filters.entidadId} onChange={event => onFilterChange({ entidadId: Number(event.target.value) })} aria-label="Filtrar por cliente">
            <option value={0}>Todos los clientes</option>
            {entidades.map(e => <option key={e.id} value={e.id}>{e.nombre}{e.active === false ? ' (inactivo)' : ''}</option>)}
          </select>
          <select value={filters.status} onChange={event => onFilterChange({ status: event.target.value })} aria-label="Filtrar por estado">
            <option value="">Todos los estados</option>
            {PROCESS_STATUS_OPTIONS.map(status => <option key={status} value={status}>{statusLabel(status)}</option>)}
          </select>
        </div>
      )}

      <ul className="process-list">
        {processes.length === 0 && <p className="placeholder">No hay procesos que coincidan.</p>}
        {processes.map(process => {
          const hasDeudor = Boolean(process.deudorNombre || process.deudorDocumento);
          return (
            <li key={process.id}>
              <button className="process" onClick={() => onSelect(process.id)}>
                <b>{process.code}</b>
                <span className="title">{process.title}</span>
                <small>
                  <span className="status" data-status={process.status}>{statusLabel(process.status)}</span>
                  {process.entidad ? process.entidad.nombre : 'Sin cliente'} · {process._count?.files ?? 0} archivos
                </small>
                <small className={hasDeudor ? 'deudor' : 'deudor muted'}>
                  {hasDeudor
                    ? `${process.deudorNombre ?? 'Deudor sin nombre'}${process.deudorDocumento ? ` · C.C. ${process.deudorDocumento}` : ''}`
                    : 'Sin datos de deudor (analiza o consolida)'}
                </small>
              </button>
            </li>
          );
        })}
      </ul>

      {creating && (
        <Modal title="Nuevo proceso" onClose={() => setCreating(false)}>
          <form onSubmit={handleCreate} className="modal-form">
            <label className="field-inline">Nombre del proceso
              <input name="title" placeholder="Nombre del proceso" required />
            </label>
            <label className="field-inline">Cliente
              <select name="entidadId" defaultValue="0">
                <option value="0">Sin cliente</option>
                {activeEntidades.map(e => <option key={e.id} value={e.id}>{e.nombre}</option>)}
              </select>
            </label>
            <div className="modal-actions">
              <button type="button" className="btn-ghost" onClick={() => setCreating(false)}>Cancelar</button>
              <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear proceso'}</button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
