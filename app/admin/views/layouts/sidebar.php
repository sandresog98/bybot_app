<?php
declare(strict_types=1);
/**
 * Sidebar — navegación lateral del admin.
 * Filtra items por los módulos permitidos para el rol según roles.json.
 */

require_once __DIR__ . '/../../config/paths.php';

use Core\Auth;
use Core\Roles;

$auth = new Auth();
$rol = $auth->rol() ?? 'operador';
$modulosPermitidos = Roles::modulesFor($rol);

// Catálogo de menú: cada item declara su página (id) y label/icono.
// La visibilidad la decide roles.json (modulosPermitidos).
$catalogo = [
    'dashboard'     => ['icon' => 'speedometer2', 'label' => 'Dashboard'],
    'procesos'       => ['icon' => 'folder',       'label' => 'Procesos'],
    'analisis'       => ['icon' => 'robot',        'label' => 'Análisis IA'],
    'prompts'        => ['icon' => 'chat-left-text','label' => 'Prompts IA'],
    'usuarios'       => ['icon' => 'people',       'label' => 'Usuarios'],
    'configuracion'  => ['icon' => 'gear',         'label' => 'Configuración'],
];

$current = $_GET['page'] ?? '';
?>
<aside class="app-sidebar">
    <div class="logo">
        <span>ByBot</span>
        <small>App de casos</small>
    </div>

    <nav class="nav flex-column mt-2">
        <?php foreach ($modulosPermitidos as $modId):
            if (!isset($catalogo[$modId])) continue;
            $m = $catalogo[$modId];
            $url = by_admin_url('index.php?page=' . $modId);
        ?>
            <a href="<?= $url ?>" class="nav-link <?= $current === $modId ? 'active' : '' ?>" data-page="<?= $modId ?>">
                <i class="bi bi-<?= htmlspecialchars($m['icon']) ?>"></i>
                <span><?= htmlspecialchars($m['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="mt-auto section-label">Rol · <?= htmlspecialchars(ucfirst($rol)) ?></div>
</aside>