<?php
/**
 * Middleware de API Token
 * Autenticación basada en tokens para workers y servicios externos
 */

require_once dirname(__DIR__, 3) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';

class ApiTokenMiddleware {
    private const TOKEN_HEADER = 'X-API-Token';
    private const BEARER_PREFIX = 'Bearer ';
    
    /**
     * Verifica el token de API
     * @param bool $required Si es true, devuelve error 401 si no hay token válido
     * @return bool True si el token es válido
     */
    public static function check(bool $required = true): bool {
        $token = self::extractToken();
        
        if (!$token) {
            if ($required) {
                Response::error('Token de API no proporcionado', [], 401);
            }
            return false;
        }
        
        // Validar token
        if (!self::validateToken($token)) {
            if ($required) {
                Response::error('Token de API inválido', [], 401);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Extrae el token del request
     */
    private static function extractToken(): ?string {
        // 1. Buscar en header personalizado
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? null;
        if ($token) {
            return $token;
        }
        
        // 2. Buscar en Authorization header (Bearer)
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        // Apache a veces no pasa Authorization, intentar obtenerlo
        if (empty($authHeader) && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }
        
        if (!empty($authHeader) && strpos($authHeader, self::BEARER_PREFIX) === 0) {
            return substr($authHeader, strlen(self::BEARER_PREFIX));
        }
        
        // 3. Buscar en query string (menos seguro, solo para debug)
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $token = $_GET['api_token'] ?? null;
            if ($token) {
                return $token;
            }
        }
        
        return null;
    }
    
    /**
     * Valida el token
     */
    private static function validateToken(string $token): bool {
        // Token para workers (configurado en .env)
        $workerToken = defined('WORKER_API_TOKEN') ? WORKER_API_TOKEN : null;
        
        if ($workerToken && hash_equals($workerToken, $token)) {
            return true;
        }
        
        // Token de API general
        $apiToken = defined('API_TOKEN_SECRET') ? API_TOKEN_SECRET : null;
        
        if ($apiToken && hash_equals($apiToken, $token)) {
            return true;
        }
        
        // Verificar tokens en base de datos (para múltiples servicios)
        return self::checkDatabaseToken($token);
    }
    
    /**
     * Verifica token en base de datos
     */
    private static function checkDatabaseToken(string $token): bool {
        try {
            $db = getConnection();
            
            // Hash del token para comparación segura
            $tokenHash = hash('sha256', $token);
            
            $stmt = $db->prepare("
                SELECT clave, valor 
                FROM configuracion 
                WHERE clave = 'api_token' 
                   OR clave LIKE 'api_token_%'
            ");
            $stmt->execute();
            $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($tokens as $row) {
                if (hash_equals(hash('sha256', $row['valor']), $tokenHash)) {
                    return true;
                }
            }
        } catch (Exception $e) {
            error_log("Error verificando token en BD: " . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Genera un nuevo token de API
     */
    public static function generateToken(int $length = 64): string {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verifica que la solicitud venga de un worker autorizado
     */
    public static function checkWorker(): bool {
        $token = self::extractToken();
        
        if (!$token) {
            Response::error('Token de worker no proporcionado', [], 401);
        }
        
        $workerToken = defined('WORKER_API_TOKEN') ? WORKER_API_TOKEN : null;
        
        if (!$workerToken || !hash_equals($workerToken, $token)) {
            Response::error('Token de worker inválido', [], 401);
        }
        
        return true;
    }
    
    /**
     * Middleware combinado: acepta sesión O token de API
     */
    public static function checkAny(): bool {
        // Primero intentar autenticación por sesión
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            return true;
        }
        
        // Si no hay sesión, verificar token
        return self::check(true);
    }
}

