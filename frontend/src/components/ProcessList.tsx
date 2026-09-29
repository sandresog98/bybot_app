import type { FormEvent } from 'react';
import type { Process } from '../types';

type Props = {
  processes: Process[];
  busy: boolean;
  onSelect: (id: number) => void;
  onCreate: (title: string) => Promise<void>;
};

export function ProcessList({ processes, busy, onSelect, onCreate }: Props) {
  const handle = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    onCreate(String(form.get('title') ?? ''));
    event.currentTarget.reset();
  };

  return (
    <aside>
      <h2>Procesos</h2>
      <form onSubmit={handle} className="inline">
        <input name="title" placeholder="Nombre del proceso" required />
        <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear'}</button>
      </form>
      <ul>
        {processes.map(process => (
          <li key={process.id}>
            <button className="process" onClick={() => onSelect(process.id)}>
              <b>{process.code}</b>
              <span className="title">{process.title}</span>
              <small>
                <span className="status" data-status={process.status}>{process.status}</span>
                {process._count?.files ?? 0} archivos
              </small>
            </button>
          </li>
        ))}
      </ul>
    </aside>
  );
}