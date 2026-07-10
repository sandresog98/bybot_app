<?php
declare(strict_types=1);

/**
 * LocalStorage — guarda archivos en disco (carpeta UPLOAD_DIR).
 * Las claves (key) son rutas relativas a UPLOAD_DIR, sin leading slash.
 */

namespace Core\Storage;

use Core\Environ;

final class LocalStorage implements StorageInterface
{
    private string $baseDir;

    public function __construct()
    {
        $dir = Environ::get('UPLOAD_DIR', 'uploads');
        if ($dir !== '' && $dir[0] !== '/') {
            // relativo a la raíz del proyecto
            $dir = dirname(__DIR__, 3) . '/' . $dir;
        }
        $this->baseDir = rtrim($dir, '/');
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }
    }

    private function fullPath(string $key): string
    {
        $key = ltrim($key, '/');
        $full = $this->baseDir . '/' . $key;
        // Evitar path traversal
        $real = realpath($this->baseDir);
        $target = realpath(dirname($full)) ?: dirname($full);
        if ($real !== false && strpos($target, $real) !== 0) {
            throw new RuntimeException('Path traversal detectado en LocalStorage.');
        }
        return $full;
    }

    public function store(string $tmpPath, string $storedName): string
    {
        // Subcarpetas opcionales en storedName (ej. "2026/07/archivo.pdf")
        $key = ltrim($storedName, '/');
        $full = $this->fullPath($key);
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!move_uploaded_file($tmpPath, $full) && !rename($tmpPath, $full)) {
            throw new RuntimeException("No se pudo mover el archivo subido a $full");
        }
        return $key;
    }

    public function read(string $key): string
    {
        $full = $this->fullPath($key);
        if (!is_file($full)) {
            throw new RuntimeException("Archivo no encontrado: $key");
        }
        return (string)file_get_contents($full);
    }

    public function stream(string $key)
    {
        $full = $this->fullPath($key);
        if (!is_file($full)) {
            return null;
        }
        return fopen($full, 'rb');
    }

    public function delete(string $key): bool
    {
        $full = $this->fullPath($key);
        return is_file($full) && unlink($full);
    }

    public function exists(string $key): bool
    {
        return is_file($this->fullPath($key));
    }

    public function size(string $key): ?int
    {
        $full = $this->fullPath($key);
        return is_file($full) ? (int)filesize($full) : null;
    }

    public function mime(string $key): ?string
    {
        $full = $this->fullPath($key);
        if (!is_file($full)) return null;
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $m = finfo_file($f, $full);
        finfo_close($f);
        return $m ?: null;
    }
}