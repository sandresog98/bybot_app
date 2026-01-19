<?php
// test_health.php - Prueba simple del endpoint health
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Cargar configuración base
    require_once dirname(__DIR__, 2) . '/config/constants.php';
    require_once BASE_DIR . '/web/core/Response.php';
    
    // Verificar constantes
    if (!defined('APP_VERSION')) {
        throw new Exception('APP_VERSION no está definido');
    }
    
    // Simular el endpoint health
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
        'version' => APP_VERSION,
        'environment' => APP_ENV ?? 'production',
        'message' => 'Health check funcionando'
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}

