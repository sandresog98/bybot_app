<?php
/**
 * Modelo de Usuario - ByBot App
 */

require_once __DIR__ . '/../../config/database.php';

class User {
    private $conn;
    
    public function __construct() {
        $this->conn = getConnection();
    }
    
    /**
     * Verificar credenciales de usuario
     */
    public function verifyCredentials($username, $password) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, usuario, password, nombre_completo, email, rol, estado_activo
                FROM control_usuarios
                WHERE usuario = ? AND estado_activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                unset($user['password']);
                return $user;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log('User::verifyCredentials error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener usuario por ID
     */
    public function getById($userId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id, usuario, nombre_completo, email, rol, estado_activo, 
                       fecha_creacion, fecha_actualizacion
                FROM control_usuarios
                WHERE id = ? AND estado_activo = TRUE
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('User::getById error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener todos los usuarios activos
     */
    public function getAll() {
        try {
            $stmt = $this->conn->query("
                SELECT id, usuario, nombre_completo, email, rol, estado_activo, 
                       fecha_creacion, fecha_actualizacion
                FROM control_usuarios
                WHERE estado_activo = TRUE
                ORDER BY nombre_completo ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('User::getAll error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Crear nuevo usuario
     */
    public function create($data) {
        try {
            $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->conn->prepare("
                INSERT INTO control_usuarios (usuario, password, nombre_completo, email, rol, estado_activo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['usuario'],
                $passwordHash,
                $data['nombre_completo'],
                $data['email'] ?? null,
                $data['rol'] ?? 'operador',
                $data['estado_activo'] ?? true
            ]);
            
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log('User::create error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar usuario
     */
    public function update($userId, $data) {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['nombre_completo'])) {
                $fields[] = "nombre_completo = ?";
                $params[] = $data['nombre_completo'];
            }
            if (isset($data['email'])) {
                $fields[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['rol'])) {
                $fields[] = "rol = ?";
                $params[] = $data['rol'];
            }
            if (isset($data['estado_activo'])) {
                $fields[] = "estado_activo = ?";
                $params[] = $data['estado_activo'] ? 1 : 0;
            }
            if (isset($data['password']) && !empty($data['password'])) {
                $fields[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($fields)) {
                return false;
            }
            
            $params[] = $userId;
            $sql = "UPDATE control_usuarios SET " . implode(", ", $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log('User::update error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Eliminar usuario (soft delete)
     */
    public function delete($userId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE control_usuarios 
                SET estado_activo = FALSE 
                WHERE id = ?
            ");
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log('User::delete error: ' . $e->getMessage());
            return false;
        }
    }
}
?>

