<?php
/**
 * Archivo de prueba para diagnosticar el problema 403
 * Eliminar después de diagnosticar
 */

// Iniciar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$debug = [
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
    'script_name' => $_SERVER['SCRIPT_NAME'] ?? null,
    'query_string' => $_SERVER['QUERY_STRING'] ?? null,
    'parsed_path' => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH),
    'session' => [
        'id' => session_id(),
        'status' => session_status(),
        'has_user_id' => isset($_SESSION['user_id']),
        'user_id' => $_SESSION['user_id'] ?? null,
        'has_user' => isset($_SESSION['user']),
    ],
    'get_params' => $_GET,
    'method' => $_SERVER['REQUEST_METHOD'] ?? null,
];

echo json_encode($debug, JSON_PRETTY_PRINT);

