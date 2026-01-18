<?php
/**
 * Servicio de Procesos
 * Lógica de negocio para gestión de procesos
 */

require_once BASE_DIR . '/web/core/BaseService.php';
require_once BASE_DIR . '/web/core/N8nClient.php';
require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
require_once BASE_DIR . '/web/modules/procesos/models/Anexo.php';
require_once BASE_DIR . '/web/modules/procesos/models/DatosIA.php';
require_once BASE_DIR . '/web/modules/procesos/models/Historial.php';

class ProcesoService extends BaseService {
    private Proceso $procesoModel;
    private Anexo $anexoModel;
    private DatosIA $datosIAModel;
    private Historial $historialModel;
    private N8nClient $n8nClient;
    
    public function __construct() {
        $this->procesoModel = new Proceso();
        $this->anexoModel = new Anexo();
        $this->datosIAModel = new DatosIA();
        $this->historialModel = new Historial();
        $this->n8nClient = new N8nClient();
    }
    
    /**
     * Lista procesos con filtros y paginación
     */
    public function listar(array $filters = [], int $page = 1, int $perPage = 10): array {
        return $this->procesoModel->search($filters, $page, $perPage);
    }
    
    /**
     * Obtiene un proceso por ID con todos sus datos
     */
    public function obtener(int $id): ?array {
        return $this->procesoModel->getWithDetails($id);
    }
    
    /**
     * Obtiene un proceso por código
     */
    public function obtenerPorCodigo(string $codigo): ?array {
        $proceso = $this->procesoModel->findByCodigo($codigo);
        if ($proceso) {
            return $this->procesoModel->getWithDetails($proceso['id']);
        }
        return null;
    }
    
    /**
     * Crea un nuevo proceso
     */
    public function crear(array $data, int $userId): array {
        // Validar datos
        $errors = $this->validarCreacion($data);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors));
        }
        
        // Crear proceso
        $id = $this->procesoModel->createProcess($data, $userId);
        
        if (!$id) {
            throw new RuntimeException('Error al crear el proceso');
        }
        
        // Registrar log
        $this->logAction('crear', 'procesos', "Proceso creado", [
            'proceso_id' => $id,
            'datos' => $data
        ], $userId);
        
        return $this->obtener($id);
    }
    
    /**
     * Actualiza un proceso
     */
    public function actualizar(int $id, array $data, int $userId): array {
        $proceso = $this->procesoModel->findById($id);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Validar datos
        $errors = $this->validarActualizacion($data, $proceso);
        if (!empty($errors)) {
            throw new InvalidArgumentException(json_encode($errors));
        }
        
        // Filtrar campos permitidos
        $permitidos = ['tipo', 'prioridad', 'asignado_a', 'notas'];
        $updates = array_intersect_key($data, array_flip($permitidos));
        
        if (empty($updates)) {
            return $this->obtener($id);
        }
        
        $this->procesoModel->update($id, $updates);
        
        // Registrar en historial
        $this->historialModel->registrar(
            $id,
            $userId,
            'datos_editados',
            null,
            null,
            'Proceso actualizado',
            ['cambios' => $updates]
        );
        
        return $this->obtener($id);
    }
    
    /**
     * Elimina un proceso
     */
    public function eliminar(int $id, int $userId): bool {
        $proceso = $this->procesoModel->findById($id);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Solo permitir eliminar procesos en estado inicial o cancelado
        $estadosPermitidos = ['creado', 'cancelado', 'error_analisis', 'error_llenado'];
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException(
                'No se puede eliminar un proceso en estado: ' . $proceso['estado']
            );
        }
        
        // Eliminar archivos asociados
        $anexos = $this->anexoModel->getByProcesoId($id);
        foreach ($anexos as $anexo) {
            $this->anexoModel->deleteFile($anexo['id']);
        }
        
        // Registrar log antes de eliminar
        $this->logAction('eliminar', 'procesos', "Proceso eliminado: {$proceso['codigo']}", [
            'proceso_id' => $id,
            'codigo' => $proceso['codigo']
        ], $userId);
        
        return $this->procesoModel->delete($id);
    }
    
    /**
     * Cambia el estado de un proceso
     */
    public function cambiarEstado(int $id, string $nuevoEstado, int $userId, ?string $mensaje = null): array {
        $proceso = $this->procesoModel->findById($id);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Validar transición de estado
        if (!$this->esTransicionValida($proceso['estado'], $nuevoEstado)) {
            throw new InvalidArgumentException(
                "Transición de estado no permitida: {$proceso['estado']} → {$nuevoEstado}"
            );
        }
        
        $this->procesoModel->updateStatus($id, $nuevoEstado, $userId, $mensaje);
        
        return $this->obtener($id);
    }
    
    /**
     * Cancela un proceso
     */
    public function cancelar(int $id, int $userId, ?string $motivo = null): array {
        $proceso = $this->procesoModel->findById($id);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // No cancelar procesos ya completados
        if ($proceso['estado'] === 'completado') {
            throw new InvalidArgumentException('No se puede cancelar un proceso completado');
        }
        
        $mensaje = $motivo ?? 'Proceso cancelado por el usuario';
        $this->procesoModel->updateStatus($id, 'cancelado', $userId, $mensaje);
        
        return $this->obtener($id);
    }
    
    /**
     * Encola un proceso para análisis usando n8n
     */
    public function encolarAnalisis(int $id, int $userId): array {
        $proceso = $this->procesoModel->findById($id);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Verificar que tenga archivos
        $anexos = $this->anexoModel->getByProcesoId($id);
        if (empty($anexos)) {
            throw new InvalidArgumentException('El proceso no tiene archivos para analizar');
        }
        
        // Verificar estado válido para encolar
        $estadosPermitidos = ['creado', 'error_analisis'];
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException(
                'El proceso no está en un estado válido para análisis'
            );
        }
        
        // Cambiar estado
        $this->procesoModel->updateStatus($id, 'en_cola_analisis', $userId, 'Encolado para análisis');
        
        // Disparar flujo en n8n
        try {
            // Preparar archivos con tokens de acceso
            $archivosParaN8n = $this->n8nClient->prepareFilesForN8n($id, $anexos);
            
            // Disparar webhook de análisis
            $result = $this->n8nClient->triggerAnalysis($id, $archivosParaN8n, [
                'prioridad' => $proceso['prioridad'],
                'reintentar' => $proceso['intentos_analisis'] > 0
            ]);
            
            if (!$result['success']) {
                // Si falla n8n, registrar error pero mantener en cola
                $this->historialModel->registrar(
                    $id,
                    $userId,
                    'error',
                    'en_cola_analisis',
                    'en_cola_analisis',
                    'Error al contactar n8n: ' . ($result['message'] ?? 'Error desconocido'),
                    ['n8n_error' => $result]
                );
                error_log("Error disparando análisis n8n: " . json_encode($result));
            } else {
                // Registrar execution_id de n8n
                $this->procesoModel->update($id, [
                    'job_id_analisis' => $result['execution_id'] ?? null
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error encolando proceso para análisis: " . $e->getMessage());
            // Continuar - el estado ya cambió, n8n puede reintentar
        }
        
        return $this->obtener($id);
    }
    
    /**
     * Encola un proceso para llenado de pagaré usando n8n
     */
    public function encolarLlenado(int $id, int $userId): array {
        $proceso = $this->procesoModel->findById($id);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Verificar que esté validado
        $estadosPermitidos = ['validado', 'error_llenado'];
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException('El proceso debe estar validado antes de encolar para llenado');
        }
        
        // Verificar que tenga datos validados
        $datosIA = $this->datosIAModel->getByProcesoId($id);
        if (!$datosIA || !$datosIA['datos_validados']) {
            throw new InvalidArgumentException('El proceso no tiene datos validados');
        }
        
        // Obtener pagaré original
        $anexos = $this->anexoModel->getByProcesoId($id);
        $pagareOriginal = null;
        foreach ($anexos as $anexo) {
            if ($anexo['tipo'] === 'pagare_original') {
                $pagareOriginal = $anexo;
                break;
            }
        }
        
        if (!$pagareOriginal) {
            throw new InvalidArgumentException('No se encontró el pagaré original');
        }
        
        // Cambiar estado
        $this->procesoModel->updateStatus($id, 'en_cola_llenado', $userId, 'Encolado para llenado de pagaré');
        
        // Disparar flujo en n8n
        try {
            // Preparar URL del pagaré
            $archivosParaN8n = $this->n8nClient->prepareFilesForN8n($id, [$pagareOriginal]);
            $pagareUrl = $archivosParaN8n[0]['url'] ?? '';
            
            // Datos validados para llenar
            $datosValidados = json_decode($datosIA['datos_validados'], true);
            
            // Disparar webhook de llenado
            $result = $this->n8nClient->triggerFilling($id, $datosValidados, $pagareUrl, [
                'plantilla' => 'default'
            ]);
            
            if (!$result['success']) {
                $this->historialModel->registrar(
                    $id,
                    $userId,
                    'error',
                    'en_cola_llenado',
                    'en_cola_llenado',
                    'Error al contactar n8n: ' . ($result['message'] ?? 'Error desconocido'),
                    ['n8n_error' => $result]
                );
                error_log("Error disparando llenado n8n: " . json_encode($result));
            } else {
                $this->procesoModel->update($id, [
                    'job_id_llenado' => $result['execution_id'] ?? null
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Error encolando proceso para llenado: " . $e->getMessage());
        }
        
        return $this->obtener($id);
    }
    
    /**
     * Verifica estado de salud de n8n
     */
    public function verificarN8n(): array {
        $disponible = $this->n8nClient->healthCheck();
        return [
            'n8n_disponible' => $disponible,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Obtiene estadísticas
     */
    public function getEstadisticas(?int $userId = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array {
        return $this->procesoModel->getStats($userId, $fechaDesde, $fechaHasta);
    }
    
    /**
     * Obtiene el historial de un proceso
     */
    public function getHistorial(int $id, int $limit = 50): array {
        return $this->historialModel->getTimeline($id);
    }
    
    /**
     * Valida datos de creación
     */
    private function validarCreacion(array $data): array {
        $errors = [];
        
        if (!empty($data['tipo']) && !isset(Proceso::TIPOS[$data['tipo']])) {
            $errors['tipo'] = 'Tipo de proceso no válido';
        }
        
        if (!empty($data['prioridad']) && ($data['prioridad'] < 1 || $data['prioridad'] > 10)) {
            $errors['prioridad'] = 'La prioridad debe estar entre 1 y 10';
        }
        
        return $errors;
    }
    
    /**
     * Valida datos de actualización
     */
    private function validarActualizacion(array $data, array $proceso): array {
        $errors = $this->validarCreacion($data);
        
        // Validaciones adicionales según estado
        if ($proceso['estado'] === 'completado') {
            $errors['general'] = 'No se puede modificar un proceso completado';
        }
        
        return $errors;
    }
    
    /**
     * Verifica si una transición de estado es válida
     */
    private function esTransicionValida(string $estadoActual, string $nuevoEstado): bool {
        $transiciones = [
            'creado' => ['en_cola_analisis', 'cancelado'],
            'en_cola_analisis' => ['analizando', 'error_analisis', 'cancelado'],
            'analizando' => ['analizado', 'error_analisis', 'cancelado'],
            'analizado' => ['validado', 'en_cola_analisis', 'cancelado'], // Puede re-analizar
            'validado' => ['en_cola_llenado', 'analizado', 'cancelado'], // Puede volver a analizado
            'en_cola_llenado' => ['llenando', 'error_llenado', 'cancelado'],
            'llenando' => ['completado', 'error_llenado', 'cancelado'],
            'completado' => [], // Estado final
            'error_analisis' => ['en_cola_analisis', 'cancelado'],
            'error_llenado' => ['en_cola_llenado', 'validado', 'cancelado'],
            'cancelado' => ['creado'] // Puede reactivarse
        ];
        
        $permitidos = $transiciones[$estadoActual] ?? [];
        return in_array($nuevoEstado, $permitidos);
    }
    
    /**
     * Registra acción en logs
     */
    private function logAction(string $accion, string $modulo, string $detalle, array $datos = [], ?int $userId = null): void {
        try {
            $db = getConnection();
            $stmt = $db->prepare("
                INSERT INTO control_logs (id_usuario, accion, modulo, detalle, datos_nuevos, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $accion,
                $modulo,
                $detalle,
                json_encode($datos),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error registrando log: " . $e->getMessage());
        }
    }
}

