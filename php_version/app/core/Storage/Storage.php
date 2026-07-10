<?php
declare(strict_types=1);

/**
 * Storage — factory que entrega la implementación activa.
 * Driver definido en .env STORAGE_DRIVER (local|remote).
 */

namespace Core\Storage;

use Core\Environ;

abstract class Storage
{
    private static ?StorageInterface $instance = null;

    public static function driver(): StorageInterface
    {
        if (self::$instance instanceof StorageInterface) {
            return self::$instance;
        }
        $driver = strtolower((string)Environ::get('STORAGE_DRIVER', 'local'));
        return self::$instance = match ($driver) {
            'remote' => new RemoteStorage(),
            default => new LocalStorage(),
        };
    }

    /** Para tests: inyectar mock. */
    public static function setInstance(?StorageInterface $instance): void
    {
        self::$instance = $instance;
    }
}