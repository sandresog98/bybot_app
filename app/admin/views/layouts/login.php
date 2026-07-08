<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/paths.php';
$error = $error ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ByBot — Iniciar sesión</title>
    <link rel="icon" type="image/svg+xml" href="<?= by_asset_url('favicons/bybot.svg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= by_asset_url('css/variables.css') ?>" rel="stylesheet">
    <link href="<?= by_asset_url('css/common.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="POST" action="<?= by_admin_url('login.php') ?>">
        <div class="login-logo">By</div>
        <h1 class="h4 text-center mb-1" style="font-family: var(--by-fuente-titulo); color: var(--by-azul);">ByBot App</h1>
        <p class="text-center text-muted mb-4" style="font-size: .85rem;">Ingresa con tu usuario y contraseña.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-floating mb-3">
            <input type="text" class="form-control" id="usuario" name="usuario" placeholder="usuario" required autofocus
                   value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
            <label for="usuario"><i class="bi bi-person"></i> Usuario</label>
        </div>
        <div class="form-floating mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
            <label for="password"><i class="bi bi-lock"></i> Contraseña</label>
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit">
            <i class="bi bi-box-arrow-in-right"></i> Entrar
        </button>
        <p class="text-center text-muted mt-3 mb-0" style="font-size: .75rem;">
            ByBot · <?= date('Y') ?>
        </p>
    </form>
</div>
</body>
</html>