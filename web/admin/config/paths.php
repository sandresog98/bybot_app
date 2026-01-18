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

// Detectar paths base automáticamente desde el script actual
// Ejemplo: /projects/bybot/web/admin/index.php -> /projects/bybot/web/admin
$adminScriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
$adminBasePath = rtrim($adminScriptPath, '/');

// Construir URLs usando APP_URL si está disponible, sino usar detección automática
if (defined('APP_URL') && !empty(APP_URL)) {
    // Usar APP_URL como base y construir rutas relativas
    $appUrlParts = parse_url(APP_URL);
    $protocol = $appUrlParts['scheme'] ?? 'http';
    $host = $appUrlParts['host'] ?? 'localhost';
    $appBasePath = rtrim($appUrlParts['path'] ?? '', '/');
    
    // Extraer rutas relativas desde la raíz del proyecto
    // adminBasePath ejemplo: /projects/bybot/web/admin -> necesitamos /web/admin
    $projectBasePath = dirname(dirname($adminBasePath)); // Raíz del proyecto
    $relativeAdminPath = str_replace($projectBasePath, '', $adminBasePath); // /web/admin o web/admin
    // Asegurar que empiece con /
    if (!empty($relativeAdminPath) && $relativeAdminPath[0] !== '/') {
        $relativeAdminPath = '/' . $relativeAdminPath;
    }
    $relativeApiPath = dirname($relativeAdminPath) . '/api/v1'; // /web/api/v1
    $relativeAssetsPath = '/assets'; // Siempre /assets desde la raíz del proyecto
    
    // Construir URLs completas
    $baseUrl = $protocol . '://' . $host . $appBasePath;
    define('ADMIN_URL', $baseUrl . $relativeAdminPath);
    define('API_URL', $baseUrl . $relativeApiPath);
    define('ASSETS_URL', $baseUrl . $relativeAssetsPath);
} else {
    // Detección automática desde $_SERVER
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $projectBasePath = dirname(dirname($adminBasePath)); // Raíz del proyecto
    define('ADMIN_URL', $protocol . "://" . $host . $adminBasePath);
    define('API_URL', $protocol . "://" . $host . dirname($adminBasePath) . '/api/v1');
    define('ASSETS_URL', $protocol . "://" . $host . $projectBasePath . '/assets');
}

/**
 * Genera URL completa para una página del admin
 */
function adminUrl(string $path = ''): string {
    $url = ADMIN_URL;
    if (!empty($path)) {
        // Asegurar que ADMIN_URL termine con / y path no empiece con /
        $url = rtrim($url, '/') . '/' . ltrim($path, '/');
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

