<?php
/**
 * Configuración de la base de datos - ByBot App
 * Motor: 11.8.3-MariaDB-log
 */

// Cargar variables de entorno desde .env
require_once __DIR__ . '/env_loader.php';
loadEnv();

// Detección de entorno: APP_ENV (development|production)
$__appEnv = env('APP_ENV');
if ($__appEnv === null || $__appEnv === '') {
    $isCli = php_sapi_name() === 'cli';
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $serverAddr = $_SERVER['SERVER_ADDR'] ?? '';
    $httpHost   = $_SERVER['HTTP_HOST'] ?? '';
    $looksLocal = (
        $serverName === 'localhost' ||
        $httpHost === 'localhost' ||
        $serverAddr === '127.0.0.1' ||
        strpos($httpHost, '.local') !== false ||
        $isCli
    );
    $__appEnv = $looksLocal ? 'development' : 'production';
}
define('APP_ENV', $__appEnv);

// Configuración de base de datos desde variables de entorno
$dbHost = env('DB_HOST', 'localhost');
$dbUser = env('DB_USER', null);
$dbPass = env('DB_PASS', '');
$dbName = env('DB_NAME', null);

// Validar que las variables críticas estén definidas
if ($dbUser === null || $dbUser === '') {
    $envFile = __DIR__ . '/../.env';
    $envExists = file_exists($envFile) ? 'existe' : 'NO existe';
    die("Error: DB_USER no está definido. Por favor, configura el archivo .env o las variables de entorno del sistema.\n" .
        "Archivo .env: $envFile ($envExists)");
}
if ($dbName === null || $dbName === '') {
    $envFile = __DIR__ . '/../.env';
    $envExists = file_exists($envFile) ? 'existe' : 'NO existe';
    die("Error: DB_NAME no está definido. Por favor, configura el archivo .env o las variables de entorno del sistema.\n" .
        "Archivo .env: $envFile ($envExists)");
}

define('DB_HOST', $dbHost);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_NAME', $dbName);

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
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
    
    private function __clone() {}
    
    public function __wakeup() {}
}

function getConnection() {
    return Database::getInstance()->getConnection();
}

function testConnection() {
    try {
        $conn = Database::getInstance()->getConnection();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

