import { useState } from 'react';
import { usePrompts, useCreatePrompt, useUpdatePrompt, useDeletePrompt, useActivatePrompt } from '../api/queries';
import { useAuth } from '../auth/useAuth';
import type { Prompt } from '../api/types';

type ModalMode = 'create' | 'edit' | null;

const PROMPT_TIPOS = [
  { value: 'estado_cuenta', label: 'Estado de cuenta' },
  { value: 'anexos', label: 'Anexos' },
  { value: 'vinculacion', label: 'Vinculación' },
  { value: 'otros', label: 'Otros' },
];

const emptyForm = { nombre: '', version: 'v1', tipo: 'estado_cuenta', contenido: '', notas: '', activo: false };

export default function Prompts() {
  const { data, isLoading, error } = usePrompts();
  const createMut = useCreatePrompt();
  const updateMut = useUpdatePrompt();
  const deleteMut = useDeletePrompt();
  const activateMut = useActivatePrompt();
  const { user } = useAuth();
  const isAdmin = user?.rol === 'admin';

  const [modal, setModal] = useState<ModalMode>(null);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm);
  const [saving, setSaving] = useState(false);

  const openCreate = () => {
    setForm(emptyForm);
    setEditId(null);
    setModal('create');
  };

  const openEdit = (p: Prompt) => {
    setForm({ nombre: p.nombre, version: p.version, tipo: p.tipo, contenido: p.contenido, notas: p.notas ?? '', activo: p.activo });
    setEditId(p.id);
    setModal('edit');
  };

  const closeModal = () => { setModal(null); setEditId(null); };

  const handleSave = async () => {
    if (!form.nombre || !form.version || !form.tipo || !form.contenido) return;
    setSaving(true);
    try {
      if (modal === 'create') {
        await createMut.mutateAsync(form);
      } else if (editId != null) {
        await updateMut.mutateAsync({ id: editId, data: form });
      }
      closeModal();
    } catch { /* toast handled by interceptor */ }
    setSaving(false);
  };

  const handleDelete = (id: number, nombre: string) => {
    if (!confirm(`¿Eliminar el prompt "${nombre}"?`)) return;
    deleteMut.mutate(id);
  };

  const handleActivate = (id: number) => {
    activateMut.mutate(id);
  };

  return (
    <div className="page-card">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="h5 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
          <i className="bi bi-chat-left-text" /> Prompts de IA
        </h2>
        {isAdmin && (
          <button className="btn btn-sm btn-primary" onClick={openCreate}>
            <i className="bi bi-plus-lg" /> Nuevo
          </button>
        )}
      </div>

      {error && <div className="alert alert-danger">No se pudo cargar.</div>}

      <table className="table table-sm align-middle">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Versión</th>
            <th>Tipo</th>
            <th>Activo</th>
            <th>Actualizado</th>
            {isAdmin && <th></th>}
          </tr>
        </thead>
        <tbody>
          {isLoading
            ? <tr><td colSpan={isAdmin ? 6 : 5} className="text-center text-muted">Cargando…</td></tr>
            : data?.map((p) => (
                <tr key={p.id}>
                  <td><code>{p.nombre}</code></td>
                  <td>{p.version}</td>
                  <td>{p.tipo}</td>
                  <td>
                    {p.activo
                      ? <span className="badge bg-success"><i className="bi bi-check2" /> Activo</span>
                      : <span className="badge bg-light text-secondary">Inactivo</span>}
                  </td>
                  <td className="text-muted small">{new Date(p.updated_at).toLocaleString()}</td>
                  {isAdmin && (
                    <td>
                      <div className="btn-group btn-group-sm">
                        <button className="btn btn-outline-primary" onClick={() => openEdit(p)} title="Editar"><i className="bi bi-pencil" /></button>
                        {!p.activo && (
                          <button className="btn btn-outline-success" onClick={() => handleActivate(p.id)} title="Activar"><i className="bi bi-check2" /></button>
                        )}
                        <button className="btn btn-outline-danger" onClick={() => handleDelete(p.id, p.nombre)} title="Eliminar"><i className="bi bi-trash" /></button>
                      </div>
                    </td>
                  )}
                </tr>
              ))}
        </tbody>
      </table>

      {/* Modal */}
      {modal && (
        <div className="modal d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,.5)' }}>
          <div className="modal-dialog modal-lg modal-dialog-centered">
            <div className="modal-content">
              <div className="modal-header">
                <h5 className="modal-title">{modal === 'create' ? 'Nuevo prompt' : 'Editar prompt'}</h5>
                <button type="button" className="btn-close" onClick={closeModal} />
              </div>
              <div className="modal-body">
                <div className="row g-3">
                  <div className="col-6">
                    <label className="form-label small">Nombre</label>
                    <input className="form-control form-control-sm" value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} placeholder="ej: estado_cuenta_v2" />
                  </div>
                  <div className="col-3">
                    <label className="form-label small">Versión</label>
                    <input className="form-control form-control-sm" value={form.version} onChange={(e) => setForm({ ...form, version: e.target.value })} />
                  </div>
                  <div className="col-3">
                    <label className="form-label small">Tipo</label>
                    <select className="form-select form-select-sm" value={form.tipo} onChange={(e) => setForm({ ...form, tipo: e.target.value })}>
                      {PROMPT_TIPOS.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                  </div>
                  <div className="col-12">
                    <label className="form-label small">Contenido del prompt</label>
                    <textarea className="form-control form-control-sm" rows={12} value={form.contenido} onChange={(e) => setForm({ ...form, contenido: e.target.value })} style={{ fontFamily: 'monospace', fontSize: '.8rem' }} />
                  </div>
                  <div className="col-8">
                    <label className="form-label small">Notas</label>
                    <input className="form-control form-control-sm" value={form.notas} onChange={(e) => setForm({ ...form, notas: e.target.value })} />
                  </div>
                  <div className="col-4 d-flex align-items-end">
                    <div className="form-check">
                      <input className="form-check-input" type="checkbox" id="activoChk" checked={form.activo} onChange={(e) => setForm({ ...form, activo: e.target.checked })} />
                      <label className="form-check-label small" htmlFor="activoChk">Activo</label>
                    </div>
                  </div>
                </div>
              </div>
              <div className="modal-footer">
                <button className="btn btn-sm btn-secondary" onClick={closeModal}>Cancelar</button>
                <button className="btn btn-sm btn-primary" onClick={handleSave} disabled={saving || !form.contenido}>
                  {saving ? <><span className="spinner-border spinner-border-sm me-1" /> Guardando…</> : 'Guardar'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
