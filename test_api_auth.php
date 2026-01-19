<?php
// test_api_auth.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Test de Autenticación API</h2>";

try {
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/web/admin/config/paths.php';
    require_once __DIR__ . '/web/admin/utils/session.php';
    
    // Iniciar sesión
    initSession();
    
    echo "<h3>Estado de la sesión:</h3>";
    echo "<ul>";
    echo "<li><strong>Session ID:</strong> " . session_id() . "</li>";
    echo "<li><strong>User ID en sesión:</strong> " . ($_SESSION['user_id'] ?? 'NO DEFINIDO') . "</li>";
    echo "<li><strong>Usuario en sesión:</strong> " . (isset($_SESSION['user']) ? json_encode($_SESSION['user']) : 'NO DEFINIDO') . "</li>";
    echo "</ul>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<p style='color:orange'>⚠️ No hay sesión activa. Necesitas hacer login primero.</p>";
        echo "<p><a href='web/admin/login.php'>Ir al login</a></p>";
        exit;
    }
    
    echo "<h3>Prueba de llamada a la API:</h3>";
    
    // Simular una llamada fetch desde JavaScript
    $apiUrl = API_URL . '/usuarios';
    echo "<p>URL de la API: <code>" . $apiUrl . "</code></p>";
    
    // Hacer una llamada usando cURL con las cookies de sesión
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
    
    if ($error) {
        echo "<p style='color:red'>❌ Error cURL: $error</p>";
    }
    
    if ($httpCode === 200) {
        echo "<p style='color:green'>✅ API responde correctamente</p>";
        $data = json_decode($response, true);
        if ($data) {
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
    } elseif ($httpCode === 401) {
        echo "<p style='color:red'>❌ Error 401: No autenticado</p>";
        echo "<p>Esto significa que la sesión no se está compartiendo correctamente entre el admin y la API.</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p style='color:orange'>⚠️ Respuesta inesperada (HTTP $httpCode)</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
    
    echo "<h3>Configuración de sesión PHP:</h3>";
    echo "<ul>";
    echo "<li><strong>session.cookie_path:</strong> " . ini_get('session.cookie_path') . "</li>";
    echo "<li><strong>session.cookie_domain:</strong> " . ini_get('session.cookie_domain') . "</li>";
    echo "<li><strong>session.cookie_httponly:</strong> " . (ini_get('session.cookie_httponly') ? 'Sí' : 'No') . "</li>";
    echo "<li><strong>session.cookie_secure:</strong> " . (ini_get('session.cookie_secure') ? 'Sí' : 'No') . "</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

