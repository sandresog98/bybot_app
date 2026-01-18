<?php
/**
 * Router de Autenticación
 * Maneja: login, logout, me, refresh
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';

/**
 * Enruta las solicitudes de autenticación
 */
function routeAuth(string $method, ?string $action, array $body): void {
    switch ($action) {
        case 'login':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleLogin($body);
            break;
            
        case 'logout':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleLogout();
            break;
            
        case 'me':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleMe();
            break;
            
        case 'check':
            handleCheck();
            break;
            
        case 'change-password':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleChangePassword($body);
            break;
            
        default:
            Response::error('Acción no encontrada', [], 404);
    }
}

/**
 * POST /api/v1/auth/login
 * Inicia sesión con usuario y contraseña
 */
function handleLogin(array $body): void {
    // Rate limiting estricto para login
    RateLimitMiddleware::checkAuth();
    
    // Validar campos requeridos
    $usuario = trim($body['usuario'] ?? '');
    $password = $body['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        Response::error('Usuario y contraseña son requeridos', [
            'fields' => [
                'usuario' => empty($usuario) ? 'Campo requerido' : null,
                'password' => empty($password) ? 'Campo requerido' : null
            ]
        ], 400);
    }
    
    // Intentar login
    $result = AuthMiddleware::login($usuario, $password);
    
    if (!$result) {
        Response::error('Credenciales inválidas', [], 401);
    }
    
    if (isset($result['error'])) {
        Response::error($result['error'], [], 403);
    }
    
    Response::success('Login exitoso', [
        'user' => $result,
        'session_id' => session_id()
    ]);
}

/**
 * POST /api/v1/auth/logout
 * Cierra la sesión actual
 */
function handleLogout(): void {
    AuthMiddleware::logout();
    Response::success('Sesión cerrada correctamente');
}

/**
 * GET /api/v1/auth/me
 * Obtiene información del usuario actual
 */
function handleMe(): void {
    $user = AuthMiddleware::check();
    
    // Obtener información adicional
    $db = getConnection();
    $stmt = $db->prepare("
        SELECT 
            id, usuario, nombre_completo, email, rol, 
            estado_activo, ultimo_acceso, fecha_creacion
        FROM control_usuarios 
        WHERE id = ?
    ");
    $stmt->execute([$user['id']]);
    $fullUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener permisos del rol
    $rolesFile = BASE_DIR . '/roles.json';
    $permisos = [];
    
    if (file_exists($rolesFile)) {
        $roles = json_decode(file_get_contents($rolesFile), true);
        $rolData = $roles['roles'][$fullUser['rol']] ?? null;
        if ($rolData) {
            $permisos = $rolData['modulos'] ?? [];
        }
    }
    
    Response::success('Usuario actual', [
        'user' => $fullUser,
        'permisos' => $permisos,
        'session' => [
            'id' => session_id(),
            'started' => $_SESSION['login_time'] ?? null,
            'ip' => $_SESSION['ip'] ?? null
        ]
    ]);
}

/**
 * GET /api/v1/auth/check
 * Verifica si hay una sesión activa (sin requerir autenticación)
 */
function handleCheck(): void {
    $user = AuthMiddleware::check(false);
    
    if ($user) {
        Response::success('Sesión activa', [
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'usuario' => $user['usuario'],
                'rol' => $user['rol']
            ]
        ]);
    } else {
        Response::json([
            'success' => true,
            'message' => 'No autenticado',
            'data' => [
                'authenticated' => false
            ]
        ]);
    }
}

/**
 * POST /api/v1/auth/change-password
 * Cambia la contraseña del usuario actual
 */
function handleChangePassword(array $body): void {
    $user = AuthMiddleware::check();
    
    $currentPassword = $body['current_password'] ?? '';
    $newPassword = $body['new_password'] ?? '';
    $confirmPassword = $body['confirm_password'] ?? '';
    
    // Validaciones
    $errors = [];
    
    if (empty($currentPassword)) {
        $errors['current_password'] = 'La contraseña actual es requerida';
    }
    
    if (empty($newPassword)) {
        $errors['new_password'] = 'La nueva contraseña es requerida';
    } elseif (strlen($newPassword) < 8) {
        $errors['new_password'] = 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = 'Las contraseñas no coinciden';
    }
    
    if (!empty($errors)) {
        Response::error('Errores de validación', ['fields' => $errors], 400);
    }
    
    // Verificar contraseña actual
    $db = getConnection();
    $stmt = $db->prepare("SELECT password FROM control_usuarios WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!password_verify($currentPassword, $userData['password'])) {
        Response::error('La contraseña actual es incorrecta', [], 400);
    }
    
    // Actualizar contraseña
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $updateStmt = $db->prepare("UPDATE control_usuarios SET password = ? WHERE id = ?");
    $updateStmt->execute([$hashedPassword, $user['id']]);
    
    // Registrar en log
    $logStmt = $db->prepare("
        INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address)
        VALUES (?, 'cambio_password', 'auth', 'Contraseña actualizada', ?)
    ");
    $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'] ?? null]);
    
    Response::success('Contraseña actualizada correctamente');
}

