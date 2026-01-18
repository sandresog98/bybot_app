<?php
/**
 * Cargador de Variables de Entorno - ByBot v2.0
 * 
 * Carga variables desde el archivo .env en la raíz del proyecto
 */

class EnvLoader {
    private static $loaded = false;
    private static $vars = [];
    
    /**
     * Cargar variables de entorno desde .env
     */
    public static function load($path = null) {
        if (self::$loaded) {
            return;
        }
        
        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }
        
        if (!file_exists($path)) {
            // Intentar con .env.example como fallback en desarrollo
            $examplePath = dirname(__DIR__) . '/.env.example';
            if (file_exists($examplePath)) {
                $path = $examplePath;
            } else {
                throw new Exception("Archivo .env no encontrado en: $path");
            }
        }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parsear KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si existen
                $value = trim($value, '"\'');
                
                // Guardar en array interno y en $_ENV
                self::$vars[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Obtener variable de entorno
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$vars[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Verificar si una variable existe
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$vars[$key]) || isset($_ENV[$key]) || getenv($key) !== false;
    }
    
    /**
     * Obtener variable como booleano
     */
    public static function getBool($key, $default = false) {
        $value = self::get($key);
        
        if ($value === null) {
            return $default;
        }
        
        return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
    }
    
    /**
     * Obtener variable como entero
     */
    public static function getInt($key, $default = 0) {
        $value = self::get($key);
        return $value !== null ? (int)$value : $default;
    }
    
    /**
     * Obtener variable como float
     */
    public static function getFloat($key, $default = 0.0) {
        $value = self::get($key);
        return $value !== null ? (float)$value : $default;
    }
    
    /**
     * Obtener todas las variables cargadas
     */
    public static function all() {
        if (!self::$loaded) {
            self::load();
        }
        return self::$vars;
    }
}

// Auto-cargar al incluir este archivo
EnvLoader::load();

/**
 * Función helper global para obtener variables de entorno
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

