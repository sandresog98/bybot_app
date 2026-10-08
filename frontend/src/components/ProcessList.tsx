import type { FormEvent } from 'react';
import { Plus, Search, X } from 'lucide-react';
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

const STATUS_OPTIONS = ['created', 'files_uploaded', 'analysis_queued', 'analyzed'];

export function ProcessList({ processes, entidades, busy, onSelect, onCreate, filters, onFilterChange }: Props) {
  const handle = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const entidadId = Number(form.get('entidadId') ?? '0');
    onCreate(String(form.get('title') ?? ''), entidadId > 0 ? entidadId : undefined);
    event.currentTarget.reset();
  };

  return (
    <div className="section-card">
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
            {entidades.map(e => <option key={e.id} value={e.id}>{e.nombre}</option>)}
          </select>
          <select value={filters.status} onChange={event => onFilterChange({ status: event.target.value })} aria-label="Filtrar por estado">
            <option value="">Todos los estados</option>
            {STATUS_OPTIONS.map(status => <option key={status} value={status}>{status}</option>)}
          </select>
        </div>
      )}

      <form onSubmit={handle} className="inline create-form">
        <input name="title" placeholder="Nombre del proceso" required />
        <select name="entidadId" defaultValue="0">
          <option value="0">Sin cliente</option>
          {entidades.map(e => <option key={e.id} value={e.id}>{e.nombre}</option>)}
        </select>
        <button type="submit" disabled={busy}>
          <Plus size={16} aria-hidden />
          {busy ? 'Creando…' : 'Crear'}
        </button>
      </form>

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
                  <span className="status" data-status={process.status}>{process.status}</span>
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
    </div>
  );
}
