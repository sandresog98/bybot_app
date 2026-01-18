<?php
/**
 * Router de Validación
 * Gestión de validación de datos extraídos por IA
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';
require_once BASE_DIR . '/web/modules/procesos/services/ValidacionService.php';

/**
 * Enruta solicitudes de validación
 */
function routeValidacion(string $method, ?string $id, array $body): void {
    AuthMiddleware::check();
    RateLimitMiddleware::checkApi();
    
    $service = new ValidacionService();
    
    switch ($id) {
        case 'guardar':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleGuardar($body, $service);
            break;
            
        case 'confirmar':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleConfirmar($body, $service);
            break;
            
        case 'campo':
            if ($method !== 'POST' && $method !== 'PUT') {
                Response::error('Método no permitido', [], 405);
            }
            handleActualizarCampo($body, $service);
            break;
            
        case 'reanalizar':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleReanalizar($body, $service);
            break;
            
        case 'estadisticas':
        case 'stats':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleEstadisticas($service);
            break;
            
        default:
            // Si es numérico, obtener datos para validar
            if (is_numeric($id)) {
                if ($method === 'GET') {
                    handleObtener((int)$id, $service);
                } else {
                    Response::error('Método no permitido', [], 405);
                }
            } else {
                Response::error('Ruta no encontrada', [], 404);
            }
    }
}

// ========================================
// HANDLERS
// ========================================

/**
 * GET /validacion/{proceso_id}
 * Obtiene datos de IA para validar
 */
function handleObtener(int $procesoId, ValidacionService $service): void {
    AuthMiddleware::requireAccess('procesos.ver');
    
    try {
        $datos = $service->obtenerDatosParaValidar($procesoId);
        Response::success('Datos para validación', $datos);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 404);
    }
}

/**
 * POST /validacion/guardar
 * Guarda datos validados (sin confirmar)
 */
function handleGuardar(array $body, ValidacionService $service): void {
    AuthMiddleware::requireAccess('procesos.editar_ia');
    
    // Validar campos requeridos
    if (empty($body['proceso_id'])) {
        Response::error('proceso_id es requerido', [], 400);
    }
    
    if (empty($body['datos'])) {
        Response::error('datos es requerido', [], 400);
    }
    
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $resultado = $service->guardarDatos(
            (int)$body['proceso_id'],
            $body['datos'],
            $userId
        );
        
        Response::success('Datos guardados', $resultado);
    } catch (InvalidArgumentException $e) {
        $errors = json_decode($e->getMessage(), true);
        if (is_array($errors)) {
            Response::error($errors['message'] ?? 'Error de validación', $errors['errors'] ?? [], 400);
        }
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * POST /validacion/confirmar
 * Confirma validación y cambia estado del proceso
 */
function handleConfirmar(array $body, ValidacionService $service): void {
    AuthMiddleware::requireAccess('procesos.validar_ia');
    
    if (empty($body['proceso_id'])) {
        Response::error('proceso_id es requerido', [], 400);
    }
    
    if (empty($body['datos'])) {
        Response::error('datos es requerido', [], 400);
    }
    
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $resultado = $service->confirmarValidacion(
            (int)$body['proceso_id'],
            $body['datos'],
            $userId
        );
        
        Response::success($resultado['mensaje'], $resultado);
    } catch (InvalidArgumentException $e) {
        $errors = json_decode($e->getMessage(), true);
        if (is_array($errors)) {
            Response::error($errors['message'] ?? 'Error de validación', $errors['errors'] ?? [], 400);
        }
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * POST /validacion/campo
 * Actualiza un campo específico
 */
function handleActualizarCampo(array $body, ValidacionService $service): void {
    AuthMiddleware::requireAccess('procesos.editar_ia');
    
    // Validar campos requeridos
    $required = ['proceso_id', 'campo', 'valor'];
    foreach ($required as $field) {
        if (!isset($body[$field])) {
            Response::error("{$field} es requerido", [], 400);
        }
    }
    
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $resultado = $service->actualizarCampo(
            (int)$body['proceso_id'],
            $body['campo'],
            $body['valor'],
            $userId
        );
        
        Response::success('Campo actualizado', $resultado);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * POST /validacion/reanalizar
 * Solicita re-análisis de un proceso
 */
function handleReanalizar(array $body, ValidacionService $service): void {
    AuthMiddleware::requireAccess('procesos.editar_ia');
    
    if (empty($body['proceso_id'])) {
        Response::error('proceso_id es requerido', [], 400);
    }
    
    $userId = AuthMiddleware::getCurrentUserId();
    $motivo = $body['motivo'] ?? null;
    
    try {
        $resultado = $service->reanalizar(
            (int)$body['proceso_id'],
            $userId,
            $motivo
        );
        
        Response::success('Proceso encolado para re-análisis', $resultado);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * GET /validacion/estadisticas
 * Obtiene estadísticas de validación
 */
function handleEstadisticas(ValidacionService $service): void {
    $stats = $service->getEstadisticas();
    Response::success('Estadísticas de validación', $stats);
}

