import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Pencil, Plus, Power } from 'lucide-react';
import { api } from '../api';
import { Modal } from '../components/Modal';
import { useApp } from '../context';
import type { Entidad } from '../types';

export function ClientsPage() {
  const { toast } = useApp();
  const [entidades, setEntidades] = useState<Entidad[]>([]);
  const [creating, setCreating] = useState(false);
  const [editing, setEditing] = useState<Entidad | null>(null);
  const [busy, setBusy] = useState(false);

  const load = async () => { setEntidades(await api.listEntidades({ all: true })); };

  useEffect(() => {
    load().catch(() => toast('error', 'No se pudieron cargar los clientes.'));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleCreate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const nit = String(form.get('nit') ?? '').trim();
    setBusy(true);
    void api.createEntidad({ codigo: String(form.get('codigo') ?? ''), nombre: String(form.get('nombre') ?? ''), ...(nit ? { nit } : {}) })
      .then(async () => { toast('success', 'Cliente creado.'); await load(); setCreating(false); })
      .catch((error: unknown) => toast('error', error instanceof Error ? error.message : 'No se pudo crear el cliente.'))
      .finally(() => setBusy(false));
  };

  const handleUpdate = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!editing) return;
    const form = new FormData(event.currentTarget);
    const nit = String(form.get('nit') ?? '').trim();
    setBusy(true);
    void api.updateEntidad(editing.id, { codigo: String(form.get('codigo') ?? ''), nombre: String(form.get('nombre') ?? ''), nit: nit || null })
      .then(async () => { toast('success', 'Cliente actualizado.'); await load(); setEditing(null); })
      .catch((error: unknown) => toast('error', error instanceof Error ? error.message : 'No se pudo actualizar el cliente.'))
      .finally(() => setBusy(false));
  };

  const toggleActive = async (entidad: Entidad) => {
    try { await api.updateEntidad(entidad.id, { active: !entidad.active }); toast('success', entidad.active ? 'Cliente desactivado.' : 'Cliente activado.'); await load(); }
    catch (error) { toast('error', error instanceof Error ? error.message : 'No se pudo cambiar el estado.'); }
  };

  return (
    <section className="page">
      <header className="page-head">
        <div>
          <h1>Clientes</h1>
          <p className="hint">Clientes disponibles para crear procesos. Al desactivar uno, deja de aparecer en nuevos procesos.</p>
        </div>
      </header>

      <section className="section-card">
        <div className="section-head">
          <h3>Listado de clientes</h3>
          <button type="button" className="btn-small" onClick={() => setCreating(true)}>
            <Plus size={16} aria-hidden />
            Nuevo cliente
          </button>
        </div>
        <table className="data-table">
          <thead>
            <tr><th>Código</th><th>Nombre</th><th>NIT</th><th>Estado</th><th className="row-actions" /></tr>
          </thead>
          <tbody>
            {entidades.length === 0 && <tr><td colSpan={5} className="placeholder">Aún no hay clientes.</td></tr>}
            {entidades.map(entidad => (
              <tr key={entidad.id}>
                <td>{entidad.codigo}</td>
                <td>{entidad.nombre}</td>
                <td>{entidad.nit ?? '—'}</td>
                <td>{entidad.active === false ? 'Inactivo' : 'Activo'}</td>
                <td className="row-actions">
                  <button type="button" className="icon-btn" title="Editar" onClick={() => setEditing(entidad)}>
                    <Pencil size={15} aria-hidden />
                  </button>
                  <button type="button" className={entidad.active === false ? 'icon-btn' : 'icon-btn danger'} title={entidad.active === false ? 'Activar' : 'Desactivar'} onClick={() => void toggleActive(entidad)}>
                    <Power size={15} aria-hidden />
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>

      {creating && (
        <Modal title="Nuevo cliente" onClose={() => setCreating(false)}>
          <form onSubmit={handleCreate} className="modal-form">
            <label className="field-inline">Código
              <input name="codigo" placeholder="Código (p. ej. confiar)" required />
            </label>
            <label className="field-inline">Nombre
              <input name="nombre" placeholder="Nombre" required />
            </label>
            <label className="field-inline">NIT (opcional)
              <input name="nit" placeholder="NIT" />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn-ghost" onClick={() => setCreating(false)}>Cancelar</button>
              <button type="submit" disabled={busy}>{busy ? 'Creando…' : 'Crear cliente'}</button>
            </div>
          </form>
        </Modal>
      )}

      {editing && (
        <Modal title={`Editar cliente: ${editing.codigo}`} onClose={() => setEditing(null)}>
          <form onSubmit={handleUpdate} className="modal-form">
            <label className="field-inline">Código
              <input name="codigo" defaultValue={editing.codigo} required />
            </label>
            <label className="field-inline">Nombre
              <input name="nombre" defaultValue={editing.nombre} required />
            </label>
            <label className="field-inline">NIT (opcional)
              <input name="nit" defaultValue={editing.nit ?? ''} placeholder="NIT" />
            </label>
            <div className="modal-actions">
              <button type="button" className="btn-ghost" onClick={() => setEditing(null)}>Cancelar</button>
              <button type="submit" disabled={busy}>{busy ? 'Guardando…' : 'Guardar'}</button>
            </div>
          </form>
        </Modal>
      )}
    </section>
  );
}
