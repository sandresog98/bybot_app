<?php
/**
 * Script de prueba para verificar autenticación y sesión
 * Acceder: /web/api/test_auth.php
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';

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

header('Content-Type: application/json');

$info = [
    'session' => [
        'id' => session_id(),
        'status' => session_status(),
        'status_name' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : (session_status() === PHP_SESSION_NONE ? 'NONE' : 'DISABLED'),
        'has_user_id' => isset($_SESSION['user_id']),
        'has_user' => isset($_SESSION['user']),
        'user_id' => $_SESSION['user_id'] ?? null,
        'user' => $_SESSION['user'] ?? null,
        'cookie_params' => session_get_cookie_params(),
        'session_name' => session_name()
    ],
    'cookies' => $_COOKIE,
    'server' => [
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
        'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? null,
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
        'HTTPS' => $_SERVER['HTTPS'] ?? null
    ],
    'test_url' => '/web/api/v1/procesos?estado=analizado&per_page=5',
    'message' => 'Verifica si hay user_id y user en la sesión. Si no hay, la sesión no se está compartiendo correctamente.'
];

echo json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

