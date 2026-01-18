<?php
/**
 * Header del Panel Administrativo
 * Variables esperadas: $pageTitle, $pageDescription (opcional)
 */

require_once dirname(__DIR__, 2) . '/utils/session.php';
requireAuth();

$user = getCurrentUser();
if (!$user) {
    // Si no hay usuario, redirigir al login
    header('Location: ' . adminUrl('login.php'));
    exit;
}
$pageTitle = $pageTitle ?? 'Panel Administrativo';
$pageDescription = $pageDescription ?? '';
$userNombre = $user['nombre_completo'] ?? $user['usuario'] ?? 'Usuario';
$userRol = $user['rol'] ?? 'operador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> - ByBot</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= assetUrl('favicons/favicon.ico') ?>">
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= assetUrl('css/variables.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('css/admin.css') ?>" rel="stylesheet">
    
    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link href="<?= assetUrl($css) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <style>
        :root {
            --bs-primary: #55A5C8;
            --bs-secondary: #B1BCBF;
            --bs-success: #9AD082;
            --bs-info: #35719E;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f6f9;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: linear-gradient(180deg, #35719E 0%, #55A5C8 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar .logo {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar .logo h4 {
            color: white;
            font-weight: 800;
            margin: 0;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #9AD082;
        }
        
        .sidebar .nav-link i {
            font-size: 1.1rem;
            width: 24px;
        }
        
        .sidebar .nav-section {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 1.5rem 0.5rem;
            margin-top: 0.5rem;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .top-navbar {
            background: white;
            padding: 1rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .top-navbar .page-title {
            font-weight: 600;
            color: #35719E;
            margin: 0;
        }
        
        .top-navbar .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .top-navbar .user-info {
            text-align: right;
        }
        
        .top-navbar .user-name {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
        }
        
        .top-navbar .user-role {
            color: #666;
            font-size: 0.75rem;
        }
        
        .page-content {
            padding: 1.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-radius: 0.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            font-weight: 600;
            color: #35719E;
        }
        
        .btn-primary {
            background-color: #55A5C8;
            border-color: #55A5C8;
        }
        
        .btn-primary:hover {
            background-color: #35719E;
            border-color: #35719E;
        }
        
        .btn-success {
            background-color: #9AD082;
            border-color: #9AD082;
        }
        
        .badge-estado {
            font-weight: 500;
            padding: 0.35rem 0.65rem;
        }
        
        .text-primary { color: #55A5C8 !important; }
        .bg-primary { background-color: #55A5C8 !important; }
        .text-info { color: #35719E !important; }
        .bg-info { background-color: #35719E !important; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-link d-lg-none p-0" id="sidebarToggle">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h5 class="page-title"><?= htmlspecialchars($pageTitle) ?></h5>
            </div>
            
            <div class="user-menu">
                <!-- Notificaciones -->
                <div class="dropdown">
                    <button class="btn btn-link position-relative" data-bs-toggle="dropdown">
                        <i class="bi bi-bell fs-5 text-secondary"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notificationBadge" style="display:none;">
                            0
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end" style="width: 320px;">
                        <h6 class="dropdown-header">Notificaciones</h6>
                        <div id="notificationList">
                            <div class="text-center text-muted py-3">
                                <small>No hay notificaciones</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Usuario -->
                <div class="dropdown">
                    <button class="btn btn-link d-flex align-items-center gap-2 text-decoration-none" data-bs-toggle="dropdown">
                        <div class="user-info d-none d-md-block">
                            <div class="user-name"><?= htmlspecialchars($userNombre) ?></div>
                            <div class="user-role"><?= ucfirst($userRol) ?></div>
                        </div>
                        <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <?= strtoupper(substr($userNombre, 0, 1)) ?>
                        </div>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?= adminUrl('perfil.php') ?>"><i class="bi bi-person me-2"></i>Mi Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= adminUrl('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="page-content">
            <!-- Alertas Flash -->
            <div id="alertContainer">
                <?php showFlashMessage(); ?>
            </div>

