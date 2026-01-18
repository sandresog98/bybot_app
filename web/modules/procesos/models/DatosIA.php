<?php
/**
 * Modelo DatosIA
 * Gestiona los datos extraídos por inteligencia artificial
 */

require_once BASE_DIR . '/web/core/BaseModel.php';

class DatosIA extends BaseModel {
    protected $table = 'procesos_datos_ia';
    
    /**
     * Campos esperados en datos_originales
     */
    const CAMPOS_ESTADO_CUENTA = [
        'capital' => ['tipo' => 'numero', 'label' => 'Capital'],
        'interes_plazo' => ['tipo' => 'numero', 'label' => 'Interés de Plazo'],
        'interes_mora' => ['tipo' => 'numero', 'label' => 'Interés de Mora'],
        'gastos_cobranza' => ['tipo' => 'numero', 'label' => 'Gastos de Cobranza'],
        'honorarios' => ['tipo' => 'numero', 'label' => 'Honorarios'],
        'total_deuda' => ['tipo' => 'numero', 'label' => 'Total Deuda'],
        'tasa_interes' => ['tipo' => 'porcentaje', 'label' => 'Tasa de Interés'],
        'tasa_mora' => ['tipo' => 'porcentaje', 'label' => 'Tasa de Mora'],
        'fecha_desembolso' => ['tipo' => 'fecha', 'label' => 'Fecha Desembolso'],
        'fecha_vencimiento' => ['tipo' => 'fecha', 'label' => 'Fecha Vencimiento'],
        'fecha_corte' => ['tipo' => 'fecha', 'label' => 'Fecha Corte'],
        'plazo_meses' => ['tipo' => 'entero', 'label' => 'Plazo (meses)']
    ];
    
    const CAMPOS_DEUDOR = [
        'nombre' => ['tipo' => 'texto', 'label' => 'Nombre Completo'],
        'cedula' => ['tipo' => 'documento', 'label' => 'Cédula/NIT'],
        'direccion' => ['tipo' => 'texto', 'label' => 'Dirección'],
        'ciudad' => ['tipo' => 'texto', 'label' => 'Ciudad'],
        'telefono' => ['tipo' => 'telefono', 'label' => 'Teléfono'],
        'email' => ['tipo' => 'email', 'label' => 'Email']
    ];
    
    /**
     * Obtiene los datos IA de un proceso (última versión)
     */
    public function getByProcesoId(int $procesoId): ?array {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE proceso_id = ?
            ORDER BY version DESC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procesoId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($data) {
            $data['datos_originales'] = json_decode($data['datos_originales'], true);
            $data['datos_validados'] = $data['datos_validados'] 
                ? json_decode($data['datos_validados'], true) 
                : null;
            $data['metadata'] = $data['metadata'] 
                ? json_decode($data['metadata'], true) 
                : null;
        }
        
        return $data;
    }
    
    /**
     * Obtiene todas las versiones de datos IA de un proceso
     */
    public function getAllVersions(int $procesoId): array {
        $sql = "
            SELECT id, version, fecha_analisis, fecha_validacion, validado_por
            FROM {$this->table}
            WHERE proceso_id = ?
            ORDER BY version DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procesoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Guarda datos de análisis IA
     */
    public function saveAnalysis(
        int $procesoId, 
        array $datosOriginales, 
        array $metadata = []
    ): int {
        // Obtener siguiente versión
        $stmt = $this->db->prepare(
            "SELECT COALESCE(MAX(version), 0) + 1 FROM {$this->table} WHERE proceso_id = ?"
        );
        $stmt->execute([$procesoId]);
        $version = (int) $stmt->fetchColumn();
        
        $data = [
            'proceso_id' => $procesoId,
            'version' => $version,
            'datos_originales' => json_encode($datosOriginales, JSON_UNESCAPED_UNICODE),
            'metadata' => !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null
        ];
        
        return $this->create($data);
    }
    
    /**
     * Actualiza datos validados
     */
    public function saveValidation(
        int $procesoId, 
        array $datosValidados, 
        int $userId
    ): bool {
        // Obtener registro actual
        $current = $this->getByProcesoId($procesoId);
        
        if (!$current) {
            throw new InvalidArgumentException('No hay datos de IA para validar');
        }
        
        $sql = "
            UPDATE {$this->table}
            SET 
                datos_validados = ?,
                fecha_validacion = NOW(),
                validado_por = ?
            WHERE id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            json_encode($datosValidados, JSON_UNESCAPED_UNICODE),
            $userId,
            $current['id']
        ]);
    }
    
    /**
     * Actualiza un campo específico de datos validados
     */
    public function updateField(
        int $procesoId, 
        string $campo, 
        $valor, 
        int $userId
    ): bool {
        $current = $this->getByProcesoId($procesoId);
        
        if (!$current) {
            throw new InvalidArgumentException('No hay datos de IA');
        }
        
        // Usar datos validados o crear desde originales
        $datos = $current['datos_validados'] ?? $current['datos_originales'];
        
        // Actualizar campo
        $this->setNestedValue($datos, $campo, $valor);
        
        return $this->saveValidation($procesoId, $datos, $userId);
    }
    
    /**
     * Establece un valor en una estructura anidada usando notación de punto
     */
    private function setNestedValue(array &$data, string $path, $value): void {
        $keys = explode('.', $path);
        $current = &$data;
        
        foreach ($keys as $i => $key) {
            if ($i === count($keys) - 1) {
                $current[$key] = $value;
            } else {
                if (!isset($current[$key]) || !is_array($current[$key])) {
                    $current[$key] = [];
                }
                $current = &$current[$key];
            }
        }
    }
    
    /**
     * Obtiene los datos para llenado de pagaré
     */
    public function getDataForFilling(int $procesoId): ?array {
        $current = $this->getByProcesoId($procesoId);
        
        if (!$current) {
            return null;
        }
        
        // Preferir datos validados, si no, usar originales
        $datos = $current['datos_validados'] ?? $current['datos_originales'];
        
        // Aplanar estructura para llenado
        $flat = [];
        
        // Estado de cuenta
        if (isset($datos['estado_cuenta'])) {
            foreach ($datos['estado_cuenta'] as $key => $value) {
                $flat[$key] = $value;
            }
        }
        
        // Deudor
        if (isset($datos['deudor'])) {
            foreach ($datos['deudor'] as $key => $value) {
                $flat['deudor_' . $key] = $value;
            }
        }
        
        // Codeudor
        if (isset($datos['codeudor'])) {
            foreach ($datos['codeudor'] as $key => $value) {
                $flat['codeudor_' . $key] = $value;
            }
        }
        
        return $flat;
    }
    
    /**
     * Valida la estructura de datos
     */
    public function validateStructure(array $datos): array {
        $errors = [];
        
        // Validar estado_cuenta
        if (isset($datos['estado_cuenta'])) {
            foreach (self::CAMPOS_ESTADO_CUENTA as $campo => $config) {
                if (isset($datos['estado_cuenta'][$campo])) {
                    $value = $datos['estado_cuenta'][$campo];
                    $error = $this->validateField($value, $config['tipo']);
                    if ($error) {
                        $errors["estado_cuenta.{$campo}"] = $error;
                    }
                }
            }
        }
        
        // Validar deudor
        if (isset($datos['deudor'])) {
            foreach (self::CAMPOS_DEUDOR as $campo => $config) {
                if (isset($datos['deudor'][$campo])) {
                    $value = $datos['deudor'][$campo];
                    $error = $this->validateField($value, $config['tipo']);
                    if ($error) {
                        $errors["deudor.{$campo}"] = $error;
                    }
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Valida un campo según su tipo
     */
    private function validateField($value, string $tipo): ?string {
        if ($value === null || $value === '') {
            return null; // Campos vacíos permitidos
        }
        
        switch ($tipo) {
            case 'numero':
                if (!is_numeric($value)) {
                    return 'Debe ser un número válido';
                }
                break;
                
            case 'entero':
                if (!is_numeric($value) || floor($value) != $value) {
                    return 'Debe ser un número entero';
                }
                break;
                
            case 'porcentaje':
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    return 'Debe ser un porcentaje entre 0 y 100';
                }
                break;
                
            case 'fecha':
                if (!strtotime($value)) {
                    return 'Debe ser una fecha válida';
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return 'Debe ser un email válido';
                }
                break;
                
            case 'documento':
                if (!preg_match('/^[\d\.-]+$/', $value)) {
                    return 'Formato de documento inválido';
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Compara datos originales con validados
     */
    public function getDiff(int $procesoId): array {
        $current = $this->getByProcesoId($procesoId);
        
        if (!$current || !$current['datos_validados']) {
            return [];
        }
        
        return $this->arrayDiffRecursive(
            $current['datos_originales'],
            $current['datos_validados']
        );
    }
    
    /**
     * Diferencia recursiva de arrays
     */
    private function arrayDiffRecursive(array $original, array $validado, string $prefix = ''): array {
        $diff = [];
        
        foreach ($validado as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (!isset($original[$key])) {
                $diff[$fullKey] = [
                    'tipo' => 'agregado',
                    'nuevo' => $value
                ];
            } elseif (is_array($value) && is_array($original[$key])) {
                $subDiff = $this->arrayDiffRecursive($original[$key], $value, $fullKey);
                $diff = array_merge($diff, $subDiff);
            } elseif ($original[$key] !== $value) {
                $diff[$fullKey] = [
                    'tipo' => 'modificado',
                    'original' => $original[$key],
                    'nuevo' => $value
                ];
            }
        }
        
        return $diff;
    }
    
    /**
     * Obtiene estadísticas de tokens usados
     */
    public function getTokenStats(?string $fechaDesde = null, ?string $fechaHasta = null): array {
        $where = ['1 = 1'];
        $params = [];
        
        if ($fechaDesde) {
            $where[] = 'DATE(fecha_analisis) >= ?';
            $params[] = $fechaDesde;
        }
        
        if ($fechaHasta) {
            $where[] = 'DATE(fecha_analisis) <= ?';
            $params[] = $fechaHasta;
        }
        
        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT 
                COUNT(*) as total_analisis,
                SUM(JSON_EXTRACT(metadata, '$.tokens_total')) as total_tokens,
                AVG(JSON_EXTRACT(metadata, '$.tokens_total')) as promedio_tokens
            FROM {$this->table}
            WHERE {$whereClause}
            AND metadata IS NOT NULL
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_analisis' => (int) $stats['total_analisis'],
            'total_tokens' => (int) ($stats['total_tokens'] ?? 0),
            'promedio_tokens' => round($stats['promedio_tokens'] ?? 0, 0)
        ];
    }
}

