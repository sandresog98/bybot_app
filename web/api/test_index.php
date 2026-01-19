<?php
/**
 * Test que simula exactamente index.php
 * Para diagnosticar por qué da 403
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Cargar configuración
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';
require_once __DIR__ . '/middleware/cors.php';

header('Content-Type: application/json; charset=utf-8');

// Obtener método y path (usar los reales)
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Detectar path base
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = rtrim($scriptPath, '/');

// Extraer path relativo
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $parsedPath);
$path = trim($path, '/');

// Remover "index.php"
$path = str_replace('index.php', '', $path);
$path = trim($path, '/');

// Limpiar query string
$path = preg_replace('/[?&].*$/', '', $path);

// Parsear segmentos
$segments = $path ? explode('/', $path) : [];
$segments = array_filter($segments, function($seg) {
    return !empty($seg);
});
$segments = array_values($segments);

$version = array_shift($segments) ?? 'v1';
$resource = array_shift($segments) ?? '';
$id = array_shift($segments);
$action = array_shift($segments);

if ($id === '') $id = null;
if ($action === '') $action = null;

$debug = [
    'request_uri' => $requestUri,
    'parsed_path' => $parsedPath,
    'path' => $path,
    'version' => $version,
    'resource' => $resource,
    'id' => $id,
    'action' => $action,
    'method' => $method,
    'session' => [
        'id' => session_id(),
        'has_user_id' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
    ],
];

// Intentar cargar el router de procesos
if ($resource === 'procesos' && $version === 'v1') {
    try {
        require_once BASE_DIR . '/web/api/middleware/auth.php';
        require_once BASE_DIR . '/web/api/middleware/rate_limit.php';
        require_once BASE_DIR . '/web/modules/procesos/services/ProcesoService.php';
        
        // Verificar autenticación
        $user = AuthMiddleware::check(false);
        $debug['auth_check'] = [
            'success' => $user !== null,
            'user' => $user ? ['id' => $user['id']] : null,
        ];
        
        // Si requiere autenticación
        if (!$user) {
            $debug['error'] = 'No autenticado - AuthMiddleware::check() devolvió null';
            $debug['would_return'] = '401 Unauthorized';
        } else {
            $debug['success'] = 'Autenticación OK, debería funcionar';
        }
        
    } catch (Exception $e) {
        $debug['error'] = $e->getMessage();
        $debug['trace'] = $e->getTraceAsString();
    }
}

echo json_encode($debug, JSON_PRETTY_PRINT);

