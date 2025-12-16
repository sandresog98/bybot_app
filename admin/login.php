<?php
/**
 * Página de login - ByBot App
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/controllers/AuthController.php';

$authController = new AuthController();
$error = '';

if ($authController->isAuthenticated()) {
    header("Location: " . getRedirectPath('pages/dashboard.php'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = $authController->login($username, $password);
    
    if ($result['success']) {
        header("Location: " . $result['redirect']);
        exit();
    } else {
        $error = $result['message'];
    }
}

$pageTitle = 'Login - ByBot';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" href="<?php echo getAppUrl(); ?>assets/favicons/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Estilos comunes -->
    <link rel="stylesheet" href="<?php echo getAppUrl(); ?>assets/css/common.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-container">
                    <?php 
                    $logoPath = getAppUrl() . 'assets/images/logo.png';
                    $logoExists = file_exists(dirname(__DIR__) . '/assets/images/logo.png');
                    ?>
                    <?php if ($logoExists): ?>
                        <img src="<?php echo $logoPath; ?>" alt="ByBot Logo" class="logo-image" style="max-width: 200px; height: auto; max-height: 80px; margin-bottom: 10px;">
                        <p style="color: var(--secondary-color); margin: 5px 0 0; font-size: 0.9rem;">Sistema de Gestión Jurídica</p>
                    <?php else: ?>
                        <h2 style="color: var(--primary-color); font-weight: 800; margin: 0;">ByBot</h2>
                        <p style="color: var(--secondary-color); margin: 5px 0 0; font-size: 0.9rem;">Sistema de Gestión Jurídica</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-clock me-2"></i>
                        Su sesión ha expirado por inactividad.
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Usuario
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                   placeholder="Ingrese su usuario" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Contraseña
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Ingrese su contraseña" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
        
        <div class="footer-text text-center mt-4" style="color: rgba(255,255,255,0.8);">
            <i class="fas fa-shield-alt me-1"></i>
            Sistema de Gestión ByBot
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

