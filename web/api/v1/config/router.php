<?php
/**
 * Router de Configuración
 * Gestión de configuraciones, prompts y plantillas
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';

/**
 * Enruta solicitudes de configuración
 */
function routeConfig(string $method, ?string $id, ?string $action, array $body): void {
    AuthMiddleware::check();
    RateLimitMiddleware::checkApi();
    
    switch ($id) {
        case 'general':
            if ($method === 'GET') {
                handleObtenerConfig();
            } elseif ($method === 'PUT' || $method === 'POST') {
                AuthMiddleware::requireRole('admin');
                handleActualizarConfig($body);
            } else {
                Response::error('Método no permitido', [], 405);
            }
            break;
            
        case 'prompts':
            routePrompts($method, $action, $body);
            break;
            
        case 'plantillas':
            routePlantillas($method, $action, $body);
            break;
            
        default:
            Response::error('Ruta no encontrada', [], 404);
    }
}

/**
 * Rutas de prompts
 */
function routePrompts(string $method, ?string $action, array $body): void {
    switch ($method) {
        case 'GET':
            if ($action && is_numeric($action)) {
                handleObtenerPrompt((int)$action);
            } else {
                handleListarPrompts();
            }
            break;
        case 'POST':
            AuthMiddleware::requireRole('admin');
            handleCrearPrompt($body);
            break;
        case 'PUT':
            AuthMiddleware::requireRole('admin');
            if (!$action || !is_numeric($action)) {
                Response::error('ID de prompt requerido', [], 400);
            }
            handleActualizarPrompt((int)$action, $body);
            break;
        default:
            Response::error('Método no permitido', [], 405);
    }
}

/**
 * Rutas de plantillas
 */
function routePlantillas(string $method, ?string $action, array $body): void {
    switch ($method) {
        case 'GET':
            if ($action && is_numeric($action)) {
                handleObtenerPlantilla((int)$action);
            } else {
                handleListarPlantillas();
            }
            break;
        case 'POST':
            AuthMiddleware::requireRole('admin');
            handleCrearPlantilla($body);
            break;
        case 'PUT':
            AuthMiddleware::requireRole('admin');
            if (!$action || !is_numeric($action)) {
                Response::error('ID de plantilla requerido', [], 400);
            }
            handleActualizarPlantilla((int)$action, $body);
            break;
        default:
            Response::error('Método no permitido', [], 405);
    }
}

// ========================================
// CONFIG GENERAL HANDLERS
// ========================================

function handleObtenerConfig(): void {
    $db = getConnection();
    
    $stmt = $db->query("SELECT clave, valor, tipo, descripcion FROM configuracion ORDER BY categoria, clave");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir valores según tipo
    foreach ($configs as &$config) {
        switch ($config['tipo']) {
            case 'int':
                $config['valor'] = (int)$config['valor'];
                break;
            case 'float':
                $config['valor'] = (float)$config['valor'];
                break;
            case 'bool':
                $config['valor'] = $config['valor'] === 'true' || $config['valor'] === '1';
                break;
            case 'json':
                $config['valor'] = json_decode($config['valor'], true);
                break;
        }
    }
    
    Response::success('Configuración del sistema', $configs);
}

function handleActualizarConfig(array $body): void {
    if (empty($body['clave']) || !isset($body['valor'])) {
        Response::error('clave y valor son requeridos', [], 400);
    }
    
    $db = getConnection();
    
    // Verificar que existe
    $stmt = $db->prepare("SELECT id, tipo FROM configuracion WHERE clave = ?");
    $stmt->execute([$body['clave']]);
    $config = $stmt->fetch();
    
    if (!$config) {
        Response::error('Configuración no encontrada', [], 404);
    }
    
    // Convertir valor según tipo
    $valor = $body['valor'];
    if ($config['tipo'] === 'json' && is_array($valor)) {
        $valor = json_encode($valor);
    } elseif ($config['tipo'] === 'bool') {
        $valor = $valor ? 'true' : 'false';
    }
    
    $stmt = $db->prepare("UPDATE configuracion SET valor = ? WHERE clave = ?");
    $stmt->execute([$valor, $body['clave']]);
    
    Response::success('Configuración actualizada');
}

// ========================================
// PROMPTS HANDLERS
// ========================================

function handleListarPrompts(): void {
    $db = getConnection();
    
    $stmt = $db->query("
        SELECT id, nombre, version, tipo, activo, notas, fecha_creacion
        FROM prompts
        ORDER BY tipo, version DESC
    ");
    
    Response::success('Prompts', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function handleObtenerPrompt(int $id): void {
    $db = getConnection();
    
    $stmt = $db->prepare("SELECT * FROM prompts WHERE id = ?");
    $stmt->execute([$id]);
    $prompt = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$prompt) {
        Response::error('Prompt no encontrado', [], 404);
    }
    
    Response::success('Prompt', $prompt);
}

function handleCrearPrompt(array $body): void {
    $required = ['nombre', 'version', 'tipo', 'contenido'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            Response::error("{$field} es requerido", [], 400);
        }
    }
    
    $db = getConnection();
    
    $stmt = $db->prepare("
        INSERT INTO prompts (nombre, version, tipo, contenido, notas, creado_por, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $body['nombre'],
        $body['version'],
        $body['tipo'],
        $body['contenido'],
        $body['notas'] ?? null,
        AuthMiddleware::getCurrentUserId(),
        $body['activo'] ?? 0
    ]);
    
    Response::success('Prompt creado', ['id' => $db->lastInsertId()], 201);
}

function handleActualizarPrompt(int $id, array $body): void {
    $db = getConnection();
    
    $updates = [];
    $params = [];
    
    $campos = ['nombre', 'version', 'tipo', 'contenido', 'notas', 'activo'];
    foreach ($campos as $campo) {
        if (isset($body[$campo])) {
            $updates[] = "{$campo} = ?";
            $params[] = $body[$campo];
        }
    }
    
    if (empty($updates)) {
        Response::error('No hay campos para actualizar', [], 400);
    }
    
    $params[] = $id;
    $sql = "UPDATE prompts SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    Response::success('Prompt actualizado');
}

// ========================================
// PLANTILLAS HANDLERS
// ========================================

function handleListarPlantillas(): void {
    $db = getConnection();
    
    $stmt = $db->query("
        SELECT id, nombre, version, descripcion, activa, fecha_creacion
        FROM plantillas_pagare
        ORDER BY nombre, version DESC
    ");
    
    Response::success('Plantillas', $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function handleObtenerPlantilla(int $id): void {
    $db = getConnection();
    
    $stmt = $db->prepare("SELECT * FROM plantillas_pagare WHERE id = ?");
    $stmt->execute([$id]);
    $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$plantilla) {
        Response::error('Plantilla no encontrada', [], 404);
    }
    
    $plantilla['configuracion'] = json_decode($plantilla['configuracion'], true);
    
    Response::success('Plantilla', $plantilla);
}

function handleCrearPlantilla(array $body): void {
    $required = ['nombre', 'version', 'configuracion'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            Response::error("{$field} es requerido", [], 400);
        }
    }
    
    $db = getConnection();
    
    $configuracion = is_array($body['configuracion']) 
        ? json_encode($body['configuracion']) 
        : $body['configuracion'];
    
    $stmt = $db->prepare("
        INSERT INTO plantillas_pagare (nombre, version, descripcion, configuracion, creado_por, activa)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $body['nombre'],
        $body['version'],
        $body['descripcion'] ?? null,
        $configuracion,
        AuthMiddleware::getCurrentUserId(),
        $body['activa'] ?? 0
    ]);
    
    Response::success('Plantilla creada', ['id' => $db->lastInsertId()], 201);
}

function handleActualizarPlantilla(int $id, array $body): void {
    $db = getConnection();
    
    $updates = [];
    $params = [];
    
    $campos = ['nombre', 'version', 'descripcion', 'activa'];
    foreach ($campos as $campo) {
        if (isset($body[$campo])) {
            $updates[] = "{$campo} = ?";
            $params[] = $body[$campo];
        }
    }
    
    if (isset($body['configuracion'])) {
        $updates[] = 'configuracion = ?';
        $params[] = is_array($body['configuracion']) 
            ? json_encode($body['configuracion']) 
            : $body['configuracion'];
    }
    
    if (empty($updates)) {
        Response::error('No hay campos para actualizar', [], 400);
    }
    
    $params[] = $id;
    $sql = "UPDATE plantillas_pagare SET " . implode(', ', $updates) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    Response::success('Plantilla actualizada');
}

