<?php
declare(strict_types=1);
/**
 * Layout header — encabezado HTML común del admin.
 * Variables esperadas (todas opcionales con defaults):
 *   $pageTitle  string  — título de la pestaña
 *   $pageHeading string — H1 del header
 *   $page       string  — identificador para marcar item activo en sidebar
 */

require_once __DIR__ . '/../../config/paths.php';

use Core\Auth;

$pageTitle  = $pageTitle  ?? 'ByBot';
$pageHeading = $pageHeading ?? $pageTitle;
$page       = $page       ?? '';

$auth = new Auth();
$user = $auth->user();
$nombreUsuario = $user['nombre_completo'] ?? 'Invitado';
$rolUsuario    = $user['rol'] ?? '-';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — ByBot</title>
    <link rel="icon" type="image/svg+xml" href="<?= by_asset_url('favicons/bybot.svg') ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= by_asset_url('css/variables.css') ?>" rel="stylesheet">
    <link href="<?= by_asset_url('css/common.css') ?>" rel="stylesheet">
    <link href="<?= by_asset_url('css/admin.css') ?>" rel="stylesheet">
</head>
<body data-page="<?= htmlspecialchars($page) ?>">
<div class="app-shell" id="app-shell">

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="app-main">
    <header class="app-header">
        <button id="by-sidebar-toggle" class="sidebar-toggle" aria-label="Colapsar menú">
            <i class="bi bi-list fs-4"></i>
        </button>
        <h1 class="h5 mb-0"><?= htmlspecialchars($pageHeading) ?></h1>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="badge bg-light text-secondary fw-normal">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($nombreUsuario) ?> · <?= htmlspecialchars(ucfirst($rolUsuario)) ?>
            </span>
            <a href="<?= by_admin_url('logout.php') ?>" class="btn btn-sm btn-outline-danger" data-confirm="¿Cerrar sesión?">
                <i class="bi bi-box-arrow-right"></i> Salir
            </a>
        </div>
    </header>
    <main class="app-content">