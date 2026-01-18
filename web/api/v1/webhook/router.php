<?php
/**
 * Router de Webhook
 * Recibe callbacks de los workers de Python
 */

require_once BASE_DIR . '/web/api/middleware/api_token.php';
require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
require_once BASE_DIR . '/web/modules/procesos/models/DatosIA.php';
require_once BASE_DIR . '/web/modules/procesos/models/Anexo.php';
require_once BASE_DIR . '/web/modules/procesos/models/Historial.php';

/**
 * Enruta solicitudes de webhook
 */
function routeWebhook(string $method, ?string $action, array $body): void {
    if ($method !== 'POST') {
        Response::error('Método no permitido', [], 405);
    }
    
    // Para n8n, usar el archivo dedicado
    if ($action === 'n8n') {
        require_once __DIR__ . '/n8n.php';
        return; // El archivo n8n.php maneja su propia respuesta
    }
    
    // Verificar token de worker (para otros webhooks)
    ApiTokenMiddleware::checkWorker();
    
    switch ($action) {
        case 'analisis':
        case 'analysis':
            handleAnalisisResultado($body);
            break;
            
        case 'llenado':
        case 'fill':
            handleLlenadoResultado($body);
            break;
            
        case 'error':
            handleError($body);
            break;
            
        case 'progreso':
        case 'progress':
            handleProgreso($body);
            break;
            
        case 'heartbeat':
        case 'ping':
            handleHeartbeat($body);
            break;
            
        default:
            Response::error('Acción de webhook no reconocida', [], 400);
    }
}

// ========================================
// HANDLERS
// ========================================

/**
 * POST /webhook/analisis
 * Recibe resultado del análisis de IA
 */
function handleAnalisisResultado(array $body): void {
    // Validar campos requeridos
    $required = ['proceso_id', 'success'];
    foreach ($required as $field) {
        if (!isset($body[$field])) {
            Response::error("{$field} es requerido", [], 400);
        }
    }
    
    $procesoId = (int)$body['proceso_id'];
    $success = (bool)$body['success'];
    
    $procesoModel = new Proceso();
    $datosIAModel = new DatosIA();
    $historialModel = new Historial();
    
    // Verificar proceso
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        Response::error('Proceso no encontrado', [], 404);
    }
    
    if ($success) {
        // Análisis exitoso
        if (empty($body['datos'])) {
            Response::error('datos es requerido cuando success=true', [], 400);
        }
        
        $datos = $body['datos'];
        $metadata = $body['metadata'] ?? [];
        
        // Guardar datos de IA
        $datosIAModel->saveAnalysis($procesoId, $datos, $metadata);
        
        // Actualizar estado
        $procesoModel->updateStatus($procesoId, 'analizado', null, 'Análisis completado por worker');
        
        // Registrar en historial
        $historialModel->registrarAnalisis($procesoId, $metadata);
        
        // Notificar vía WebSocket (si está disponible)
        notifyWebSocket('proceso.analizado', [
            'proceso_id' => $procesoId,
            'codigo' => $proceso['codigo'],
            'success' => true
        ]);
        
        Response::success('Análisis registrado', [
            'proceso_id' => $procesoId,
            'estado' => 'analizado'
        ]);
        
    } else {
        // Análisis fallido
        $errorMsg = $body['error'] ?? 'Error desconocido en análisis';
        
        // Incrementar intentos
        $intentos = $proceso['intentos_analisis'] + 1;
        $procesoModel->update($procesoId, ['intentos_analisis' => $intentos]);
        
        // Si excede máximo de intentos, marcar como error definitivo
        if ($intentos >= $proceso['max_intentos']) {
            $procesoModel->updateStatus($procesoId, 'error_analisis', null, $errorMsg);
        } else {
            // Mantener en cola para reintento
            $procesoModel->updateStatus($procesoId, 'en_cola_analisis', null, 
                "Reintento {$intentos}/{$proceso['max_intentos']}: {$errorMsg}");
        }
        
        // Registrar error
        $historialModel->registrarError($procesoId, 'analisis', $errorMsg, $body['details'] ?? null);
        
        // Notificar
        notifyWebSocket('proceso.error', [
            'proceso_id' => $procesoId,
            'codigo' => $proceso['codigo'],
            'tipo' => 'analisis',
            'mensaje' => $errorMsg
        ]);
        
        Response::success('Error de análisis registrado', [
            'proceso_id' => $procesoId,
            'intentos' => $intentos,
            'max_intentos' => $proceso['max_intentos']
        ]);
    }
}

/**
 * POST /webhook/llenado
 * Recibe resultado del llenado de pagaré
 */
function handleLlenadoResultado(array $body): void {
    $required = ['proceso_id', 'success'];
    foreach ($required as $field) {
        if (!isset($body[$field])) {
            Response::error("{$field} es requerido", [], 400);
        }
    }
    
    $procesoId = (int)$body['proceso_id'];
    $success = (bool)$body['success'];
    
    $procesoModel = new Proceso();
    $anexoModel = new Anexo();
    $historialModel = new Historial();
    
    $proceso = $procesoModel->findById($procesoId);
    if (!$proceso) {
        Response::error('Proceso no encontrado', [], 404);
    }
    
    if ($success) {
        // Llenado exitoso
        if (empty($body['archivo_contenido']) && empty($body['archivo_ruta'])) {
            Response::error('archivo_contenido o archivo_ruta es requerido', [], 400);
        }
        
        // Guardar archivo llenado
        if (!empty($body['archivo_contenido'])) {
            // Contenido en base64
            $contenido = base64_decode($body['archivo_contenido']);
            $nombreOriginal = $body['archivo_nombre'] ?? "pagare_llenado_{$proceso['codigo']}.pdf";
            
            $archivo = $anexoModel->saveFromContent(
                $procesoId,
                $contenido,
                $nombreOriginal,
                'pagare_llenado'
            );
            
            // Actualizar proceso
            $procesoModel->update($procesoId, [
                'archivo_pagare_llenado' => $archivo['ruta_archivo']
            ]);
        }
        
        // Actualizar estado
        $procesoModel->updateStatus($procesoId, 'completado', null, 'Pagaré llenado correctamente');
        
        // Registrar
        $historialModel->registrar(
            $procesoId,
            null,
            'llenado',
            'llenando',
            'completado',
            'Pagaré llenado exitosamente por worker'
        );
        
        notifyWebSocket('proceso.completado', [
            'proceso_id' => $procesoId,
            'codigo' => $proceso['codigo']
        ]);
        
        Response::success('Llenado completado', [
            'proceso_id' => $procesoId,
            'estado' => 'completado'
        ]);
        
    } else {
        // Llenado fallido
        $errorMsg = $body['error'] ?? 'Error desconocido en llenado';
        
        $intentos = $proceso['intentos_llenado'] + 1;
        $procesoModel->update($procesoId, ['intentos_llenado' => $intentos]);
        
        if ($intentos >= $proceso['max_intentos']) {
            $procesoModel->updateStatus($procesoId, 'error_llenado', null, $errorMsg);
        } else {
            $procesoModel->updateStatus($procesoId, 'en_cola_llenado', null,
                "Reintento {$intentos}/{$proceso['max_intentos']}: {$errorMsg}");
        }
        
        $historialModel->registrarError($procesoId, 'llenado', $errorMsg, $body['details'] ?? null);
        
        notifyWebSocket('proceso.error', [
            'proceso_id' => $procesoId,
            'codigo' => $proceso['codigo'],
            'tipo' => 'llenado',
            'mensaje' => $errorMsg
        ]);
        
        Response::success('Error de llenado registrado', [
            'proceso_id' => $procesoId,
            'intentos' => $intentos
        ]);
    }
}

/**
 * POST /webhook/error
 * Recibe errores generales de workers
 */
function handleError(array $body): void {
    $procesoId = $body['proceso_id'] ?? null;
    $tipo = $body['tipo'] ?? 'general';
    $mensaje = $body['mensaje'] ?? $body['error'] ?? 'Error desconocido';
    $detalles = $body['detalles'] ?? $body['details'] ?? null;
    
    // Log del error
    error_log("Webhook Error: [{$tipo}] {$mensaje} - Proceso: {$procesoId}");
    
    if ($procesoId) {
        $historialModel = new Historial();
        $historialModel->registrarError((int)$procesoId, $tipo, $mensaje, $detalles);
    }
    
    Response::success('Error registrado');
}

/**
 * POST /webhook/progreso
 * Recibe actualizaciones de progreso
 */
function handleProgreso(array $body): void {
    $procesoId = $body['proceso_id'] ?? null;
    $progreso = $body['progreso'] ?? 0;
    $mensaje = $body['mensaje'] ?? '';
    $etapa = $body['etapa'] ?? '';
    
    if (!$procesoId) {
        Response::error('proceso_id es requerido', [], 400);
    }
    
    // Notificar vía WebSocket
    notifyWebSocket('proceso.progreso', [
        'proceso_id' => $procesoId,
        'progreso' => $progreso,
        'mensaje' => $mensaje,
        'etapa' => $etapa
    ]);
    
    Response::success('Progreso actualizado');
}

/**
 * POST /webhook/heartbeat
 * Heartbeat de workers
 */
function handleHeartbeat(array $body): void {
    $workerId = $body['worker_id'] ?? 'unknown';
    $tipo = $body['tipo'] ?? 'unknown';
    $timestamp = $body['timestamp'] ?? time();
    
    // Guardar estado del worker (en producción usar Redis)
    $estado = [
        'worker_id' => $workerId,
        'tipo' => $tipo,
        'ultimo_heartbeat' => $timestamp,
        'info' => $body['info'] ?? []
    ];
    
    // Log
    error_log("Worker heartbeat: {$workerId} ({$tipo})");
    
    Response::success('Heartbeat recibido', [
        'timestamp' => time()
    ]);
}

/**
 * Notifica eventos vía WebSocket
 */
function notifyWebSocket(string $event, array $data): void {
    // En producción, publicar en Redis para que el servidor WS lo recoja
    try {
        if (class_exists('QueueManager')) {
            require_once BASE_DIR . '/web/core/QueueManager.php';
            $queue = new QueueManager();
            $queue->push('bybot:notify', [
                'event' => $event,
                'data' => $data,
                'timestamp' => time()
            ]);
        }
    } catch (Exception $e) {
        error_log("Error notificando WebSocket: " . $e->getMessage());
    }
}

