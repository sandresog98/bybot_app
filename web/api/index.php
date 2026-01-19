<?php
/**
 * Router Principal de la API REST
 * Punto de entrada para todas las solicitudes /api/v1/*
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurar sesión para compartir cookies entre admin y API
if (session_status() === PHP_SESSION_NONE) {
    // Asegurar que la cookie de sesión se comparta en todo el dominio
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Cargar configuración base
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';

// Middleware global
require_once __DIR__ . '/middleware/cors.php';

// Headers JSON para todas las respuestas
header('Content-Type: application/json; charset=utf-8');

// Obtener método y path
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Detectar path base automáticamente desde el script actual
// Ejemplo: /projects/bybot/web/api/index.php -> /projects/bybot/web/api
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = rtrim($scriptPath, '/');

// Extraer path relativo a la base de la API
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = str_replace($basePath, '', $parsedPath);
$path = trim($path, '/');

// Remover "index.php" del path si está presente
$path = str_replace('index.php', '', $path);
$path = trim($path, '/');

// Limpiar cualquier carácter inválido que pueda quedar (como ? o &)
$path = preg_replace('/[?&].*$/', '', $path);

// Si el path está vacío o es solo "index.php", intentar obtener desde REQUEST_URI
if (empty($path) || $path === 'index.php') {
    // Si hay un parámetro 'resource' en GET, usarlo (para debugging)
    if (isset($_GET['resource'])) {
        $path = 'v1/' . $_GET['resource'];
    } else {
        // Intentar extraer el path desde REQUEST_URI completo
        // Buscar 'api' en el path y tomar todo lo que viene después
        $fullPath = trim($parsedPath, '/');
        $pathParts = explode('/', $fullPath);
        $apiIndex = array_search('api', $pathParts);
        if ($apiIndex !== false && isset($pathParts[$apiIndex + 1])) {
            // Tomar todo después de 'api', pero saltar 'index.php' si existe
            $afterApi = array_slice($pathParts, $apiIndex + 1);
            $afterApi = array_filter($afterApi, function($part) {
                return $part !== 'index.php';
            });
            $path = implode('/', $afterApi);
        } else {
            // Si no se encuentra, usar path vacío (root de API)
            $path = '';
        }
    }
}

// Parsear segmentos del path
$segments = $path ? explode('/', $path) : [];
// Filtrar segmentos vacíos (pueden aparecer por barras diagonales dobles o al final)
$segments = array_filter($segments, function($seg) {
    return !empty($seg);
});
$segments = array_values($segments); // Reindexar

// Obtener versión de API (v1, v2, etc.)
$version = array_shift($segments) ?? 'v1';

if ($version !== 'v1') {
    Response::jsonError("Versión de API no soportada: {$version}", 400);
}

// Obtener recurso principal
$resource = array_shift($segments) ?? '';

// Obtener ID o acción
$id = array_shift($segments);
$action = array_shift($segments);

// Normalizar: si $id es cadena vacía, convertir a null
if ($id === '') {
    $id = null;
}
if ($action === '') {
    $action = null;
}

// Obtener body de la solicitud
$input = file_get_contents('php://input');
$body = json_decode($input, true) ?? [];

// Agregar datos POST/GET al body si están vacíos
if (empty($body) && !empty($_POST)) {
    $body = $_POST;
}

// Debug info (solo cuando se solicita explícitamente con ?debug=1)
// No interrumpir el flujo normal, solo agregar headers de debug
if (isset($_GET['debug']) && $_GET['debug'] === '1' && defined('APP_DEBUG') && APP_DEBUG) {
    // Iniciar sesión para debug
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Agregar información de debug como headers (no interrumpe la respuesta)
    header('X-Debug-Method: ' . $method);
    header('X-Debug-Resource: ' . $resource);
    header('X-Debug-Path: ' . $path);
    header('X-Debug-Version: ' . $version);
    if (session_status() === PHP_SESSION_ACTIVE) {
        header('X-Debug-Session-Id: ' . session_id());
        header('X-Debug-User-Id: ' . ($_SESSION['user_id'] ?? 'null'));
    }
}

// Router principal
try {
    switch ($resource) {
        // =========================================
        // AUTH ENDPOINTS
        // =========================================
        case 'auth':
            require_once __DIR__ . '/v1/auth/router.php';
            routeAuth($method, $id, $body);
            break;
            
        // =========================================
        // PROCESOS ENDPOINTS
        // =========================================
        case 'procesos':
            require_once __DIR__ . '/v1/procesos/router.php';
            routeProcesos($method, $id, $action, $body);
            break;
            
        // =========================================
        // ARCHIVOS ENDPOINTS
        // =========================================
        case 'archivos':
            require_once __DIR__ . '/v1/archivos/router.php';
            routeArchivos($method, $id, $action, $body);
            break;
            
        // =========================================
        // VALIDACION ENDPOINTS
        // =========================================
        case 'validacion':
            require_once __DIR__ . '/v1/validacion/router.php';
            routeValidacion($method, $id, $body);
            break;
            
        // =========================================
        // COLAS ENDPOINTS
        // =========================================
        case 'colas':
            require_once __DIR__ . '/v1/colas/router.php';
            routeColas($method, $id, $body);
            break;
            
        // =========================================
        // USUARIOS ENDPOINTS
        // =========================================
        case 'usuarios':
            require_once __DIR__ . '/v1/usuarios/router.php';
            routeUsuarios($method, $id, $action, $body);
            break;
            
        // =========================================
        // WEBHOOK ENDPOINTS (Workers)
        // =========================================
        case 'webhook':
            require_once __DIR__ . '/v1/webhook/router.php';
            routeWebhook($method, $id, $body);
            break;
            
        // =========================================
        // CONFIGURACIÓN ENDPOINTS
        // =========================================
        case 'config':
            require_once __DIR__ . '/v1/config/router.php';
            routeConfig($method, $id, $action, $body);
            break;
            
        // =========================================
        // ESTADÍSTICAS / DASHBOARD
        // =========================================
        case 'stats':
        case 'dashboard':
            require_once __DIR__ . '/v1/stats/router.php';
            routeStats($method, $id, $body);
            break;
            
        // =========================================
        // HEALTH CHECK
        // =========================================
        case 'health':
        case 'ping':
            Response::json([
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => defined('APP_VERSION') ? APP_VERSION : '2.0.0',
                'environment' => APP_ENV ?? 'production'
            ]);
            break;
            
        // =========================================
        // ROOT - INFO DE API
        // =========================================
        case '':
            Response::json([
                'name' => 'ByBot API',
                'version' => 'v1',
                'status' => 'running',
                'endpoints' => [
                    '/api/v1/auth' => 'Autenticación',
                    '/api/v1/procesos' => 'Gestión de procesos',
                    '/api/v1/archivos' => 'Gestión de archivos',
                    '/api/v1/validacion' => 'Validación de datos IA',
                    '/api/v1/colas' => 'Estado de colas',
                    '/api/v1/usuarios' => 'Gestión de usuarios',
                    '/api/v1/webhook' => 'Callbacks de workers',
                    '/api/v1/config' => 'Configuración',
                    '/api/v1/stats' => 'Estadísticas',
                    '/api/v1/health' => 'Health check'
                ],
                'documentation' => (defined('BASE_URL') ? BASE_URL : APP_URL) . '/docs'
            ]);
            break;
            
        // =========================================
        // NOT FOUND
        // =========================================
        default:
            Response::error("Recurso no encontrado: {$resource}", 404, []);
    }
    
} catch (PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
    Response::error('Error de base de datos', 
        500,
        APP_ENV === 'development' ? ['detail' => $e->getMessage()] : []
    );
    
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 400, []);
    
} catch (Exception $e) {
    error_log("Error en API: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    Response::error('Error interno del servidor', 
        500,
        APP_ENV === 'development' ? ['detail' => $e->getMessage()] : []
    );
}

