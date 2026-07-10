<?php
declare(strict_types=1);

/**
 * BaseService — base para servicios de negocio que envuelven modelos.
 * Sirve como sitio común para transacciones, segmentación de lógica y
 * disparo de auditoría (control_logs) y eventos de historial.
 */

namespace Core;

abstract class BaseService
{
    protected function pdo(): \PDO
    {
        return Database::pdo();
    }

    /** Log a control_logs (auditoría). */
    protected function audit(
        string $accion,
        string $modulo,
        ?int $entidadId = null,
        ?string $entidadTipo = null,
        ?string $detalle = null,
        ?int $usuarioId = null,
        string $nivel = 'info'
    ): void {
        $sql = "INSERT INTO control_logs
                (usuario_id, accion, modulo, entidad_id, entidad_tipo, detalle, ip, user_agent, nivel)
                VALUES (?,?,?,?,?,?,?,?,?)";
        $pdo = $this->pdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $usuarioId,
            $accion,
            $modulo,
            $entidadId,
            $entidadTipo,
            $detalle,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            $nivel,
        ]);
    }

    protected function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    protected function commit(): void
    {
        $this->pdo()->commit();
    }

    protected function rollBack(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }
}