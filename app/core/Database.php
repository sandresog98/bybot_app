<?php
declare(strict_types=1);

/**
 * Database — conexión PDO singleton a MariaDB/MySQL.
 * Lee configuración de Environ. Lanza excepciones en modo error.
 * FETCH_ASSOC por defecto para modelos.
 */

namespace Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Environ::get('DB_HOST', '127.0.0.1');
        $port = Environ::get('DB_PORT', '3306');
        $name = Environ::get('DB_NAME', 'bybot_consolidado');
        $user = Environ::get('DB_USER', 'root');
        $pass = Environ::get('DB_PASS', '');
        $charset = Environ::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("No se pudo conectar a MariaDB/MySQL: " . $e->getMessage(), (int)$e->getCode(), $e);
        }

        return self::$pdo;
    }

    /** Probar conexión. Lanza si falla. */
    public static function test(): bool
    {
        return (bool)self::pdo()->query('SELECT 1')->fetchColumn();
    }

    /** Resetea el singleton (solo para tests). */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}