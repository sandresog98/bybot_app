<?php
declare(strict_types=1);

/**
 * BaseModel — CRUD genérico simple sobre PDO.
 * Las subclases definen tabla y campos; BaseModel aporta findOne/findAll/insert/update/delete.
 */

namespace Core;

use PDO;

class BaseModel
{
    protected string $table;
    protected string $pk = 'id';
    /** Columnas permitidas para asignación masiva. */
    protected array $fillable = [];

    protected function pdo(): PDO
    {
        return Database::pdo();
    }

    public function find(int|array $idOrWhere): ?array
    {
        if (is_int($idOrWhere)) {
            $sql = "SELECT * FROM {$this->table} WHERE {$this->pk} = ?";
            $stmt = $this->pdo()->prepare($sql);
            $stmt->execute([$idOrWhere]);
            return $stmt->fetch() ?: null;
        }
        $where = [];
        $args = [];
        foreach ($idOrWhere as $k => $v) {
            $where[] = "$k = ?";
            $args[] = $v;
        }
        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where) . ' LIMIT 1';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetch() ?: null;
    }

    public function findAll(array $where = [], array $opts = []): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $args = [];
        if ($where) {
            $clauses = [];
            foreach ($where as $k => $v) {
                $clauses[] = "$k = ?";
                $args[] = $v;
            }
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }
        if (isset($opts['order'])) {
            $sql .= ' ORDER BY ' . $opts['order'];
        }
        if (isset($opts['limit'])) {
            $sql .= ' LIMIT ' . (int)$opts['limit'];
            if (isset($opts['offset'])) {
                $sql .= ' OFFSET ' . (int)$opts['offset'];
            }
        }
        return $this->pdo()->prepare($sql)->execute($args) ? $this->pdo()->query($sql)->fetchAll() : [];
    }

    public function insert(array $data): int
    {
        $data = $this->onlyFillable($data);
        $cols = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO {$this->table} (" . implode(',', $cols) . ") VALUES ($placeholders)";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(array_values($data));
        return (int)$this->pdo()->lastInsertId();
    }

    public function update(int $id, array $data): int
    {
        $data = $this->onlyFillable($data);
        if (!$data) {
            return 0;
        }
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "$col = ?";
        }
        $sql = "UPDATE {$this->table} SET " . implode(',', $set) . " WHERE {$this->pk} = ?";
        $args = array_values($data);
        $args[] = $id;
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($args);
        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->pk} = ?";
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    protected function onlyFillable(array $data): array
    {
        if (!$this->fillable) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }
}