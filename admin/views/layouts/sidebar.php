<?php 
/**
 * Sidebar - ByBot App
 */
?>
<!-- Overlay para móviles -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="mainSidebar">
    <!-- Botón cerrar para móviles -->
    <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Cerrar menú">
        <i class="fas fa-times"></i>
    </button>
    
    <div class="brand-area">
        <a href="<?php echo getBaseUrl(); ?>pages/dashboard.php" class="text-decoration-none">
            <div class="logo-container">
                <?php 
                $logoPath = getAppUrl() . 'assets/images/logo.png';
                $logoExists = file_exists(dirname(__DIR__, 3) . '/assets/images/logo.png');
                ?>
                <?php if ($logoExists): ?>
                    <img src="<?php echo $logoPath; ?>" alt="ByBot Logo" class="logo-image" style="max-width: 100%; height: auto; max-height: 60px;">
                <?php else: ?>
                    <h2 style="color: var(--primary-color); font-weight: 800; margin: 0; font-size: 1.8rem;">ByBot</h2>
                    <p style="color: var(--secondary-color); margin: 5px 0 0; font-size: 0.75rem; font-weight: 500;">Sistema Jurídico</p>
                <?php endif; ?>
            </div>
        </a>
    </div>
    
    <?php
    // Helper para verificar permisos
    if (!function_exists('canAccess')) {
        require_once dirname(__DIR__, 2) . '/controllers/AuthController.php';
        $___authTmp = new AuthController();
        function canAccess($moduleKey) {
            global $___authTmp;
            return $___authTmp->canAccessModule($moduleKey);
        }
    }
    ?>
    
    <nav class="nav flex-column mt-3">
        <!-- Dashboard -->
        <a class="nav-link <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>" 
           href="<?php echo getBaseUrl(); ?>pages/dashboard.php">
            <i class="fas fa-home me-2"></i>Inicio
        </a>
        
        <?php if (!empty($currentUser)): ?>
        
        <!-- MÓDULO CREAR COOP -->
        <?php if (canAccess('crear_coop') || canAccess('crear_coop.procesos') || canAccess('crear_coop.crear')): ?>
        <a class="nav-link <?php echo in_array(($currentPage ?? ''), ['crear_coop', 'crear_coop_procesos', 'crear_coop_crear']) ? 'active' : ''; ?>" 
           href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/procesos.php">
            <i class="fas fa-file-contract me-2"></i>Crear Coop
        </a>
        <?php endif; ?>
        
        <!-- ADMINISTRACIÓN -->
        <?php if (canAccess('usuarios') || canAccess('usuarios.gestion')): ?>
        <a class="nav-link <?php echo ($currentPage ?? '') === 'usuarios' ? 'active' : ''; ?>" 
           href="<?php echo getBaseUrl(); ?>modules/usuarios/pages/usuarios.php">
            <i class="fas fa-users-cog me-2"></i>Usuarios
        </a>
        <?php endif; ?>
        
        <?php if (canAccess('logs') || canAccess('logs.ver')): ?>
        <a class="nav-link <?php echo ($currentPage ?? '') === 'logs' ? 'active' : ''; ?>" 
           href="<?php echo getBaseUrl(); ?>modules/logs/pages/logs.php">
            <i class="fas fa-list-alt me-2"></i>Logs
        </a>
        <?php endif; ?>
        
        <?php endif; ?>
    </nav>
    
    <!-- User Info -->
    <div class="user-welcome">
        <div class="user-name"><?php echo htmlspecialchars($currentUser['nombre_completo'] ?? 'Usuario'); ?></div>
        <div class="user-role"><?php echo htmlspecialchars($currentUser['rol'] ?? ''); ?></div>
    </div>
    
    <!-- Logout -->
    <div class="px-3 pb-3">
        <a href="<?php echo getBaseUrl(); ?>logout.php" class="btn btn-outline-light btn-sm w-100">
            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
        </a>
    </div>
</div>

<script>
// Toggle sidebar en móviles
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.classList.add('sidebar-open');
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        });
    }
});
</script>

