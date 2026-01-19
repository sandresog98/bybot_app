<?php
/**
 * Test de parsing para verificar cómo se interpreta la URL
 */

header('Content-Type: application/json; charset=utf-8');

// Simular diferentes URLs
$testUrls = [
    '/web/api/v1/procesos?estado=analizado&per_page=5',
    '/web/api/v1/procesos/?estado=analizado&per_page=5',
    '/web/api/v1/stats/dashboard',
    '/web/api/v1/colas/estado',
];

$results = [];

foreach ($testUrls as $requestUri) {
    $scriptPath = '/web/api';
    $basePath = rtrim($scriptPath, '/');
    
    $parsedPath = parse_url($requestUri, PHP_URL_PATH);
    $path = str_replace($basePath, '', $parsedPath);
    $path = trim($path, '/');
    $path = str_replace('index.php', '', $path);
    $path = trim($path, '/');
    $path = preg_replace('/[?&].*$/', '', $path);
    
    $segments = $path ? explode('/', $path) : [];
    $segments = array_filter($segments, function($seg) {
        return !empty($seg);
    });
    $segments = array_values($segments);
    
    $version = array_shift($segments) ?? 'v1';
    $resource = array_shift($segments) ?? '';
    $id = array_shift($segments);
    
    if ($id === '') $id = null;
    
    $results[] = [
        'url' => $requestUri,
        'parsed_path' => $parsedPath,
        'path' => $path,
        'version' => $version,
        'resource' => $resource,
        'id' => $id,
    ];
}

echo json_encode($results, JSON_PRETTY_PRINT);

