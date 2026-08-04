import { useState, type FormEvent } from 'react';
import { useUsuarios, useCreateUsuario, useUpdateUsuario, useResetPassword } from '../api/queries';
import { useAuth } from '../auth/useAuth';
import { formatDate } from '../components/format';
import type { Usuario } from '../api/types';
import Modal from '../components/Modal';

export default function Usuarios() {
  const { user } = useAuth();
  const tokenValid = !!user;
  const { data: usuarios, isLoading, error } = useUsuarios(tokenValid);
  const createMut = useCreateUsuario();
  const updateMut = useUpdateUsuario();
  const resetMut = useResetPassword();

  const [showNew, setShowNew] = useState(false);
  const [tempPass, setTempPass] = useState<string | null>(null);
  const [newU, setNewU] = useState({ usuario: '', nombre_completo: '', email: '', rol: 'operador' });
  const [editId, setEditId] = useState<number | null>(null);
  const [editData, setEditData] = useState({ nombre_completo: '', email: '', rol: 'operador', estado_activo: true });

  const onCrear = async (e: FormEvent) => {
    e.preventDefault();
    try {
      const u = await createMut.mutateAsync({ ...newU, email: newU.email || undefined });
      setTempPass(u.password_temporal);
      setShowNew(false);
      setNewU({ usuario: '', nombre_completo: '', email: '', rol: 'operador' });
    } catch { /* ignore */ }
  };

  const onReset = async (id: number) => {
    if (!confirm('¿Resetear la contraseña? Se generará una temporal de un solo uso.')) return;
    try {
      const r = await resetMut.mutateAsync(id);
      setTempPass(r.password_temporal);
    } catch { /* ignore */ }
  };

  const onEditar = (id: number, u: Usuario) => {
    setEditId(id);
    setEditData({ nombre_completo: u.nombre_completo, email: u.email ?? '', rol: u.rol, estado_activo: u.estado_activo ?? true });
  };

  const onGuardar = async () => {
    if (editId === null) return;
    try {
      await updateMut.mutateAsync({ id: editId, data: { ...editData, email: editData.email || null } });
      setEditId(null);
    } catch { /* ignore */ }
  };

  const onToggleActivo = async (u: Usuario) => {
    try {
      await updateMut.mutateAsync({ id: u.id, data: { estado_activo: !u.estado_activo } });
    } catch { /* ignore */ }
  };

  return (
    <>
      <div className="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h2 className="h4 mb-0" style={{ fontFamily: 'var(--by-fuente-titulo)', color: 'var(--by-azul)' }}>
            <i className="bi bi-people" /> Usuarios
          </h2>
          <p className="text-muted mb-0" style={{ fontSize: '.85rem' }}>Cuentas del sistema. Las contraseñas son de un solo uso.</p>
        </div>
        <button className="btn btn-primary" onClick={() => setShowNew(true)}><i className="bi bi-plus-lg" /> Nuevo usuario</button>
      </div>

      {error && <div className="alert alert-danger">Error al cargar usuarios.</div>}

      <div className="page-card">
        <table className="table table-sm align-middle">
          <thead><tr><th>Usuario</th><th>Nombre</th><th>Email</th><th>Rol</th><th>Un solo uso</th><th>Activo</th><th>Último acceso</th><th></th></tr></thead>
          <tbody>
            {isLoading && <tr><td colSpan={8} className="text-center text-muted py-3">Cargando…</td></tr>}
            {usuarios?.map((u) => (
              <tr key={u.id}>
                {editId === u.id ? (
                  <>
                    <td><code>{u.usuario}</code></td>
                    <td><input className="form-control form-control-sm" value={editData.nombre_completo} onChange={(e) => setEditData({ ...editData, nombre_completo: e.target.value })} /></td>
                    <td><input className="form-control form-control-sm" value={editData.email} onChange={(e) => setEditData({ ...editData, email: e.target.value })} /></td>
                    <td>
                      <select className="form-select form-select-sm" value={editData.rol} onChange={(e) => setEditData({ ...editData, rol: e.target.value })}>
                        <option value="admin">admin</option><option value="supervisor">supervisor</option><option value="operador">operador</option>
                      </select>
                    </td>
                    <td>{u.clave_un_solo_uso ? <span className="badge bg-warning">Sí</span> : <span className="text-muted">No</span>}</td>
                    <td>
                      <div className="form-check form-switch">
                        <input className="form-check-input" type="checkbox" checked={editData.estado_activo} onChange={(e) => setEditData({ ...editData, estado_activo: e.target.checked })} />
                      </div>
                    </td>
                    <td />
                    <td>
                      <button className="btn btn-sm btn-success me-1" onClick={onGuardar}><i className="bi bi-check-lg" /></button>
                      <button className="btn btn-sm btn-outline-secondary" onClick={() => setEditId(null)}><i className="bi bi-x-lg" /></button>
                    </td>
                  </>
                ) : (
                  <>
                    <td><code>{u.usuario}</code></td>
                    <td>{u.nombre_completo}</td>
                    <td className="text-muted small">{u.email ?? '—'}</td>
                    <td><span className="badge bg-light text-secondary">{u.rol}</span></td>
                    <td>{u.clave_un_solo_uso ? <span className="badge bg-warning">Sí</span> : <span className="text-muted">No</span>}</td>
                    {/* Switch directo de activo (Fix 4): toggle in-place sin entrar a edición completa */}
                    <td>
                      <div className="form-check form-switch">
                        <input className="form-check-input" type="checkbox" checked={u.estado_activo ?? true} onChange={() => onToggleActivo(u)} />
                      </div>
                    </td>
                    <td className="text-muted small">{u.ultimo_acceso ? formatDate(u.ultimo_acceso) : '—'}</td>
                    <td>
                      <button className="btn btn-sm btn-outline-primary me-1" onClick={() => onEditar(u.id, u)} title="Editar"><i className="bi bi-pencil" /></button>
                      <button className="btn btn-sm btn-outline-warning" onClick={() => onReset(u.id)} title="Resetear contraseña"><i className="bi bi-key" /></button>
                    </td>
                  </>
                )}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Modal nuevo usuario */}
      <Modal
        open={showNew}
        onClose={() => setShowNew(false)}
        title="Nuevo usuario"
        footer={
          <>
            <button type="button" className="btn btn-outline-secondary" onClick={() => setShowNew(false)}>Cancelar</button>
            <button type="submit" form="form-new-user" className="btn btn-primary" disabled={createMut.isPending}>
              {createMut.isPending ? 'Creando…' : 'Crear'}
            </button>
          </>
        }
      >
        <form id="form-new-user" onSubmit={onCrear}>
          <div className="mb-2"><label className="form-label">Usuario</label><input className="form-control" required value={newU.usuario} onChange={(e) => setNewU({ ...newU, usuario: e.target.value })} /></div>
          <div className="mb-2"><label className="form-label">Nombre completo</label><input className="form-control" required value={newU.nombre_completo} onChange={(e) => setNewU({ ...newU, nombre_completo: e.target.value })} /></div>
          <div className="mb-2"><label className="form-label">Email (opcional)</label><input className="form-control" type="email" value={newU.email} onChange={(e) => setNewU({ ...newU, email: e.target.value })} /></div>
          <div className="mb-2"><label className="form-label">Rol</label><select className="form-select" value={newU.rol} onChange={(e) => setNewU({ ...newU, rol: e.target.value })}><option value="admin">admin</option><option value="supervisor">supervisor</option><option value="operador">operador</option></select></div>
        </form>
      </Modal>

      {/* Modal contraseña temporal */}
      <Modal
        open={!!tempPass}
        onClose={() => setTempPass(null)}
        title="Contraseña temporal"
        size="sm"
      >
        <div className="text-center">
          <i className="bi bi-key-fill fs-1 text-warning d-block mb-2" />
          <p>Comparte esta contraseña con el usuario. Es de <strong>un solo uso</strong> y se pedirá cambiarla al primer ingreso:</p>
          <div className="alert alert-warning font-monospace fs-4 mb-2">{tempPass}</div>
          <button className="btn btn-sm btn-outline-primary" onClick={() => navigator.clipboard?.writeText(tempPass ?? '')}><i className="bi bi-clipboard" /> Copiar</button>
        </div>
      </Modal>
    </>
  );
}