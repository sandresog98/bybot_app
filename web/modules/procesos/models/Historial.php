<?php
/**
 * Modelo Historial
 * Gestiona el historial de eventos de cada proceso
 */

require_once BASE_DIR . '/web/core/BaseModel.php';

class Historial extends BaseModel {
    protected $table = 'procesos_historial';
    
    /**
     * Tipos de acciones registradas
     */
    const ACCIONES = [
        'creado' => 'Proceso creado',
        'estado_cambiado' => 'Estado cambiado',
        'archivos_subidos' => 'Archivos subidos',
        'archivo_eliminado' => 'Archivo eliminado',
        'analizado' => 'Análisis completado',
        'datos_editados' => 'Datos editados',
        'validado' => 'Datos validados',
        'llenado' => 'Pagaré llenado',
        'error' => 'Error ocurrido',
        'nota_agregada' => 'Nota agregada',
        'asignado' => 'Proceso asignado',
        'reencolado' => 'Reencolado para procesar',
        'cancelado' => 'Proceso cancelado'
    ];
    
    /**
     * Obtiene el historial de un proceso
     */
    public function getByProcesoId(int $procesoId, int $limit = 50): array {
        $sql = "
            SELECT 
                h.*,
                u.nombre_completo as usuario_nombre,
                u.usuario as usuario_login
            FROM {$this->table} h
            LEFT JOIN control_usuarios u ON h.usuario_id = u.id
            WHERE h.proceso_id = ?
            ORDER BY h.fecha DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procesoId, $limit]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar JSON
        foreach ($items as &$item) {
            if ($item['datos_cambio']) {
                $item['datos_cambio'] = json_decode($item['datos_cambio'], true);
            }
        }
        
        return $items;
    }
    
    /**
     * Registra un evento en el historial
     */
    public function registrar(
        int $procesoId,
        ?int $userId,
        string $accion,
        ?string $estadoAnterior = null,
        ?string $estadoNuevo = null,
        ?string $descripcion = null,
        ?array $datosCambio = null
    ): int {
        $data = [
            'proceso_id' => $procesoId,
            'usuario_id' => $userId,
            'accion' => $accion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'descripcion' => $descripcion,
            'datos_cambio' => $datosCambio ? json_encode($datosCambio, JSON_UNESCAPED_UNICODE) : null
        ];
        
        return $this->create($data);
    }
    
    /**
     * Registra cambio de estado
     */
    public function registrarCambioEstado(
        int $procesoId,
        ?int $userId,
        string $estadoAnterior,
        string $estadoNuevo,
        ?string $mensaje = null
    ): int {
        $descripcion = $mensaje ?? "Estado cambiado de '{$estadoAnterior}' a '{$estadoNuevo}'";
        
        return $this->registrar(
            $procesoId,
            $userId,
            'estado_cambiado',
            $estadoAnterior,
            $estadoNuevo,
            $descripcion
        );
    }
    
    /**
     * Registra subida de archivos
     */
    public function registrarArchivosSubidos(
        int $procesoId,
        ?int $userId,
        array $archivos
    ): int {
        $cantidad = count($archivos);
        $nombres = array_column($archivos, 'nombre_original');
        
        return $this->registrar(
            $procesoId,
            $userId,
            'archivos_subidos',
            null,
            null,
            "Se subieron {$cantidad} archivo(s)",
            ['archivos' => $nombres]
        );
    }
    
    /**
     * Registra análisis completado
     */
    public function registrarAnalisis(
        int $procesoId,
        array $metadata = []
    ): int {
        $tokens = $metadata['tokens_total'] ?? 'N/A';
        $modelo = $metadata['modelo'] ?? 'N/A';
        
        return $this->registrar(
            $procesoId,
            null, // Sistema
            'analizado',
            null,
            'analizado',
            "Análisis completado. Modelo: {$modelo}, Tokens: {$tokens}",
            ['metadata' => $metadata]
        );
    }
    
    /**
     * Registra validación de datos
     */
    public function registrarValidacion(
        int $procesoId,
        int $userId,
        array $cambios = []
    ): int {
        $cantidadCambios = count($cambios);
        $descripcion = $cantidadCambios > 0 
            ? "Datos validados con {$cantidadCambios} modificación(es)"
            : "Datos validados sin modificaciones";
        
        return $this->registrar(
            $procesoId,
            $userId,
            'validado',
            'analizado',
            'validado',
            $descripcion,
            $cantidadCambios > 0 ? ['cambios' => $cambios] : null
        );
    }
    
    /**
     * Registra error
     */
    public function registrarError(
        int $procesoId,
        string $tipo,
        string $mensaje,
        ?array $detalles = null
    ): int {
        return $this->registrar(
            $procesoId,
            null, // Sistema
            'error',
            null,
            "error_{$tipo}",
            "Error de {$tipo}: {$mensaje}",
            $detalles ? ['error' => $detalles] : null
        );
    }
    
    /**
     * Obtiene el timeline formateado para UI
     */
    public function getTimeline(int $procesoId): array {
        $items = $this->getByProcesoId($procesoId);
        $timeline = [];
        
        foreach ($items as $item) {
            $timeline[] = [
                'id' => $item['id'],
                'fecha' => $item['fecha'],
                'fecha_relativa' => $this->tiempoRelativo($item['fecha']),
                'accion' => $item['accion'],
                'accion_label' => self::ACCIONES[$item['accion']] ?? $item['accion'],
                'descripcion' => $item['descripcion'],
                'usuario' => $item['usuario_nombre'] ?? 'Sistema',
                'icono' => $this->getIcono($item['accion']),
                'color' => $this->getColor($item['accion']),
                'estado_anterior' => $item['estado_anterior'],
                'estado_nuevo' => $item['estado_nuevo'],
                'datos' => $item['datos_cambio']
            ];
        }
        
        return $timeline;
    }
    
    /**
     * Calcula tiempo relativo
     */
    private function tiempoRelativo(string $fecha): string {
        $timestamp = strtotime($fecha);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Hace un momento';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return "Hace {$mins} minuto" . ($mins > 1 ? 's' : '');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "Hace {$hours} hora" . ($hours > 1 ? 's' : '');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "Hace {$days} día" . ($days > 1 ? 's' : '');
        } else {
            return date('d/m/Y H:i', $timestamp);
        }
    }
    
    /**
     * Obtiene icono para cada acción
     */
    private function getIcono(string $accion): string {
        $iconos = [
            'creado' => 'bi-plus-circle',
            'estado_cambiado' => 'bi-arrow-repeat',
            'archivos_subidos' => 'bi-upload',
            'archivo_eliminado' => 'bi-trash',
            'analizado' => 'bi-robot',
            'datos_editados' => 'bi-pencil',
            'validado' => 'bi-check-circle',
            'llenado' => 'bi-file-earmark-pdf',
            'error' => 'bi-exclamation-triangle',
            'nota_agregada' => 'bi-chat-text',
            'asignado' => 'bi-person-check',
            'reencolado' => 'bi-arrow-clockwise',
            'cancelado' => 'bi-x-circle'
        ];
        
        return $iconos[$accion] ?? 'bi-circle';
    }
    
    /**
     * Obtiene color para cada acción
     */
    private function getColor(string $accion): string {
        $colores = [
            'creado' => 'primary',
            'estado_cambiado' => 'info',
            'archivos_subidos' => 'success',
            'archivo_eliminado' => 'warning',
            'analizado' => 'primary',
            'datos_editados' => 'info',
            'validado' => 'success',
            'llenado' => 'success',
            'error' => 'danger',
            'nota_agregada' => 'secondary',
            'asignado' => 'info',
            'reencolado' => 'warning',
            'cancelado' => 'danger'
        ];
        
        return $colores[$accion] ?? 'secondary';
    }
    
    /**
     * Obtiene actividad reciente global
     */
    public function getActividadReciente(int $limit = 20, ?int $userId = null): array {
        $where = '1 = 1';
        $params = [];
        
        if ($userId) {
            $where = 'h.usuario_id = ?';
            $params[] = $userId;
        }
        
        $sql = "
            SELECT 
                h.*,
                u.nombre_completo as usuario_nombre,
                p.codigo as proceso_codigo
            FROM {$this->table} h
            LEFT JOIN control_usuarios u ON h.usuario_id = u.id
            LEFT JOIN procesos p ON h.proceso_id = p.id
            WHERE {$where}
            ORDER BY h.fecha DESC
            LIMIT ?
        ";
        
        $params[] = $limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($items as &$item) {
            if ($item['datos_cambio']) {
                $item['datos_cambio'] = json_decode($item['datos_cambio'], true);
            }
            $item['fecha_relativa'] = $this->tiempoRelativo($item['fecha']);
        }
        
        return $items;
    }
    
    /**
     * Cuenta eventos por tipo en un período
     */
    public function countByAccion(?string $fechaDesde = null, ?string $fechaHasta = null): array {
        $where = ['1 = 1'];
        $params = [];
        
        if ($fechaDesde) {
            $where[] = 'DATE(fecha) >= ?';
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where[] = 'DATE(fecha) <= ?';
            $params[] = $fechaHasta;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT accion, COUNT(*) as total
            FROM {$this->table}
            WHERE {$whereClause}
            GROUP BY accion
            ORDER BY total DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['accion']] = (int) $row['total'];
        }
        
        return $result;
    }
}

