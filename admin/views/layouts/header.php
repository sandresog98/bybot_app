<?php
require_once dirname(__DIR__, 2) . '/config/paths.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'ByBot - Sistema de Gestión Jurídica'; ?></title>
    <link rel="icon" href="<?php echo getAppUrl(); ?>assets/favicons/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Estilos comunes -->
    <link rel="stylesheet" href="<?php echo getAppUrl(); ?>assets/css/common.css">
    
    <?php if (isset($additionalCSS) && is_array($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <?php if (isset($inlineCSS)): ?>
    <style>
        <?php echo $inlineCSS; ?>
    </style>
    <?php endif; ?>
</head>
<body>
<!-- Botón hamburguesa para móviles -->
<button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Abrir menú">
    <i class="fas fa-bars"></i>
</button>

