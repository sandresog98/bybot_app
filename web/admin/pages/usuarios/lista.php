<?php
/**
 * Lista de Usuarios
 */

$pageTitle = 'Usuarios';
$pageDescription = 'Gestión de usuarios del sistema';

include ADMIN_LAYOUTS . '/header.php';
?>

<!-- Barra de acciones -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
                <li class="breadcrumb-item active">Usuarios</li>
            </ol>
        </nav>
    </div>
    <?php if (hasAccess('usuarios.crear')): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario" onclick="prepararCrear()">
        <i class="bi bi-plus-lg me-1"></i> Nuevo Usuario
    </button>
    <?php endif; ?>
</div>

<!-- Tabla de Usuarios -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-people me-2"></i>Lista de Usuarios</span>
        <input type="text" class="form-control form-control-sm w-auto" placeholder="Buscar..." id="buscarUsuario">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Último Acceso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaUsuarios">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Usuario -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioTitulo">Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUsuario">
                <div class="modal-body">
                    <input type="hidden" name="id" id="usuarioId">
                    
                    <div class="mb-3">
                        <label class="form-label">Usuario <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="usuario" id="usuarioUsuario" required 
                               pattern="[a-z0-9_]+" title="Solo letras minúsculas, números y guiones bajos">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nombre_completo" id="usuarioNombre" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="usuarioEmail" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contraseña <span class="text-danger" id="passwordReq">*</span></label>
                        <input type="password" class="form-control" name="password" id="usuarioPassword" minlength="8">
                        <div class="form-text" id="passwordHelp">Mínimo 8 caracteres</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rol <span class="text-danger">*</span></label>
                        <select class="form-select" name="rol" id="usuarioRol" required>
                            <option value="admin">Administrador</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="operador">Operador</option>
                        </select>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="estado_activo" id="usuarioEstado" checked>
                        <label class="form-check-label" for="usuarioEstado">Usuario Activo</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarUsuario">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = <<<'JS'
let usuarios = [];
let modoEdicion = false;

document.addEventListener('DOMContentLoaded', function() {
    loadUsuarios();
    initEvents();
});

function initEvents() {
    // Buscar
    document.getElementById('buscarUsuario').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        renderUsuarios(usuarios.filter(u => 
            u.usuario.toLowerCase().includes(term) ||
            u.nombre_completo.toLowerCase().includes(term) ||
            u.email.toLowerCase().includes(term)
        ));
    });
    
    // Form submit
    document.getElementById('formUsuario').addEventListener('submit', async (e) => {
        e.preventDefault();
        await guardarUsuario();
    });
}

async function loadUsuarios() {
    try {
        const response = await fetch(`${CONFIG.apiUrl}/usuarios`, {
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error cargando usuarios');
        
        const { data } = await response.json();
        usuarios = data || [];
        renderUsuarios(usuarios);
        
    } catch (error) {
        document.getElementById('tablaUsuarios').innerHTML = `
            <tr><td colspan="7" class="text-center text-danger py-4">${error.message}</td></tr>
        `;
    }
}

function renderUsuarios(lista) {
    const tbody = document.getElementById('tablaUsuarios');
    
    if (!lista.length) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">No hay usuarios</td></tr>`;
        return;
    }
    
    tbody.innerHTML = lista.map(u => `
        <tr>
            <td><strong>${escapeHtml(u.usuario)}</strong></td>
            <td>${escapeHtml(u.nombre_completo)}</td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="badge bg-${getRolColor(u.rol)}">${u.rol}</span></td>
            <td>
                <span class="badge ${u.estado_activo ? 'bg-success' : 'bg-danger'}">
                    ${u.estado_activo ? 'Activo' : 'Inactivo'}
                </span>
            </td>
            <td><small class="text-muted">${u.ultimo_acceso ? formatDate(u.ultimo_acceso) : 'Nunca'}</small></td>
            <td class="text-end">
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="editarUsuario(${u.id})" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-${u.estado_activo ? 'warning' : 'success'}" 
                            onclick="toggleEstado(${u.id}, ${u.estado_activo ? 0 : 1})" 
                            title="${u.estado_activo ? 'Desactivar' : 'Activar'}">
                        <i class="bi bi-${u.estado_activo ? 'pause' : 'play'}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function getRolColor(rol) {
    const colores = { admin: 'primary', supervisor: 'info', operador: 'secondary' };
    return colores[rol] || 'secondary';
}

function prepararCrear() {
    modoEdicion = false;
    document.getElementById('modalUsuarioTitulo').textContent = 'Nuevo Usuario';
    document.getElementById('formUsuario').reset();
    document.getElementById('usuarioId').value = '';
    document.getElementById('usuarioPassword').required = true;
    document.getElementById('passwordReq').style.display = '';
    document.getElementById('passwordHelp').textContent = 'Mínimo 8 caracteres';
    document.getElementById('usuarioUsuario').readOnly = false;
}

function editarUsuario(id) {
    const usuario = usuarios.find(u => u.id === id);
    if (!usuario) return;
    
    modoEdicion = true;
    document.getElementById('modalUsuarioTitulo').textContent = 'Editar Usuario';
    document.getElementById('usuarioId').value = usuario.id;
    document.getElementById('usuarioUsuario').value = usuario.usuario;
    document.getElementById('usuarioUsuario').readOnly = true;
    document.getElementById('usuarioNombre').value = usuario.nombre_completo;
    document.getElementById('usuarioEmail').value = usuario.email;
    document.getElementById('usuarioRol').value = usuario.rol;
    document.getElementById('usuarioEstado').checked = usuario.estado_activo;
    document.getElementById('usuarioPassword').value = '';
    document.getElementById('usuarioPassword').required = false;
    document.getElementById('passwordReq').style.display = 'none';
    document.getElementById('passwordHelp').textContent = 'Dejar vacío para mantener la actual';
    
    new bootstrap.Modal(document.getElementById('modalUsuario')).show();
}

async function guardarUsuario() {
    const form = document.getElementById('formUsuario');
    const formData = new FormData(form);
    const id = formData.get('id');
    
    const data = {
        usuario: formData.get('usuario'),
        nombre_completo: formData.get('nombre_completo'),
        email: formData.get('email'),
        rol: formData.get('rol'),
        estado_activo: document.getElementById('usuarioEstado').checked ? 1 : 0
    };
    
    const password = formData.get('password');
    if (password) {
        data.password = password;
    }
    
    try {
        const url = id ? `${CONFIG.apiUrl}/usuarios/${id}` : `${CONFIG.apiUrl}/usuarios`;
        const method = id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.message || 'Error al guardar');
        }
        
        bootstrap.Modal.getInstance(document.getElementById('modalUsuario')).hide();
        showAlert(id ? 'Usuario actualizado' : 'Usuario creado', 'success');
        loadUsuarios();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

async function toggleEstado(id, nuevoEstado) {
    const usuario = usuarios.find(u => u.id === id);
    const accion = nuevoEstado ? 'activar' : 'desactivar';
    
    if (!confirm(`¿Desea ${accion} al usuario ${usuario.usuario}?`)) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/usuarios/${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ estado_activo: nuevoEstado })
        });
        
        if (!response.ok) throw new Error('Error al actualizar');
        
        showAlert(`Usuario ${accion}do`, 'success');
        loadUsuarios();
        
    } catch (error) {
        showAlert(error.message, 'danger');
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(message, type = 'info') {
    const container = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.prepend(alert);
    setTimeout(() => { alert.classList.remove('show'); setTimeout(() => alert.remove(), 150); }, 4000);
}
JS;

include ADMIN_LAYOUTS . '/footer.php';

