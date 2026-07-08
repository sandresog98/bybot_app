<?php
declare(strict_types=1);
use Core\Database;

$pdo = Database::pdo();
$prompts = $pdo->query("SELECT id, nombre, version, tipo, activo, created_at, updated_at FROM app_prompts ORDER BY activo DESC, nombre")->fetchAll();
$pageHeading = 'Prompts IA';
$pageTitle   = 'Prompts IA · ByBot';
?>
<div class="page-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0" style="font-family:var(--by-fuente-titulo); color:var(--by-azul);">
            <i class="bi bi-chat-left-text"></i> Prompts de IA
        </h2>
        <button class="btn btn-sm btn-outline-secondary" disabled title="Disponible en Fase 2">
            <i class="bi bi-plus-lg"></i> Nuevo
        </button>
    </div>
    <p class="text-muted small">Versión semilla de prompts migrada del legado. Editor usable en Fase 2.</p>
    <table class="table table-sm align-middle">
        <thead><tr><th>Nombre</th><th>Versión</th><th>Tipo</th><th>Activo</th><th>Actualizado</th></tr></thead>
        <tbody>
        <?php foreach ($prompts as $pr): ?>
            <tr>
                <td><code><?= htmlspecialchars($pr['nombre']) ?></code></td>
                <td><?= htmlspecialchars($pr['version']) ?></td>
                <td><?= htmlspecialchars($pr['tipo']) ?></td>
                <td>
                    <?php if ((int)$pr['activo'] === 1): ?>
                        <span class="badge bg-success"><i class="bi bi-check2"></i> Activo</span>
                    <?php else: ?>
                        <span class="badge bg-light text-secondary">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted small"><?= htmlspecialchars($pr['updated_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>