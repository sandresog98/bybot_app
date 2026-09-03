import { useState } from 'react';
import {
  useEntidadesAdmin, useCreateEntidad, useUpdateEntidad, useDeleteEntidad,
  useCatalogo, useAddTipoDoc, useUpdateTipoDoc, useDeleteTipoDoc,
} from '../api/queries';
import { useAuth } from '../auth/useAuth';
import { CATEGORIAS_LOGICAS, type EntidadAdmin, type EntidadTipoDocFull } from '../api/types';
import Modal from '../components/Modal';

const emptyEntidad = { codigo: '', nombre: '', nit: '', activo: true };
const emptyDoc = { clave: '', label: '', categoria_logica: 'estado_cuenta', obligatorio: false, orden: 0, activo: true };

export default function Entidades() {
  const { user } = useAuth();
  const tokenValid = !!user;
  const isAdmin = user?.rol === 'admin';

  const { data: entidades, isLoading, error } = useEntidadesAdmin(tokenValid);
  const createMut = useCreateEntidad();
  const updateMut = useUpdateEntidad();
  const deleteMut = useDeleteEntidad();

  const [modal, setModal] = useState<'create' | 'edit' | null>(null);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyEntidad);
  const [saving, setSaving] = useState(false);
  const [catalogoDe, setCatalogoDe] = useState<EntidadAdmin | null>(null);

  const openCreate = () => { setForm(emptyEntidad); setEditId(null); setModal('create'); };
  const openEdit = (e: EntidadAdmin) => {
    setForm({ codigo: e.codigo, nombre: e.nombre, nit: e.nit ?? '', activo: e.activo });
    setEditId(e.id); setModal('edit');
  };
  const close = () => { setModal(null); setEditId(null); };

  const save = async () => {
    if (!form.codigo || !form.nombre) return;
    setSaving(true);
    try {
      if (modal === 'create') await createMut.mutateAsync({ codigo: form.codigo, nombre: form.nombre, nit: form.nit || undefined, activo: form.activo });
      else if (editId != null) await updateMut.mutateAsync({ id: editId, data: { codigo: form.codigo, nombre: form.nombre, nit: form.nit || null, activo: form.activo } });
      close();
    } catch { /* interceptor muestra el error */ }
    setSaving(false);
  };

  const del = (e: EntidadAdmin) => {
    if (!confirm(`¿Eliminar la entidad "${e.nombre}"? Se borrarán su catálogo de documentos y sus prompts.`)) return;
    deleteMut.mutate(e.id);
  };

  return (
    <div className="page-card">
      <div className="d-flex justify-content-between align-items-center mb-3">
        <h2 className="h5 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
          <i className="bi bi-building" /> Entidades
        </h2>
        {isAdmin && <button className="btn btn-sm btn-primary" onClick={openCreate}><i className="bi bi-plus-lg" /> Nueva</button>}
      </div>
      <p className="text-muted small">Clientes/cooperativas que remiten procesos. Cada una define su catálogo de documentos y sus prompts de análisis.</p>

      {error && <div className="alert alert-danger">No se pudo cargar.</div>}

      <table className="table table-sm align-middle">
        <thead>
          <tr>
            <th>Código</th><th>Nombre</th><th>NIT</th><th>Docs</th><th>Procesos</th><th>Prompts</th><th>Estado</th>
            {isAdmin && <th></th>}
          </tr>
        </thead>
        <tbody>
          {isLoading && <tr><td colSpan={isAdmin ? 8 : 7} className="text-center text-muted py-3">Cargando…</td></tr>}
          {!isLoading && entidades?.length === 0 && <tr><td colSpan={isAdmin ? 8 : 7} className="text-center text-muted py-3">Sin entidades.</td></tr>}
          {entidades?.map((e) => (
            <tr key={e.id}>
              <td><code>{e.codigo}</code></td>
              <td>{e.nombre}</td>
              <td className="text-muted small">{e.nit ?? '—'}</td>
              <td>{e.total_tipos_doc}</td>
              <td>{e.total_procesos}</td>
              <td>{e.total_prompts}</td>
              <td>{e.activo ? <span className="badge bg-success">Activa</span> : <span className="badge bg-light text-secondary">Inactiva</span>}</td>
              {isAdmin && (
                <td>
                  <div className="btn-group btn-group-sm">
                    <button className="btn btn-outline-secondary" onClick={() => setCatalogoDe(e)} title="Documentos"><i className="bi bi-file-earmark-text" /></button>
                    <button className="btn btn-outline-primary" onClick={() => openEdit(e)} title="Editar"><i className="bi bi-pencil" /></button>
                    <button className="btn btn-outline-danger" onClick={() => del(e)} title="Eliminar"><i className="bi bi-trash" /></button>
                  </div>
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>

      {/* Modal crear/editar entidad */}
      <Modal
        open={modal !== null}
        onClose={close}
        title={modal === 'create' ? 'Nueva entidad' : 'Editar entidad'}
        footer={
          <>
            <button className="btn btn-outline-secondary" onClick={close}>Cancelar</button>
            <button className="btn btn-primary" onClick={save} disabled={saving || !form.codigo || !form.nombre}>
              {saving ? 'Guardando…' : 'Guardar'}
            </button>
          </>
        }
      >
        <div className="mb-3">
          <label className="form-label small">Código (slug)</label>
          <input className="form-control form-control-sm" value={form.codigo} onChange={(e) => setForm({ ...form, codigo: e.target.value })} placeholder="ej: confiar" />
          <div className="form-text">Minúsculas, números y guion bajo.</div>
        </div>
        <div className="mb-3">
          <label className="form-label small">Nombre</label>
          <input className="form-control form-control-sm" value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} />
        </div>
        <div className="mb-3">
          <label className="form-label small">NIT (opcional)</label>
          <input className="form-control form-control-sm" value={form.nit} onChange={(e) => setForm({ ...form, nit: e.target.value })} />
        </div>
        <div className="form-check">
          <input className="form-check-input" type="checkbox" id="ent-activo" checked={form.activo} onChange={(e) => setForm({ ...form, activo: e.target.checked })} />
          <label className="form-check-label small" htmlFor="ent-activo">Activa (disponible al crear procesos)</label>
        </div>
      </Modal>

      {/* Modal catálogo de documentos */}
      {catalogoDe && <CatalogoModal entidad={catalogoDe} onClose={() => setCatalogoDe(null)} />}
    </div>
  );
}

function CatalogoModal({ entidad, onClose }: { entidad: EntidadAdmin; onClose: () => void }) {
  const { user } = useAuth();
  const { data: catalogo, isLoading } = useCatalogo(entidad.id, !!user);
  const addMut = useAddTipoDoc(entidad.id);
  const updateMut = useUpdateTipoDoc(entidad.id);
  const deleteMut = useDeleteTipoDoc(entidad.id);

  const [form, setForm] = useState(emptyDoc);
  const [editId, setEditId] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);

  const reset = () => { setForm(emptyDoc); setEditId(null); };
  const openEdit = (d: EntidadTipoDocFull) => {
    setForm({ clave: d.clave, label: d.label, categoria_logica: d.categoria_logica, obligatorio: !!d.obligatorio, orden: d.orden, activo: !!d.activo });
    setEditId(d.id);
  };

  const save = async () => {
    if (!form.clave || !form.label) return;
    setSaving(true);
    try {
      if (editId != null) await updateMut.mutateAsync({ tid: editId, data: form });
      else await addMut.mutateAsync(form);
      reset();
    } catch { /* interceptor */ }
    setSaving(false);
  };

  const del = (d: EntidadTipoDocFull) => {
    if (!confirm(`¿Eliminar el documento "${d.label}"?`)) return;
    deleteMut.mutate(d.id);
  };

  return (
    <Modal open onClose={onClose} title={`Documentos de ${entidad.nombre}`} size="lg"
      footer={<button className="btn btn-outline-secondary" onClick={onClose}>Cerrar</button>}
    >
      <p className="text-muted small">Cada documento mapea a una <strong>categoría lógica</strong> canónica; el análisis usa el prompt de esa categoría para la entidad.</p>

      <table className="table table-sm align-middle">
        <thead><tr><th>Orden</th><th>Etiqueta</th><th>Categoría</th><th>Oblig.</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          {isLoading && <tr><td colSpan={6} className="text-center text-muted">Cargando…</td></tr>}
          {!isLoading && catalogo?.length === 0 && <tr><td colSpan={6} className="text-center text-muted">Sin documentos configurados.</td></tr>}
          {catalogo?.map((d) => (
            <tr key={d.id}>
              <td>{d.orden}</td>
              <td>{d.label} <span className="text-muted small">({d.clave})</span></td>
              <td><span className="badge bg-light text-secondary">{d.categoria_logica}</span></td>
              <td>{d.obligatorio ? '✓' : '—'}</td>
              <td>{d.activo ? <span className="badge bg-success">Activo</span> : <span className="badge bg-light text-secondary">Inactivo</span>}</td>
              <td>
                <div className="btn-group btn-group-sm">
                  <button className="btn btn-outline-primary" onClick={() => openEdit(d)} title="Editar"><i className="bi bi-pencil" /></button>
                  <button className="btn btn-outline-danger" onClick={() => del(d)} title="Eliminar"><i className="bi bi-trash" /></button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <hr />
      <h6 className="small text-uppercase text-muted">{editId != null ? 'Editar documento' : 'Añadir documento'}</h6>
      <div className="row g-2">
        <div className="col-md-4">
          <label className="form-label small">Etiqueta</label>
          <input className="form-control form-control-sm" value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} placeholder="ej: Extracto de crédito" />
        </div>
        <div className="col-md-3">
          <label className="form-label small">Clave</label>
          <input className="form-control form-control-sm" value={form.clave} onChange={(e) => setForm({ ...form, clave: e.target.value })} placeholder="ej: extracto" />
        </div>
        <div className="col-md-3">
          <label className="form-label small">Categoría lógica</label>
          <select className="form-select form-select-sm" value={form.categoria_logica} onChange={(e) => setForm({ ...form, categoria_logica: e.target.value })}>
            {CATEGORIAS_LOGICAS.map((c) => <option key={c} value={c}>{c}</option>)}
          </select>
        </div>
        <div className="col-md-2">
          <label className="form-label small">Orden</label>
          <input type="number" className="form-control form-control-sm" value={form.orden} onChange={(e) => setForm({ ...form, orden: Number(e.target.value) })} />
        </div>
        <div className="col-12 d-flex gap-3 align-items-center mt-2">
          <div className="form-check">
            <input className="form-check-input" type="checkbox" id="doc-oblig" checked={form.obligatorio} onChange={(e) => setForm({ ...form, obligatorio: e.target.checked })} />
            <label className="form-check-label small" htmlFor="doc-oblig">Obligatorio</label>
          </div>
          <div className="form-check">
            <input className="form-check-input" type="checkbox" id="doc-activo" checked={form.activo} onChange={(e) => setForm({ ...form, activo: e.target.checked })} />
            <label className="form-check-label small" htmlFor="doc-activo">Activo</label>
          </div>
          <div className="ms-auto">
            {editId != null && <button className="btn btn-sm btn-outline-secondary me-2" onClick={reset}>Cancelar edición</button>}
            <button className="btn btn-sm btn-primary" onClick={save} disabled={saving || !form.clave || !form.label}>
              {saving ? 'Guardando…' : (editId != null ? 'Guardar cambios' : 'Añadir')}
            </button>
          </div>
        </div>
      </div>
    </Modal>
  );
}
