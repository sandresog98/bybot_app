<?php
/**
 * Configuración de rutas del Panel Administrativo
 */

// Cargar configuración base
require_once dirname(__DIR__, 3) . '/config/constants.php';

// Rutas del admin
define('ADMIN_DIR', dirname(__DIR__));
define('ADMIN_VIEWS', ADMIN_DIR . '/views');
define('ADMIN_LAYOUTS', ADMIN_VIEWS . '/layouts');
define('ADMIN_PAGES', ADMIN_DIR . '/pages');
define('ADMIN_MODULES', dirname(__DIR__) . '/modules');

// Calcular path relativo del admin desde la raíz del proyecto
// ADMIN_DIR es la ruta absoluta del sistema de archivos: /opt/lampp/htdocs/projects/bybot_v1/web/admin
// Necesitamos el path relativo desde la raíz web: /web/admin
// BYBOT_ROOT ya está definido en constants.php como la raíz del proyecto
$adminDirRelative = str_replace(BYBOT_ROOT, '', ADMIN_DIR); // /web/admin
// Normalizar separadores de directorio
$adminDirRelative = str_replace('\\', '/', $adminDirRelative);
// Asegurar que empiece con /
if (!empty($adminDirRelative) && $adminDirRelative[0] !== '/') {
    $adminDirRelative = '/' . $adminDirRelative;
}

// Construir URLs usando APP_URL si está disponible, sino usar detección automática
if (defined('APP_URL') && !empty(APP_URL)) {
    // Usar APP_URL como base y construir rutas relativas
    $appUrlParts = parse_url(APP_URL);
    $protocol = $appUrlParts['scheme'] ?? 'http';
    $host = $appUrlParts['host'] ?? 'localhost';
    $appBasePath = rtrim($appUrlParts['path'] ?? '', '/');
    
    // Construir paths relativos desde la raíz web
    $relativeAdminPath = $adminDirRelative; // /web/admin
    $relativeApiPath = dirname($adminDirRelative) . '/api/v1'; // /web/api/v1
    $relativeAssetsPath = '/assets'; // Siempre /assets desde la raíz del proyecto
    
    // Construir URLs completas
    // Si appBasePath ya incluye parte del path, no duplicar
    // Ejemplo: si APP_URL es https://domain.com/bybot y adminDirRelative es /web/admin
    // entonces ADMIN_URL debe ser https://domain.com/bybot/web/admin
    $baseUrl = $protocol . '://' . $host;
    // Si appBasePath no está vacío, agregarlo
    if (!empty($appBasePath)) {
        $baseUrl .= $appBasePath;
    }
    define('ADMIN_URL', $baseUrl . $relativeAdminPath);
    define('API_URL', $baseUrl . $relativeApiPath);
    define('ASSETS_URL', $baseUrl . $relativeAssetsPath);
} else {
    // Detección automática desde $_SERVER
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Usar el path relativo calculado
    define('ADMIN_URL', $protocol . "://" . $host . $adminDirRelative);
    define('API_URL', $protocol . "://" . $host . dirname($adminDirRelative) . '/api/v1');
    define('ASSETS_URL', $protocol . "://" . $host . '/assets');
}

/**
 * Genera URL completa para una página del admin
 */
function adminUrl(string $path = ''): string {
    $url = ADMIN_URL;
    if (!empty($path)) {
        // Si el path ya contiene query string, no agregar barra adicional
        if (strpos($path, '?') !== false) {
            // Path con query string: index.php?page=procesos
            $url = rtrim($url, '/') . '/' . ltrim($path, '/');
        } else {
            // Path normal: index.php
            $url = rtrim($url, '/') . '/' . ltrim($path, '/');
        }
    }
    return $url;
}

/**
 * Genera URL para un asset
 */
function assetUrl(string $path): string {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Genera URL para la API
 */
function apiUrl(string $endpoint): string {
    return API_URL . '/' . ltrim($endpoint, '/');
}

/**
 * Redirige a una URL del admin
 */
function redirect(string $path, array $params = []): void {
    $url = adminUrl($path);
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    header("Location: {$url}");
    exit;
}

/**
 * Verifica si la página actual es la indicada
 */
function isCurrentPage(string $page): bool {
    $current = $_GET['page'] ?? 'dashboard';
    return $current === $page;
}

/**
 * Genera clase CSS activa para navegación
 */
function activeClass(string $page): string {
    return isCurrentPage($page) ? 'active' : '';
}

