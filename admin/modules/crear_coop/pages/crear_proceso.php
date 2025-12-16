<?php
/**
 * Crear Proceso - Crear Coop
 */

require_once '../../../controllers/AuthController.php';
require_once '../../../config/paths.php';
require_once '../models/CrearCoop.php';
require_once '../../../models/Logger.php';
require_once '../../../utils/FileUploadManager.php';

$authController = new AuthController();
$authController->requireModule('crear_coop.crear');
$currentUser = $authController->getCurrentUser();

$crearCoopModel = new CrearCoop();
$logger = new Logger();

$message = '';
$error = '';

// Directorio de uploads
$uploadsDir = __DIR__ . '/../../../../uploads/crear_coop/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $archivos = [
            'pagare' => null,
            'estado_cuenta' => null,
            'anexos' => []
        ];
        
        // Subir pagaré
        if (!empty($_FILES['archivo_pagare']['name'])) {
            $result = FileUploadManager::saveUploadedFile(
                $_FILES['archivo_pagare'],
                $uploadsDir . 'pagares',
                [
                    'prefix' => 'pagare',
                    'allowedExtensions' => ['pdf'],
                    'maxSize' => 10 * 1024 * 1024 // 10MB
                ]
            );
            $archivos['pagare'] = $result['fullPath'];
        }
        
        // Subir estado de cuenta
        if (!empty($_FILES['archivo_estado_cuenta']['name'])) {
            $result = FileUploadManager::saveUploadedFile(
                $_FILES['archivo_estado_cuenta'],
                $uploadsDir . 'estados_cuenta',
                [
                    'prefix' => 'estado_cuenta',
                    'allowedExtensions' => ['pdf'],
                    'maxSize' => 10 * 1024 * 1024 // 10MB
                ]
            );
            $archivos['estado_cuenta'] = $result['fullPath'];
        }
        
        // Subir anexos (mínimo 1, máximo 5)
        $anexosCount = 0;
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($_FILES['archivo_anexo_' . $i]['name'])) {
                $result = FileUploadManager::saveUploadedFile(
                    $_FILES['archivo_anexo_' . $i],
                    $uploadsDir . 'anexos',
                    [
                        'prefix' => 'anexo',
                        'allowedExtensions' => ['pdf'],
                        'maxSize' => 10 * 1024 * 1024 // 10MB
                    ]
                );
                $archivos['anexos'][] = $result['fullPath'];
                $anexosCount++;
            }
        }
        
        // Validar que se hayan subido los archivos requeridos
        if (empty($archivos['pagare'])) {
            throw new Exception('Debe subir el archivo del pagaré');
        }
        
        if (empty($archivos['estado_cuenta'])) {
            throw new Exception('Debe subir el archivo del estado de cuenta');
        }
        
        if ($anexosCount < 1) {
            throw new Exception('Debe subir al menos un archivo de anexos');
        }
        
        if ($anexosCount > 5) {
            throw new Exception('No puede subir más de 5 archivos de anexos');
        }
        
        // Crear proceso
        $procesoData = [
            'archivo_pagare' => $archivos['pagare'],
            'archivo_estado_cuenta' => $archivos['estado_cuenta'],
            'archivo_anexos' => $archivos['anexos'][0], // Primer anexo como principal
            'anexos_adicionales' => array_slice($archivos['anexos'], 1),
            'creado_por' => $currentUser['id']
        ];
        
        $result = $crearCoopModel->crearProceso($procesoData);
        
        if ($result['success']) {
            $message = 'Proceso creado exitosamente con código: ' . $result['codigo'];
            $logger->logCrear('crear_coop', 'Creación de proceso', [
                'codigo' => $result['codigo'],
                'id' => $result['id']
            ]);
            
            // Redirigir después de 2 segundos
            header("refresh:2;url=" . getBaseUrl() . "modules/crear_coop/pages/procesos.php");
        } else {
            $error = $result['message'] ?? 'Error al crear el proceso';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Crear Proceso - Crear Coop';
$currentPage = 'crear_coop_crear';
include '../../../views/layouts/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include '../../../views/layouts/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="page-title">
                        <i class="fas fa-plus-circle me-2" style="color: var(--primary-color);"></i>
                        Crear Nuevo Proceso
                    </h1>
                    <p class="text-muted mb-0">Cargue los archivos necesarios para iniciar el proceso</p>
                </div>
                <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/procesos.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Volver
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Archivo Pagaré -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-file-pdf me-2"></i>Pagaré (PDF) *
                            </label>
                            <input type="file" class="form-control" name="archivo_pagare" accept=".pdf" required>
                            <small class="form-text text-muted">Tamaño máximo: 10MB</small>
                        </div>
                        
                        <!-- Archivo Estado de Cuenta -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-file-pdf me-2"></i>Estado de Cuenta (PDF) *
                            </label>
                            <input type="file" class="form-control" name="archivo_estado_cuenta" accept=".pdf" required>
                            <small class="form-text text-muted">Tamaño máximo: 10MB</small>
                        </div>
                        
                        <!-- Archivos Anexos -->
                        <div class="mb-4">
                            <label class="form-label">
                                <i class="fas fa-file-archive me-2"></i>Anexos (PDF) * (Mínimo 1, Máximo 5)
                            </label>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <div class="mb-2">
                                <input type="file" class="form-control" name="archivo_anexo_<?php echo $i; ?>" accept=".pdf" <?php echo $i === 1 ? 'required' : ''; ?>>
                                <?php if ($i === 1): ?>
                                <small class="form-text text-muted">Primer anexo (obligatorio) - Tamaño máximo: 10MB</small>
                                <?php else: ?>
                                <small class="form-text text-muted">Anexo adicional <?php echo $i; ?> (opcional) - Tamaño máximo: 10MB</small>
                                <?php endif; ?>
                            </div>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Una vez creado el proceso, este quedará en estado "creado" y será procesado por el sistema de análisis con IA.
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?php echo getBaseUrl(); ?>modules/crear_coop/pages/procesos.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Crear Proceso
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../../views/layouts/footer.php'; ?>

