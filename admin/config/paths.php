<?php
/**
 * Configuración de rutas para la aplicación ByBot
 */

define('BASE_PATH', __DIR__ . '/..');

function getBaseUrl() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        return substr($scriptName, 0, $pos + strlen($marker));
    }
    return './';
}

/**
 * Obtiene la URL base de la aplicación (hasta /by_bot_app/)
 * Útil para assets compartidos
 */
function getAppUrl() {
    $scriptName = $_SERVER['SCRIPT_NAME'];
    // Buscar /by_bot_app/ en la ruta
    $marker = '/by_bot_app/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        return substr($scriptName, 0, $pos + strlen($marker));
    }
    // Fallback: buscar /admin/ y subir un nivel
    $marker = '/admin/';
    $pos = strpos($scriptName, $marker);
    if ($pos !== false) {
        return substr($scriptName, 0, $pos) . '/';
    }
    return './';
}

function getAbsolutePath($relativePath) {
    return BASE_PATH . '/' . $relativePath;
}

function getRedirectPath($path) {
    return getBaseUrl() . $path;
}
?>

