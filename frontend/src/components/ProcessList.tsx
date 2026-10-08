import type { FormEvent } from 'react';
import type { Entidad, Process } from '../types';

type Props = {
  processes: Process[];
  entidades: Entidad[];
  busy: boolean;
  onSelect: (id: number) => void;
  onCreate: (title: string, entidadId?: number) => Promise<void>;
};

export function ProcessList({ processes, entidades, busy, onSelect, onCreate }: Props) {
  const handle = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const entidadId = Number(form.get('entidadId') ?? '0');
    onCreate(String(form.get('title') ?? ''), entidadId > 0 ? entidadId : undefined);
    event.currentTarget.reset();
  };

  return (
    <div className="section-card">
      <form onSubmit={handle} className="inline">
        <input name="title" placeholder="Nombre del proceso" required />
        <select name="entidadId" defaultValue="0">
          <option value="0">Sin cliente</option>
          {entidades.map(e => <option key={e.id} value={e.id}>{e.nombre}</option>)}
        </select>
        <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear'}</button>
      </form>
      <ul className="process-list">
        {processes.length === 0 && <p className="placeholder">Aún no hay procesos. Crea el primero.</p>}
        {processes.map(process => (
          <li key={process.id}>
            <button className="process" onClick={() => onSelect(process.id)}>
              <b>{process.code}</b>
              <span className="title">{process.title}</span>
              <small>
                <span className="status" data-status={process.status}>{process.status}</span>
                {process.entidad ? process.entidad.nombre : 'Sin cliente'} · {process._count?.files ?? 0} archivos
              </small>
            </button>
          </li>
        ))}
      </ul>
    </div>
  );
}
