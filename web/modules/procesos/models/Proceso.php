<?php
/**
 * Modelo Proceso
 * Gestiona los procesos de cobranza
 */

require_once BASE_DIR . '/web/core/BaseModel.php';

class Proceso extends BaseModel {
    protected $table = 'procesos';
    
    /**
     * Estados posibles del proceso
     */
    const ESTADOS = [
        'creado' => 'Creado',
        'en_cola_analisis' => 'En cola de análisis',
        'analizando' => 'Analizando',
        'analizado' => 'Analizado',
        'validado' => 'Validado',
        'en_cola_llenado' => 'En cola de llenado',
        'llenando' => 'Llenando pagaré',
        'completado' => 'Completado',
        'error_analisis' => 'Error en análisis',
        'error_llenado' => 'Error en llenado',
        'cancelado' => 'Cancelado'
    ];
    
    /**
     * Tipos de proceso
     */
    const TIPOS = [
        'cobranza' => 'Cobranza',
        'demanda' => 'Demanda',
        'otro' => 'Otro'
    ];
    
    /**
     * Busca procesos con filtros y paginación
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 10): array {
        $where = ['1 = 1'];
        $params = [];
        
        // Filtro por estado
        if (!empty($filters['estado'])) {
            $where[] = 'p.estado = ?';
            $params[] = $filters['estado'];
        }
        
        // Filtro por estados múltiples
        if (!empty($filters['estados']) && is_array($filters['estados'])) {
            $placeholders = implode(',', array_fill(0, count($filters['estados']), '?'));
            $where[] = "p.estado IN ({$placeholders})";
            $params = array_merge($params, $filters['estados']);
        }
        
        // Filtro por tipo
        if (!empty($filters['tipo'])) {
            $where[] = 'p.tipo = ?';
            $params[] = $filters['tipo'];
        }
        
        // Filtro por código
        if (!empty($filters['codigo'])) {
            $where[] = 'p.codigo LIKE ?';
            $params[] = '%' . $filters['codigo'] . '%';
        }
        
        // Filtro por creador
        if (!empty($filters['creado_por'])) {
            $where[] = 'p.creado_por = ?';
            $params[] = $filters['creado_por'];
        }
        
        // Filtro por asignado
        if (!empty($filters['asignado_a'])) {
            $where[] = 'p.asignado_a = ?';
            $params[] = $filters['asignado_a'];
        }
        
        // Filtro por fecha de creación
        if (!empty($filters['fecha_desde'])) {
            $where[] = 'DATE(p.fecha_creacion) >= ?';
            $params[] = $filters['fecha_desde'];
        }
        if (!empty($filters['fecha_hasta'])) {
            $where[] = 'DATE(p.fecha_creacion) <= ?';
            $params[] = $filters['fecha_hasta'];
        }
        
        // Búsqueda general
        if (!empty($filters['q'])) {
            $where[] = '(p.codigo LIKE ? OR p.notas LIKE ?)';
            $searchTerm = '%' . $filters['q'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Ordenamiento
        $orderBy = 'p.fecha_creacion DESC';
        if (!empty($filters['order_by'])) {
            $allowed = ['codigo', 'estado', 'fecha_creacion', 'fecha_actualizacion', 'prioridad'];
            if (in_array($filters['order_by'], $allowed)) {
                $direction = ($filters['order_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
                $orderBy = "p.{$filters['order_by']} {$direction}";
            }
        }
        
        // Contar total
        $countSql = "SELECT COUNT(*) FROM {$this->table} p WHERE {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();
        
        // Calcular paginación
        $offset = ($page - 1) * $perPage;
        $totalPages = ceil($total / $perPage);
        
        // Query principal con JOINs
        $sql = "
            SELECT 
                p.*,
                u_creador.nombre_completo as creador_nombre,
                u_asignado.nombre_completo as asignado_nombre,
                (SELECT COUNT(*) FROM procesos_anexos WHERE proceso_id = p.id) as total_anexos
            FROM {$this->table} p
            LEFT JOIN control_usuarios u_creador ON p.creado_por = u_creador.id
            LEFT JOIN control_usuarios u_asignado ON p.asignado_a = u_asignado.id
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT {$perPage} OFFSET {$offset}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ];
    }
    
    /**
     * Obtiene un proceso con todos sus datos relacionados
     */
    public function getWithDetails(int $id): ?array {
        // Proceso principal
        $sql = "
            SELECT 
                p.*,
                u_creador.nombre_completo as creador_nombre,
                u_creador.usuario as creador_usuario,
                u_asignado.nombre_completo as asignado_nombre,
                u_asignado.usuario as asignado_usuario
            FROM {$this->table} p
            LEFT JOIN control_usuarios u_creador ON p.creado_por = u_creador.id
            LEFT JOIN control_usuarios u_asignado ON p.asignado_a = u_asignado.id
            WHERE p.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $proceso = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$proceso) {
            return null;
        }
        
        // Anexos
        $stmtAnexos = $this->db->prepare("
            SELECT * FROM procesos_anexos 
            WHERE proceso_id = ? 
            ORDER BY orden ASC, fecha_subida ASC
        ");
        $stmtAnexos->execute([$id]);
        $proceso['anexos'] = $stmtAnexos->fetchAll(PDO::FETCH_ASSOC);
        
        // Datos IA (última versión)
        $stmtDatosIA = $this->db->prepare("
            SELECT * FROM procesos_datos_ia 
            WHERE proceso_id = ? 
            ORDER BY version DESC 
            LIMIT 1
        ");
        $stmtDatosIA->execute([$id]);
        $datosIA = $stmtDatosIA->fetch(PDO::FETCH_ASSOC);
        
        if ($datosIA) {
            $datosIA['datos_originales'] = json_decode($datosIA['datos_originales'], true);
            $datosIA['datos_validados'] = $datosIA['datos_validados'] 
                ? json_decode($datosIA['datos_validados'], true) 
                : null;
            $datosIA['metadata'] = $datosIA['metadata'] 
                ? json_decode($datosIA['metadata'], true) 
                : null;
        }
        $proceso['datos_ia'] = $datosIA;
        
        // Historial (últimos 20)
        $stmtHistorial = $this->db->prepare("
            SELECT 
                h.*,
                u.nombre_completo as usuario_nombre
            FROM procesos_historial h
            LEFT JOIN control_usuarios u ON h.usuario_id = u.id
            WHERE h.proceso_id = ? 
            ORDER BY h.fecha DESC 
            LIMIT 20
        ");
        $stmtHistorial->execute([$id]);
        $historial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($historial as &$h) {
            if ($h['datos_cambio']) {
                $h['datos_cambio'] = json_decode($h['datos_cambio'], true);
            }
        }
        $proceso['historial'] = $historial;
        
        return $proceso;
    }
    
    /**
     * Crea un nuevo proceso con código único
     */
    public function createProcess(array $data, int $userId): int {
        // Generar código único
        $year = date('Y');
        $month = date('m');
        
        // Obtener siguiente número
        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(codigo, -4) AS UNSIGNED)) as max_num
            FROM {$this->table}
            WHERE codigo LIKE ?
        ");
        $prefix = "COOP{$year}{$month}";
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nextNum = ($result['max_num'] ?? 0) + 1;
        $codigo = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        
        // Preparar datos
        $processData = [
            'codigo' => $codigo,
            'tipo' => $data['tipo'] ?? 'cobranza',
            'estado' => 'creado',
            'prioridad' => $data['prioridad'] ?? 5,
            'creado_por' => $userId,
            'notas' => $data['notas'] ?? null
        ];
        
        $id = $this->create($processData);
        
        if ($id) {
            // Registrar en historial
            $this->addHistory($id, $userId, 'creado', null, 'creado', 
                "Proceso creado con código: {$codigo}");
        }
        
        return $id;
    }
    
    /**
     * Actualiza el estado de un proceso
     */
    public function updateStatus(int $id, string $newStatus, ?int $userId = null, ?string $message = null): bool {
        // Obtener estado actual
        $proceso = $this->findById($id);
        if (!$proceso) {
            return false;
        }
        
        $oldStatus = $proceso['estado'];
        
        // Actualizar estado
        $updates = ['estado' => $newStatus];
        
        // Actualizar fechas según el estado
        switch ($newStatus) {
            case 'analizado':
                $updates['fecha_analisis'] = date('Y-m-d H:i:s');
                break;
            case 'validado':
                $updates['fecha_validacion'] = date('Y-m-d H:i:s');
                break;
            case 'completado':
                $updates['fecha_completado'] = date('Y-m-d H:i:s');
                break;
        }
        
        $result = $this->update($id, $updates);
        
        if ($result) {
            $descripcion = $message ?? "Estado cambiado de '{$oldStatus}' a '{$newStatus}'";
            $this->addHistory($id, $userId, 'estado_cambiado', $oldStatus, $newStatus, $descripcion);
        }
        
        return $result;
    }
    
    /**
     * Registra en el historial
     */
    public function addHistory(
        int $procesoId, 
        ?int $userId, 
        string $accion, 
        ?string $estadoAnterior, 
        ?string $estadoNuevo, 
        ?string $descripcion = null,
        ?array $datosCambio = null
    ): bool {
        $sql = "
            INSERT INTO procesos_historial 
            (proceso_id, usuario_id, accion, estado_anterior, estado_nuevo, descripcion, datos_cambio)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $procesoId,
            $userId,
            $accion,
            $estadoAnterior,
            $estadoNuevo,
            $descripcion,
            $datosCambio ? json_encode($datosCambio) : null
        ]);
    }
    
    /**
     * Obtiene estadísticas de procesos
     */
    public function getStats(?int $userId = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array {
        $where = ['1 = 1'];
        $params = [];
        
        if ($userId) {
            $where[] = '(creado_por = ? OR asignado_a = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }
        
        if ($fechaDesde) {
            $where[] = 'DATE(fecha_creacion) >= ?';
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where[] = 'DATE(fecha_creacion) <= ?';
            $params[] = $fechaHasta;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Totales por estado
        $sql = "
            SELECT estado, COUNT(*) as total
            FROM {$this->table}
            WHERE {$whereClause}
            GROUP BY estado
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $porEstado = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $porEstado[$row['estado']] = (int) $row['total'];
        }
        
        // Total general
        $total = array_sum($porEstado);
        
        // Procesos hoy
        $sqlHoy = "
            SELECT COUNT(*) FROM {$this->table}
            WHERE DATE(fecha_creacion) = CURDATE()
        ";
        $procesosHoy = (int) $this->db->query($sqlHoy)->fetchColumn();
        
        // Completados esta semana
        $sqlSemana = "
            SELECT COUNT(*) FROM {$this->table}
            WHERE estado = 'completado' 
            AND YEARWEEK(fecha_completado) = YEARWEEK(NOW())
        ";
        $completadosSemana = (int) $this->db->query($sqlSemana)->fetchColumn();
        
        // Tiempo promedio de procesamiento (completados)
        $sqlTiempo = "
            SELECT AVG(TIMESTAMPDIFF(HOUR, fecha_creacion, fecha_completado)) as promedio
            FROM {$this->table}
            WHERE estado = 'completado' 
            AND fecha_completado IS NOT NULL
            AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";
        $tiempoPromedio = $this->db->query($sqlTiempo)->fetchColumn();
        
        return [
            'total' => $total,
            'por_estado' => $porEstado,
            'hoy' => $procesosHoy,
            'completados_semana' => $completadosSemana,
            'tiempo_promedio_horas' => $tiempoPromedio ? round($tiempoPromedio, 1) : null,
            'estados_disponibles' => self::ESTADOS
        ];
    }
    
    /**
     * Busca por código exacto
     */
    public function findByCodigo(string $codigo): ?array {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE codigo = ?");
        $stmt->execute([$codigo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Obtiene procesos pendientes para análisis
     */
    public function getPendingAnalysis(int $limit = 10): array {
        $sql = "
            SELECT p.*, 
                   (SELECT COUNT(*) FROM procesos_anexos WHERE proceso_id = p.id) as total_anexos
            FROM {$this->table} p
            WHERE p.estado IN ('creado', 'en_cola_analisis')
            AND p.intentos_analisis < p.max_intentos
            ORDER BY p.prioridad ASC, p.fecha_creacion ASC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene procesos pendientes para llenado
     */
    public function getPendingFill(int $limit = 10): array {
        $sql = "
            SELECT p.*
            FROM {$this->table} p
            WHERE p.estado IN ('validado', 'en_cola_llenado')
            AND p.intentos_llenado < p.max_intentos
            ORDER BY p.prioridad ASC, p.fecha_validacion ASC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

