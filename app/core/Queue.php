<?php
declare(strict_types=1);

/**
 * Queue — Cola simple persistida en app_colas_trabajos.
 * PHP encola, un daemon Python (en Fase 2) desencola y ejecuta.
 * En Fase 0 sin daemon; se mantiene la tabla lista.
 */

namespace Core;

use PDO;

final class Queue
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    /** Encola un trabajo. Devuelve job_id (uuid). */
    public function push(string $cola, string $tipoTrabajo, array $payload, ?int $procesoId = null, int $prioridad = 5): string
    {
        $jobId = sprintf(
            '%s-%s',
            $cola,
            bin2hex(random_bytes(16))
        );
        $stmt = $this->pdo->prepare(
            "INSERT INTO app_colas_trabajos
             (job_id, cola, proceso_id, tipo_trabajo, estado, payload, prioridad)
             VALUES (?,?,?,?, 'pendiente', ?, ?)"
        );
        $stmt->execute([
            $jobId, $cola, $procesoId, $tipoTrabajo,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $prioridad,
        ]);
        return $jobId;
    }

    public function getStatus(string $jobId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT job_id, estado, resultado, error_mensaje, intentos, started_at, finished_at, duracion_ms
             FROM app_colas_trabajos WHERE job_id = ? LIMIT 1"
        );
        $stmt->execute([$jobId]);
        return $stmt->fetch() ?: null;
    }
}