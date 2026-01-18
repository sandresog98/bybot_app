<?php
/**
 * Sidebar del Panel Administrativo
 */
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<nav class="sidebar" id="sidebar">
    <div class="logo">
        <h4><i class="bi bi-robot me-2"></i>ByBot</h4>
        <small class="text-white-50">Panel Administrativo</small>
    </div>
    
    <ul class="nav flex-column mt-3">
        <!-- Dashboard -->
        <li class="nav-item">
            <a href="<?= adminUrl('index.php') ?>" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <!-- Sección: Operaciones -->
        <li class="nav-section">Operaciones</li>
        
        <?php if (hasAccess('procesos')): ?>
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=procesos') ?>" class="nav-link <?= $currentPage === 'procesos' ? 'active' : '' ?>">
                <i class="bi bi-folder2-open"></i>
                <span>Procesos</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasAccess('procesos.crear')): ?>
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=procesos&action=crear') ?>" class="nav-link <?= ($currentPage === 'procesos' && ($_GET['action'] ?? '') === 'crear') ? 'active' : '' ?>">
                <i class="bi bi-plus-circle"></i>
                <span>Nuevo Proceso</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Sección: Monitoreo -->
        <li class="nav-section">Monitoreo</li>
        
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=colas') ?>" class="nav-link <?= $currentPage === 'colas' ? 'active' : '' ?>">
                <i class="bi bi-stack"></i>
                <span>Estado de Colas</span>
                <span class="badge bg-light text-dark ms-auto" id="queueCount">-</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=actividad') ?>" class="nav-link <?= $currentPage === 'actividad' ? 'active' : '' ?>">
                <i class="bi bi-activity"></i>
                <span>Actividad</span>
            </a>
        </li>
        
        <!-- Sección: Administración -->
        <?php if (hasRole('admin') || hasRole('supervisor')): ?>
        <li class="nav-section">Administración</li>
        
        <?php if (hasAccess('usuarios')): ?>
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=usuarios') ?>" class="nav-link <?= $currentPage === 'usuarios' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Usuarios</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (hasRole('admin')): ?>
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=configuracion') ?>" class="nav-link <?= $currentPage === 'configuracion' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Configuración</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=prompts') ?>" class="nav-link <?= $currentPage === 'prompts' ? 'active' : '' ?>">
                <i class="bi bi-chat-square-text"></i>
                <span>Prompts IA</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=plantillas') ?>" class="nav-link <?= $currentPage === 'plantillas' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text"></i>
                <span>Plantillas</span>
            </a>
        </li>
        
        <li class="nav-item">
            <a href="<?= adminUrl('index.php?page=logs') ?>" class="nav-link <?= $currentPage === 'logs' ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>Logs del Sistema</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>
    </ul>
    
    <!-- Footer del Sidebar -->
    <div class="mt-auto p-3 text-center" style="position: absolute; bottom: 0; width: 100%;">
        <small class="text-white-50">
            ByBot v<?= APP_VERSION ?? '1.0.0' ?><br>
            <span id="serverTime"></span>
        </small>
    </div>
</nav>

