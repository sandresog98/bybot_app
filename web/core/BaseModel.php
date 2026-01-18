<?php
/**
 * BaseModel - Clase base para todos los modelos
 * ByBot v2.0
 */

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/config/constants.php';

abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $timestamps = true;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Obtener conexión PDO
     */
    protected function getConnection() {
        return $this->db->getConnection();
    }
    
    /**
     * Obtener todos los registros
     */
    public function all($orderBy = null, $order = 'ASC') {
        $sql = "SELECT * FROM {$this->table}";
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy $order";
        }
        return $this->db->query($sql);
    }
    
    /**
     * Buscar por ID
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->queryOne($sql, [$id]);
    }
    
    /**
     * Buscar por campo específico
     */
    public function findBy($field, $value) {
        $sql = "SELECT * FROM {$this->table} WHERE $field = ?";
        return $this->db->queryOne($sql, [$value]);
    }
    
    /**
     * Buscar múltiples por campo
     */
    public function where($field, $value, $orderBy = null, $order = 'ASC') {
        $sql = "SELECT * FROM {$this->table} WHERE $field = ?";
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy $order";
        }
        return $this->db->query($sql, [$value]);
    }
    
    /**
     * Buscar con múltiples condiciones
     */
    public function whereMultiple(array $conditions, $orderBy = null, $order = 'ASC') {
        $whereClauses = [];
        $params = [];
        
        foreach ($conditions as $field => $value) {
            if ($value === null) {
                $whereClauses[] = "$field IS NULL";
            } else {
                $whereClauses[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        $sql = "SELECT * FROM {$this->table}";
        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy $order";
        }
        
        return $this->db->query($sql, $params);
    }
    
    /**
     * Crear nuevo registro
     */
    public function create(array $data) {
        // Filtrar solo campos permitidos
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }
        
        if (empty($data)) {
            throw new Exception("No hay datos válidos para insertar");
        }
        
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $this->db->execute($sql, array_values($data));
        return $this->db->lastInsertId();
    }
    
    /**
     * Actualizar registro
     */
    public function update($id, array $data) {
        // Filtrar solo campos permitidos
        if (!empty($this->fillable)) {
            $data = array_intersect_key($data, array_flip($this->fillable));
        }
        
        if (empty($data)) {
            return false;
        }
        
        $sets = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            $sets[] = "$field = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = ?",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );
        
        return $this->db->execute($sql, $params);
    }
    
    /**
     * Eliminar registro
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        return $this->db->execute($sql, [$id]);
    }
    
    /**
     * Soft delete (marcar como inactivo)
     */
    public function softDelete($id, $field = 'estado_activo') {
        return $this->update($id, [$field => 0]);
    }
    
    /**
     * Contar registros
     */
    public function count(array $conditions = []) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                if ($value === null) {
                    $whereClauses[] = "$field IS NULL";
                } else {
                    $whereClauses[] = "$field = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $result = $this->db->queryOne($sql, $params);
        return (int)$result['total'];
    }
    
    /**
     * Verificar si existe
     */
    public function exists($id) {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        return $this->db->queryOne($sql, [$id]) !== false;
    }
    
    /**
     * Paginación
     */
    public function paginate($page = 1, $perPage = 20, array $conditions = [], $orderBy = null, $order = 'DESC') {
        $page = max(1, (int)$page);
        $perPage = max(1, min(100, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        
        // Contar total
        $total = $this->count($conditions);
        $totalPages = ceil($total / $perPage);
        
        // Construir query
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                if ($value === null) {
                    $whereClauses[] = "$field IS NULL";
                } else {
                    $whereClauses[] = "$field = ?";
                    $params[] = $value;
                }
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy $order";
        }
        
        $sql .= " LIMIT $perPage OFFSET $offset";
        
        $data = $this->db->query($sql, $params);
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_more' => $page < $totalPages
            ]
        ];
    }
    
    /**
     * Búsqueda con LIKE
     */
    public function search($field, $term, $orderBy = null, $order = 'ASC') {
        $sql = "SELECT * FROM {$this->table} WHERE $field LIKE ?";
        if ($orderBy) {
            $sql .= " ORDER BY $orderBy $order";
        }
        return $this->db->query($sql, ["%$term%"]);
    }
    
    /**
     * Ejecutar query raw
     */
    public function raw($sql, $params = []) {
        return $this->db->query($sql, $params);
    }
    
    /**
     * Ejecutar query raw (un solo resultado)
     */
    public function rawOne($sql, $params = []) {
        return $this->db->queryOne($sql, $params);
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->db->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->db->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->db->rollback();
    }
}

