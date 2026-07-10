<?php
declare(strict_types=1);

use Core\Database;

$pdo = Database::pdo();

// Estadísticas básicas para F0 (no sobreviven vacío: count(0))
$counts = [
    'procesos'        => (int) $pdo->query("SELECT COUNT(*) FROM procesos")->fetchColumn(),
    'archivos'        => (int) $pdo->query("SELECT COUNT(*) FROM procesos_archivos")->fetchColumn(),
    'analizados'      => (int) $pdo->query("SELECT COUNT(*) FROM procesos WHERE estado IN ('analizado','validado','completado')")->fetchColumn(),
    'cola_pendientes' => (int) $pdo->query("SELECT COUNT(*) FROM app_colas_trabajos WHERE estado = 'pendiente'")->fetchColumn(),
    'usuarios'        => (int) $pdo->query("SELECT COUNT(*) FROM control_usuarios")->fetchColumn(),
    'prompts'         => (int) $pdo->query("SELECT COUNT(*) FROM app_prompts WHERE activo = 1")->fetchColumn(),
];

$procesosPorEstado = $pdo->query("SELECT estado, COUNT(*) AS n FROM procesos GROUP BY estado ORDER BY n DESC")->fetchAll();

// Últimos procesos (5) — puede estar vacío
$ultimos = $pdo->query("SELECT id, codigo, estado, prioridad, created_at
                        FROM procesos
                        ORDER BY created_at DESC
                        LIMIT 5")->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2 class="h4 mb-0" style="font-family: var(--by-fuente-titulo); color: var(--by-azul);">Bienvenido</h2>
        <p class="text-muted mb-0" style="font-size:.875rem;">Panel general del sistema de casos.</p>
    </div>
    <a href="<?= by_admin_url('index.php?page=procesos') ?>" class="btn btn-primary">
        <i class="bi bi-folder-plus"></i> Ir a Procesos
    </a>
</div>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['label' => 'Procesos', 'value' => $counts['procesos'], 'icon' => 'folder', 'hint' => 'Total creados'],
        ['label' => 'Archivos', 'value' => $counts['archivos'], 'icon' => 'files', 'hint' => 'Subidos a storage'],
        ['label' => 'Analizados', 'value' => $counts['analizados'], 'icon' => 'robot', 'hint' => 'Estado analizado / validado'],
        ['label' => 'Cola pendiente', 'value' => $counts['cola_pendientes'], 'icon' => 'hourglass-split', 'hint' => 'Trabajos en cola'],
        ['label' => 'Usuarios', 'value' => $counts['usuarios'], 'icon' => 'people', 'hint' => 'Cuentas activas'],
        ['label' => 'Prompts IA', 'value' => $counts['prompts'], 'icon' => 'chat-left-text', 'hint' => 'Prompts activos'],
    ] as $stat): ?>
        <div class="col-12 col-md-6 col-xl-2">
            <div class="by-stat-card">
                <div class="stat-label"><i class="bi bi-<?= $stat['icon'] ?>"></i> <?= $stat['label'] ?></div>
                <div class="stat-value"><?= $stat['value'] ?></div>
                <div class="stat-hint"><?= $stat['hint'] ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="page-card">
            <h3 class="h6 mb-3" style="font-family:var(--by-fuente-titulo); color:var(--by-azul);">Últimos procesos</h3>
            <?php if (!$ultimos): ?>
                <div class="text-muted py-4 text-center">
                    <i class="bi bi-inbox fs-1 d-block mb-2" style="color:var(--by-gris-claro);"></i>
                    Aún no has creado procesos. La carga se habilita en la Fase 1.
                </div>
            <?php else: ?>
                <table class="table table-sm align-middle">
                    <thead><tr><th>Código</th><th>Estado</th><th>Prioridad</th><th>Creado</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimos as $p): ?>
                        <tr>
                            <td><a href="<?= by_admin_url('index.php?page=procesos') ?>"><code><?= htmlspecialchars($p['codigo']) ?></code></a></td>
                            <td><span class="badge badge-estado <?= htmlspecialchars($p['estado']) ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
                            <td><?= (int)$p['prioridad'] ?></td>
                            <td class="text-muted small"><?= htmlspecialchars($p['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="page-card">
            <h3 class="h6 mb-3" style="font-family:var(--by-fuente-titulo); color:var(--by-azul);">Procesos por estado</h3>
            <?php if (!$procesosPorEstado): ?>
                <div class="text-muted py-4 text-center">
                    <i class="bi bi-bar-chart-line fs-1 d-block mb-2" style="color:var(--by-gris-claro);"></i>
                    Sin datos todavía.
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($procesosPorEstado as $row): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="badge badge-estado <?= htmlspecialchars($row['estado']) ?>"><?= htmlspecialchars($row['estado']) ?></span>
                            <strong><?= (int)$row['n'] ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="page-card mt-4">
    <h3 class="h6 mb-2" style="font-family:var(--by-fuente-titulo); color:var(--by-azul);">Estado de la implementación</h3>
    <p class="text-muted small mb-2">Fase 0 (Fundamentos) — disponible:</p>
    <ul class="small">
        <li><i class="bi bi-check-circle-fill text-success"></i> Estructura del proyecto + BD unificada + núcleo PHP/Storage</li>
        <li><i class="bi bi-check-circle-fill text-success"></i> Login con contraseña de un solo uso + roles</li>
        <li><i class="bi bi bi-hourglass-split text-warning"></i> <strong>Fase 1</strong> (Carga de archivos) — pendiente</li>
        <li><i class="bi bi bi-hourglass-split text-warning"></i> <strong>Fase 2</strong> (Análisis IA) — pendiente</li>
    </ul>
</div>