<?php
/**
 * Servir Archivos para n8n y Servicios Externos
 * 
 * GET /api/v1/archivos/servir/{id}?token=xxx
 * 
 * Permite a n8n descargar archivos del proceso usando un token temporal
 */

require_once BASE_DIR . '/web/core/Response.php';
require_once BASE_DIR . '/web/core/N8nClient.php';
require_once BASE_DIR . '/web/modules/procesos/models/Anexo.php';

/**
 * Sirve un archivo a n8n u otros servicios autorizados
 */
function servirArchivo(int $archivoId): void {
    // Validar token
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        Response::error('Token requerido', [], 401);
    }
    
    $n8nClient = new N8nClient();
    $tokenData = $n8nClient->validateFileAccessToken($token);
    
    if (!$tokenData) {
        Response::error('Token inválido o expirado', [], 401);
    }
    
    // Verificar que el token corresponde al archivo solicitado
    if ($tokenData['archivo_id'] !== $archivoId) {
        Response::error('Token no corresponde al archivo', [], 403);
    }
    
    // Obtener información del archivo
    $anexoModel = new Anexo();
    $anexo = $anexoModel->findById($archivoId);
    
    if (!$anexo) {
        Response::error('Archivo no encontrado', [], 404);
    }
    
    // Verificar que el proceso también corresponde
    if ($tokenData['proceso_id'] !== $anexo['proceso_id']) {
        Response::error('Token no válido para este archivo', [], 403);
    }
    
    // Construir ruta del archivo
    $uploadsDir = defined('UPLOADS_DIR') ? UPLOADS_DIR : (BASE_DIR . '/uploads');
    $filePath = $uploadsDir . '/' . $anexo['ruta_archivo'];
    
    if (!file_exists($filePath)) {
        Response::error('Archivo no existe en el servidor', [], 404);
    }
    
    // Enviar archivo
    $mimeType = $anexo['mime_type'] ?? 'application/octet-stream';
    $fileName = $anexo['nombre_original'] ?? basename($filePath);
    
    // Limpiar buffers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers para descarga
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    
    // Log de acceso
    logFileAccess($archivoId, $tokenData);
    
    // Enviar contenido
    readfile($filePath);
    exit;
}

/**
 * Log de acceso a archivos
 */
function logFileAccess(int $archivoId, array $tokenData): void {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'archivo_id' => $archivoId,
        'proceso_id' => $tokenData['proceso_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];
    
    $logPath = $_ENV['LOG_PATH'] ?? (defined('BASE_DIR') ? BASE_DIR . '/logs' : '/tmp');
    $logFile = $logPath . '/file_access_' . date('Y-m-d') . '.log';
    
    if (is_dir($logPath) && is_writable($logPath)) {
        file_put_contents(
            $logFile,
            json_encode($logData) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

