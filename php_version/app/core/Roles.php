<?php
declare(strict_types=1);

/**
 * Roles — lector de roles.json y validador de acceso por módulo.
 * Permite reutilizar la misma fuente de verdad para el sidebar y el router.
 */

namespace Core;

final class Roles
{
    private static ?array $rolesData = null;

    private static function load(): array
    {
        if (self::$rolesData !== null) {
            return self::$rolesData;
        }
        $path = dirname(__DIR__, 2) . '/roles.json';
        if (!is_file($path)) {
            return self::$rolesData = [];
        }
        $json = file_get_contents($path);
        $data = json_decode($json, true);
        return self::$rolesData = is_array($data) ? $data : [];
    }

    /** Devuelve todos los roles definidos como [clave => ['label'=>, 'modulos'=>[]]]. */
    public static function all(): array
    {
        return self::load()['roles'] ?? [];
    }

    /** ¿El usuario actual (rol) puede acceder al módulo $module? */
    public static function can(?string $rol, string $module): bool
    {
        if ($rol === null) {
            return false;
        }
        $roles = self::all();
        $modulos = $roles[$rol]['modulos'] ?? [];
        return in_array($module, $modulos, true);
    }

    /** Módulos a los que el rol puede acceder. */
    public static function modulesFor(?string $rol): array
    {
        if ($rol === null) {
            return [];
        }
        return self::all()[$rol]['modulos'] ?? [];
    }
}