<?php
/**
 * API: Servir archivo para el Bot - ByBot App
 * Endpoint seguro para que el bot Python descargue archivos del servidor
 */

header('Content-Type: application/json');

// Cargar configuración primero
require_once __DIR__ . '/../../../../config/env_loader.php';
loadEnv();

// Validar token de API
// Apache convierte headers a HTTP_X_API_TOKEN, pero también verificar otros formatos
$apiToken = '';
if (isset($_SERVER['HTTP_X_API_TOKEN'])) {
    $apiToken = $_SERVER['HTTP_X_API_TOKEN'];
} elseif (isset($_SERVER['X_API_TOKEN'])) {
    $apiToken = $_SERVER['X_API_TOKEN'];
} elseif (isset($_GET['token'])) {
    $apiToken = $_GET['token'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $apiToken = $headers['X-API-Token'] ?? $headers['x-api-token'] ?? '';
}

if (empty($apiToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de API requerido']);
    exit;
}

// Intentar obtener token desde múltiples fuentes
$expectedToken = getenv('BOT_API_TOKEN');
if (empty($expectedToken)) {
    $expectedToken = $_ENV['BOT_API_TOKEN'] ?? $_SERVER['BOT_API_TOKEN'] ?? '';
}
if (empty($expectedToken) && isset($GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN'])) {
    $expectedToken = $GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN'];
}

// Limpiar token esperado (eliminar espacios y saltos de línea)
$expectedToken = trim($expectedToken);

if (empty($expectedToken)) {
    // Log para debugging
    error_log("BOT_API_TOKEN no encontrado. Fuentes verificadas: getenv=" . (getenv('BOT_API_TOKEN') ? 'Sí' : 'No') . 
              ", _ENV=" . (isset($_ENV['BOT_API_TOKEN']) ? 'Sí' : 'No') . 
              ", _SERVER=" . (isset($_SERVER['BOT_API_TOKEN']) ? 'Sí' : 'No') . 
              ", GLOBALS=" . (isset($GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN']) ? 'Sí' : 'No'));
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Token de API no configurado en el servidor',
        'hint' => 'Verifica que BOT_API_TOKEN esté en el archivo .env en la raíz del proyecto'
    ]);
    exit;
}

// Normalizar tokens (eliminar espacios y saltos de línea)
$apiToken = trim($apiToken);
$expectedToken = trim($expectedToken);

if ($apiToken !== $expectedToken) {
    // Log detallado para debugging
    $debugInfo = [
        'received_length' => strlen($apiToken),
        'expected_length' => strlen($expectedToken),
        'received_start' => substr($apiToken, 0, 20),
        'expected_start' => substr($expectedToken, 0, 20),
        'received_end' => substr($apiToken, -10),
        'expected_end' => substr($expectedToken, -10),
        'match' => $apiToken === $expectedToken ? 'true' : 'false',
        'received_hex' => bin2hex(substr($apiToken, 0, 10)),
        'expected_hex' => bin2hex(substr($expectedToken, 0, 10))
    ];
    
    error_log("BOT API Token mismatch - " . json_encode($debugInfo));
    
    // En desarrollo, mostrar información de debug
    $isDevelopment = getenv('APP_ENV') === 'development' || empty(getenv('APP_ENV'));
    
    http_response_code(403);
    $response = ['error' => 'Token de API inválido'];
    
    if ($isDevelopment) {
        $response['debug'] = $debugInfo;
        $response['hint'] = 'Verifica que BOT_API_TOKEN en .env del servidor coincida exactamente con el del bot';
    }
    
    echo json_encode($response);
    exit;
}

// Validar parámetros
$procesoId = (int)($_GET['proceso_id'] ?? 0);
$tipo = $_GET['tipo'] ?? ''; // 'pagare', 'estado_cuenta', 'anexo'
$anexoId = (int)($_GET['anexo_id'] ?? 0);

if ($procesoId <= 0 || empty($tipo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos: proceso_id y tipo son requeridos']);
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
require_once '../models/CrearCoop.php';

$crearCoopModel = new CrearCoop();
$proceso = $crearCoopModel->obtenerProceso($procesoId);

if (!$proceso) {
    http_response_code(404);
    echo json_encode(['error' => 'Proceso no encontrado']);
    exit;
}

// Convertir rutas relativas de BD a absolutas
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/opt/lampp/htdocs';
$archivoPath = null;
$nombreArchivo = 'archivo.pdf';

switch ($tipo) {
    case 'pagare':
        $rutaRelativa = $proceso['archivo_pagare_original'] ?? null;
        if ($rutaRelativa) {
            $archivoPath = $docRoot . $rutaRelativa;
            $nombreArchivo = 'pagare_' . $proceso['codigo'] . '.pdf';
        }
        break;
        
    case 'estado_cuenta':
        $rutaRelativa = $proceso['archivo_estado_cuenta'] ?? null;
        if ($rutaRelativa) {
            $archivoPath = $docRoot . $rutaRelativa;
            $nombreArchivo = 'estado_cuenta_' . $proceso['codigo'] . '.pdf';
        }
        break;
        
    case 'anexo':
        if ($anexoId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'anexo_id es requerido para tipo anexo']);
            exit;
        }
        
        $anexo = $crearCoopModel->obtenerAnexoPorId($anexoId);
        if (!$anexo || $anexo['proceso_id'] != $procesoId) {
            http_response_code(404);
            echo json_encode(['error' => 'Anexo no encontrado']);
            exit;
        }
        
        $rutaRelativa = $anexo['ruta_archivo'];
        $archivoPath = $docRoot . $rutaRelativa;
        $nombreArchivo = $anexo['nombre_archivo'];
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido. Valores permitidos: pagare, estado_cuenta, anexo']);
        exit;
}

// Validar que el archivo existe y está dentro del directorio permitido
if (empty($archivoPath) || !file_exists($archivoPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Archivo no encontrado']);
    exit;
}

$uploadsBase = realpath($docRoot . '/projects/bybot_app/uploads/');
$archivoReal = realpath($archivoPath);

if (!$uploadsBase || !$archivoReal || strpos($archivoReal, $uploadsBase) !== 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

// Servir el archivo
$extension = strtolower(pathinfo($archivoPath, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png'
];
$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($archivoPath));
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('X-File-Name: ' . $nombreArchivo);
header('X-File-Size: ' . filesize($archivoPath));

readfile($archivoPath);
exit;

