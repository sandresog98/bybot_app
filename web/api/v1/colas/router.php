<?php
/**
 * Router de Colas
 * Gestión de trabajos en colas Redis
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';

/**
 * Enruta solicitudes de colas
 */
function routeColas(string $method, ?string $id, array $body): void {
    AuthMiddleware::check();
    RateLimitMiddleware::checkApi();
    
    switch ($id) {
        case 'estado':
        case 'status':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleEstado();
            break;
            
        case 'encolar':
        case 'push':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            AuthMiddleware::requireRole('admin');
            handleEncolar($body);
            break;
            
        case 'trabajos':
        case 'jobs':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleListarTrabajos();
            break;
            
        case 'limpiar':
        case 'clear':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            AuthMiddleware::requireRole('admin');
            handleLimpiar($body);
            break;
            
        default:
            Response::error('Ruta no encontrada', [], 404);
    }
}

/**
 * GET /colas/estado
 * Obtiene estado de las colas
 */
function handleEstado(): void {
    $estado = [
        'colas' => [],
        'redis_conectado' => false,
        'timestamp' => date('c')
    ];
    
    try {
        require_once BASE_DIR . '/web/core/QueueManager.php';
        $queue = new QueueManager();
        
        $estado['redis_conectado'] = true;
        
        // Obtener tamaño de cada cola
        $colas = ['bybot:analyze', 'bybot:fill', 'bybot:notify'];
        foreach ($colas as $cola) {
            $estado['colas'][$cola] = [
                'nombre' => $cola,
                'pendientes' => $queue->size($cola)
            ];
        }
        
    } catch (Exception $e) {
        $estado['redis_conectado'] = false;
        $estado['error'] = 'No se pudo conectar a Redis: ' . $e->getMessage();
    }
    
    // Obtener trabajos registrados en BD
    $db = getConnection();
    
    $stmt = $db->query("
        SELECT estado, COUNT(*) as total 
        FROM colas_trabajos 
        WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY estado
    ");
    $estado['trabajos_24h'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Trabajos en progreso
    $stmt = $db->query("
        SELECT * FROM colas_trabajos 
        WHERE estado IN ('pendiente', 'procesando')
        ORDER BY fecha_creacion DESC
        LIMIT 10
    ");
    $estado['en_progreso'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::success('Estado de colas', $estado);
}

/**
 * POST /colas/encolar
 * Encola un trabajo manualmente
 */
function handleEncolar(array $body): void {
    if (empty($body['cola'])) {
        Response::error('cola es requerido', [], 400);
    }
    
    if (empty($body['payload'])) {
        Response::error('payload es requerido', [], 400);
    }
    
    $colasPermitidas = ['bybot:analyze', 'bybot:fill', 'bybot:notify'];
    if (!in_array($body['cola'], $colasPermitidas)) {
        Response::error('Cola no permitida: ' . $body['cola'], [], 400);
    }
    
    try {
        require_once BASE_DIR . '/web/core/QueueManager.php';
        $queue = new QueueManager();
        
        $resultado = $queue->push($body['cola'], $body['payload']);
        
        // Registrar en BD
        $db = getConnection();
        $jobId = 'manual_' . uniqid();
        
        $stmt = $db->prepare("
            INSERT INTO colas_trabajos 
            (job_id, cola, tipo_trabajo, payload, estado, proceso_id)
            VALUES (?, ?, ?, ?, 'pendiente', ?)
        ");
        $stmt->execute([
            $jobId,
            $body['cola'],
            $body['tipo'] ?? 'manual',
            json_encode($body['payload']),
            $body['proceso_id'] ?? null
        ]);
        
        Response::success('Trabajo encolado', [
            'job_id' => $jobId,
            'cola' => $body['cola'],
            'posicion' => $resultado
        ]);
        
    } catch (Exception $e) {
        Response::error('Error encolando trabajo: ' . $e->getMessage(), [], 500);
    }
}

/**
 * GET /colas/trabajos
 * Lista trabajos en cola
 */
function handleListarTrabajos(): void {
    $db = getConnection();
    
    // Filtros
    $where = ['1 = 1'];
    $params = [];
    
    if (!empty($_GET['estado'])) {
        $where[] = 'estado = ?';
        $params[] = $_GET['estado'];
    }
    
    if (!empty($_GET['cola'])) {
        $where[] = 'cola = ?';
        $params[] = $_GET['cola'];
    }
    
    if (!empty($_GET['proceso_id'])) {
        $where[] = 'proceso_id = ?';
        $params[] = (int)$_GET['proceso_id'];
    }
    
    $whereClause = implode(' AND ', $where);
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    
    $sql = "
        SELECT * FROM colas_trabajos 
        WHERE {$whereClause}
        ORDER BY fecha_creacion DESC
        LIMIT {$limit}
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $trabajos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decodificar JSON
    foreach ($trabajos as &$trabajo) {
        $trabajo['payload'] = json_decode($trabajo['payload'], true);
        $trabajo['resultado'] = $trabajo['resultado'] 
            ? json_decode($trabajo['resultado'], true) 
            : null;
    }
    
    Response::success('Trabajos en cola', $trabajos);
}

/**
 * POST /colas/limpiar
 * Limpia una cola específica
 */
function handleLimpiar(array $body): void {
    if (empty($body['cola'])) {
        Response::error('cola es requerido', [], 400);
    }
    
    try {
        require_once BASE_DIR . '/web/core/QueueManager.php';
        $queue = new QueueManager();
        
        $eliminados = $queue->clear($body['cola']);
        
        Response::success('Cola limpiada', [
            'cola' => $body['cola'],
            'trabajos_eliminados' => $eliminados
        ]);
        
    } catch (Exception $e) {
        Response::error('Error limpiando cola: ' . $e->getMessage(), [], 500);
    }
}

