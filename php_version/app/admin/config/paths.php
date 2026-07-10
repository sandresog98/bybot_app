<?php
declare(strict_types=1);

/**
 * paths.php — Gestión de rutas y URLs del admin.
 * Define constantes absolutas (filesystem) y URLs (web).
 *
 * Las URLs se calculan a partir de appName() para funcionar tanto en XAMPP
 * (http://localhost/projects/bybot_v1/app/admin/) como en Hostinger futuro.
 */

/** Filesystem del proyecto (raíz). */
define('BYBOT_FS_ROOT', dirname(__DIR__, 3));

/** Filesystem de la app admin. */
define('BYAPP_ADMIN_FS', dirname(__DIR__, 2));

function by_app_base_url(): string
{
    // SCRIPT_NAME → /projects/bybot_v1/app/admin/index.php (o login.php)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $dir = dirname($scriptName);

    // Recorta el directorio para llegar hasta la raíz web del proyecto.
    // app/admin/  → raíz = subir dos niveles desde "app/admin"
    if (str_ends_with($dir, '/app/admin')) {
        return substr($dir, 0, -strlen('/app/admin')) . '/';
    }
    if (str_ends_with($dir, '/app/admin/')) {
        return substr($dir, 0, -strlen('/app/admin/')) . '/';
    }
    // Fallback: usar APP_URL del .env vía Environ (sin bootstrap cargado siempre)
    return '/';
}

function by_asset_url(string $rel): string
{
    static $base = null;
    $base ??= by_app_base_url();
    return $base . 'assets/' . ltrim($rel, '/');
}

function by_url(string $rel = ''): string
{
    static $base = null;
    $base ??= by_app_base_url();
    return $base . ltrim($rel, '/');
}

function by_admin_url(string $rel = ''): string
{
    static $base = null;
    $base ??= by_app_base_url();
    return $base . 'app/admin/' . ltrim($rel, '/');
}

function by_api_url(string $rel = ''): string
{
    static $base = null;
    $base ??= by_app_base_url();
    return $base . 'app/api/v1/' . ltrim($rel, '/');
}