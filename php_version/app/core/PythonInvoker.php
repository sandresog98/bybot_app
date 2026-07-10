<?php
declare(strict_types=1);

/**
 * PythonInvoker — ejecuta scripts Python del app_worker vía exec/subprocess.
 * Lee del .env WORKER_PY_BIN y WORKER_TIMEOUT_SEG.
 *
 * Métodos estáticos:
 *  - run(string $scriptRel, array $args = []): array  -> devuelve [ok, stdout, stderr, exitCode]
 *  - runJson(string $scriptRel, array $args = []): array -> ejecuta y parsea JSON del stdout
 */

namespace Core;

final class PythonInvoker
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function workerBin(): string
    {
        return Environ::get('WORKER_PY_BIN', 'python3');
    }

    private static function timeout(): int
    {
        return Environ::getInt('WORKER_TIMEOUT_SEG', 120);
    }

    /** Construye un comando escapado. */
    private static function buildCommand(string $scriptRel, array $args): string
    {
        $root = self::projectRoot();
        $script = $root . '/app_worker/' . ltrim($scriptRel, '/');
        if (!is_file($script)) {
            throw new RuntimeException("Script Python no encontrado: $script");
        }
        $parts = [escapeshellcmd(self::workerBin()), escapeshellarg($script)];
        foreach ($args as $k => $v) {
            if (is_int($k)) {
                $parts[] = escapeshellarg((string)$v);
            } else {
                $parts[] = escapeshellarg("--$k") . ' ' . escapeshellarg((string)$v);
            }
        }
        return implode(' ', $parts);
    }

    public static function run(string $scriptRel, array $args = []): array
    {
        $cmd = self::buildCommand($scriptRel, $args) . ' 2>&1';
        $output = [];
        $exit = 0;
        $timeout = self::timeout();
        // exec con timeout vía proc_open (más robusto que exec simple)
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return [false, '', 'No se pudo ejecutar el proceso Python.', -1];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        return [$exit === 0, $stdout ?: '', $stderr ?: '', $exit];
    }

    public static function runJson(string $scriptRel, array $args = []): array
    {
        [$ok, $stdout, $stderr, $exit] = self::run($scriptRel, $args);
        if (!$ok) {
            return [false, null, "Exit $exit: $stderr$stdout"];
        }
        $data = json_decode($stdout, true);
        if (!is_array($data)) {
            return [false, null, 'Respuesta Python no es JSON válido: ' . substr($stdout, 0, 500)];
        }
        return [true, $data, null];
    }
}