<?php
/**
 * Test directo del router de procesos
 * Para verificar si el problema está en el router o en LiteSpeed
 */

// Cargar configuración
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';
require_once __DIR__ . '/middleware/cors.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Simular exactamente lo que hace index.php para procesos
$_GET['estado'] = 'analizado';
$_GET['per_page'] = '5';

try {
    require_once BASE_DIR . '/web/api/middleware/auth.php';
    require_once BASE_DIR . '/web/api/middleware/rate_limit.php';
    require_once BASE_DIR . '/web/modules/procesos/services/ProcesoService.php';
    
    // Verificar autenticación primero
    $user = AuthMiddleware::check(false);
    
    if (!$user) {
        echo json_encode([
            'error' => 'No autenticado',
            'session' => [
                'id' => session_id(),
                'has_user_id' => isset($_SESSION['user_id']),
                'user_id' => $_SESSION['user_id'] ?? null,
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Intentar listar procesos
    $service = new ProcesoService();
    $filters = ['estado' => 'analizado'];
    $result = $service->listar($filters, 1, 5);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'user' => ['id' => $user['id'], 'rol' => $user['rol']]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

