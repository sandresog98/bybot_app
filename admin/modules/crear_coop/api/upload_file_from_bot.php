<?php
/**
 * API: Subir archivo desde el Bot - ByBot App
 * Endpoint seguro para que el bot Python suba archivos extraídos al servidor
 */

header('Content-Type: application/json');

// Cargar configuración primero
require_once __DIR__ . '/../../../../config/env_loader.php';
loadEnv();

// Validar token de API
$apiToken = '';
if (isset($_SERVER['HTTP_X_API_TOKEN'])) {
    $apiToken = $_SERVER['HTTP_X_API_TOKEN'];
} elseif (isset($_SERVER['X_API_TOKEN'])) {
    $apiToken = $_SERVER['X_API_TOKEN'];
} elseif (function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $apiToken = $headers['X-API-Token'] ?? $headers['x-api-token'] ?? '';
}

if (empty($apiToken)) {
    http_response_code(401);
    echo json_encode(['error' => 'Token de API requerido']);
    exit;
}

// Obtener token esperado
$expectedToken = '';
if (isset($GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN'])) {
    $expectedToken = $GLOBALS['_BY_BOT_ENV']['BOT_API_TOKEN'];
} elseif (isset($_ENV['BOT_API_TOKEN'])) {
    $expectedToken = $_ENV['BOT_API_TOKEN'];
} elseif (isset($_SERVER['BOT_API_TOKEN'])) {
    $expectedToken = $_SERVER['BOT_API_TOKEN'];
} else {
    $expectedToken = getenv('BOT_API_TOKEN') ?: '';
}

$expectedToken = trim($expectedToken);

if (empty($expectedToken)) {
    http_response_code(500);
    echo json_encode(['error' => 'Token de API no configurado en el servidor']);
    exit;
}

// Normalizar tokens
$apiToken = trim($apiToken);
$expectedToken = trim($expectedToken);

if ($apiToken !== $expectedToken) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de API inválido']);
    exit;
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Use POST']);
    exit;
}

// Validar parámetros
$procesoId = (int)($_POST['proceso_id'] ?? 0);
$tipo = $_POST['tipo'] ?? ''; // 'solicitud_vinculacion_deudor', 'solicitud_vinculacion_codeudor'

if ($procesoId <= 0 || empty($tipo)) {
    http_response_code(400);
    echo json_encode(['error' => 'Parámetros inválidos: proceso_id y tipo son requeridos']);
    exit;
}

// Validar tipo
$tiposPermitidos = ['solicitud_vinculacion_deudor', 'solicitud_vinculacion_codeudor'];
if (!in_array($tipo, $tiposPermitidos, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tipo inválido. Valores permitidos: ' . implode(', ', $tiposPermitidos)]);
    exit;
}

// Validar archivo
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Error en la subida del archivo']);
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';
require_once '../models/CrearCoop.php';
require_once __DIR__ . '/../../../../admin/utils/FileUploadManager.php';

$crearCoopModel = new CrearCoop();
$proceso = $crearCoopModel->obtenerProceso($procesoId);

if (!$proceso) {
    http_response_code(404);
    echo json_encode(['error' => 'Proceso no encontrado']);
    exit;
}

try {
    // Determinar directorio de destino según el tipo
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '/opt/lampp/htdocs';
    $baseDir = $docRoot . '/uploads/crear_coop/solicitudes_vinculacion';
    
    // Crear subdirectorios por año/mes
    $year = date('Y');
    $month = date('m');
    $destDir = $baseDir . '/' . $year . '/' . $month;
    
    if (!is_dir($destDir)) {
        if (!@mkdir($destDir, 0775, true)) {
            throw new Exception('No se pudo crear el directorio: ' . $destDir);
        }
    }
    
    // Generar nombre único para el archivo
    $originalName = $_FILES['archivo']['name'];
    $uniqueFileName = FileUploadManager::generateUniqueFileName(
        $originalName,
        $tipo,
        $proceso['codigo']
    );
    
    $fullPath = $destDir . '/' . $uniqueFileName;
    
    // Mover archivo
    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $fullPath)) {
        throw new Exception('No se pudo guardar el archivo');
    }
    
    // Ruta relativa desde DOCUMENT_ROOT
    $rutaRelativa = str_replace($docRoot, '', $fullPath);
    
    // Guardar en base de datos
    $anexoId = $crearCoopModel->guardarAnexo($procesoId, $rutaRelativa, $tipo);
    
    if (!$anexoId) {
        // Si falla guardar en BD, eliminar archivo
        @unlink($fullPath);
        throw new Exception('No se pudo guardar el registro en la base de datos');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Archivo subido exitosamente',
        'ruta_archivo' => $rutaRelativa,
        'anexo_id' => $anexoId
    ]);
    
} catch (Exception $e) {
    error_log('Error en upload_file_from_bot.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al procesar el archivo',
        'message' => $e->getMessage()
    ]);
}

