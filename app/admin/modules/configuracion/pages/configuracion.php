<?php
declare(strict_types=1);
use Core\Database;

$pdo = Database::pdo();
$configRows = $pdo->query("SELECT clave, valor, tipo, categoria, descripcion FROM app_configuracion ORDER BY categoria, clave")->fetchAll();
$pageHeading = 'Configuración';
$pageTitle   = 'Configuración · ByBot';
?>
<div class="page-card">
    <h2 class="h5 mb-3" style="font-family:var(--by-fuente-titulo); color:var(--by-azul);">
        <i class="bi bi-gear"></i> Configuración del sistema
    </h2>
    <p class="text-muted small">Valores cargados en <code>app_configuracion</code>. Editor GUI disposición condiciones más adelante.</p>
    <table class="table table-sm align-middle">
        <thead><tr><th>Categoría</th><th>Clave</th><th>Valor</th><th>Tipo</th><th>Descripción</th></tr></thead>
        <tbody>
        <?php foreach ($configRows as $row): ?>
            <tr>
                <td><span class="badge bg-light text-secondary"><?= htmlspecialchars($row['categoria']) ?></span></td>
                <td><code><?= htmlspecialchars($row['clave']) ?></code></td>
                <td class="small text-truncate" style="max-width: 320px;" title="<?= htmlspecialchars($row['valor']) ?>">
                    <?= htmlspecialchars(mb_strimwidth($row['valor'], 0, 80, '…')) ?>
                </td>
                <td><span class="badge bg-light text-secondary"><?= htmlspecialchars($row['tipo']) ?></span></td>
                <td class="small text-muted"><?= htmlspecialchars($row['descripcion'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>