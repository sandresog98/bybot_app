<?php
/**
 * Archivo de prueba para diagnosticar el parsing del router
 * Eliminar después de diagnosticar
 */

// Simular la misma lógica del router
$requestUri = '/web/api/v1/procesos/?estado=analizado&per_page=5';
$scriptPath = '/web/api';
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

// Parsear segmentos del path
$segments = $path ? explode('/', $path) : [];
$segments = array_filter($segments, function($seg) {
    return !empty($seg);
});
$segments = array_values($segments);

// Obtener versión de API
$version = array_shift($segments) ?? 'v1';

// Obtener recurso principal
$resource = array_shift($segments) ?? '';

// Obtener ID o acción
$id = array_shift($segments);
$action = array_shift($segments);

// Normalizar
if ($id === '') {
    $id = null;
}
if ($action === '') {
    $action = null;
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'request_uri' => $requestUri,
    'parsed_path' => $parsedPath,
    'path' => $path,
    'segments' => $segments,
    'version' => $version,
    'resource' => $resource,
    'id' => $id,
    'action' => $action,
    'query_string' => parse_url($requestUri, PHP_URL_QUERY),
], JSON_PRETTY_PRINT);

