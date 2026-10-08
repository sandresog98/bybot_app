import { useState } from 'react';
import type { FormEvent } from 'react';
import { Eye, Plus, Search, X } from 'lucide-react';
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

      <div className="table-scroll">
        <table className="data-table process-table">
          <thead>
            <tr>
              <th>Código</th><th>Título</th><th>Cliente</th><th>Deudor</th><th>Estado</th><th>Archivos</th><th className="row-actions" />
            </tr>
          </thead>
          <tbody>
            {processes.length === 0 && <tr><td colSpan={7} className="placeholder">No hay procesos que coincidan.</td></tr>}
            {processes.map(process => {
              const hasDeudor = Boolean(process.deudorNombre || process.deudorDocumento);
              return (
                <tr key={process.id} className="row-clickable" onClick={() => onSelect(process.id)}>
                  <td data-label="Código"><b>{process.code}</b></td>
                  <td data-label="Título">{process.title}</td>
                  <td data-label="Cliente">{process.entidad ? process.entidad.nombre : 'Sin cliente'}</td>
                  <td data-label="Deudor" className={hasDeudor ? undefined : 'muted'}>
                    {hasDeudor
                      ? `${process.deudorNombre ?? 'Deudor sin nombre'}${process.deudorDocumento ? ` · C.C. ${process.deudorDocumento}` : ''}`
                      : 'Sin datos de deudor'}
                  </td>
                  <td data-label="Estado"><span className="status" data-status={process.status}>{statusLabel(process.status)}</span></td>
                  <td data-label="Archivos">{process._count?.files ?? 0}</td>
                  <td className="row-actions">
                    <button
                      type="button"
                      className="icon-btn"
                      title="Ver"
                      onClick={event => { event.stopPropagation(); onSelect(process.id); }}
                    >
                      <Eye size={16} aria-hidden />
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

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
