<?php
/**
 * Configuración de Base de Datos - ByBot v2.0
 */

require_once __DIR__ . '/env_loader.php';

/**
 * Obtener conexión PDO a la base de datos
 */
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '3306');
        $dbname = env('DB_NAME', 'bybot');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', '');
        
        try {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
            $conn = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                throw new Exception("Error de conexión a BD: " . $e->getMessage());
            } else {
                throw new Exception("Error de conexión a la base de datos");
            }
        }
    }
    
    return $conn;
}

/**
 * Clase para gestión de conexiones (Singleton Pattern)
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $this->connection = getConnection();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecutar query SELECT
     */
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Ejecutar query SELECT y obtener un solo registro
     */
    public function queryOne($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Ejecutar INSERT/UPDATE/DELETE
     */
    public function execute($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Obtener último ID insertado
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Confirmar transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Revertir transacción
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Verificar si hay transacción activa
     */
    public function inTransaction() {
        return $this->connection->inTransaction();
    }
}

