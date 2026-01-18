<?php
/**
 * Página de Perfil del Usuario
 */

$pageTitle = 'Mi Perfil';
$pageDescription = 'Información y configuración de la cuenta';

include ADMIN_LAYOUTS . '/header.php';

$user = getCurrentUser();
?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= adminUrl('index.php') ?>">Dashboard</a></li>
        <li class="breadcrumb-item active">Perfil</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-4">
        <!-- Tarjeta de usuario -->
        <div class="card text-center">
            <div class="card-body">
                <div class="mb-3">
                    <div class="avatar-placeholder mx-auto" style="width:100px;height:100px;font-size:2.5rem;">
                        <?= strtoupper(substr($user['nombre_completo'] ?? 'U', 0, 1)) ?>
                    </div>
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($user['nombre_completo'] ?? '') ?></h5>
                <p class="text-muted mb-2">@<?= htmlspecialchars($user['usuario'] ?? '') ?></p>
                <span class="badge bg-<?= $user['rol'] === 'admin' ? 'primary' : ($user['rol'] === 'supervisor' ? 'info' : 'secondary') ?>">
                    <?= ucfirst($user['rol'] ?? 'usuario') ?>
                </span>
            </div>
            <div class="card-footer bg-transparent">
                <small class="text-muted">
                    Miembro desde: <?= isset($user['fecha_creacion']) ? date('d M Y', strtotime($user['fecha_creacion'])) : 'N/A' ?>
                </small>
            </div>
        </div>
        
        <!-- Información de sesión -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-check me-2"></i>Sesión Actual
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Último acceso:</strong><br>
                    <small class="text-muted"><?= isset($user['ultimo_acceso']) ? date('d/m/Y H:i', strtotime($user['ultimo_acceso'])) : 'N/A' ?></small>
                </div>
                <div class="mb-2">
                    <strong>IP actual:</strong><br>
                    <small class="text-muted"><?= $_SERVER['REMOTE_ADDR'] ?? 'N/A' ?></small>
                </div>
                <button class="btn btn-outline-danger btn-sm w-100 mt-2" onclick="cerrarOtrasSesiones()">
                    <i class="bi bi-box-arrow-right me-1"></i>Cerrar otras sesiones
                </button>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Información personal -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="bi bi-person me-2"></i>Información Personal
            </div>
            <div class="card-body">
                <form id="formPerfil">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" name="nombre_completo" 
                                   value="<?= htmlspecialchars($user['nombre_completo'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['usuario'] ?? '') ?>" 
                                   readonly disabled>
                            <div class="form-text">El nombre de usuario no puede cambiarse</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control" value="<?= ucfirst($user['rol'] ?? '') ?>" 
                                   readonly disabled>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i>Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Cambiar contraseña -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-key me-2"></i>Cambiar Contraseña
            </div>
            <div class="card-body">
                <form id="formPassword">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" name="new_password" 
                                   minlength="8" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" name="confirm_password" 
                                   minlength="8" required>
                        </div>
                        <div class="col-12">
                            <div class="form-text mb-2">
                                La contraseña debe tener al menos 8 caracteres
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key me-1"></i>Cambiar Contraseña
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-placeholder {
    background: var(--primary);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}
</style>

<?php
$inlineJs = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    // Formulario de perfil
    document.getElementById('formPerfil').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        try {
            const response = await fetch(`${CONFIG.apiUrl}/auth/me`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error al guardar');
            }
            
            showAlert('Perfil actualizado correctamente', 'success');
            
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
    
    // Formulario de contraseña
    document.getElementById('formPassword').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = Object.fromEntries(formData);
        
        if (data.new_password !== data.confirm_password) {
            showAlert('Las contraseñas no coinciden', 'warning');
            return;
        }
        
        try {
            const response = await fetch(`${CONFIG.apiUrl}/auth/change-password`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    current_password: data.current_password,
                    new_password: data.new_password
                })
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.message || 'Error al cambiar contraseña');
            }
            
            showAlert('Contraseña actualizada correctamente', 'success');
            e.target.reset();
            
        } catch (error) {
            showAlert(error.message, 'danger');
        }
    });
});

async function cerrarOtrasSesiones() {
    if (!confirm('¿Está seguro de cerrar todas las demás sesiones activas?')) return;
    
    try {
        const response = await fetch(`${CONFIG.apiUrl}/auth/logout-others`, {
            method: 'POST',
            credentials: 'include'
        });
        
        if (!response.ok) throw new Error('Error');
        
        showAlert('Otras sesiones cerradas', 'success');
        
    } catch (error) {
        showAlert('Error al cerrar sesiones', 'danger');
    }
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

