<?php
/**
 * QueueManager - Gestor de colas Redis
 * ByBot v2.0
 */

require_once dirname(dirname(__DIR__)) . '/config/constants.php';

class QueueManager {
    private static $instance = null;
    private $redis = null;
    private $connected = false;
    
    private function __construct() {
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Conectar a Redis
     */
    private function connect() {
        if (!extension_loaded('redis')) {
            error_log("QueueManager: Extensión Redis no está cargada");
            return false;
        }
        
        try {
            $this->redis = new Redis();
            $this->connected = $this->redis->connect(
                REDIS_HOST,
                REDIS_PORT,
                5 // timeout
            );
            
            if (REDIS_PASSWORD) {
                $this->redis->auth(REDIS_PASSWORD);
            }
            
            if (REDIS_DB > 0) {
                $this->redis->select(REDIS_DB);
            }
            
            return $this->connected;
        } catch (Exception $e) {
            error_log("QueueManager: Error conectando a Redis - " . $e->getMessage());
            $this->connected = false;
            return false;
        }
    }
    
    /**
     * Verificar si está conectado
     */
    public function isConnected() {
        return $this->connected && $this->redis !== null;
    }
    
    /**
     * Publicar trabajo en cola
     */
    public function publish($queue, array $data) {
        if (!$this->isConnected()) {
            throw new Exception("No hay conexión a Redis");
        }
        
        // Generar ID único para el trabajo
        $jobId = $this->generateJobId();
        
        $job = [
            'id' => $jobId,
            'queue' => $queue,
            'payload' => $data,
            'created_at' => date('Y-m-d H:i:s'),
            'attempts' => 0
        ];
        
        // Agregar a la cola (usando RPUSH para FIFO)
        $this->redis->rPush($queue, json_encode($job));
        
        // Registrar en BD para auditoría
        $this->registrarTrabajo($jobId, $queue, $data);
        
        return $jobId;
    }
    
    /**
     * Encolar trabajo de análisis
     */
    public function encolarAnalisis($procesoId, $prioridad = 5) {
        return $this->publish(Cola::ANALYZE, [
            'proceso_id' => $procesoId,
            'tipo' => 'analizar_documentos',
            'prioridad' => $prioridad
        ]);
    }
    
    /**
     * Encolar trabajo de llenado de pagaré
     */
    public function encolarLlenado($procesoId, $prioridad = 5) {
        return $this->publish(Cola::FILL, [
            'proceso_id' => $procesoId,
            'tipo' => 'llenar_pagare',
            'prioridad' => $prioridad
        ]);
    }
    
    /**
     * Encolar notificación
     */
    public function encolarNotificacion($tipo, $datos) {
        return $this->publish(Cola::NOTIFY, [
            'tipo' => $tipo,
            'datos' => $datos
        ]);
    }
    
    /**
     * Obtener longitud de una cola
     */
    public function getQueueLength($queue) {
        if (!$this->isConnected()) {
            return 0;
        }
        return $this->redis->lLen($queue);
    }
    
    /**
     * Obtener estado de todas las colas
     */
    public function getStatus() {
        $queues = [Cola::ANALYZE, Cola::FILL, Cola::NOTIFY, Cola::RESULTS];
        $status = [];
        
        foreach ($queues as $queue) {
            $status[$queue] = [
                'length' => $this->getQueueLength($queue),
                'name' => $this->getQueueName($queue)
            ];
        }
        
        return [
            'connected' => $this->isConnected(),
            'queues' => $status
        ];
    }
    
    /**
     * Obtener nombre amigable de cola
     */
    private function getQueueName($queue) {
        $names = [
            Cola::ANALYZE => 'Análisis IA',
            Cola::FILL => 'Llenado Pagaré',
            Cola::NOTIFY => 'Notificaciones',
            Cola::RESULTS => 'Resultados'
        ];
        return $names[$queue] ?? $queue;
    }
    
    /**
     * Generar ID único para trabajo
     */
    private function generateJobId() {
        return 'job_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * Registrar trabajo en BD
     */
    private function registrarTrabajo($jobId, $cola, $payload) {
        try {
            $db = Database::getInstance();
            
            $procesoId = $payload['proceso_id'] ?? null;
            $tipoTrabajo = $payload['tipo'] ?? 'desconocido';
            $prioridad = $payload['prioridad'] ?? 5;
            
            $sql = "INSERT INTO colas_trabajos 
                    (job_id, cola, proceso_id, tipo_trabajo, estado, payload, prioridad) 
                    VALUES (?, ?, ?, ?, 'pendiente', ?, ?)";
            
            $db->execute($sql, [
                $jobId,
                $cola,
                $procesoId,
                $tipoTrabajo,
                json_encode($payload),
                $prioridad
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("QueueManager: Error registrando trabajo - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar estado de trabajo
     */
    public function actualizarTrabajo($jobId, $estado, $resultado = null, $error = null) {
        try {
            $db = Database::getInstance();
            
            $updates = ['estado' => $estado];
            $params = [$estado];
            
            if ($estado === 'procesando') {
                $sql = "UPDATE colas_trabajos SET estado = ?, fecha_inicio = NOW() WHERE job_id = ?";
            } elseif (in_array($estado, ['completado', 'fallido'])) {
                $sql = "UPDATE colas_trabajos SET estado = ?, fecha_fin = NOW(), 
                        duracion_ms = TIMESTAMPDIFF(MICROSECOND, fecha_inicio, NOW()) / 1000";
                
                if ($resultado !== null) {
                    $sql .= ", resultado = ?";
                    $params[] = json_encode($resultado);
                }
                
                if ($error !== null) {
                    $sql .= ", error_mensaje = ?";
                    $params[] = $error;
                }
                
                $sql .= " WHERE job_id = ?";
            } else {
                $sql = "UPDATE colas_trabajos SET estado = ? WHERE job_id = ?";
            }
            
            $params[] = $jobId;
            $db->execute($sql, $params);
            
            return true;
        } catch (Exception $e) {
            error_log("QueueManager: Error actualizando trabajo - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Incrementar intentos de un trabajo
     */
    public function incrementarIntentos($jobId) {
        try {
            $db = Database::getInstance();
            $sql = "UPDATE colas_trabajos SET intentos = intentos + 1 WHERE job_id = ?";
            $db->execute($sql, [$jobId]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Cerrar conexión
     */
    public function close() {
        if ($this->redis && $this->connected) {
            $this->redis->close();
            $this->connected = false;
        }
    }
    
    public function __destruct() {
        $this->close();
    }
}

