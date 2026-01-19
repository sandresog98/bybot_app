<?php
// debug.php - Archivo de debug para ver qué está pasando
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    // Cargar configuración base
    require_once dirname(__DIR__, 2) . '/config/constants.php';
    
    // Verificar que BASE_DIR esté definido
    if (!defined('BASE_DIR')) {
        throw new Exception('BASE_DIR no está definido. Verifica constants.php');
    }
    
    require_once BASE_DIR . '/web/core/Response.php';
    
    // Obtener método y path
    $method = $_SERVER['REQUEST_METHOD'];
    $requestUri = $_SERVER['REQUEST_URI'];
    
    // Detectar path base automáticamente desde el script actual
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $basePath = rtrim($scriptPath, '/');
    
    // Extraer path relativo a la base de la API
    $path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
    $path = trim($path, '/');
    
    // Parsear segmentos del path
    $segments = $path ? explode('/', $path) : [];
    
    // Obtener versión de API (v1, v2, etc.)
    $version = array_shift($segments) ?? 'v1';
    
    // Obtener recurso principal
    $resource = array_shift($segments) ?? '';
    
    echo json_encode([
        'status' => 'ok',
        'debug' => [
            'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'N/A',
            'request_uri' => $requestUri,
            'script_path' => $scriptPath,
            'base_path' => $basePath,
            'parsed_path' => $path,
            'segments' => $segments,
            'version' => $version,
            'resource' => $resource,
            'query_string' => $_SERVER['QUERY_STRING'] ?? '',
            'get_params' => $_GET
        ],
        'constants' => [
            'BASE_DIR' => BASE_DIR,
            'APP_ENV' => APP_ENV ?? 'N/A',
            'APP_DEBUG' => APP_DEBUG ?? false
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

