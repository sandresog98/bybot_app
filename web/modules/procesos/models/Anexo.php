<?php
/**
 * Modelo Anexo
 * Gestiona los archivos anexos de cada proceso
 */

require_once BASE_DIR . '/web/core/BaseModel.php';

class Anexo extends BaseModel {
    protected $table = 'procesos_anexos';
    
    /**
     * Tipos de anexo permitidos
     */
    const TIPOS = [
        'pagare_original' => 'Pagaré Original',
        'estado_cuenta' => 'Estado de Cuenta',
        'anexo' => 'Anexo General',
        'solicitud_deudor' => 'Solicitud Vinculación Deudor',
        'solicitud_codeudor' => 'Solicitud Vinculación Codeudor',
        'pagare_llenado' => 'Pagaré Llenado',
        'otro' => 'Otro'
    ];
    
    /**
     * Extensiones permitidas por tipo MIME
     */
    const ALLOWED_MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    
    /**
     * Tamaños máximos por tipo (en bytes)
     */
    const MAX_SIZES = [
        'pdf' => 10485760,    // 10 MB
        'image' => 5242880    // 5 MB
    ];
    
    /**
     * Obtiene todos los anexos de un proceso
     */
    public function getByProcesoId(int $procesoId): array {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE proceso_id = ?
            ORDER BY orden ASC, fecha_subida ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procesoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene anexos por tipo
     */
    public function getByTipo(int $procesoId, string $tipo): array {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE proceso_id = ? AND tipo = ?
            ORDER BY orden ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$procesoId, $tipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Guarda un archivo anexo
     */
    public function saveFile(
        int $procesoId, 
        array $file, 
        string $tipo = 'anexo',
        ?int $orden = null
    ): ?array {
        // Validar archivo
        $validation = $this->validateFile($file);
        if ($validation !== true) {
            throw new InvalidArgumentException($validation);
        }
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $timestamp = date('YmdHis');
        $unique = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $nombreArchivo = "{$tipo}_{$procesoId}_{$timestamp}_{$unique}.{$extension}";
        
        // Crear directorio de destino
        $year = date('Y');
        $month = date('m');
        $uploadDir = UPLOADS_DIR . "/procesos/{$year}/{$month}";
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $rutaCompleta = $uploadDir . '/' . $nombreArchivo;
        $rutaRelativa = "procesos/{$year}/{$month}/{$nombreArchivo}";
        
        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $rutaCompleta)) {
            throw new RuntimeException('Error al mover el archivo subido');
        }
        
        // Calcular orden si no se especifica
        if ($orden === null) {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(MAX(orden), 0) + 1 FROM {$this->table} WHERE proceso_id = ?"
            );
            $stmt->execute([$procesoId]);
            $orden = (int) $stmt->fetchColumn();
        }
        
        // Guardar en BD
        $data = [
            'proceso_id' => $procesoId,
            'nombre_original' => $file['name'],
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $rutaRelativa,
            'tipo' => $tipo,
            'tamanio_bytes' => $file['size'],
            'mime_type' => $file['type'],
            'orden' => $orden
        ];
        
        $id = $this->create($data);
        
        if ($id) {
            return array_merge($data, ['id' => $id]);
        }
        
        // Si falló BD, eliminar archivo
        @unlink($rutaCompleta);
        return null;
    }
    
    /**
     * Guarda archivo desde contenido (no upload)
     */
    public function saveFromContent(
        int $procesoId,
        string $content,
        string $nombreOriginal,
        string $tipo = 'pagare_llenado',
        string $mimeType = 'application/pdf'
    ): ?array {
        $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION) ?: 'pdf';
        $timestamp = date('YmdHis');
        $unique = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $nombreArchivo = "{$tipo}_{$procesoId}_{$timestamp}_{$unique}.{$extension}";
        
        // Crear directorio
        $year = date('Y');
        $month = date('m');
        $uploadDir = UPLOADS_DIR . "/procesos/{$year}/{$month}";
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $rutaCompleta = $uploadDir . '/' . $nombreArchivo;
        $rutaRelativa = "procesos/{$year}/{$month}/{$nombreArchivo}";
        
        // Guardar contenido
        if (file_put_contents($rutaCompleta, $content) === false) {
            throw new RuntimeException('Error al guardar el archivo');
        }
        
        // Guardar en BD
        $data = [
            'proceso_id' => $procesoId,
            'nombre_original' => $nombreOriginal,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $rutaRelativa,
            'tipo' => $tipo,
            'tamanio_bytes' => strlen($content),
            'mime_type' => $mimeType,
            'orden' => 999 // Archivos generados van al final
        ];
        
        $id = $this->create($data);
        
        if ($id) {
            return array_merge($data, ['id' => $id]);
        }
        
        @unlink($rutaCompleta);
        return null;
    }
    
    /**
     * Valida un archivo subido
     */
    private function validateFile(array $file): true|string {
        // Verificar errores de upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo del formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se seleccionó ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta directorio temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
                UPLOAD_ERR_EXTENSION => 'Extensión de PHP detuvo la subida'
            ];
            return $errors[$file['error']] ?? 'Error desconocido al subir archivo';
        }
        
        // Verificar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!isset(self::ALLOWED_MIMES[$mimeType])) {
            return 'Tipo de archivo no permitido. Solo se permiten: PDF, JPG, PNG, GIF, WebP';
        }
        
        // Verificar tamaño
        $isPdf = $mimeType === 'application/pdf';
        $maxSize = $isPdf ? self::MAX_SIZES['pdf'] : self::MAX_SIZES['image'];
        
        if ($file['size'] > $maxSize) {
            $maxMB = $maxSize / 1024 / 1024;
            return "El archivo excede el tamaño máximo de {$maxMB} MB";
        }
        
        return true;
    }
    
    /**
     * Obtiene la ruta completa de un archivo
     */
    public function getFilePath(int $id): ?string {
        $anexo = $this->findById($id);
        if (!$anexo) {
            return null;
        }
        
        $path = UPLOADS_DIR . '/' . $anexo['ruta_archivo'];
        
        if (!file_exists($path)) {
            return null;
        }
        
        return $path;
    }
    
    /**
     * Elimina un archivo (físico y de BD)
     */
    public function deleteFile(int $id): bool {
        $anexo = $this->findById($id);
        if (!$anexo) {
            return false;
        }
        
        // Eliminar archivo físico
        $path = UPLOADS_DIR . '/' . $anexo['ruta_archivo'];
        if (file_exists($path)) {
            @unlink($path);
        }
        
        // Eliminar de BD
        return $this->delete($id);
    }
    
    /**
     * Reordena los anexos de un proceso
     */
    public function reorder(int $procesoId, array $orden): bool {
        $this->db->beginTransaction();
        
        try {
            foreach ($orden as $index => $anexoId) {
                $stmt = $this->db->prepare(
                    "UPDATE {$this->table} SET orden = ? WHERE id = ? AND proceso_id = ?"
                );
                $stmt->execute([$index, $anexoId, $procesoId]);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    /**
     * Cuenta anexos por proceso
     */
    public function countByProceso(int $procesoId): int {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE proceso_id = ?"
        );
        $stmt->execute([$procesoId]);
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Obtiene estadísticas de almacenamiento
     */
    public function getStorageStats(?int $procesoId = null): array {
        $where = $procesoId ? 'WHERE proceso_id = ?' : '';
        $params = $procesoId ? [$procesoId] : [];
        
        $sql = "
            SELECT 
                COUNT(*) as total_archivos,
                COALESCE(SUM(tamanio_bytes), 0) as total_bytes,
                tipo,
                COUNT(*) as cantidad
            FROM {$this->table}
            {$where}
            GROUP BY tipo
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $porTipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Totales
        $sql2 = "
            SELECT 
                COUNT(*) as total_archivos,
                COALESCE(SUM(tamanio_bytes), 0) as total_bytes
            FROM {$this->table}
            {$where}
        ";
        
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute($params);
        $totales = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_archivos' => (int) $totales['total_archivos'],
            'total_bytes' => (int) $totales['total_bytes'],
            'total_mb' => round($totales['total_bytes'] / 1024 / 1024, 2),
            'por_tipo' => $porTipo
        ];
    }
}

