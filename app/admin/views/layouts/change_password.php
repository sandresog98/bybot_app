<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/paths.php';
$ok = $ok ?? null;
$err = $err ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ByBot — Cambiar contraseña</title>
    <link rel="icon" type="image/svg+xml" href="<?= by_asset_url('favicons/bybot.svg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= by_asset_url('css/variables.css') ?>" rel="stylesheet">
    <link href="<?= by_asset_url('css/common.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="POST" action="<?= by_admin_url('index.php?page=change_password') ?>">
        <div class="login-logo"><i class="bi bi-key"></i></div>
        <h1 class="h5 text-center mb-1" style="font-family: var(--by-fuente-titulo); color: var(--by-azul);">Cambia tu contraseña</h1>
        <p class="text-center text-muted mb-4" style="font-size: .85rem;">Esta es una contraseña de un solo uso. Define una nueva para continuar.</p>

        <?php if ($err): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($err) ?></div>
        <?php elseif ($ok): ?>
            <div class="alert alert-success py-2"><?= htmlspecialchars($ok) ?></div>
        <?php endif; ?>

        <div class="form-floating mb-3">
            <input type="password" class="form-control" id="nueva" name="nueva" placeholder="Nueva contraseña" required minlength="8" autofocus>
            <label for="nueva"><i class="bi bi-lock"></i> Nueva (mín. 8)</label>
        </div>
        <div class="form-floating mb-3">
            <input type="password" class="form-control" id="confirmacion" name="confirmacion" placeholder="Confirmar" required>
            <label for="confirmacion"><i class="bi bi-lock"></i> Repite la contraseña</label>
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit">
            <i class="bi bi-check2-circle"></i> Actualizar y continuar
        </button>
    </form>
</div>
</body>
</html>