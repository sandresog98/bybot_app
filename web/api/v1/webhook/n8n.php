<?php
/**
 * Webhook Receptor para n8n
 * 
 * Recibe callbacks de los flujos de n8n:
 * - analysis_complete: Análisis de documentos completado
 * - analysis_error: Error en análisis
 * - fill_complete: Llenado de pagaré completado
 * - fill_error: Error en llenado
 */

require_once BASE_DIR . '/web/core/Response.php';
require_once BASE_DIR . '/web/core/N8nClient.php';
require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
require_once BASE_DIR . '/web/modules/procesos/models/DatosIA.php';
require_once BASE_DIR . '/web/modules/procesos/models/Anexo.php';
require_once BASE_DIR . '/web/modules/procesos/models/Historial.php';

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Método no permitido', [], 405);
}

// Obtener payload
$rawPayload = file_get_contents('php://input');
$payload = json_decode($rawPayload, true);

if (!$payload) {
    Response::error('Payload inválido', [], 400);
}

// Validar firma de seguridad
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? $_SERVER['HTTP_X_N8N_SIGNATURE'] ?? '';
$n8nClient = new N8nClient();

if (!empty($_ENV['N8N_WEBHOOK_SECRET']) && !$n8nClient->validateSignature($rawPayload, $signature)) {
    logWebhook('signature_invalid', $payload, false);
    Response::error('Firma inválida', [], 401);
}

// Validar campos requeridos
if (!isset($payload['action']) || !isset($payload['proceso_id'])) {
    Response::error('Campos requeridos: action, proceso_id', [], 400);
}

$action = $payload['action'];
$procesoId = (int) $payload['proceso_id'];

// Log de recepción
logWebhook($action, $payload, true);

// Procesar según la acción
try {
    switch ($action) {
        case 'analysis_complete':
            $result = handleAnalysisComplete($procesoId, $payload);
            break;
            
        case 'analysis_error':
            $result = handleAnalysisError($procesoId, $payload);
            break;
            
        case 'fill_complete':
            $result = handleFillComplete($procesoId, $payload);
            break;
            
        case 'fill_error':
            $result = handleFillError($procesoId, $payload);
            break;
            
        case 'status_update':
            $result = handleStatusUpdate($procesoId, $payload);
            break;
            
        default:
            Response::error("Acción desconocida: $action", [], 400);
    }
    
    Response::success('Webhook procesado', $result);
    
} catch (Exception $e) {
    logWebhook($action . '_exception', [
        'proceso_id' => $procesoId,
        'error' => $e->getMessage()
    ], false);
    
    Response::error('Error procesando webhook: ' . $e->getMessage(), [], 500);
}

/**
 * Maneja el resultado exitoso del análisis
 */
function handleAnalysisComplete(int $procesoId, array $payload): array {
    $procesoModel = new Proceso();
    $datosIAModel = new DatosIA();
    $historialModel = new Historial();
    
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        throw new Exception("Proceso no encontrado: $procesoId");
    }
    
    // Extraer datos del payload
    $datosExtraidos = $payload['datos'] ?? [];
    $metadata = [
        'n8n_execution_id' => $payload['execution_id'] ?? null,
        'modelo' => $payload['modelo'] ?? null,
        'tokens_usados' => $payload['tokens'] ?? null,
        'tiempo_procesamiento' => $payload['tiempo_ms'] ?? null,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Guardar datos de IA
    $datosIAId = $datosIAModel->saveOriginalData($procesoId, $datosExtraidos, $metadata);
    
    // Actualizar estado del proceso
    $procesoModel->update($procesoId, [
        'estado' => 'analizado',
        'fecha_analisis' => date('Y-m-d H:i:s')
    ]);
    
    // Registrar en historial
    $historialModel->addEntry(
        $procesoId,
        'analizado',
        'Análisis de documentos completado por IA',
        null, // Usuario sistema
        [
            'datos_ia_id' => $datosIAId,
            'execution_id' => $payload['execution_id'] ?? null
        ],
        $proceso['estado'],
        'analizado'
    );
    
    return [
        'proceso_id' => $procesoId,
        'estado' => 'analizado',
        'datos_ia_id' => $datosIAId
    ];
}

/**
 * Maneja un error en el análisis
 */
function handleAnalysisError(int $procesoId, array $payload): array {
    $procesoModel = new Proceso();
    $historialModel = new Historial();
    
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        throw new Exception("Proceso no encontrado: $procesoId");
    }
    
    $errorMessage = $payload['error'] ?? 'Error desconocido en análisis';
    $intentos = $proceso['intentos_analisis'] + 1;
    $maxIntentos = $proceso['max_intentos'] ?? 3;
    
    // Determinar nuevo estado
    $nuevoEstado = $intentos >= $maxIntentos ? 'error_analisis' : 'creado';
    
    // Actualizar proceso
    $procesoModel->update($procesoId, [
        'estado' => $nuevoEstado,
        'intentos_analisis' => $intentos
    ]);
    
    // Registrar en historial
    $historialModel->addEntry(
        $procesoId,
        'error_analisis',
        "Error en análisis (intento $intentos/$maxIntentos): $errorMessage",
        null,
        [
            'error' => $errorMessage,
            'execution_id' => $payload['execution_id'] ?? null,
            'intentos' => $intentos
        ],
        $proceso['estado'],
        $nuevoEstado
    );
    
    return [
        'proceso_id' => $procesoId,
        'estado' => $nuevoEstado,
        'intentos' => $intentos,
        'max_intentos' => $maxIntentos,
        'puede_reintentar' => $intentos < $maxIntentos
    ];
}

/**
 * Maneja el resultado exitoso del llenado de pagaré
 */
function handleFillComplete(int $procesoId, array $payload): array {
    $procesoModel = new Proceso();
    $anexoModel = new Anexo();
    $historialModel = new Historial();
    
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        throw new Exception("Proceso no encontrado: $procesoId");
    }
    
    // Datos del archivo subido
    $archivoInfo = $payload['archivo'] ?? null;
    
    if ($archivoInfo && isset($archivoInfo['nombre_archivo'])) {
        // Registrar el archivo llenado como anexo
        $anexoId = $anexoModel->create([
            'proceso_id' => $procesoId,
            'nombre_original' => 'pagare_llenado_' . $proceso['codigo'] . '.pdf',
            'nombre_archivo' => $archivoInfo['nombre_archivo'],
            'ruta_archivo' => $archivoInfo['ruta_archivo'] ?? '',
            'tipo' => 'pagare_llenado',
            'tamanio_bytes' => $archivoInfo['tamanio'] ?? 0,
            'mime_type' => 'application/pdf'
        ]);
        
        // Actualizar proceso con referencia al archivo
        $procesoModel->update($procesoId, [
            'estado' => 'completado',
            'archivo_pagare_llenado' => $archivoInfo['ruta_archivo'] ?? $archivoInfo['nombre_archivo'],
            'fecha_llenado' => date('Y-m-d H:i:s'),
            'fecha_completado' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Solo actualizar estado
        $procesoModel->update($procesoId, [
            'estado' => 'completado',
            'fecha_llenado' => date('Y-m-d H:i:s'),
            'fecha_completado' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Registrar en historial
    $historialModel->addEntry(
        $procesoId,
        'completado',
        'Pagaré llenado exitosamente',
        null,
        [
            'archivo' => $archivoInfo,
            'execution_id' => $payload['execution_id'] ?? null
        ],
        $proceso['estado'],
        'completado'
    );
    
    return [
        'proceso_id' => $procesoId,
        'estado' => 'completado',
        'archivo' => $archivoInfo
    ];
}

/**
 * Maneja un error en el llenado
 */
function handleFillError(int $procesoId, array $payload): array {
    $procesoModel = new Proceso();
    $historialModel = new Historial();
    
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        throw new Exception("Proceso no encontrado: $procesoId");
    }
    
    $errorMessage = $payload['error'] ?? 'Error desconocido en llenado';
    $intentos = $proceso['intentos_llenado'] + 1;
    $maxIntentos = $proceso['max_intentos'] ?? 3;
    
    // Determinar nuevo estado
    $nuevoEstado = $intentos >= $maxIntentos ? 'error_llenado' : 'validado';
    
    // Actualizar proceso
    $procesoModel->update($procesoId, [
        'estado' => $nuevoEstado,
        'intentos_llenado' => $intentos
    ]);
    
    // Registrar en historial
    $historialModel->addEntry(
        $procesoId,
        'error_llenado',
        "Error en llenado (intento $intentos/$maxIntentos): $errorMessage",
        null,
        [
            'error' => $errorMessage,
            'execution_id' => $payload['execution_id'] ?? null,
            'intentos' => $intentos
        ],
        $proceso['estado'],
        $nuevoEstado
    );
    
    return [
        'proceso_id' => $procesoId,
        'estado' => $nuevoEstado,
        'intentos' => $intentos,
        'max_intentos' => $maxIntentos,
        'puede_reintentar' => $intentos < $maxIntentos
    ];
}

/**
 * Maneja actualizaciones de estado intermedias
 */
function handleStatusUpdate(int $procesoId, array $payload): array {
    $procesoModel = new Proceso();
    $historialModel = new Historial();
    
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        throw new Exception("Proceso no encontrado: $procesoId");
    }
    
    $nuevoEstado = $payload['estado'] ?? null;
    $mensaje = $payload['mensaje'] ?? 'Actualización de estado';
    
    $estadosPermitidos = [
        'analizando', 'llenando'
    ];
    
    if ($nuevoEstado && in_array($nuevoEstado, $estadosPermitidos)) {
        $procesoModel->update($procesoId, ['estado' => $nuevoEstado]);
        
        $historialModel->addEntry(
            $procesoId,
            'estado_actualizado',
            $mensaje,
            null,
            ['execution_id' => $payload['execution_id'] ?? null],
            $proceso['estado'],
            $nuevoEstado
        );
    }
    
    return [
        'proceso_id' => $procesoId,
        'estado' => $nuevoEstado ?? $proceso['estado']
    ];
}

/**
 * Log de webhooks recibidos
 */
function logWebhook(string $action, array $payload, bool $success): void {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'action' => $action,
        'proceso_id' => $payload['proceso_id'] ?? null,
        'success' => $success,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logPath = $_ENV['LOG_PATH'] ?? (defined('BASE_DIR') ? BASE_DIR . '/logs' : '/tmp');
    $logFile = $logPath . '/webhook_n8n_' . date('Y-m-d') . '.log';
    
    if (is_dir($logPath) && is_writable($logPath)) {
        file_put_contents(
            $logFile,
            json_encode($logData) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

