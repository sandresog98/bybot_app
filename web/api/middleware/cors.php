<?php
/**
 * Middleware CORS
 * Configura los headers para permitir Cross-Origin Resource Sharing
 */

function cors_headers() {
    // Orígenes permitidos (en producción, especificar dominios exactos)
    $allowed_origins = [
        'http://localhost',
        'http://localhost:80',
        'http://127.0.0.1',
        defined('APP_URL') ? APP_URL : ''
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, $allowed_origins) || APP_ENV === 'development') {
        header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-Token");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400"); // 24 horas cache
    
    // Manejar preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// Auto-ejecutar al incluir
cors_headers();

