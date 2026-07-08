<?php
declare(strict_types=1);

/**
 * Response — utilidades para emitir JSON (API) y redireccionar (web).
 */

namespace Core;

final class Response
{
    public static function json(mixed $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $name => $value) {
            header("$name: $value");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function jsonOk(mixed $data = null, string $message = 'OK'): void
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function jsonError(string $message, int $status = 400, mixed $data = null): void
    {
        self::json(['success' => false, 'message' => $message, 'data' => $data], $status);
    }

    public static function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }
}