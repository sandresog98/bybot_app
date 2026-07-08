<?php
declare(strict_types=1);

/**
 * Auth — gestor de sesión y autenticación.
 *
 * Implementa:
 *  - Sesión token-basada en cookie (tabla control_sesiones).
 *  - Login con usuario + password (bcrypt).
 *  - Contraseña de un solo uso: si el usuario tiene clave_un_solo_uso=1,
 *    tras validar la contraseña temporal se fuerza el cambio. El flag
 *    se resetea automáticamente cuando el usuario define una nueva.
 */

namespace Core;

use PDO;

final class Auth
{
    private const TOKEN_BYTES = 32;

    private function pdo(): PDO
    {
        return Database::pdo();
    }

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $cookie = Environ::get('SESSION_COOKIE', 'bybot_sess');
            session_name($cookie);
            session_set_cookie_params([
                'lifetime' => Environ::getInt('SESSION_LIFETIME', 28800),
                'path' => '/',
                'secure' => Environ::getBool('SESSION_SECURE', false),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /** Intento de login. Devuelve [usuario|null, error|null]. */
    public function attempt(string $usuario, string $password): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM control_usuarios WHERE usuario = ? AND estado_activo = 1 LIMIT 1"
        );
        $stmt->execute([$usuario]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password'])) {
            return [null, 'Usuario o contraseña incorrectos.'];
        }
        // Sesión
        $_SESSION['usuario_id'] = (int)$u['id'];
        $_SESSION['rol'] = $u['rol'];
        $_SESSION['nombre'] = $u['nombre_completo'];
        $_SESSION['forzar_cambio'] = (int)$u['clave_un_solo_uso'] === 1;

        // Registrar sesión en BD
        $this->registrarSesion((int)$u['id']);

        // Actualizar ultimo_acceso
        $this->pdo()->prepare("UPDATE control_usuarios SET ultimo_acceso = NOW() WHERE id = ?")
            ->execute([$u['id']]);

        return [$u, null];
    }

    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            "SELECT id, usuario, nombre_completo, email, rol, clave_un_solo_uso, ultimo_acceso
             FROM control_usuarios WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$_SESSION['usuario_id']]);
        return $stmt->fetch() ?: null;
    }

    public function check(): bool
    {
        return !empty($_SESSION['usuario_id']);
    }

    public function mustChangePassword(): bool
    {
        return $this->check() && !empty($_SESSION['forzar_cambio']);
    }

    public function rol(): ?string
    {
        return $this->check() ? ($_SESSION['rol'] ?? null) : null;
    }

    public function logout(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Borrar sesiones en BD para este usuario
            if (!empty($_SESSION['usuario_id'])) {
                $this->pdo()->prepare("DELETE FROM control_sesiones WHERE usuario_id = ?")
                    ->execute([$_SESSION['usuario_id']]);
            }
            session_destroy();
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
            }
        }
    }

    /**
     * Cambia la contraseña del usuario autenticado. Quita el flag `clave_un_solo_uso`.
     * Devuelve [ok, error].
     */
    public function changeOwnPassword(string $nueva, string $confirmacion): array
    {
        if (!$this->check()) {
            return [false, 'No hay sesión.'];
        }
        if (strlen($nueva) < 8) {
            return [false, 'La contraseña debe tener al menos 8 caracteres.'];
        }
        if ($nueva !== $confirmacion) {
            return [false, 'Las contraseñas no coinciden.'];
        }
        $hash = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->pdo()->prepare(
            "UPDATE control_usuarios SET password = ?, clave_un_solo_uso = 0 WHERE id = ?"
        );
        $stmt->execute([$hash, $_SESSION['usuario_id']]);
        $_SESSION['forzar_cambio'] = false;
        return [true, null];
    }

    /**
     * Resetea la contraseña de un usuario (admin) a una aleatoria y activa
     * clave_un_solo_uso=1. Devuelve la contraseña temporal para mostrar al admin.
     */
    public function resetPassword(int $usuarioId): string
    {
        $temp = bin2hex(random_bytes(6)); // 12 chars hex
        $hash = password_hash($temp, PASSWORD_BCRYPT, ['cost' => 10]);
        $stmt = $this->pdo()->prepare(
            "UPDATE control_usuarios SET password = ?, clave_un_solo_uso = 1 WHERE id = ?"
        );
        $stmt->execute([$hash, $usuarioId]);
        return $temp;
    }

    private function registrarSesion(int $usuarioId): void
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $vida = Environ::getInt('SESSION_LIFETIME', 28800);
        $stmt = $this->pdo()->prepare(
            "INSERT INTO control_sesiones (usuario_id, token, ip, user_agent, expires_at)
             VALUES (?,?,?,?,?)"
        );
        $stmt->execute([
            $usuarioId,
            $token,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            date('Y-m-d H:i:s', time() + $vida),
        ]);
        $_SESSION['token'] = $token;
    }
}