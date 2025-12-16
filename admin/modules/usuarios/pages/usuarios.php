<?php
/**
 * Módulo Usuarios - ByBot App
 */

require_once '../../../controllers/AuthController.php';
require_once '../../../models/User.php';
require_once '../../../config/paths.php';
require_once '../../../models/Logger.php';

$authController = new AuthController();
$authController->requireModule('usuarios.gestion');

$userModel = new User();
$message = '';
$error = '';
$currentUser = $authController->getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $nombre_completo = trim($_POST['nombre_completo'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $rol = $_POST['rol'] ?? 'operador';
            
            if (empty($username) || empty($password) || empty($nombre_completo)) {
                $error = 'Todos los campos obligatorios deben estar completos.';
            } else {
                $userId = $userModel->create([
                    'usuario' => $username,
                    'password' => $password,
                    'nombre_completo' => $nombre_completo,
                    'email' => $email,
                    'rol' => $rol
                ]);
                if ($userId) {
                    $message = 'Usuario creado exitosamente.';
                    (new Logger())->logCrear('usuarios', 'Creación de usuario', [
                        'usuario' => $username,
                        'nombre_completo' => $nombre_completo,
                        'rol' => $rol
                    ]);
                } else {
                    $error = 'Error al crear el usuario.';
                }
            }
            break;
            
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $nombre_completo = trim($_POST['nombre_completo'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $rol = $_POST['rol'] ?? 'operador';
            $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;
            
            if (empty($id) || empty($nombre_completo)) {
                $error = 'Todos los campos obligatorios deben estar completos.';
            } else {
                $before = $userModel->getById($id);
                $result = $userModel->update($id, [
                    'nombre_completo' => $nombre_completo,
                    'email' => $email,
                    'rol' => $rol,
                    'estado_activo' => $estado
                ]);
                if ($result) {
                    $message = 'Usuario actualizado exitosamente.';
                    (new Logger())->logEditar('usuarios', 'Actualización de usuario', $before, [
                        'id' => $id,
                        'nombre_completo' => $nombre_completo,
                        'email' => $email,
                        'rol' => $rol,
                        'estado' => $estado
                    ]);
                } else {
                    $error = 'Error al actualizar el usuario.';
                }
            }
            break;
            
        case 'change_password':
            $id = (int)($_POST['id'] ?? 0);
            $new_password = $_POST['new_password'] ?? '';
            
            if (empty($id) || empty($new_password)) {
                $error = 'Debe proporcionar una nueva contraseña.';
            } else {
                $result = $userModel->update($id, ['password' => $new_password]);
                if ($result) {
                    $message = 'Contraseña actualizada exitosamente.';
                    (new Logger())->logEditar('usuarios', 'Cambio de contraseña', ['id' => $id], ['id' => $id]);
                } else {
                    $error = 'Error al actualizar la contraseña.';
                }
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if (!empty($id) && $id != $currentUser['id']) {
                $before = $userModel->getById($id);
                $result = $userModel->delete($id);
                if ($result) {
                    $message = 'Usuario desactivado exitosamente.';
                    (new Logger())->logEliminar('usuarios', 'Desactivación de usuario', $before);
                } else {
                    $error = 'Error al desactivar el usuario.';
                }
            }
            break;
    }
}

$usuarios = $userModel->getAll();

$roles = [
    'admin' => ['label' => 'Administrador', 'class' => 'bg-danger'],
    'supervisor' => ['label' => 'Supervisor', 'class' => 'bg-primary'],
    'operador' => ['label' => 'Operador', 'class' => 'bg-success']
];

$pageTitle = 'Gestión de Usuarios - ByBot';
$currentPage = 'usuarios';
include '../../../views/layouts/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../views/layouts/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-users-cog me-2" style="color: var(--primary-color);"></i>
                        Gestión de Usuarios
                    </h1>
                    <p class="text-muted mb-0">Administra los usuarios del sistema</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-plus me-2"></i>Nuevo Usuario
                </button>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Nombre Completo</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th width="150">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($usuario['usuario']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($usuario['email'] ?? '-'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $roles[$usuario['rol']]['class'] ?? 'bg-secondary'; ?>">
                                            <?php echo $roles[$usuario['rol']]['label'] ?? ucfirst($usuario['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $estadoActivo = ($usuario['estado_activo'] == 1 || $usuario['estado_activo'] === true || $usuario['estado_activo'] === '1');
                                        ?>
                                        <span class="badge <?php echo $estadoActivo ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $estadoActivo ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($usuario['fecha_creacion'])); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal<?php echo $usuario['id']; ?>"
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#changePasswordModal<?php echo $usuario['id']; ?>"
                                                    title="Cambiar contraseña">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($usuario['id'] != $currentUser['id']): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteUserModal<?php echo $usuario['id']; ?>"
                                                    title="Desactivar">
                                                <i class="fas fa-user-slash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label class="form-label">Usuario *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" name="nombre_completo" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol">
                            <option value="operador">Operador</option>
                            <option value="supervisor">Supervisor</option>
                            <?php if ($currentUser['rol'] === 'admin'): ?>
                            <option value="admin">Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($usuarios as $usuario): ?>
<!-- Modal Editar Usuario -->
<div class="modal fade" id="editUserModal<?php echo $usuario['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    <div class="mb-3">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($usuario['usuario']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" name="nombre_completo" value="<?php echo htmlspecialchars($usuario['nombre_completo']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($usuario['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol">
                            <option value="operador" <?php echo $usuario['rol'] === 'operador' ? 'selected' : ''; ?>>Operador</option>
                            <option value="supervisor" <?php echo $usuario['rol'] === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            <?php if ($currentUser['rol'] === 'admin'): ?>
                            <option value="admin" <?php echo $usuario['rol'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="estado">
                            <option value="1" <?php echo ($usuario['estado_activo'] == 1 || $usuario['estado_activo'] === true || $usuario['estado_activo'] === '1') ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo ($usuario['estado_activo'] == 0 || $usuario['estado_activo'] === false || $usuario['estado_activo'] === '0') ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cambiar Contraseña -->
<div class="modal fade" id="changePasswordModal<?php echo $usuario['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Cambiar Contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    <p>Usuario: <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña *</label>
                        <input type="password" class="form-control" name="new_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Cambiar Contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($usuario['id'] != $currentUser['id']): ?>
<!-- Modal Desactivar Usuario -->
<div class="modal fade" id="deleteUserModal<?php echo $usuario['id']; ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-slash me-2"></i>Desactivar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $usuario['id']; ?>">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡Atención!</strong> El usuario será desactivado.
                    </div>
                    <p>¿Está seguro de que desea desactivar al usuario <strong><?php echo htmlspecialchars($usuario['nombre_completo']); ?></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Desactivar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php include '../../../views/layouts/footer.php'; ?>

