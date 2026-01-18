<?php
/**
 * Middleware de Autenticación
 * Valida sesiones de usuario para la API
 */

require_once dirname(__DIR__, 3) . '/config/constants.php';
require_once BASE_DIR . '/config/database.php';
require_once BASE_DIR . '/web/core/Response.php';

class AuthMiddleware {
    private static $currentUser = null;
    
    /**
     * Verifica que el usuario esté autenticado
     * @param bool $required Si es true, devuelve error 401 si no autenticado
     * @return array|null Usuario actual o null
     */
    public static function check(bool $required = true): ?array {
        // Iniciar sesión si no está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar si hay sesión activa
        if (isset($_SESSION['user_id']) && isset($_SESSION['user'])) {
            // Verificar que el usuario siga activo en BD
            $user = self::validateUserSession($_SESSION['user_id']);
            
            if ($user) {
                self::$currentUser = $user;
                return $user;
            }
            
            // Sesión inválida, limpiar
            self::destroySession();
        }
        
        // Si la autenticación es requerida, devolver error
        if ($required) {
            Response::error('No autenticado', [], 401);
        }
        
        return null;
    }
    
    /**
     * Valida que el usuario exista y esté activo
     */
    private static function validateUserSession(int $userId): ?array {
        try {
            $db = getConnection();
            $stmt = $db->prepare("
                SELECT id, usuario, nombre_completo, email, rol, estado_activo, ultimo_acceso
                FROM control_usuarios 
                WHERE id = ? AND estado_activo = 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Actualizar último acceso cada 5 minutos
                if (empty($user['ultimo_acceso']) || 
                    strtotime($user['ultimo_acceso']) < (time() - 300)) {
                    $updateStmt = $db->prepare("UPDATE control_usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                    $updateStmt->execute([$userId]);
                }
                return $user;
            }
        } catch (Exception $e) {
            error_log("Error validando sesión: " . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Inicia sesión para un usuario
     */
    public static function login(string $username, string $password): ?array {
        try {
            $db = getConnection();
            $stmt = $db->prepare("
                SELECT id, usuario, password, nombre_completo, email, rol, estado_activo
                FROM control_usuarios 
                WHERE usuario = ?
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return null;
            }
            
            if (!$user['estado_activo']) {
                return ['error' => 'Usuario inactivo'];
            }
            
            if (!password_verify($password, $user['password'])) {
                return null;
            }
            
            // Iniciar sesión
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'usuario' => $user['usuario'],
                'nombre_completo' => $user['nombre_completo'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ];
            $_SESSION['login_time'] = time();
            $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Actualizar último acceso
            $updateStmt = $db->prepare("UPDATE control_usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Registrar log
            self::logAction('login', 'auth', "Login exitoso para: {$user['usuario']}");
            
            unset($user['password']);
            return $user;
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cierra la sesión actual
     */
    public static function logout(): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $username = $_SESSION['user']['usuario'] ?? 'unknown';
        
        // Registrar log antes de destruir sesión
        self::logAction('logout', 'auth', "Logout para: {$username}");
        
        self::destroySession();
        return true;
    }
    
    /**
     * Destruye la sesión completamente
     */
    private static function destroySession(): void {
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Obtiene el usuario actual
     */
    public static function getCurrentUser(): ?array {
        if (self::$currentUser) {
            return self::$currentUser;
        }
        return self::check(false);
    }
    
    /**
     * Obtiene el ID del usuario actual
     */
    public static function getCurrentUserId(): ?int {
        $user = self::getCurrentUser();
        return $user ? (int)$user['id'] : null;
    }
    
    /**
     * Verifica si el usuario tiene un rol específico
     */
    public static function hasRole(string $role): bool {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        // Admin tiene acceso a todo
        if ($user['rol'] === 'admin') return true;
        
        return $user['rol'] === $role;
    }
    
    /**
     * Verifica si el usuario tiene acceso a un módulo
     */
    public static function hasAccess(string $module): bool {
        $user = self::getCurrentUser();
        if (!$user) return false;
        
        $rol = $user['rol'];
        
        // Cargar roles desde JSON
        $rolesFile = BASE_DIR . '/roles.json';
        if (!file_exists($rolesFile)) {
            return $rol === 'admin';
        }
        
        $roles = json_decode(file_get_contents($rolesFile), true);
        
        if (!isset($roles['roles'][$rol])) {
            return false;
        }
        
        $modulos = $roles['roles'][$rol]['modulos'] ?? [];
        
        // Wildcard para admin
        if (in_array('*', $modulos)) return true;
        
        // Verificar acceso directo o jerárquico
        foreach ($modulos as $m) {
            if ($m === $module) return true;
            // Verificar acceso a submódulos (procesos.ver cuando tiene acceso a procesos)
            if (strpos($module, $m . '.') === 0) return true;
        }
        
        return false;
    }
    
    /**
     * Requiere un rol específico o devuelve 403
     */
    public static function requireRole(string $role): void {
        if (!self::hasRole($role)) {
            Response::error('No tienes permisos para esta acción', [], 403);
        }
    }
    
    /**
     * Requiere acceso a un módulo o devuelve 403
     */
    public static function requireAccess(string $module): void {
        if (!self::hasAccess($module)) {
            Response::error("No tienes acceso al módulo: {$module}", [], 403);
        }
    }
    
    /**
     * Registra una acción en logs
     */
    private static function logAction(string $accion, string $modulo, string $detalle): void {
        try {
            $db = getConnection();
            $userId = $_SESSION['user_id'] ?? null;
            
            $stmt = $db->prepare("
                INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userId,
                $accion,
                $modulo,
                $detalle,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error registrando log: " . $e->getMessage());
        }
    }
}

