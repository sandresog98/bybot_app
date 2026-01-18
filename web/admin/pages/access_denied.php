<?php
/**
 * Página de Acceso Denegado
 */

$pageTitle = 'Acceso Denegado';
$pageDescription = 'No tiene permisos para acceder a esta sección';

include ADMIN_LAYOUTS . '/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <div class="mb-4">
                <i class="bi bi-shield-exclamation text-danger" style="font-size: 6rem;"></i>
            </div>
            <h1 class="h3 mb-3">Acceso Denegado</h1>
            <p class="text-muted mb-4">
                No tiene los permisos necesarios para acceder a esta sección.<br>
                Contacte al administrador si cree que esto es un error.
            </p>
            <div class="d-flex gap-2 justify-content-center">
                <a href="<?= adminUrl('index.php') ?>" class="btn btn-primary">
                    <i class="bi bi-house me-1"></i>Ir al Dashboard
                </a>
                <button onclick="history.back()" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Volver
                </button>
            </div>
        </div>
    </div>
</div>

<?php include ADMIN_LAYOUTS . '/footer.php'; ?>

