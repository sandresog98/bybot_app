<?php
// test_database.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Base de Datos</h2>";

try {
    require_once __DIR__ . '/config/database.php';
    
    $conn = getConnection();
    
    if ($conn) {
        echo "<p>✅ Conexión exitosa a la base de datos</p>";
        
        // Probar query simple
        $stmt = $conn->query("SELECT 1 as test");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['test'] == 1) {
            echo "<p>✅ Query de prueba exitoso</p>";
        }
        
        // Verificar tablas
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>✅ Tablas encontradas: " . count($tables) . "</p>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>" . htmlspecialchars($table) . "</li>";
        }
        echo "</ul>";
        
        // Verificar usuario
        $stmt = $conn->query("SELECT COUNT(*) as total FROM control_usuarios");
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>✅ Usuarios en BD: " . $userCount['total'] . "</p>";
        
        echo "<p style='color:green;font-weight:bold'>✅ FASE 1.3 COMPLETADA</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>❌ Error de conexión: " . $e->getMessage() . "</p>";
    echo "<p>Verifica las credenciales en .env</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

