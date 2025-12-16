<?php
/**
 * Index - ByBot App
 * Redirige al login o dashboard según autenticación
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/controllers/AuthController.php';

$authController = new AuthController();

if ($authController->isAuthenticated()) {
    header("Location: " . getRedirectPath('pages/dashboard.php'));
} else {
    header("Location: " . getRedirectPath('login.php'));
}
exit();

