<?php
/**
 * Router de Estadísticas
 * Dashboard y métricas del sistema
 */

require_once BASE_DIR . '/web/api/middleware/auth.php';
require_once BASE_DIR . '/web/api/middleware/rate_limit.php';

/**
 * Enruta solicitudes de estadísticas
 */
function routeStats(string $method, ?string $id, array $body): void {
    AuthMiddleware::check();
    RateLimitMiddleware::checkApi();
    
    if ($method !== 'GET') {
        Response::methodNotAllowed('Método no permitido');
    }
    
    switch ($id) {
        case 'dashboard':
        case null:
        case '':
            handleDashboard();
            break;
            
        case 'procesos':
            handleProcesoStats();
            break;
            
        case 'usuarios':
            AuthMiddleware::requireRole('admin');
            handleUsuarioStats();
            break;
            
        case 'actividad':
            handleActividadReciente();
            break;
            
        case 'rendimiento':
            handleRendimiento();
            break;
            
        default:
            Response::notFound('Estadística no encontrada');
    }
}

/**
 * GET /stats/dashboard
 * Estadísticas generales para dashboard
 */
function handleDashboard(): void {
    $db = getConnection();
    $stats = [];
    
    // Procesos por estado
    $stmt = $db->query("
        SELECT estado, COUNT(*) as total 
        FROM procesos 
        GROUP BY estado
    ");
    $stats['procesos_por_estado'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Total procesos
    $stats['total_procesos'] = array_sum($stats['procesos_por_estado']);
    
    // Procesos hoy
    $stmt = $db->query("
        SELECT COUNT(*) FROM procesos 
        WHERE DATE(fecha_creacion) = CURDATE()
    ");
    $stats['procesos_hoy'] = (int) $stmt->fetchColumn();
    
    // Completados esta semana
    $stmt = $db->query("
        SELECT COUNT(*) FROM procesos 
        WHERE estado = 'completado'
        AND YEARWEEK(fecha_completado) = YEARWEEK(NOW())
    ");
    $stats['completados_semana'] = (int) $stmt->fetchColumn();
    
    // Tasa de éxito (últimos 30 días)
    $stmt = $db->query("
        SELECT 
            COUNT(CASE WHEN estado = 'completado' THEN 1 END) as completados,
            COUNT(CASE WHEN estado IN ('error_analisis', 'error_llenado') THEN 1 END) as errores,
            COUNT(*) as total
        FROM procesos
        WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $tasas = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['tasa_exito'] = $tasas['total'] > 0 
        ? round(($tasas['completados'] / $tasas['total']) * 100, 1) 
        : 0;
    
    // Tiempo promedio de procesamiento
    $stmt = $db->query("
        SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_completado)) as promedio
        FROM procesos
        WHERE estado = 'completado'
        AND fecha_completado IS NOT NULL
        AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['tiempo_promedio_horas'] = round($stmt->fetchColumn() ?? 0, 1);
    
    // Estado de colas (si Redis está disponible)
    $stats['colas'] = [];
    try {
        require_once BASE_DIR . '/web/core/QueueManager.php';
        $queue = QueueManager::getInstance();
        if ($queue->isConnected()) {
            // Usar constantes de Cola
            $stats['colas'] = [
                'analyze' => $queue->getQueueLength(Cola::ANALYZE),
                'fill' => $queue->getQueueLength(Cola::FILL),
                'notify' => $queue->getQueueLength(Cola::NOTIFY)
            ];
            $stats['redis_conectado'] = true;
        } else {
            $stats['redis_conectado'] = false;
        }
    } catch (Exception $e) {
        $stats['redis_conectado'] = false;
        error_log("Error obteniendo estado de colas: " . $e->getMessage());
    }
    
    // Actividad reciente (últimas 5)
    $stmt = $db->query("
        SELECT 
            h.accion,
            h.descripcion,
            h.fecha,
            p.codigo as proceso_codigo,
            u.nombre_completo as usuario
        FROM procesos_historial h
        LEFT JOIN procesos p ON h.proceso_id = p.id
        LEFT JOIN control_usuarios u ON h.usuario_id = u.id
        ORDER BY h.fecha DESC
        LIMIT 5
    ");
    $stats['actividad_reciente'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::jsonSuccess($stats, 'Estadísticas de dashboard');
}

/**
 * GET /stats/procesos
 * Estadísticas detalladas de procesos
 */
function handleProcesoStats(): void {
    $db = getConnection();
    $stats = [];
    
    // Fechas de filtro
    $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-01');
    $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
    
    // Procesos por día
    $stmt = $db->prepare("
        SELECT DATE(fecha_creacion) as fecha, COUNT(*) as total
        FROM procesos
        WHERE DATE(fecha_creacion) BETWEEN ? AND ?
        GROUP BY DATE(fecha_creacion)
        ORDER BY fecha ASC
    ");
    $stmt->execute([$fechaDesde, $fechaHasta]);
    $stats['por_dia'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesos por tipo
    $stmt = $db->query("SELECT tipo, COUNT(*) as total FROM procesos GROUP BY tipo");
    $stats['por_tipo'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Promedio de intentos
    $stmt = $db->query("
        SELECT 
            AVG(intentos_analisis) as promedio_analisis,
            AVG(intentos_llenado) as promedio_llenado
        FROM procesos
        WHERE estado IN ('completado', 'error_analisis', 'error_llenado')
    ");
    $stats['promedio_intentos'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Distribución de tiempos
    $stmt = $db->query("
        SELECT 
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_completado) < 1 THEN '< 1 hora'
                WHEN TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_completado) < 4 THEN '1-4 horas'
                WHEN TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_completado) < 24 THEN '4-24 horas'
                ELSE '> 24 horas'
            END as rango,
            COUNT(*) as total
        FROM procesos
        WHERE estado = 'completado' AND fecha_completado IS NOT NULL
        GROUP BY rango
    ");
    $stats['distribucion_tiempos'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    Response::jsonSuccess($stats, 'Estadísticas de procesos');
}

/**
 * GET /stats/usuarios
 * Estadísticas de usuarios (solo admin)
 */
function handleUsuarioStats(): void {
    $db = getConnection();
    $stats = [];
    
    // Total usuarios
    $stmt = $db->query("SELECT COUNT(*) FROM control_usuarios WHERE estado_activo = 1");
    $stats['total_activos'] = (int) $stmt->fetchColumn();
    
    // Por rol
    $stmt = $db->query("
        SELECT rol, COUNT(*) as total 
        FROM control_usuarios 
        WHERE estado_activo = 1
        GROUP BY rol
    ");
    $stats['por_rol'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Actividad por usuario (últimos 7 días)
    $stmt = $db->query("
        SELECT 
            u.nombre_completo,
            COUNT(h.id) as acciones,
            MAX(h.fecha) as ultima_accion
        FROM control_usuarios u
        LEFT JOIN procesos_historial h ON u.id = h.usuario_id 
            AND h.fecha >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        WHERE u.estado_activo = 1
        GROUP BY u.id, u.nombre_completo
        ORDER BY acciones DESC
        LIMIT 10
    ");
    $stats['actividad_usuarios'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Logins hoy
    $stmt = $db->query("
        SELECT COUNT(*) FROM control_logs 
        WHERE accion = 'login' 
        AND DATE(timestamp) = CURDATE()
    ");
    $stats['logins_hoy'] = (int) $stmt->fetchColumn();
    
    Response::jsonSuccess($stats, 'Estadísticas de usuarios');
}

/**
 * GET /stats/actividad
 * Actividad reciente
 */
function handleActividadReciente(): void {
    $db = getConnection();
    
    $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    
    $stmt = $db->prepare("
        SELECT 
            h.id,
            h.accion,
            h.descripcion,
            h.fecha,
            h.estado_anterior,
            h.estado_nuevo,
            p.codigo as proceso_codigo,
            p.id as proceso_id,
            u.nombre_completo as usuario,
            u.id as usuario_id
        FROM procesos_historial h
        LEFT JOIN procesos p ON h.proceso_id = p.id
        LEFT JOIN control_usuarios u ON h.usuario_id = u.id
        ORDER BY h.fecha DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $actividad = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    Response::jsonSuccess($actividad, 'Actividad reciente');
}

/**
 * GET /stats/rendimiento
 * Métricas de rendimiento
 */
function handleRendimiento(): void {
    $db = getConnection();
    $stats = [];
    
    // Tokens usados (si hay datos de IA)
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_analisis,
            SUM(JSON_EXTRACT(metadata, '$.tokens_total')) as total_tokens
        FROM procesos_datos_ia
        WHERE metadata IS NOT NULL
        AND fecha_analisis >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['tokens_30d'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Errores por tipo
    $stmt = $db->query("
        SELECT 
            estado,
            COUNT(*) as total
        FROM procesos
        WHERE estado LIKE 'error_%'
        AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY estado
    ");
    $stats['errores_30d'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Trabajos en cola (últimas 24h)
    $stmt = $db->query("
        SELECT estado, COUNT(*) as total
        FROM colas_trabajos
        WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY estado
    ");
    $stats['trabajos_24h'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Tiempo promedio por etapa
    $stmt = $db->query("
        SELECT 
            AVG(TIMESTAMPDIFF(MINUTE, fecha_creacion, fecha_analisis)) as tiempo_analisis,
            AVG(TIMESTAMPDIFF(MINUTE, fecha_validacion, fecha_llenado)) as tiempo_llenado
        FROM procesos
        WHERE estado = 'completado'
        AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats['tiempos_promedio_min'] = $stmt->fetch(PDO::FETCH_ASSOC);
    
    Response::jsonSuccess($stats, 'Métricas de rendimiento');
}

