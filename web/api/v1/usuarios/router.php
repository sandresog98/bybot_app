<?php
/**
 * Router de Usuarios
 * Gestión de usuarios del sistema
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';

/**
 * Enruta solicitudes de usuarios
 */
function routeUsuarios(string $method, ?string $id, ?string $action, array $body): void {
    AuthMiddleware::check();
    RateLimitMiddleware::checkApi();
    
    // Solo admin y supervisor pueden gestionar usuarios
    if (!AuthMiddleware::hasRole('admin') && !AuthMiddleware::hasRole('supervisor')) {
        AuthMiddleware::requireAccess('usuarios.ver');
    }
    
    // Si hay ID numérico
    if ($id !== null && is_numeric($id)) {
        routeUsuarioById((int)$id, $method, $action, $body);
        return;
    }
    
    switch ($id) {
        case 'roles':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleRoles();
            break;
            
        case null:
        case '':
            if ($method === 'GET') {
                handleListar();
            } elseif ($method === 'POST') {
                AuthMiddleware::requireRole('admin');
                handleCrear($body);
            } else {
                Response::error('Método no permitido', [], 405);
            }
            break;
            
        default:
            Response::error('Ruta no encontrada', [], 404);
    }
}

/**
 * Rutas para usuario específico
 */
function routeUsuarioById(int $id, string $method, ?string $action, array $body): void {
    switch ($method) {
        case 'GET':
            handleObtener($id);
            break;
        case 'PUT':
        case 'PATCH':
            AuthMiddleware::requireRole('admin');
            handleActualizar($id, $body);
            break;
        case 'DELETE':
            AuthMiddleware::requireRole('admin');
            handleEliminar($id);
            break;
        default:
            Response::error('Método no permitido', [], 405);
    }
}

/**
 * GET /usuarios
 */
function handleListar(): void {
    $db = getConnection();
    
    $where = ['1 = 1'];
    $params = [];
    
    if (!empty($_GET['rol'])) {
        $where[] = 'rol = ?';
        $params[] = $_GET['rol'];
    }
    
    if (!empty($_GET['estado'])) {
        $where[] = 'estado_activo = ?';
        $params[] = $_GET['estado'] === 'activo' ? 1 : 0;
    }
    
    if (!empty($_GET['q'])) {
        $where[] = '(usuario LIKE ? OR nombre_completo LIKE ? OR email LIKE ?)';
        $search = '%' . $_GET['q'] . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "
        SELECT id, usuario, nombre_completo, email, rol, estado_activo, 
               ultimo_acceso, fecha_creacion
        FROM control_usuarios
        WHERE {$whereClause}
        ORDER BY nombre_completo ASC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success('Usuarios', $usuarios);
}

/**
 * GET /usuarios/{id}
 */
function handleObtener(int $id): void {
    $db = getConnection();
    
    $stmt = $db->prepare("
        SELECT id, usuario, nombre_completo, email, rol, estado_activo,
               ultimo_acceso, fecha_creacion, fecha_actualizacion
        FROM control_usuarios
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        Response::error('Usuario no encontrado', [], 404);
    }
    
    Response::success('Usuario', $usuario);
}

/**
 * POST /usuarios
 */
function handleCrear(array $body): void {
    $required = ['usuario', 'password', 'nombre_completo'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            Response::error("{$field} es requerido", [], 400);
        }
    }
    
    $db = getConnection();
    
    // Verificar usuario único
    $stmt = $db->prepare("SELECT id FROM control_usuarios WHERE usuario = ?");
    $stmt->execute([$body['usuario']]);
    if ($stmt->fetch()) {
        Response::error('El nombre de usuario ya existe', [], 400);
    }
    
    // Validar rol
    $rolesPermitidos = ['admin', 'supervisor', 'operador'];
    $rol = $body['rol'] ?? 'operador';
    if (!in_array($rol, $rolesPermitidos)) {
        Response::error('Rol no válido', [], 400);
    }
    
    // Crear usuario
    $passwordHash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    
    $stmt = $db->prepare("
        INSERT INTO control_usuarios (usuario, password, nombre_completo, email, rol, estado_activo)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $body['usuario'],
        $passwordHash,
        $body['nombre_completo'],
        $body['email'] ?? null,
        $rol,
        $body['estado_activo'] ?? 1
    ]);
    
    $id = $db->lastInsertId();
    
    // Log
    $logStmt = $db->prepare("
        INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address)
        VALUES (?, 'crear', 'usuarios', ?, ?)
    ");
    $logStmt->execute([
        AuthMiddleware::getCurrentUserId(),
        "Usuario creado: {$body['usuario']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    Response::success('Usuario creado', ['id' => $id], 201);
}

/**
 * PUT /usuarios/{id}
 */
function handleActualizar(int $id, array $body): void {
    $db = getConnection();
    
    // Verificar usuario existe
    $stmt = $db->prepare("SELECT id FROM control_usuarios WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Response::error('Usuario no encontrado', [], 404);
    }
    
    // Construir actualización
    $updates = [];
    $params = [];
    
    if (isset($body['nombre_completo'])) {
        $updates[] = 'nombre_completo = ?';
        $params[] = $body['nombre_completo'];
    }
    
    if (isset($body['email'])) {
        $updates[] = 'email = ?';
        $params[] = $body['email'];
    }
    
    if (isset($body['rol'])) {
        $rolesPermitidos = ['admin', 'supervisor', 'operador'];
        if (!in_array($body['rol'], $rolesPermitidos)) {
            Response::error('Rol no válido', [], 400);
        }
        $updates[] = 'rol = ?';
        $params[] = $body['rol'];
    }
    
    if (isset($body['estado_activo'])) {
        $updates[] = 'estado_activo = ?';
        $params[] = $body['estado_activo'] ? 1 : 0;
    }
    
    if (!empty($body['password'])) {
        $updates[] = 'password = ?';
        $params[] = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    if (empty($updates)) {
        Response::error('No hay campos para actualizar', [], 400);
    }
    
    $params[] = $id;
    $sql = "UPDATE control_usuarios SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    Response::success('Usuario actualizado');
}

/**
 * DELETE /usuarios/{id}
 */
function handleEliminar(int $id): void {
    $currentUserId = AuthMiddleware::getCurrentUserId();
    
    // No permitir eliminarse a sí mismo
    if ($id === $currentUserId) {
        Response::error('No puedes eliminar tu propio usuario', [], 400);
    }
    
    $db = getConnection();
    
    // Verificar usuario existe
    $stmt = $db->prepare("SELECT usuario FROM control_usuarios WHERE id = ?");
    $stmt->execute([$id]);
    $usuario = $stmt->fetch();
    
    if (!$usuario) {
        Response::error('Usuario no encontrado', [], 404);
    }
    
    // En lugar de eliminar, desactivar
    $stmt = $db->prepare("UPDATE control_usuarios SET estado_activo = 0 WHERE id = ?");
    $stmt->execute([$id]);
    
    // Log
    $logStmt = $db->prepare("
        INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address)
        VALUES (?, 'eliminar', 'usuarios', ?, ?)
    ");
    $logStmt->execute([
        $currentUserId,
        "Usuario desactivado: {$usuario['usuario']}",
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);
    
    Response::success('Usuario desactivado');
}

/**
 * GET /usuarios/roles
 */
function handleRoles(): void {
    $rolesFile = BASE_DIR . '/roles.json';
    
    if (!file_exists($rolesFile)) {
        Response::error('Archivo de roles no encontrado', [], 500);
    }
    
    $roles = json_decode(file_get_contents($rolesFile), true);
    
    Response::success('Roles disponibles', $roles['roles']);
}

