<?php
// test_admin_urls.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de URLs del Admin</h2>";

try {
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/web/admin/config/paths.php';
    
    echo "<h3>Constantes:</h3>";
    echo "<ul>";
    echo "<li><strong>APP_URL:</strong> " . APP_URL . "</li>";
    echo "<li><strong>ADMIN_URL:</strong> " . ADMIN_URL . "</li>";
    echo "</ul>";
    
    echo "<h3>Pruebas de adminUrl():</h3>";
    echo "<ul>";
    echo "<li><strong>adminUrl('index.php'):</strong> " . adminUrl('index.php') . "</li>";
    echo "<li><strong>adminUrl('index.php?page=procesos'):</strong> " . adminUrl('index.php?page=procesos') . "</li>";
    echo "<li><strong>adminUrl('index.php?page=usuarios'):</strong> " . adminUrl('index.php?page=usuarios') . "</li>";
    echo "<li><strong>adminUrl('login.php'):</strong> " . adminUrl('login.php') . "</li>";
    echo "</ul>";
    
    echo "<h3>Información del servidor:</h3>";
    echo "<ul>";
    echo "<li><strong>SCRIPT_NAME:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "</li>";
    echo "<li><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "</li>";
    echo "</ul>";
    
    // Probar construcción manual
    echo "<h3>URL esperada:</h3>";
    $expected = "https://bybjuridicos.andapps.cloud/web/admin/index.php?page=procesos";
    echo "<p>Esperada: <code>" . $expected . "</code></p>";
    echo "<p>Generada: <code>" . adminUrl('index.php?page=procesos') . "</code></p>";
    
    if (adminUrl('index.php?page=procesos') === $expected) {
        echo "<p style='color:green;font-weight:bold'>✅ URLs correctas</p>";
    } else {
        echo "<p style='color:red;font-weight:bold'>❌ URLs incorrectas</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

