<?php
/**
 * Helpers PHP Compartidos - ByBot v2.0
 */

/**
 * Formatear fecha para mostrar
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '-';
    try {
        $d = new DateTime($date);
        return $d->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Formatear fecha y hora
 */
function formatDateTime($date, $format = 'd/m/Y H:i') {
    return formatDate($date, $format);
}

/**
 * Formatear moneda
 */
function formatCurrency($value, $decimals = 0) {
    if ($value === null || $value === '') return '-';
    return '$' . number_format((float)$value, $decimals, ',', '.');
}

/**
 * Formatear número
 */
function formatNumber($value, $decimals = 0) {
    if ($value === null || $value === '') return '-';
    return number_format((float)$value, $decimals, ',', '.');
}

/**
 * Truncar texto
 */
function truncate($text, $length = 50, $suffix = '...') {
    if (!$text) return '';
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Generar slug
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'n-a';
}

/**
 * Sanitizar string para HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Obtener extensión de archivo
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Verificar si es una petición AJAX
 */
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Obtener IP del cliente
 */
function getClientIp() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Tomar la primera IP si hay varias
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '0.0.0.0';
}

/**
 * Generar token aleatorio
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Verificar si el string es JSON válido
 */
function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Array a JSON bonito
 */
function prettyJson($data) {
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Redireccionar
 */
function redirect($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Flash message (usando sesión)
 */
function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
    } else {
        $msg = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);
        return $msg;
    }
}

/**
 * Dump y die (para debug)
 */
function dd(...$vars) {
    echo '<pre style="background:#1e1e1e;color:#fff;padding:15px;margin:10px;border-radius:5px;">';
    foreach ($vars as $var) {
        var_dump($var);
        echo "\n---\n";
    }
    echo '</pre>';
    die;
}

/**
 * Obtener valor de array anidado con notación de punto
 */
function arrayGet($array, $key, $default = null) {
    if (is_null($key)) return $array;
    
    if (isset($array[$key])) return $array[$key];
    
    foreach (explode('.', $key) as $segment) {
        if (!is_array($array) || !array_key_exists($segment, $array)) {
            return $default;
        }
        $array = $array[$segment];
    }
    
    return $array;
}

/**
 * Establecer valor en array anidado con notación de punto
 */
function arraySet(&$array, $key, $value) {
    if (is_null($key)) return $array = $value;
    
    $keys = explode('.', $key);
    
    while (count($keys) > 1) {
        $key = array_shift($keys);
        
        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }
        
        $array = &$array[$key];
    }
    
    $array[array_shift($keys)] = $value;
    
    return $array;
}

