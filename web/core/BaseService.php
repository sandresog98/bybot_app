<?php
/**
 * BaseService - Clase base para todos los servicios
 * ByBot v2.0
 */

require_once __DIR__ . '/Response.php';

abstract class BaseService {
    protected $model;
    
    /**
     * Registrar log de acción
     */
    protected function log($accion, $modulo, $detalle = null, $entidadTipo = null, $entidadId = null, $datosAnteriores = null, $datosNuevos = null, $nivel = 'info') {
        try {
            $db = Database::getInstance();
            
            $userId = $_SESSION['user_id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            $sql = "INSERT INTO control_logs 
                    (id_usuario, accion, modulo, entidad_tipo, entidad_id, detalle, 
                     ip_address, user_agent, datos_anteriores, datos_nuevos, nivel) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $db->execute($sql, [
                $userId,
                $accion,
                $modulo,
                $entidadTipo,
                $entidadId,
                $detalle,
                $ip,
                $userAgent,
                $datosAnteriores ? json_encode($datosAnteriores) : null,
                $datosNuevos ? json_encode($datosNuevos) : null,
                $nivel
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error al guardar log: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar historial de proceso
     */
    protected function registrarHistorial($procesoId, $accion, $descripcion = null, $estadoAnterior = null, $estadoNuevo = null, $datosCambio = null) {
        try {
            $db = Database::getInstance();
            
            $userId = $_SESSION['user_id'] ?? null;
            
            $sql = "INSERT INTO procesos_historial 
                    (proceso_id, usuario_id, accion, estado_anterior, estado_nuevo, descripcion, datos_cambio) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $db->execute($sql, [
                $procesoId,
                $userId,
                $accion,
                $estadoAnterior,
                $estadoNuevo,
                $descripcion,
                $datosCambio ? json_encode($datosCambio) : null
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error al guardar historial: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validar datos requeridos
     */
    protected function validarRequeridos(array $data, array $campos) {
        $errores = [];
        
        foreach ($campos as $campo) {
            if (!isset($data[$campo]) || $data[$campo] === '' || $data[$campo] === null) {
                $errores[$campo] = "El campo '$campo' es requerido";
            }
        }
        
        return $errores;
    }
    
    /**
     * Sanitizar datos de entrada
     */
    protected function sanitizar(array $data, array $reglas = []) {
        $sanitizado = [];
        
        foreach ($data as $campo => $valor) {
            if (isset($reglas[$campo])) {
                switch ($reglas[$campo]) {
                    case 'string':
                        $sanitizado[$campo] = trim(htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'));
                        break;
                    case 'int':
                        $sanitizado[$campo] = (int)$valor;
                        break;
                    case 'float':
                        $sanitizado[$campo] = (float)$valor;
                        break;
                    case 'bool':
                        $sanitizado[$campo] = (bool)$valor;
                        break;
                    case 'email':
                        $sanitizado[$campo] = filter_var($valor, FILTER_SANITIZE_EMAIL);
                        break;
                    case 'json':
                        $sanitizado[$campo] = is_array($valor) ? json_encode($valor) : $valor;
                        break;
                    default:
                        $sanitizado[$campo] = $valor;
                }
            } else {
                // Por defecto, sanitizar como string
                if (is_string($valor)) {
                    $sanitizado[$campo] = trim(htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'));
                } else {
                    $sanitizado[$campo] = $valor;
                }
            }
        }
        
        return $sanitizado;
    }
    
    /**
     * Generar código único
     */
    protected function generarCodigo($prefix = 'PROC', $tabla = 'procesos') {
        $db = Database::getInstance();
        
        $year = date('Y');
        $month = date('m');
        
        // Contar registros del mes
        $sql = "SELECT COUNT(*) as total FROM $tabla 
                WHERE YEAR(fecha_creacion) = ? AND MONTH(fecha_creacion) = ?";
        $result = $db->queryOne($sql, [$year, $month]);
        $numero = ($result['total'] ?? 0) + 1;
        
        return $prefix . $year . $month . str_pad($numero, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Obtener configuración del sistema
     */
    protected function getConfig($clave, $default = null) {
        try {
            $db = Database::getInstance();
            $sql = "SELECT valor, tipo FROM configuracion WHERE clave = ?";
            $config = $db->queryOne($sql, [$clave]);
            
            if (!$config) {
                return $default;
            }
            
            $valor = $config['valor'];
            
            // Convertir según tipo
            switch ($config['tipo']) {
                case 'int':
                    return (int)$valor;
                case 'float':
                    return (float)$valor;
                case 'bool':
                    return in_array(strtolower($valor), ['true', '1', 'yes']);
                case 'json':
                    return json_decode($valor, true);
                default:
                    return $valor;
            }
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Guardar configuración del sistema
     */
    protected function setConfig($clave, $valor, $tipo = 'string') {
        try {
            $db = Database::getInstance();
            
            if (is_array($valor) || is_object($valor)) {
                $valor = json_encode($valor);
                $tipo = 'json';
            } elseif (is_bool($valor)) {
                $valor = $valor ? 'true' : 'false';
                $tipo = 'bool';
            }
            
            $sql = "INSERT INTO configuracion (clave, valor, tipo) 
                    VALUES (?, ?, ?) 
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo)";
            
            $db->execute($sql, [$clave, $valor, $tipo]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Respuesta de éxito
     */
    protected function success($data = null, $message = 'Operación exitosa') {
        return Response::success($data, $message);
    }
    
    /**
     * Respuesta de error
     */
    protected function error($message, $code = 400, $errors = []) {
        return Response::error($message, $code, $errors);
    }
}

