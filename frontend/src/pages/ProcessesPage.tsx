import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { ProcessList } from '../components/ProcessList';
import type { ProcessFilters } from '../components/ProcessList';
import { useApp } from '../context';
import type { Entidad, Process } from '../types';

const EMPTY_FILTERS: ProcessFilters = { q: '', entidadId: 0, status: '' };

export function ProcessesPage() {
  const { toast } = useApp();
  const navigate = useNavigate();
  const [processes, setProcesses] = useState<Process[]>([]);
  const [entidades, setEntidades] = useState<Entidad[]>([]);
  const [filters, setFilters] = useState<ProcessFilters>(EMPTY_FILTERS);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.listEntidades().then(setEntidades).catch(() => undefined);
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      api.listProcesses({ q: filters.q || undefined, entidadId: filters.entidadId || undefined, status: filters.status || undefined })
        .then(page => setProcesses(page.items))
        .catch(() => toast('error', 'No se pudieron cargar los procesos.'));
    }, 300);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters]);

  const create = async (title: string, entidadId?: number) => {
    setBusy(true);
    try {
      const process = await api.createProcess(title, entidadId);
      toast('success', 'Proceso creado.');
      navigate(`/procesos/${process.id}`);
    } catch (error) {
      toast('error', error instanceof Error ? error.message : 'No se pudo crear el proceso.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <section className="page">
      <header className="page-head">
        <div>
          <h1>Procesos</h1>
          <p className="hint">Crea un proceso, carga los documentos y consulta el deudor de cada caso.</p>
        </div>
      </header>
      <ProcessList
        processes={processes}
        entidades={entidades}
        busy={busy}
        onSelect={id => navigate(`/procesos/${id}`)}
        onCreate={create}
        filters={filters}
        onFilterChange={patch => setFilters(previous => ({ ...previous, ...patch }))}
      />
    </section>
  );
}
