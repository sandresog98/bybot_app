<?php
/**
 * Lista de Procesos - Crear Coop
 */

require_once '../../../controllers/AuthController.php';
require_once '../../../config/paths.php';
require_once '../models/CrearCoop.php';

$authController = new AuthController();
$authController->requireModule('crear_coop.procesos');
$currentUser = $authController->getCurrentUser();

$crearCoopModel = new CrearCoop();

$filtros = [
    'estado' => $_GET['estado'] ?? '',
    'codigo' => $_GET['codigo'] ?? ''
];

$procesos = $crearCoopModel->obtenerProcesos($filtros);

$estados = [
    'creado' => ['label' => 'Creado', 'class' => 'bg-secondary'],
    'analizando_con_ia' => ['label' => 'Analizando con IA', 'class' => 'bg-info'],
    'analizado_con_ia' => ['label' => 'Analizado con IA', 'class' => 'bg-primary'],
    'archivos_extraidos' => ['label' => 'Archivos Extraídos', 'class' => 'bg-warning text-dark'],
    'llenar_pagare' => ['label' => 'Llenar Pagaré', 'class' => 'bg-success']
];

$pageTitle = 'Procesos - Crear Coop';
$currentPage = 'crear_coop_procesos';
include '../../../views/layouts/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../views/layouts/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-file-contract me-2" style="color: var(--primary-color);"></i>
                        Procesos CoreCoop
                    </h1>
                    <p class="text-muted mb-0">Gestión de procesos de creación de pagarés</p>
                </div>
                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/crear_proceso.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Nuevo Proceso
                </a>
            </div>
            
            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Código</label>
                            <input type="text" class="form-control" name="codigo" value="<?php echo htmlspecialchars($filtros['codigo']); ?>" placeholder="Buscar por código">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado">
                                <option value="">Todos</option>
                                <?php foreach ($estados as $key => $estado): ?>
                                <option value="<?php echo $key; ?>" <?php echo $filtros['estado'] === $key ? 'selected' : ''; ?>>
                                    <?php echo $estado['label']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tabla de Procesos -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Estado</th>
                                    <th>Creado Por</th>
                                    <th>Fecha Creación</th>
                                    <th>Última Actualización</th>
                                    <th width="100">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($procesos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-file-contract fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No se encontraron procesos</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($procesos as $proceso): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($proceso['codigo']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $estados[$proceso['estado']]['class'] ?? 'bg-secondary'; ?>">
                                                <?php echo $estados[$proceso['estado']]['label'] ?? ucfirst($proceso['estado']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($proceso['creado_por_nombre'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($proceso['fecha_creacion'])); ?></td>
                                        <td><?php echo $proceso['fecha_actualizacion'] ? date('d/m/Y H:i', strtotime($proceso['fecha_actualizacion'])) : '-'; ?></td>
                                        <td>
                                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/ver_proceso.php?id=<?php echo $proceso['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../../views/layouts/footer.php'; ?>

