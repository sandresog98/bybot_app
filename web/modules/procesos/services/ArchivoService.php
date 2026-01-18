<?php
/**
 * Servicio de Archivos
 * Lógica de negocio para gestión de archivos/anexos
 */

require_once BASE_DIR . '/web/core/BaseService.php';
require_once BASE_DIR . '/web/modules/procesos/models/Anexo.php';
require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
require_once BASE_DIR . '/web/modules/procesos/models/Historial.php';

class ArchivoService extends BaseService {
    private Anexo $anexoModel;
    private Proceso $procesoModel;
    private Historial $historialModel;
    
    public function __construct() {
        $this->anexoModel = new Anexo();
        $this->procesoModel = new Proceso();
        $this->historialModel = new Historial();
    }
    
    /**
     * Sube archivos a un proceso
     */
    public function subirArchivos(int $procesoId, array $files, int $userId, string $tipo = 'anexo'): array {
        // Verificar proceso
        $proceso = $this->procesoModel->findById($procesoId);
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Verificar estado (solo permitir en estados iniciales)
        $estadosPermitidos = ['creado', 'analizado', 'error_analisis'];
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException(
                'No se pueden subir archivos en el estado actual: ' . $proceso['estado']
            );
        }
        
        // Normalizar estructura de $_FILES
        $archivos = $this->normalizarFiles($files);
        
        if (empty($archivos)) {
            throw new InvalidArgumentException('No se proporcionaron archivos');
        }
        
        $subidos = [];
        $errores = [];
        
        foreach ($archivos as $index => $file) {
            try {
                // Determinar tipo según nombre o posición
                $tipoArchivo = $this->detectarTipo($file['name'], $tipo, $index);
                
                $resultado = $this->anexoModel->saveFile($procesoId, $file, $tipoArchivo);
                
                if ($resultado) {
                    $subidos[] = $resultado;
                } else {
                    $errores[] = [
                        'archivo' => $file['name'],
                        'error' => 'Error al guardar el archivo'
                    ];
                }
            } catch (Exception $e) {
                $errores[] = [
                    'archivo' => $file['name'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Registrar en historial
        if (!empty($subidos)) {
            $this->historialModel->registrarArchivosSubidos($procesoId, $userId, $subidos);
        }
        
        return [
            'subidos' => $subidos,
            'errores' => $errores,
            'total_subidos' => count($subidos),
            'total_errores' => count($errores)
        ];
    }
    
    /**
     * Sube archivo específico (pagaré, estado de cuenta)
     */
    public function subirArchivoEspecifico(
        int $procesoId, 
        array $file, 
        string $tipo, 
        int $userId
    ): array {
        // Verificar proceso
        $proceso = $this->procesoModel->findById($procesoId);
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Validar tipo
        if (!isset(Anexo::TIPOS[$tipo])) {
            throw new InvalidArgumentException('Tipo de archivo no válido: ' . $tipo);
        }
        
        // Guardar archivo
        $resultado = $this->anexoModel->saveFile($procesoId, $file, $tipo);
        
        if (!$resultado) {
            throw new RuntimeException('Error al guardar el archivo');
        }
        
        // Actualizar campos del proceso si es necesario
        if ($tipo === 'pagare_original') {
            $this->procesoModel->update($procesoId, [
                'archivo_pagare_original' => $resultado['ruta_archivo']
            ]);
        } elseif ($tipo === 'estado_cuenta') {
            $this->procesoModel->update($procesoId, [
                'archivo_estado_cuenta' => $resultado['ruta_archivo']
            ]);
        }
        
        // Registrar en historial
        $this->historialModel->registrar(
            $procesoId,
            $userId,
            'archivos_subidos',
            null,
            null,
            "Archivo subido: {$resultado['nombre_original']} ({$tipo})"
        );
        
        return $resultado;
    }
    
    /**
     * Descarga un archivo
     */
    public function descargar(int $anexoId): array {
        $anexo = $this->anexoModel->findById($anexoId);
        
        if (!$anexo) {
            throw new InvalidArgumentException('Archivo no encontrado');
        }
        
        $path = $this->anexoModel->getFilePath($anexoId);
        
        if (!$path || !file_exists($path)) {
            throw new RuntimeException('El archivo físico no existe');
        }
        
        return [
            'path' => $path,
            'nombre' => $anexo['nombre_original'],
            'mime' => $anexo['mime_type'],
            'tamanio' => $anexo['tamanio_bytes']
        ];
    }
    
    /**
     * Obtiene archivo para workers (con validación de token)
     */
    public function servirParaWorker(int $anexoId): array {
        return $this->descargar($anexoId);
    }
    
    /**
     * Elimina un archivo
     */
    public function eliminar(int $anexoId, int $userId): bool {
        $anexo = $this->anexoModel->findById($anexoId);
        
        if (!$anexo) {
            throw new InvalidArgumentException('Archivo no encontrado');
        }
        
        // Verificar estado del proceso
        $proceso = $this->procesoModel->findById($anexo['proceso_id']);
        $estadosPermitidos = ['creado', 'analizado', 'error_analisis'];
        
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException(
                'No se pueden eliminar archivos en el estado actual'
            );
        }
        
        // Eliminar
        $resultado = $this->anexoModel->deleteFile($anexoId);
        
        if ($resultado) {
            $this->historialModel->registrar(
                $anexo['proceso_id'],
                $userId,
                'archivo_eliminado',
                null,
                null,
                "Archivo eliminado: {$anexo['nombre_original']}"
            );
        }
        
        return $resultado;
    }
    
    /**
     * Lista archivos de un proceso
     */
    public function listarPorProceso(int $procesoId): array {
        $proceso = $this->procesoModel->findById($procesoId);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        return $this->anexoModel->getByProcesoId($procesoId);
    }
    
    /**
     * Reordena archivos
     */
    public function reordenar(int $procesoId, array $orden, int $userId): bool {
        return $this->anexoModel->reorder($procesoId, $orden);
    }
    
    /**
     * Obtiene estadísticas de almacenamiento
     */
    public function getEstadisticas(?int $procesoId = null): array {
        return $this->anexoModel->getStorageStats($procesoId);
    }
    
    /**
     * Normaliza la estructura de $_FILES para múltiples archivos
     */
    private function normalizarFiles(array $files): array {
        $resultado = [];
        
        // Si es un solo archivo
        if (isset($files['name']) && !is_array($files['name'])) {
            return [$files];
        }
        
        // Si son múltiples archivos
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $index => $name) {
                if ($files['error'][$index] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                
                $resultado[] = [
                    'name' => $name,
                    'type' => $files['type'][$index],
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index]
                ];
            }
        }
        
        // Si es un array de archivos individuales
        if (!isset($files['name'])) {
            foreach ($files as $file) {
                if (isset($file['name']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                    $resultado[] = $file;
                }
            }
        }
        
        return $resultado;
    }
    
    /**
     * Detecta el tipo de archivo según nombre o posición
     */
    private function detectarTipo(string $nombre, string $tipoDefault, int $index): string {
        $nombreLower = strtolower($nombre);
        
        // Detectar por nombre
        if (strpos($nombreLower, 'pagare') !== false || strpos($nombreLower, 'pagaré') !== false) {
            return 'pagare_original';
        }
        
        if (strpos($nombreLower, 'estado') !== false || strpos($nombreLower, 'cuenta') !== false) {
            return 'estado_cuenta';
        }
        
        if (strpos($nombreLower, 'vinculacion') !== false || strpos($nombreLower, 'solicitud') !== false) {
            if (strpos($nombreLower, 'codeudor') !== false) {
                return 'solicitud_codeudor';
            }
            return 'solicitud_deudor';
        }
        
        return $tipoDefault;
    }
    
    /**
     * Genera URL temporal para descarga
     */
    public function generarUrlTemporal(int $anexoId, int $expiracionMinutos = 60): string {
        $token = bin2hex(random_bytes(32));
        $expira = time() + ($expiracionMinutos * 60);
        
        // Guardar token en caché/BD
        // Por ahora, codificar en el token mismo (simplificado)
        $data = base64_encode(json_encode([
            'anexo_id' => $anexoId,
            'expira' => $expira,
            'hash' => hash('sha256', $anexoId . $expira . ($GLOBALS['_ENV']['API_TOKEN_SECRET'] ?? 'secret'))
        ]));
        
        return BASE_URL . "/api/v1/archivos/temporal/{$data}";
    }
    
    /**
     * Valida y descarga archivo temporal
     */
    public function descargarTemporal(string $token): array {
        $data = json_decode(base64_decode($token), true);
        
        if (!$data || !isset($data['anexo_id']) || !isset($data['expira']) || !isset($data['hash'])) {
            throw new InvalidArgumentException('Token inválido');
        }
        
        // Verificar expiración
        if (time() > $data['expira']) {
            throw new InvalidArgumentException('El enlace ha expirado');
        }
        
        // Verificar hash
        $expectedHash = hash('sha256', $data['anexo_id'] . $data['expira'] . ($GLOBALS['_ENV']['API_TOKEN_SECRET'] ?? 'secret'));
        if (!hash_equals($expectedHash, $data['hash'])) {
            throw new InvalidArgumentException('Token inválido');
        }
        
        return $this->descargar($data['anexo_id']);
    }
}

