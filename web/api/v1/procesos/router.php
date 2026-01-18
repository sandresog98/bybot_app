<?php
/**
 * Router de Procesos
 * CRUD completo para gestión de procesos
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';
require_once BASE_DIR . '/web/modules/procesos/services/ProcesoService.php';

/**
 * Enruta solicitudes de procesos
 */
function routeProcesos(string $method, ?string $id, ?string $action, array $body): void {
    // Verificar autenticación
    AuthMiddleware::check();
    
    // Rate limiting
    RateLimitMiddleware::checkApi();
    
    $service = new ProcesoService();
    
    // Si hay ID numérico, es operación sobre un proceso específico
    if ($id !== null && is_numeric($id)) {
        routeProcesoById((int)$id, $method, $action, $body, $service);
        return;
    }
    
    // Rutas especiales
    switch ($id) {
        case 'estados':
            handleEstados();
            break;
            
        case 'estadisticas':
        case 'stats':
            handleEstadisticas($service);
            break;
            
        case null:
        case '':
            // Lista o crear
            if ($method === 'GET') {
                handleListar($service);
            } elseif ($method === 'POST') {
                handleCrear($body, $service);
            } else {
                Response::error('Método no permitido', [], 405);
            }
            break;
            
        default:
            // Buscar por código
            if ($method === 'GET') {
                handleBuscarPorCodigo($id, $service);
            } else {
                Response::error('Proceso no encontrado', [], 404);
            }
    }
}

/**
 * Rutas para un proceso específico por ID
 */
function routeProcesoById(int $id, string $method, ?string $action, array $body, ProcesoService $service): void {
    // Verificar acceso al módulo
    AuthMiddleware::requireAccess('procesos.ver');
    
    switch ($action) {
        // GET /procesos/{id}/historial
        case 'historial':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleHistorial($id, $service);
            break;
            
        // POST /procesos/{id}/encolar-analisis
        case 'encolar-analisis':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            AuthMiddleware::requireAccess('procesos.editar_ia');
            handleEncolarAnalisis($id, $service);
            break;
            
        // POST /procesos/{id}/encolar-llenado
        case 'encolar-llenado':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            AuthMiddleware::requireAccess('procesos.editar_ia');
            handleEncolarLlenado($id, $service);
            break;
            
        // POST /procesos/{id}/cancelar
        case 'cancelar':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            AuthMiddleware::requireAccess('procesos.editar_ia');
            handleCancelar($id, $body, $service);
            break;
            
        // POST /procesos/{id}/estado
        case 'estado':
            if ($method !== 'POST' && $method !== 'PUT') {
                Response::error('Método no permitido', [], 405);
            }
            AuthMiddleware::requireAccess('procesos.editar_ia');
            handleCambiarEstado($id, $body, $service);
            break;
            
        // Sin acción: CRUD básico
        case null:
        case '':
            switch ($method) {
                case 'GET':
                    handleObtener($id, $service);
                    break;
                case 'PUT':
                case 'PATCH':
                    AuthMiddleware::requireAccess('procesos.editar_ia');
                    handleActualizar($id, $body, $service);
                    break;
                case 'DELETE':
                    AuthMiddleware::requireRole('admin');
                    handleEliminar($id, $service);
                    break;
                default:
                    Response::error('Método no permitido', [], 405);
            }
            break;
            
        default:
            Response::error("Acción no encontrada: {$action}", [], 404);
    }
}

// ========================================
// HANDLERS
// ========================================

/**
 * GET /procesos
 * Lista procesos con filtros y paginación
 */
function handleListar(ProcesoService $service): void {
    $filters = [];
    
    // Filtros desde query string
    if (!empty($_GET['estado'])) {
        $filters['estado'] = $_GET['estado'];
    }
    if (!empty($_GET['tipo'])) {
        $filters['tipo'] = $_GET['tipo'];
    }
    if (!empty($_GET['codigo'])) {
        $filters['codigo'] = $_GET['codigo'];
    }
    if (!empty($_GET['creado_por'])) {
        $filters['creado_por'] = (int)$_GET['creado_por'];
    }
    if (!empty($_GET['fecha_desde'])) {
        $filters['fecha_desde'] = $_GET['fecha_desde'];
    }
    if (!empty($_GET['fecha_hasta'])) {
        $filters['fecha_hasta'] = $_GET['fecha_hasta'];
    }
    if (!empty($_GET['q'])) {
        $filters['q'] = $_GET['q'];
    }
    if (!empty($_GET['order_by'])) {
        $filters['order_by'] = $_GET['order_by'];
        $filters['order_dir'] = $_GET['order_dir'] ?? 'DESC';
    }
    
    // Paginación
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 10)));
    
    $result = $service->listar($filters, $page, $perPage);
    
    Response::paginated(
        $result['items'],
        $result['pagination']['total'],
        $page,
        $perPage,
        'Procesos obtenidos'
    );
}

/**
 * POST /procesos
 * Crea un nuevo proceso
 */
function handleCrear(array $body, ProcesoService $service): void {
    AuthMiddleware::requireAccess('procesos.crear');
    
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $proceso = $service->crear($body, $userId);
        Response::success('Proceso creado exitosamente', $proceso, 201);
    } catch (InvalidArgumentException $e) {
        $errors = json_decode($e->getMessage(), true);
        Response::error('Error de validación', $errors ?: ['general' => $e->getMessage()], 400);
    }
}

/**
 * GET /procesos/{id}
 * Obtiene un proceso por ID
 */
function handleObtener(int $id, ProcesoService $service): void {
    $proceso = $service->obtener($id);
    
    if (!$proceso) {
        Response::error('Proceso no encontrado', [], 404);
    }
    
    Response::success('Proceso obtenido', $proceso);
}

/**
 * GET /procesos/{codigo}
 * Busca un proceso por código
 */
function handleBuscarPorCodigo(string $codigo, ProcesoService $service): void {
    $proceso = $service->obtenerPorCodigo($codigo);
    
    if (!$proceso) {
        Response::error('Proceso no encontrado', [], 404);
    }
    
    Response::success('Proceso obtenido', $proceso);
}

/**
 * PUT /procesos/{id}
 * Actualiza un proceso
 */
function handleActualizar(int $id, array $body, ProcesoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $proceso = $service->actualizar($id, $body, $userId);
        Response::success('Proceso actualizado', $proceso);
    } catch (InvalidArgumentException $e) {
        $errors = json_decode($e->getMessage(), true);
        Response::error($errors['message'] ?? 'Error de validación', $errors ?: [], 400);
    }
}

/**
 * DELETE /procesos/{id}
 * Elimina un proceso
 */
function handleEliminar(int $id, ProcesoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $service->eliminar($id, $userId);
        Response::success('Proceso eliminado');
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * GET /procesos/estados
 * Obtiene los estados disponibles
 */
function handleEstados(): void {
    require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
    
    Response::success('Estados disponibles', [
        'estados' => Proceso::ESTADOS,
        'tipos' => Proceso::TIPOS
    ]);
}

/**
 * GET /procesos/estadisticas
 * Obtiene estadísticas de procesos
 */
function handleEstadisticas(ProcesoService $service): void {
    $userId = null;
    $fechaDesde = $_GET['fecha_desde'] ?? null;
    $fechaHasta = $_GET['fecha_hasta'] ?? null;
    
    // Si no es admin, mostrar solo sus procesos
    if (!AuthMiddleware::hasRole('admin') && !AuthMiddleware::hasRole('supervisor')) {
        $userId = AuthMiddleware::getCurrentUserId();
    }
    
    $stats = $service->getEstadisticas($userId, $fechaDesde, $fechaHasta);
    
    Response::success('Estadísticas de procesos', $stats);
}

/**
 * GET /procesos/{id}/historial
 * Obtiene el historial de un proceso
 */
function handleHistorial(int $id, ProcesoService $service): void {
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $historial = $service->getHistorial($id, $limit);
    
    Response::success('Historial del proceso', $historial);
}

/**
 * POST /procesos/{id}/encolar-analisis
 * Encola un proceso para análisis
 */
function handleEncolarAnalisis(int $id, ProcesoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $proceso = $service->encolarAnalisis($id, $userId);
        Response::success('Proceso encolado para análisis', $proceso);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * POST /procesos/{id}/encolar-llenado
 * Encola un proceso para llenado
 */
function handleEncolarLlenado(int $id, ProcesoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $proceso = $service->encolarLlenado($id, $userId);
        Response::success('Proceso encolado para llenado', $proceso);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * POST /procesos/{id}/cancelar
 * Cancela un proceso
 */
function handleCancelar(int $id, array $body, ProcesoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    $motivo = $body['motivo'] ?? null;
    
    try {
        $proceso = $service->cancelar($id, $userId, $motivo);
        Response::success('Proceso cancelado', $proceso);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * POST /procesos/{id}/estado
 * Cambia el estado de un proceso
 */
function handleCambiarEstado(int $id, array $body, ProcesoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    
    if (empty($body['estado'])) {
        Response::error('El campo estado es requerido', [], 400);
    }
    
    $mensaje = $body['mensaje'] ?? null;
    
    try {
        $proceso = $service->cambiarEstado($id, $body['estado'], $userId, $mensaje);
        Response::success('Estado actualizado', $proceso);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

