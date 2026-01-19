<?php
/**
 * Test específico para la ruta de procesos
 * Simula exactamente lo que hace index.php
 */

// Cargar configuración
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simular REQUEST_URI con barra diagonal
$_SERVER['REQUEST_URI'] = '/web/api/v1/procesos/?estado=analizado&per_page=5';
$_SERVER['SCRIPT_NAME'] = '/web/api/index.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

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

// Limpiar query string del path
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

header('Content-Type: application/json; charset=utf-8');

$result = [
    'parsing' => [
        'request_uri' => $requestUri,
        'parsed_path' => $parsedPath,
        'base_path' => $basePath,
        'path' => $path,
        'segments' => $segments,
        'version' => $version,
        'resource' => $resource,
        'id' => $id,
        'action' => $action,
    ],
    'session' => [
        'id' => session_id(),
        'has_user_id' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
    ],
    'test_auth' => null,
];

// Probar autenticación
try {
    require_once BASE_DIR . '/web/api/middleware/auth.php';
    $user = AuthMiddleware::check(false); // No requerir, solo verificar
    $result['test_auth'] = [
        'success' => $user !== null,
        'user' => $user ? ['id' => $user['id'], 'rol' => $user['rol']] : null,
    ];
} catch (Exception $e) {
    $result['test_auth'] = [
        'success' => false,
        'error' => $e->getMessage(),
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);

