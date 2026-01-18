<?php
/**
 * Servicio de Validación
 * Lógica de negocio para validación de datos extraídos por IA
 */

require_once BASE_DIR . '/web/core/BaseService.php';
require_once BASE_DIR . '/web/modules/procesos/models/Proceso.php';
require_once BASE_DIR . '/web/modules/procesos/models/DatosIA.php';
require_once BASE_DIR . '/web/modules/procesos/models/Historial.php';

class ValidacionService extends BaseService {
    private Proceso $procesoModel;
    private DatosIA $datosIAModel;
    private Historial $historialModel;
    
    public function __construct() {
        $this->procesoModel = new Proceso();
        $this->datosIAModel = new DatosIA();
        $this->historialModel = new Historial();
    }
    
    /**
     * Obtiene datos de IA para validar
     */
    public function obtenerDatosParaValidar(int $procesoId): array {
        $proceso = $this->procesoModel->findById($procesoId);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        $datosIA = $this->datosIAModel->getByProcesoId($procesoId);
        
        if (!$datosIA) {
            throw new InvalidArgumentException('El proceso no tiene datos de IA para validar');
        }
        
        // Obtener campos configurados
        $campos = [
            'estado_cuenta' => DatosIA::CAMPOS_ESTADO_CUENTA,
            'deudor' => DatosIA::CAMPOS_DEUDOR,
            'codeudor' => DatosIA::CAMPOS_DEUDOR
        ];
        
        return [
            'proceso' => $proceso,
            'datos_originales' => $datosIA['datos_originales'],
            'datos_validados' => $datosIA['datos_validados'],
            'metadata' => $datosIA['metadata'],
            'fecha_analisis' => $datosIA['fecha_analisis'],
            'fecha_validacion' => $datosIA['fecha_validacion'],
            'campos_configurados' => $campos,
            'ya_validado' => !empty($datosIA['datos_validados'])
        ];
    }
    
    /**
     * Guarda datos validados (parcial, puede seguir editando)
     */
    public function guardarDatos(int $procesoId, array $datos, int $userId): array {
        $proceso = $this->procesoModel->findById($procesoId);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Verificar estado
        $estadosPermitidos = ['analizado', 'validado'];
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException(
                'El proceso debe estar analizado para guardar datos'
            );
        }
        
        // Validar estructura
        $errores = $this->datosIAModel->validateStructure($datos);
        if (!empty($errores)) {
            throw new InvalidArgumentException(json_encode([
                'message' => 'Errores de validación',
                'errors' => $errores
            ]));
        }
        
        // Guardar
        $this->datosIAModel->saveValidation($procesoId, $datos, $userId);
        
        // Registrar en historial
        $datosIA = $this->datosIAModel->getByProcesoId($procesoId);
        $cambios = $this->datosIAModel->getDiff($procesoId);
        
        $this->historialModel->registrar(
            $procesoId,
            $userId,
            'datos_editados',
            null,
            null,
            'Datos editados por el usuario',
            ['cambios' => count($cambios)]
        );
        
        return $this->obtenerDatosParaValidar($procesoId);
    }
    
    /**
     * Confirma validación y cambia estado del proceso
     */
    public function confirmarValidacion(int $procesoId, array $datos, int $userId): array {
        $proceso = $this->procesoModel->findById($procesoId);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Verificar estado
        if (!in_array($proceso['estado'], ['analizado', 'validado'])) {
            throw new InvalidArgumentException(
                'El proceso debe estar analizado para confirmar validación'
            );
        }
        
        // Validar datos mínimos requeridos
        $erroresRequeridos = $this->validarCamposRequeridos($datos);
        if (!empty($erroresRequeridos)) {
            throw new InvalidArgumentException(json_encode([
                'message' => 'Faltan campos requeridos',
                'errors' => $erroresRequeridos
            ]));
        }
        
        // Validar estructura
        $errores = $this->datosIAModel->validateStructure($datos);
        if (!empty($errores)) {
            throw new InvalidArgumentException(json_encode([
                'message' => 'Errores de validación',
                'errors' => $errores
            ]));
        }
        
        // Guardar datos validados
        $this->datosIAModel->saveValidation($procesoId, $datos, $userId);
        
        // Cambiar estado del proceso
        $this->procesoModel->updateStatus($procesoId, 'validado', $userId, 'Datos validados por usuario');
        
        // Registrar en historial
        $cambios = $this->datosIAModel->getDiff($procesoId);
        $this->historialModel->registrarValidacion($procesoId, $userId, $cambios);
        
        return [
            'proceso' => $this->procesoModel->getWithDetails($procesoId),
            'mensaje' => 'Datos validados correctamente',
            'cambios_realizados' => count($cambios)
        ];
    }
    
    /**
     * Actualiza un campo específico
     */
    public function actualizarCampo(
        int $procesoId, 
        string $campo, 
        $valor, 
        int $userId
    ): array {
        $proceso = $this->procesoModel->findById($procesoId);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        $this->datosIAModel->updateField($procesoId, $campo, $valor, $userId);
        
        return $this->obtenerDatosParaValidar($procesoId);
    }
    
    /**
     * Obtiene diferencias entre datos originales y validados
     */
    public function obtenerDiferencias(int $procesoId): array {
        return $this->datosIAModel->getDiff($procesoId);
    }
    
    /**
     * Valida campos mínimos requeridos
     */
    private function validarCamposRequeridos(array $datos): array {
        $errores = [];
        
        // Campos requeridos de estado de cuenta
        $requeridosEstadoCuenta = ['capital', 'fecha_vencimiento'];
        foreach ($requeridosEstadoCuenta as $campo) {
            if (empty($datos['estado_cuenta'][$campo])) {
                $errores["estado_cuenta.{$campo}"] = 'Campo requerido';
            }
        }
        
        // Campos requeridos de deudor
        $requeridosDeudor = ['nombre', 'cedula'];
        foreach ($requeridosDeudor as $campo) {
            if (empty($datos['deudor'][$campo])) {
                $errores["deudor.{$campo}"] = 'Campo requerido';
            }
        }
        
        return $errores;
    }
    
    /**
     * Re-analiza un proceso (vuelve a encolar)
     */
    public function reanalizar(int $procesoId, int $userId, ?string $motivo = null): array {
        $proceso = $this->procesoModel->findById($procesoId);
        
        if (!$proceso) {
            throw new InvalidArgumentException('Proceso no encontrado');
        }
        
        // Verificar estado
        $estadosPermitidos = ['analizado', 'validado', 'error_analisis'];
        if (!in_array($proceso['estado'], $estadosPermitidos)) {
            throw new InvalidArgumentException(
                'El proceso no puede ser re-analizado en el estado actual'
            );
        }
        
        // Incrementar intentos
        $this->procesoModel->update($procesoId, [
            'intentos_analisis' => $proceso['intentos_analisis'] + 1
        ]);
        
        // Cambiar estado
        $mensaje = $motivo ?? 'Re-análisis solicitado por usuario';
        $this->procesoModel->updateStatus($procesoId, 'en_cola_analisis', $userId, $mensaje);
        
        // Registrar
        $this->historialModel->registrar(
            $procesoId,
            $userId,
            'reencolado',
            $proceso['estado'],
            'en_cola_analisis',
            $mensaje
        );
        
        // Encolar
        try {
            require_once BASE_DIR . '/web/core/QueueManager.php';
            $queue = new QueueManager();
            $queue->push('bybot:analyze', [
                'proceso_id' => $procesoId,
                'codigo' => $proceso['codigo'],
                'prioridad' => $proceso['prioridad'],
                'reanalisis' => true,
                'timestamp' => time()
            ]);
        } catch (Exception $e) {
            error_log("Error encolando proceso: " . $e->getMessage());
        }
        
        return $this->procesoModel->getWithDetails($procesoId);
    }
    
    /**
     * Obtiene estadísticas de validación
     */
    public function getEstadisticas(): array {
        $db = getConnection();
        
        // Procesos pendientes de validación
        $stmt = $db->query("SELECT COUNT(*) FROM procesos WHERE estado = 'analizado'");
        $pendientes = (int) $stmt->fetchColumn();
        
        // Procesos validados hoy
        $stmt = $db->query("
            SELECT COUNT(*) FROM procesos 
            WHERE estado IN ('validado', 'en_cola_llenado', 'llenando', 'completado')
            AND DATE(fecha_validacion) = CURDATE()
        ");
        $validadosHoy = (int) $stmt->fetchColumn();
        
        // Promedio de cambios por validación
        $stmt = $db->query("
            SELECT AVG(
                JSON_LENGTH(JSON_KEYS(datos_validados)) - JSON_LENGTH(JSON_KEYS(datos_originales))
            ) as promedio
            FROM procesos_datos_ia
            WHERE datos_validados IS NOT NULL
        ");
        $promedioCambios = $stmt->fetchColumn();
        
        // Estadísticas de tokens
        $tokensStats = $this->datosIAModel->getTokenStats();
        
        return [
            'pendientes_validacion' => $pendientes,
            'validados_hoy' => $validadosHoy,
            'promedio_cambios' => round($promedioCambios ?? 0, 1),
            'tokens' => $tokensStats
        ];
    }
}

