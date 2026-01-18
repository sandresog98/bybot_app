<?php
/**
 * Middleware de Rate Limiting
 * Limita las solicitudes por IP para prevenir abuso
 */

require_once dirname(__DIR__, 3) . '/config/constants.php';
require_once BASE_DIR . '/web/core/Response.php';

class RateLimitMiddleware {
    // Configuración por defecto
    private const DEFAULT_LIMIT = 100;       // Solicitudes permitidas
    private const DEFAULT_WINDOW = 60;        // Ventana en segundos
    
    // Almacenamiento de requests (en producción usar Redis)
    private static $cacheDir = null;
    
    /**
     * Verifica el rate limit para la IP actual
     * @param int $limit Número máximo de solicitudes
     * @param int $window Ventana de tiempo en segundos
     * @param string $identifier Identificador personalizado (opcional, default: IP)
     */
    public static function check(
        int $limit = self::DEFAULT_LIMIT, 
        int $window = self::DEFAULT_WINDOW,
        ?string $identifier = null
    ): void {
        // En desarrollo, podemos ser más permisivos
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $limit = $limit * 10; // 10x más permisivo en desarrollo
        }
        
        $identifier = $identifier ?? self::getClientIdentifier();
        $key = 'rate_limit_' . md5($identifier);
        
        $data = self::getCache($key);
        $now = time();
        
        if ($data === null) {
            // Primera solicitud
            $data = [
                'count' => 1,
                'window_start' => $now
            ];
        } else {
            // Verificar si la ventana ha expirado
            if (($now - $data['window_start']) >= $window) {
                // Reiniciar ventana
                $data = [
                    'count' => 1,
                    'window_start' => $now
                ];
            } else {
                // Incrementar contador
                $data['count']++;
            }
        }
        
        // Guardar estado
        self::setCache($key, $data, $window);
        
        // Calcular valores para headers
        $remaining = max(0, $limit - $data['count']);
        $reset = $data['window_start'] + $window;
        
        // Enviar headers de rate limit
        header("X-RateLimit-Limit: {$limit}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$reset}");
        
        // Verificar si excedió el límite
        if ($data['count'] > $limit) {
            $retryAfter = $reset - $now;
            header("Retry-After: {$retryAfter}");
            
            Response::error(
                'Demasiadas solicitudes. Por favor, espera antes de intentar de nuevo.',
                [
                    'retry_after' => $retryAfter,
                    'limit' => $limit,
                    'window' => $window
                ],
                429
            );
        }
    }
    
    /**
     * Rate limit específico para endpoints de autenticación
     */
    public static function checkAuth(): void {
        self::check(10, 60); // 10 intentos por minuto
    }
    
    /**
     * Rate limit para uploads
     */
    public static function checkUpload(): void {
        self::check(20, 60); // 20 uploads por minuto
    }
    
    /**
     * Rate limit para API general
     */
    public static function checkApi(): void {
        $limit = defined('API_RATE_LIMIT') ? (int)API_RATE_LIMIT : self::DEFAULT_LIMIT;
        self::check($limit, self::DEFAULT_WINDOW);
    }
    
    /**
     * Obtiene el identificador del cliente
     */
    private static function getClientIdentifier(): string {
        // Intentar obtener IP real detrás de proxy
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy genérico
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR'                // Fallback
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Si hay múltiples IPs, tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Obtiene el directorio de caché
     */
    private static function getCacheDir(): string {
        if (self::$cacheDir === null) {
            self::$cacheDir = defined('STORAGE_DIR') 
                ? STORAGE_DIR . '/cache/rate_limit'
                : sys_get_temp_dir() . '/bybot_rate_limit';
                
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }
    
    /**
     * Obtiene datos del caché
     */
    private static function getCache(string $key): ?array {
        // En producción, usar Redis
        if (class_exists('QueueManager') && defined('REDIS_HOST')) {
            return self::getRediCache($key);
        }
        
        // Fallback: caché en archivos
        $file = self::getCacheDir() . '/' . $key . '.json';
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['data'];
            }
            
            // Expirado, eliminar
            @unlink($file);
        }
        
        return null;
    }
    
    /**
     * Guarda datos en caché
     */
    private static function setCache(string $key, array $data, int $ttl): void {
        // En producción, usar Redis
        if (class_exists('QueueManager') && defined('REDIS_HOST')) {
            self::setRedisCache($key, $data, $ttl);
            return;
        }
        
        // Fallback: caché en archivos
        $file = self::getCacheDir() . '/' . $key . '.json';
        $content = json_encode([
            'data' => $data,
            'expires' => time() + $ttl
        ]);
        
        file_put_contents($file, $content, LOCK_EX);
    }
    
    /**
     * Limpia caché expirado (llamar periódicamente)
     */
    public static function cleanup(): int {
        $dir = self::getCacheDir();
        $count = 0;
        $now = time();
        
        foreach (glob($dir . '/*.json') as $file) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            
            if (!$data || !isset($data['expires']) || $data['expires'] < $now) {
                @unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Obtiene datos de Redis (si está disponible)
     */
    private static function getRediCache(string $key): ?array {
        try {
            require_once BASE_DIR . '/web/core/QueueManager.php';
            $redis = new QueueManager();
            $data = $redis->get($key);
            return $data ? json_decode($data, true) : null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Guarda datos en Redis (si está disponible)
     */
    private static function setRedisCache(string $key, array $data, int $ttl): void {
        try {
            require_once BASE_DIR . '/web/core/QueueManager.php';
            $redis = new QueueManager();
            $redis->setEx($key, $ttl, json_encode($data));
        } catch (Exception $e) {
            // Fallback silencioso a archivo
        }
    }
}

