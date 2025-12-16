<?php
/**
 * Logout - ByBot App
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/controllers/AuthController.php';

$authController = new AuthController();
$result = $authController->logout();

header("Location: " . $result['redirect']);
exit();

