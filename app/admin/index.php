<?php
declare(strict_types=1);

/**
 * index.php — Router principal del admin.
 * Despacha por ?page=<modulo> a un archivo pages/<modulo>.php de cada módulo.
 * Verifica sesión; si no hay sesión → redirige a login.
 * Si la sesión tiene clave_un_solo_uso=1 → fuerza cambio.
 */

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/config/paths.php';

use Core\Auth;
use Core\Roles;

require __DIR__ . '/controllers/AuthController.php';

$auth = new Auth();
if (!$auth->check()) {
    header('Location: ' . by_admin_url('login.php'));
    exit;
}
if ($auth->mustChangePassword() && ($_GET['page'] ?? '') !== 'change_password') {
    header('Location: ' . by_admin_url('index.php?page=change_password'));
    exit;
}

$page = $_GET['page'] ?? 'dashboard';

// Casos especiales de routing
if ($page === 'change_password') {
    (new Admin\Controllers\AuthController())->changePassword();
    exit;
}

$map = [
    'dashboard'     => __DIR__ . '/modules/dashboard/pages/dashboard.php',
    'procesos'      => __DIR__ . '/modules/procesos/pages/procesos.php',
    'analisis'      => __DIR__ . '/modules/analisis/pages/analisis.php',
    'prompts'       => __DIR__ . '/modules/prompts/pages/prompts.php',
    'usuarios'      => __DIR__ . '/modules/usuarios/pages/usuarios.php',
    'configuracion' => __DIR__ . '/modules/configuracion/pages/configuracion.php',
];

if (!isset($map[$page]) || !is_file($map[$page])) {
    http_response_code(404);
    $pageHeading = 'No encontrado';
    $pageTitle = '404';
    require __DIR__ . '/views/layouts/header.php';
    echo "<div class=\"page-card\"><h2 class=\"h4 text-danger\">404</h2><p>La página <code>" . htmlspecialchars($page) . "</code> no existe.</p>
          <a class=\"btn btn-primary\" href=\"" . by_admin_url('index.php?page=dashboard') . "\">Volver al dashboard</a></div>";
    require __DIR__ . '/views/layouts/footer.php';
    exit;
}

if (!Roles::can($auth->rol(), $page)) {
    http_response_code(403);
    $pageHeading = 'Acceso denegado';
    $pageTitle = '403';
    require __DIR__ . '/views/layouts/header.php';
    echo "<div class=\"page-card\"><h2 class=\"h4 text-danger\">403</h2><p>No tienes permiso para acceder al módulo <code>" . htmlspecialchars($page) . "</code> con tu rol actual.</p>
          <a class=\"btn btn-primary\" href=\"" . by_admin_url('index.php?page=dashboard') . "\">Volver al dashboard</a></div>";
    require __DIR__ . '/views/layouts/footer.php';
    exit;
}

$pageFile = $map[$page];

// Encabezado por defecto:
$pageTitle  = ucfirst($page) . ' — ByBot';
$pageHeading = ucfirst($page);
$pageId = $page;

ob_start();
require $pageFile;
$content = ob_get_clean();

require __DIR__ . '/views/layouts/header.php';
echo $content;
require __DIR__ . '/views/layouts/footer.php';