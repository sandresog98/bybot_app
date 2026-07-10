<?php
declare(strict_types=1);

/**
 * Environ — Cargador de variables de entorno (.env)
 * Carga el .env una sola vez y expone métodos get() tipados.
 * Sustituye a vlucas/phpdotenv manteniendo cero dependencias.
 */

namespace Core;

final class Environ
{
    private static ?self $instance = null;
    private array $vars = [];
    private bool $loaded = false;

    private function __construct()
    {
        $this->load();
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        // Resuelve raíz del proyecto: ../../ desde este archivo (app/core/Environ.php)
        $root = dirname(__DIR__, 2);
        $envFile = $root . '/.env';

        if (!is_file($envFile)) {
            $this->loaded = true;
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            $this->loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            // No sobreescribir variables ya definidas en el entorno (getenv prevalece)
            if (getenv($name) !== false) {
                $this->vars[$name] = getenv($name);
                continue;
            }
            // Limpia comillas
            $value = trim($value);
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[-1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }
            $this->vars[$name] = $value;
            putenv("$name=" . $value);
            $_ENV[$name] = $value;
        }

        $this->loaded = true;
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        return self::instance()->vars[$name] ?? $default;
    }

    public static function getInt(string $name, int $default = 0): int
    {
        $v = self::get($name);
        return $v === null ? $default : (int)$v;
    }

    public static function getBool(string $name, bool $default = false): bool
    {
        $v = self::get($name);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function getFloat(string $name, float $default = 0.0): float
    {
        $v = self::get($name);
        return $v === null ? $default : (float)$v;
    }
}