<?php
/**
 * Index - Router principal del Panel Administrativo
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/utils/session.php';

// Verificar autenticación
requireAuth();

// Obtener página solicitada
$page = $_GET['page'] ?? 'dashboard';
$action = $_GET['action'] ?? null;

// Mapeo de páginas a archivos
$pages = [
    'dashboard' => 'pages/dashboard.php',
    'procesos' => 'pages/procesos/index.php',
    'usuarios' => 'pages/usuarios/index.php',
    'configuracion' => 'pages/configuracion/index.php',
    'logs' => 'pages/logs/index.php',
    'perfil' => 'pages/perfil.php',
    'access_denied' => 'pages/access_denied.php',
];

// Verificar que la página existe
if (!isset($pages[$page])) {
    $page = 'dashboard';
}

// Verificar permisos de acceso (los permisos específicos se verifican en cada página)
$permisos = [
    'procesos' => 'procesos',
    'usuarios' => 'usuarios',
    'configuracion' => 'configuracion',
    'logs' => 'logs',
];

if (isset($permisos[$page]) && $page !== 'access_denied') {
    requireAccess($permisos[$page]);
}

// Cargar la página
$pageFile = ADMIN_DIR . '/' . $pages[$page];

if (file_exists($pageFile)) {
    require_once $pageFile;
} else {
    // Página no encontrada, mostrar dashboard
    require_once ADMIN_DIR . '/pages/dashboard.php';
}

