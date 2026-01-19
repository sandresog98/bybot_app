<?php
// test_api_connection.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Conexión a la API</h2>";

try {
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/web/admin/config/paths.php';
    
    echo "<h3>URLs configuradas:</h3>";
    echo "<ul>";
    echo "<li><strong>API_URL:</strong> " . (defined('API_URL') ? API_URL : 'NO DEFINIDA') . "</li>";
    echo "<li><strong>ADMIN_URL:</strong> " . (defined('ADMIN_URL') ? ADMIN_URL : 'NO DEFINIDA') . "</li>";
    echo "</ul>";
    
    if (!defined('API_URL')) {
        echo "<p style='color:red'>❌ API_URL no está definida</p>";
        exit;
    }
    
    // Simular una llamada a la API desde JavaScript
    echo "<h3>Prueba de Endpoints:</h3>";
    echo "<p>Las siguientes URLs deberían funcionar:</p>";
    echo "<ul>";
    echo "<li><a href='" . API_URL . "/health' target='_blank'>" . API_URL . "/health</a></li>";
    echo "<li><a href='" . API_URL . "/usuarios' target='_blank'>" . API_URL . "/usuarios</a> (requiere autenticación)</li>";
    echo "<li><a href='" . API_URL . "/procesos' target='_blank'>" . API_URL . "/procesos</a> (requiere autenticación)</li>";
    echo "</ul>";
    
    // Probar endpoint de health (no requiere autenticación)
    echo "<h3>Test de Health Endpoint:</h3>";
    $ch = curl_init(API_URL . '/health');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "<p style='color:green'>✅ Health endpoint responde correctamente</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p style='color:red'>❌ Health endpoint no responde (HTTP $httpCode)</p>";
        if ($response) {
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    }
    
    echo "<h3>JavaScript CONFIG esperado:</h3>";
    echo "<pre>";
    echo "const CONFIG = {\n";
    echo "    apiUrl: '" . API_URL . "',\n";
    echo "    adminUrl: '" . ADMIN_URL . "',\n";
    echo "    csrfToken: '...'\n";
    echo "};\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

