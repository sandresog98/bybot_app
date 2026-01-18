<?php
/**
 * Logout - Cierra la sesión del usuario
 */

require_once __DIR__ . '/config/paths.php';
require_once BASE_DIR . '/config/database.php';

session_start();

// Registrar log de logout
if (isset($_SESSION['user_id'])) {
    try {
        $db = getConnection();
        $logStmt = $db->prepare("
            INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address)
            VALUES (?, 'logout', 'auth', 'Cierre de sesión', ?)
        ");
        $logStmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error registrando logout: " . $e->getMessage());
    }
}

// Destruir sesión
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirigir al login
require_once __DIR__ . '/utils/session.php';
redirect('login.php');

