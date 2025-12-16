<?php
/**
 * Logger - ByBot App
 * Registra login, crear, editar, eliminar
 */

require_once __DIR__ . '/../../config/database.php';

class Logger {
    private $conn;

    public function __construct() {
        $this->conn = getConnection();
    }

    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function log($accion, $modulo, $detalle = '', $nivel = 'info', $datos_anteriores = null, $datos_nuevos = null) {
        try {
            if (session_status() === PHP_SESSION_NONE) { session_start(); }
            $id_usuario = $_SESSION['user_id'] ?? null;
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $sql = "INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address, user_agent, datos_anteriores, datos_nuevos, nivel)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $id_usuario,
                $accion,
                $modulo,
                $detalle,
                $ip_address,
                $user_agent,
                $datos_anteriores ? json_encode($datos_anteriores, JSON_UNESCAPED_UNICODE) : null,
                $datos_nuevos ? json_encode($datos_nuevos, JSON_UNESCAPED_UNICODE) : null,
                $nivel
            ]);
            return true;
        } catch (Exception $e) {
            error_log('Logger error: ' . $e->getMessage());
            return false;
        }
    }

    public function logLogin($usuario, $exitoso = true) {
        $detalle = $exitoso ? "Login exitoso: $usuario" : "Login fallido: $usuario";
        $nivel = $exitoso ? 'info' : 'warning';
        return $this->log('login', 'auth', $detalle, $nivel);
    }

    public function logCrear($modulo, $detalle, $datos_nuevos = null) {
        return $this->log('crear', $modulo, $detalle, 'info', null, $datos_nuevos);
    }

    public function logEditar($modulo, $detalle, $datos_anteriores = null, $datos_nuevos = null) {
        return $this->log('editar', $modulo, $detalle, 'info', $datos_anteriores, $datos_nuevos);
    }

    public function logEliminar($modulo, $detalle, $datos_anteriores = null) {
        return $this->log('eliminar', $modulo, $detalle, 'warning', $datos_anteriores, null);
    }

    public function getLogs($filtros = []) {
        $where = [];
        $params = [];

        if (!empty($filtros['usuario'])) { $where[] = "l.id_usuario = ?"; $params[] = $filtros['usuario']; }
        if (!empty($filtros['modulo'])) { $where[] = "l.modulo = ?"; $params[] = $filtros['modulo']; }
        if (!empty($filtros['accion'])) { $where[] = "l.accion = ?"; $params[] = $filtros['accion']; }
        if (!empty($filtros['fecha_desde'])) { $where[] = "DATE(l.timestamp) >= ?"; $params[] = $filtros['fecha_desde']; }
        if (!empty($filtros['fecha_hasta'])) { $where[] = "DATE(l.timestamp) <= ?"; $params[] = $filtros['fecha_hasta']; }

        $whereClause = !empty($where) ? ("WHERE " . implode(" AND ", $where)) : "";
        $limit = (int)($filtros['limite'] ?? 50);
        $page = max(1, (int)($filtros['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $sql = "SELECT l.*, u.usuario, u.nombre_completo 
                FROM control_logs l 
                LEFT JOIN control_usuarios u ON l.id_usuario = u.id 
                $whereClause 
                ORDER BY l.timestamp DESC 
                LIMIT $limit OFFSET $offset";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

