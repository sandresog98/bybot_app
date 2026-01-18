<?php
// test_config.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Configuración</h2>";

try {
    require_once __DIR__ . '/config/constants.php';
    
    echo "<p>✅ constants.php cargado correctamente</p>";
    echo "<p>APP_URL: " . APP_URL . "</p>";
    echo "<p>APP_ENV: " . APP_ENV . "</p>";
    echo "<p>BYBOT_ROOT: " . BYBOT_ROOT . "</p>";
    
    // Probar clases
    echo "<p>Estados disponibles: " . implode(', ', EstadoProceso::todos()) . "</p>";
    echo "<p>Roles disponibles: " . implode(', ', RolUsuario::todos()) . "</p>";
    
    echo "<p style='color:green;font-weight:bold'>✅ FASE 1.2 COMPLETADA</p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

