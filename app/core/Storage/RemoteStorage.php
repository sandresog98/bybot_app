<?php
declare(strict_types=1);

/**
 * RemoteStorage — placeholder para S3 / B2 / Cloudflare R2.
 * No implementado en Fase 0. Lanza excepción informativa.
 * Se completará cuando se decida el proveedor (ver PLAN_DESARROLLO.md §7).
 */

namespace Core\Storage;

use RuntimeException;

final class RemoteStorage implements StorageInterface
{
    public function __construct()
    {
        throw new RuntimeException(
            'RemoteStorage aún no implementado. Defina STORAGE_DRIVER=local o implemente el proveedor elegido (S3/B2/R2).'
        );
    }

    public function store(string $tmpPath, string $storedName): string
    {
        throw new RuntimeException('RemoteStorage::store() no implementado.');
    }

    public function read(string $key): string
    {
        throw new RuntimeException('RemoteStorage::read() no implementado.');
    }

    public function stream(string $key)
    {
        throw new RuntimeException('RemoteStorage::stream() no implementado.');
    }

    public function delete(string $key): bool
    {
        throw new RuntimeException('RemoteStorage::delete() no implementado.');
    }

    public function exists(string $key): bool
    {
        throw new RuntimeException('RemoteStorage::exists() no implementado.');
    }

    public function size(string $key): ?int
    {
        throw new RuntimeException('RemoteStorage::size() no implementado.');
    }

    public function mime(string $key): ?string
    {
        throw new RuntimeException('RemoteStorage::mime() no implementado.');
    }
}