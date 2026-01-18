<?php
// test_urls.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de URLs</h2>";

try {
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/web/admin/config/paths.php';
    
    echo "<h3>Constantes definidas:</h3>";
    echo "<ul>";
    echo "<li><strong>APP_URL:</strong> " . (defined('APP_URL') ? APP_URL : 'NO DEFINIDA') . "</li>";
    echo "<li><strong>ADMIN_URL:</strong> " . (defined('ADMIN_URL') ? ADMIN_URL : 'NO DEFINIDA') . "</li>";
    echo "<li><strong>API_URL:</strong> " . (defined('API_URL') ? API_URL : 'NO DEFINIDA') . "</li>";
    echo "<li><strong>ASSETS_URL:</strong> " . (defined('ASSETS_URL') ? ASSETS_URL : 'NO DEFINIDA') . "</li>";
    echo "</ul>";
    
    echo "<h3>Funciones de URL:</h3>";
    echo "<ul>";
    echo "<li><strong>adminUrl('index.php'):</strong> " . adminUrl('index.php') . "</li>";
    echo "<li><strong>adminUrl('login.php'):</strong> " . adminUrl('login.php') . "</li>";
    echo "<li><strong>apiUrl('auth/login'):</strong> " . apiUrl('auth/login') . "</li>";
    echo "<li><strong>assetUrl('css/common.css'):</strong> " . assetUrl('css/common.css') . "</li>";
    echo "</ul>";
    
    echo "<h3>Información del servidor:</h3>";
    echo "<ul>";
    echo "<li><strong>SCRIPT_NAME:</strong> " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "</li>";
    echo "<li><strong>HTTP_HOST:</strong> " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "</li>";
    echo "<li><strong>REQUEST_URI:</strong> " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "</li>";
    echo "</ul>";
    
    echo "<p style='color:green;font-weight:bold'>✅ Test completado</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

