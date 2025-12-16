<?php
/**
 * Cargador de variables de entorno desde archivo .env
 * ByBot App
 */

$GLOBALS['_BY_BOT_ENV'] = [];

/**
 * Carga variables de entorno desde archivo .env
 */
function loadEnv() {
    $envFile = __DIR__ . '/../.env';
    
    if (!file_exists($envFile)) {
        return;
    }
    
    $handle = fopen($envFile, 'r');
    if (!$handle) {
        return;
    }
    
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        
        // Ignorar líneas vacías y comentarios
        if ($line === '' || substr($line, 0, 1) === '#') {
            continue;
        }
        
        // Buscar el signo igual
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        
        if ($key === '') {
            continue;
        }
        
        // Remover comillas
        $len = strlen($value);
        if ($len >= 2) {
            $first = substr($value, 0, 1);
            $last = substr($value, -1);
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, $len - 2);
            }
        }
        
        // Guardar variables
        $GLOBALS['_BY_BOT_ENV'][$key] = $value;
        $_SERVER[$key] = $value;
        $_ENV[$key] = $value;
    }
    
    fclose($handle);
}

/**
 * Obtiene una variable de entorno
 */
function env($key, $default = null) {
    // Buscar en $GLOBALS['_BY_BOT_ENV'] primero
    if (isset($GLOBALS['_BY_BOT_ENV'][$key])) {
        $value = $GLOBALS['_BY_BOT_ENV'][$key];
    }
    // Luego en $_ENV, $_SERVER y getenv()
    elseif (isset($_ENV[$key])) {
        $value = $_ENV[$key];
    }
    elseif (isset($_SERVER[$key])) {
        $value = $_SERVER[$key];
    }
    else {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
    }
    
    // Convertir strings booleanos
    if (is_string($value)) {
        $lower = strtolower(trim($value));
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
    }
    
    return $value;
}

