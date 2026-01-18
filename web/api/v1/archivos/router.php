<?php
/**
 * Router de Archivos
 * Gestión de subida, descarga y servicio de archivos
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/api_token.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';
require_once BASE_DIR . '/web/modules/procesos/services/ArchivoService.php';

/**
 * Enruta solicitudes de archivos
 */
function routeArchivos(string $method, ?string $id, ?string $action, array $body): void {
    $service = new ArchivoService();
    
    // Rutas especiales que no requieren autenticación de usuario
    if ($id === 'servir' && $action !== null) {
        // GET /archivos/servir/{id} - Para workers
        handleServir((int)$action, $service);
        return;
    }
    
    if ($id === 'temporal' && $action !== null) {
        // GET /archivos/temporal/{token} - Descarga temporal
        handleTemporalDescarga($action, $service);
        return;
    }
    
    // Resto de rutas requieren autenticación
    AuthMiddleware::check();
    RateLimitMiddleware::checkApi();
    
    switch ($id) {
        case 'subir':
            if ($method !== 'POST') {
                Response::error('Método no permitido', [], 405);
            }
            handleSubir($body, $service);
            break;
            
        case 'descargar':
            if ($method !== 'GET' || $action === null) {
                Response::error('ID de archivo requerido', [], 400);
            }
            handleDescargar((int)$action, $service);
            break;
            
        case 'proceso':
            if ($method !== 'GET' || $action === null) {
                Response::error('ID de proceso requerido', [], 400);
            }
            handleListarPorProceso((int)$action, $service);
            break;
            
        case 'estadisticas':
        case 'stats':
            if ($method !== 'GET') {
                Response::error('Método no permitido', [], 405);
            }
            handleEstadisticas($service);
            break;
            
        case null:
        case '':
            Response::error('Especifique una acción: subir, descargar, proceso, estadisticas', [], 400);
            break;
            
        default:
            // Si es numérico, es un ID de archivo
            if (is_numeric($id)) {
                routeArchivoById((int)$id, $method, $action, $body, $service);
            } else {
                Response::error('Ruta no encontrada', [], 404);
            }
    }
}

/**
 * Rutas para un archivo específico por ID
 */
function routeArchivoById(int $id, string $method, ?string $action, array $body, ArchivoService $service): void {
    switch ($method) {
        case 'GET':
            handleDescargar($id, $service);
            break;
            
        case 'DELETE':
            AuthMiddleware::requireAccess('procesos.editar_ia');
            handleEliminar($id, $service);
            break;
            
        default:
            Response::error('Método no permitido', [], 405);
    }
}

// ========================================
// HANDLERS
// ========================================

/**
 * POST /archivos/subir
 * Sube archivos a un proceso
 */
function handleSubir(array $body, ArchivoService $service): void {
    AuthMiddleware::requireAccess('procesos.crear');
    RateLimitMiddleware::checkUpload();
    
    // Verificar proceso_id
    $procesoId = $body['proceso_id'] ?? $_POST['proceso_id'] ?? null;
    if (!$procesoId) {
        Response::error('proceso_id es requerido', [], 400);
    }
    
    // Verificar archivos
    if (empty($_FILES)) {
        Response::error('No se proporcionaron archivos', [], 400);
    }
    
    $userId = AuthMiddleware::getCurrentUserId();
    $tipo = $body['tipo'] ?? $_POST['tipo'] ?? 'anexo';
    
    // Determinar qué campo de archivos usar
    $files = $_FILES['archivos'] ?? $_FILES['archivo'] ?? $_FILES['files'] ?? reset($_FILES);
    
    try {
        $resultado = $service->subirArchivos((int)$procesoId, $files, $userId, $tipo);
        
        if ($resultado['total_errores'] > 0 && $resultado['total_subidos'] === 0) {
            Response::error('Error al subir archivos', ['errores' => $resultado['errores']], 400);
        }
        
        Response::success(
            $resultado['total_subidos'] . ' archivo(s) subido(s)',
            $resultado,
            $resultado['total_errores'] > 0 ? 207 : 201 // 207 = Multi-Status
        );
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * GET /archivos/descargar/{id}
 * Descarga un archivo
 */
function handleDescargar(int $id, ArchivoService $service): void {
    AuthMiddleware::requireAccess('procesos.descargar_archivos');
    
    try {
        $archivo = $service->descargar($id);
        
        // Enviar archivo
        header('Content-Type: ' . $archivo['mime']);
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        header('Content-Length: ' . $archivo['tamanio']);
        header('Cache-Control: no-cache, must-revalidate');
        
        readfile($archivo['path']);
        exit;
        
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 404);
    } catch (RuntimeException $e) {
        Response::error($e->getMessage(), [], 500);
    }
}

/**
 * GET /archivos/servir/{id}
 * Sirve un archivo para workers (requiere token de API)
 */
function handleServir(int $id, ArchivoService $service): void {
    // Verificar token de worker
    ApiTokenMiddleware::checkWorker();
    
    try {
        $archivo = $service->servirParaWorker($id);
        
        // Enviar archivo
        header('Content-Type: ' . $archivo['mime']);
        header('Content-Disposition: inline; filename="' . $archivo['nombre'] . '"');
        header('Content-Length: ' . $archivo['tamanio']);
        
        readfile($archivo['path']);
        exit;
        
    } catch (Exception $e) {
        Response::error($e->getMessage(), [], 404);
    }
}

/**
 * GET /archivos/temporal/{token}
 * Descarga archivo con token temporal
 */
function handleTemporalDescarga(string $token, ArchivoService $service): void {
    try {
        $archivo = $service->descargarTemporal($token);
        
        header('Content-Type: ' . $archivo['mime']);
        header('Content-Disposition: attachment; filename="' . $archivo['nombre'] . '"');
        header('Content-Length: ' . $archivo['tamanio']);
        
        readfile($archivo['path']);
        exit;
        
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * GET /archivos/proceso/{id}
 * Lista archivos de un proceso
 */
function handleListarPorProceso(int $procesoId, ArchivoService $service): void {
    AuthMiddleware::requireAccess('procesos.ver');
    
    try {
        $archivos = $service->listarPorProceso($procesoId);
        Response::success('Archivos del proceso', $archivos);
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 404);
    }
}

/**
 * DELETE /archivos/{id}
 * Elimina un archivo
 */
function handleEliminar(int $id, ArchivoService $service): void {
    $userId = AuthMiddleware::getCurrentUserId();
    
    try {
        $service->eliminar($id, $userId);
        Response::success('Archivo eliminado');
    } catch (InvalidArgumentException $e) {
        Response::error($e->getMessage(), [], 400);
    }
}

/**
 * GET /archivos/estadisticas
 * Obtiene estadísticas de almacenamiento
 */
function handleEstadisticas(ArchivoService $service): void {
    $procesoId = !empty($_GET['proceso_id']) ? (int)$_GET['proceso_id'] : null;
    
    $stats = $service->getEstadisticas($procesoId);
    
    Response::success('Estadísticas de almacenamiento', $stats);
}

