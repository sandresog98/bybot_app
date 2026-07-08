<?php
declare(strict_types=1);

/**
 * StorageInterface — abstracción de almacenamiento de archivos.
 * Implementaciones: LocalStorage (default), RemoteStorage (placeholder S3/B2/R2).
 */

namespace Core\Storage;

interface StorageInterface
{
    /**
     * Guarda un archivo subido (tmp) con el nombre indicado.
     * Devuelve la ruta relativa (clave) de almacenamiento.
     */
    public function store(string $tmpPath, string $storedName): string;

    /** Devuelve el contenido bruto del archivo (para servirlo vía API). */
    public function read(string $key): string;

    /** Devuelve un stream del archivo (resource) o null si no existe. */
    public function stream(string $key);

    /** Elimina el archivo. */
    public function delete(string $key): bool;

    /** Indica si existe. */
    public function exists(string $key): bool;

    /** Tamaño en bytes (o null si desconocido). */
    public function size(string $key): ?int;

    /** MIME detectado (o null). */
    public function mime(string $key): ?string;
}