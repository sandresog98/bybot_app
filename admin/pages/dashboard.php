<?php
/**
 * Dashboard - ByBot App
 */

require_once '../controllers/AuthController.php';
require_once __DIR__ . '/../../config/database.php';

$authController = new AuthController();
$authController->requireAuth();
$currentUser = $authController->getCurrentUser();

$conn = getConnection();

// Obtener estadísticas
$stats = [
    'procesos' => 0,
    'procesos_creados' => 0,
    'procesos_analizando' => 0,
    'usuarios' => 0
];

try {
    // Total procesos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM crear_coop_procesos");
    $stats['procesos'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

try {
    // Procesos creados
    $stmt = $conn->query("SELECT COUNT(*) as total FROM crear_coop_procesos WHERE estado = 'creado'");
    $stats['procesos_creados'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

try {
    // Procesos analizando
    $stmt = $conn->query("SELECT COUNT(*) as total FROM crear_coop_procesos WHERE estado IN ('analizando_con_ia', 'analizado_con_ia')");
    $stats['procesos_analizando'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

try {
    // Usuarios activos
    $stmt = $conn->query("SELECT COUNT(*) as total FROM control_usuarios WHERE estado_activo = TRUE");
    $stats['usuarios'] = $stmt->fetch()['total'] ?? 0;
} catch (Exception $e) {}

// Obtener procesos recientes
$procesosRecientes = [];
try {
    $stmt = $conn->query("
        SELECT p.*, u.nombre_completo as creado_por_nombre
        FROM crear_coop_procesos p
        LEFT JOIN control_usuarios u ON p.creado_por = u.id
        ORDER BY p.fecha_creacion DESC
        LIMIT 10
    ");
    $procesosRecientes = $stmt->fetchAll();
} catch (Exception $e) {}

$pageTitle = 'ByBot - Inicio';
$currentPage = 'dashboard';
include '../views/layouts/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../views/layouts/sidebar.php'; ?>
        
        <main class="main-content">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-home me-2" style="color: var(--primary-color);"></i>
                        Bienvenido, <?php echo htmlspecialchars($currentUser['nombre_completo']); ?>
                    </h1>
                    <p class="text-muted mb-0">Panel de control ByBot</p>
                </div>
                <div class="text-end">
                    <span class="badge" style="background: var(--primary-color); font-size: 0.9rem; padding: 10px 16px;">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('d/m/Y'); ?>
                    </span>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-file-contract"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['procesos']; ?></div>
                        <div class="stat-label">Total Procesos</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['procesos_creados']; ?></div>
                        <div class="stat-label">Procesos Creados</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['procesos_analizando']; ?></div>
                        <div class="stat-label">En Análisis IA</div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo $stats['usuarios']; ?></div>
                        <div class="stat-label">Usuarios</div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Procesos Recientes -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-clock me-2"></i>Procesos Recientes
                            </h5>
                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/procesos.php" class="btn btn-sm btn-light">
                                Ver todos
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($procesosRecientes)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No hay procesos registrados</p>
                                    <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/crear_proceso.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus me-1"></i>Crear Proceso
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>Código</th>
                                                <th>Estado</th>
                                                <th>Creado Por</th>
                                                <th>Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($procesosRecientes as $proceso): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($proceso['codigo']); ?></strong>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $estadoClass = [
                                                        'creado' => 'bg-secondary',
                                                        'analizando_con_ia' => 'bg-info',
                                                        'analizado_con_ia' => 'bg-primary',
                                                        'archivos_extraidos' => 'bg-warning text-dark',
                                                        'llenar_pagare' => 'bg-success'
                                                    ];
                                                    $estadoText = [
                                                        'creado' => 'Creado',
                                                        'analizando_con_ia' => 'Analizando con IA',
                                                        'analizado_con_ia' => 'Analizado con IA',
                                                        'archivos_extraidos' => 'Archivos Extraídos',
                                                        'llenar_pagare' => 'Llenar Pagaré'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $estadoClass[$proceso['estado']] ?? 'bg-secondary'; ?>">
                                                        <?php echo $estadoText[$proceso['estado']] ?? ucfirst($proceso['estado']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($proceso['creado_por_nombre'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <i class="fas fa-calendar text-muted me-1"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($proceso['fecha_creacion'])); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Accesos Rápidos -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt me-2"></i>Accesos Rápidos
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-3">
                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/crear_proceso.php" class="btn btn-outline-primary text-start">
                                    <i class="fas fa-plus-circle me-2"></i>Nuevo Proceso
                                </a>
                                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/procesos.php" class="btn btn-outline-primary text-start">
                                    <i class="fas fa-list me-2"></i>Ver Procesos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../views/layouts/footer.php'; ?>

