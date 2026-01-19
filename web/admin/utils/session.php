<?php
/**
 * Utilidades de sesión para el Panel Administrativo
 */

require_once dirname(__DIR__) . '/config/paths.php';

/**
 * Inicia sesión si no está iniciada
 */
function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Configurar cookie de sesión para compartir entre admin y API
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Verifica si el usuario está autenticado
 */
function isAuthenticated(): bool {
    initSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['user']);
}

/**
 * Requiere autenticación o redirige al login
 */
function requireAuth(): void {
    if (!isAuthenticated()) {
        redirect('login.php');
    }
}

/**
 * Obtiene el usuario actual
 */
function getCurrentUser(): ?array {
    initSession();
    return $_SESSION['user'] ?? null;
}

/**
 * Obtiene el ID del usuario actual
 */
function getCurrentUserId(): ?int {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtiene el rol del usuario actual
 */
function getCurrentRole(): string {
    $user = getCurrentUser();
    return $user['rol'] ?? 'operador';
}

/**
 * Verifica si el usuario tiene un rol específico
 */
function hasRole(string $role): bool {
    $currentRole = getCurrentRole();
    if ($currentRole === 'admin') return true;
    return $currentRole === $role;
}

/**
 * Verifica si el usuario tiene acceso a un módulo
 */
function hasAccess(string $module): bool {
    $rol = getCurrentRole();
    
    // Admin tiene acceso a todo
    if ($rol === 'admin') return true;
    
    // Cargar roles desde JSON
    $rolesFile = BASE_DIR . '/roles.json';
    if (!file_exists($rolesFile)) {
        return false;
    }
    
    $roles = json_decode(file_get_contents($rolesFile), true);
    $modulos = $roles['roles'][$rol]['modulos'] ?? [];
    
    // Wildcard
    if (in_array('*', $modulos)) return true;
    
    // Verificar acceso directo o jerárquico
    foreach ($modulos as $m) {
        if ($m === $module) return true;
        if (strpos($module, $m . '.') === 0) return true;
    }
    
    return false;
}

/**
 * Requiere un rol específico
 */
function requireRole(string $role): void {
    if (!hasRole($role)) {
        setFlashMessage('No tienes permisos para acceder a esta sección', 'danger');
        redirect('index.php');
    }
}

/**
 * Requiere acceso a un módulo
 */
function requireAccess(string $module): void {
    if (!hasAccess($module)) {
        setFlashMessage('No tienes permisos para acceder a esta sección', 'danger');
        redirect('index.php');
    }
}

/**
 * Establece un mensaje flash
 */
function setFlashMessage(string $message, string $type = 'info'): void {
    initSession();
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type
    ];
}

/**
 * Obtiene y elimina el mensaje flash
 */
function getFlashMessage(): ?array {
    initSession();
    $flash = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    return $flash;
}

/**
 * Muestra el mensaje flash si existe
 */
function showFlashMessage(): void {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = htmlspecialchars($flash['message']);
        echo <<<HTML
        <div class="alert alert-{$type} alert-dismissible fade show" role="alert">
            {$message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        HTML;
    }
}

/**
 * Genera token CSRF
 */
function generateCsrfToken(): string {
    initSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifica token CSRF
 */
function verifyCsrfToken(string $token): bool {
    initSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Campo hidden con token CSRF
 */
function csrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
}

