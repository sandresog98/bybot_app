import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { api } from '../api';
import { ProcessList } from '../components/ProcessList';
import { useApp } from '../context';
import type { Entidad, Process } from '../types';

export function ProcessesPage() {
  const { toast } = useApp();
  const navigate = useNavigate();
  const [processes, setProcesses] = useState<Process[]>([]);
  const [entidades, setEntidades] = useState<Entidad[]>([]);
  const [busy, setBusy] = useState(false);

  const load = async () => {
    const [page, ents] = await Promise.all([api.listProcesses(), api.listEntidades()]);
    setProcesses(page.items);
    setEntidades(ents);
  };

  useEffect(() => {
    load().catch(() => toast('error', 'No se pudieron cargar los procesos.'));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const create = async (title: string, entidadId?: number) => {
    setBusy(true);
    try {
      const process = await api.createProcess(title, entidadId);
      toast('success', 'Proceso creado.');
      await load();
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
          <p className="hint">Crea un proceso y carga los documentos del cliente.</p>
        </div>
      </header>
      <ProcessList
        processes={processes}
        entidades={entidades}
        busy={busy}
        onSelect={id => navigate(`/procesos/${id}`)}
        onCreate={create}
      />
    </section>
  );
}
