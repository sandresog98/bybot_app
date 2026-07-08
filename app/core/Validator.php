<?php
declare(strict_types=1);

/**
 * Validator — utilidades de validación de entrada.
 * No reemplaza a filter_var ni frameworks; cubre los casos comunes de la app.
 */

namespace Core;

final class Validator
{
    /** Verifica que todos los campos existan y no estén vacíos. */
    public static function required(array $data, array $fields, array &$errors = []): bool
    {
        $ok = true;
        foreach ($fields as $f) {
            if (!isset($data[$f]) || $data[$f] === '' || $data[$f] === null) {
                $errors[$f] = "El campo '$f' es obligatorio.";
                $ok = false;
            }
        }
        return $ok;
    }

    public static function email(?string $value): bool
    {
        return $value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function intRange(mixed $value, ?int $min, ?int $max): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        $i = (int)$value;
        if ($min !== null && $i < $min) return false;
        if ($max !== null && $i > $max) return false;
        return true;
    }

    public static function stringLen(string $value, ?int $min = null, ?int $max = null): bool
    {
        $len = mb_strlen($value);
        if ($min !== null && $len < $min) return false;
        if ($max !== null && $len > $max) return false;
        return true;
    }

    /** Valida archivo subido ($_FILES item). Devuelve [ok, error]. */
    public static function uploadedFile(array $file, array $allowedMimes, int $maxBytes): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            return [false, 'Estructura de archivo inválida.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [false, 'Error al subir el archivo (código ' . $file['error'] . ').'];
        }
        if ($file['size'] > $maxBytes) {
            return [false, 'El archivo excede el tamaño máximo permitido.'];
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMimes, true)) {
            return [false, "Tipo de archivo no permitido: $mime"];
        }
        return [true, null];
    }
}