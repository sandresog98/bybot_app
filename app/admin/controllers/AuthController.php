<?php
declare(strict_types=1);

/**
 * AuthController — actions de login, logout, cambiar contraseña.
 */

namespace Admin\Controllers;

use Core\Auth;

require_once __DIR__ . '/../config/paths.php';

class AuthController
{
    public function login(): void
    {
        $auth = new Auth();
        if ($auth->check()) {
            $this->redirectAfterLogin($auth);
            return;
        }
        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = trim($_POST['usuario'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            [$u, $err] = $auth->attempt($usuario, $password);
            if ($err !== null) {
                $error = $err;
            } else {
                $this->redirectAfterLogin($auth);
                return;
            }
        }
        // Render login
        require __DIR__ . '/../views/layouts/login.php';
    }

    public function logout(): void
    {
        (new Auth())->logout();
        header('Location: ' . by_admin_url('login.php'));
        exit;
    }

    public function changePassword(): void
    {
        $auth = new Auth();
        if (!$auth->check()) {
            header('Location: ' . by_admin_url('login.php'));
            exit;
        }
        $ok = null; $err = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            [$o, $e] = $auth->changeOwnPassword(trim($_POST['nueva'] ?? ''), trim($_POST['confirmacion'] ?? ''));
            $o ? ($ok = 'Contraseña actualizada. Ya puedes continuar.') : ($err = $e);
            if ($ok) {
                header('Location: ' . by_admin_url('index.php?page=dashboard'));
                exit;
            }
        }
        require __DIR__ . '/../views/layouts/change_password.php';
    }

    private function redirectAfterLogin(Auth $auth): void
    {
        if ($auth->mustChangePassword()) {
            header('Location: ' . by_admin_url('index.php?page=change_password'));
        } else {
            header('Location: ' . by_admin_url('index.php?page=dashboard'));
        }
        exit;
    }
}