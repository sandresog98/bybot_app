<?php
// test_login_debug.php - Eliminar después de probar
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/php_errors.log');

echo "<h2>Test de Login - Debug</h2>";

try {
    // Cargar configuración
    require_once __DIR__ . '/config/constants.php';
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/web/admin/config/paths.php';
    require_once __DIR__ . '/web/admin/utils/session.php';
    
    echo "<p>✅ Archivos cargados correctamente</p>";
    
    // Simular datos de login
    $usuario = 'admin'; // Cambia esto por el usuario que insertaste
    $password = 'admin123'; // Cambia esto por la contraseña
    
    echo "<p>Intentando login con usuario: " . htmlspecialchars($usuario) . "</p>";
    
    // Verificar conexión
    $db = getConnection();
    echo "<p>✅ Conexión a BD exitosa</p>";
    
    // Buscar usuario
    $stmt = $db->prepare("
        SELECT id, usuario, password, nombre_completo, email, rol, estado_activo
        FROM control_usuarios 
        WHERE usuario = ?
    ");
    $stmt->execute([$usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p style='color:red'>❌ Usuario no encontrado</p>";
        // Mostrar usuarios disponibles
        $allUsers = $db->query("SELECT id, usuario, email, rol FROM control_usuarios")->fetchAll();
        echo "<p>Usuarios disponibles:</p><ul>";
        foreach ($allUsers as $u) {
            echo "<li>ID: {$u['id']}, Usuario: {$u['usuario']}, Email: {$u['email']}, Rol: {$u['rol']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>✅ Usuario encontrado:</p>";
        echo "<ul>";
        echo "<li>ID: " . $user['id'] . "</li>";
        echo "<li>Usuario: " . htmlspecialchars($user['usuario']) . "</li>";
        echo "<li>Email: " . htmlspecialchars($user['email'] ?? 'N/A') . "</li>";
        echo "<li>Rol: " . htmlspecialchars($user['rol']) . "</li>";
        echo "<li>Estado activo: " . ($user['estado_activo'] ? 'Sí' : 'No') . " (valor: " . var_export($user['estado_activo'], true) . ")</li>";
        echo "</ul>";
        
        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            echo "<p style='color:red'>❌ Contraseña incorrecta</p>";
        } else {
            echo "<p style='color:green'>✅ Contraseña correcta</p>";
            
            // Probar sesión
            try {
                initSession();
                echo "<p>✅ Sesión iniciada</p>";
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'usuario' => $user['usuario'],
                    'nombre_completo' => $user['nombre_completo'],
                    'email' => $user['email'],
                    'rol' => $user['rol']
                ];
                echo "<p>✅ Variables de sesión establecidas</p>";
                
                // Probar actualización de último acceso
                try {
                    $updateStmt = $db->prepare("UPDATE control_usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                    echo "<p>✅ Último acceso actualizado</p>";
                } catch (Exception $e) {
                    echo "<p style='color:orange'>⚠️ Error al actualizar último acceso: " . $e->getMessage() . "</p>";
                }
                
                // Probar inserción de log
                try {
                    $logStmt = $db->prepare("
                        INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address, user_agent)
                        VALUES (?, 'login', 'auth', 'Login exitoso', ?, ?)
                    ");
                    $logStmt->execute([
                        $user['id'],
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    ]);
                    echo "<p>✅ Log insertado correctamente</p>";
                } catch (Exception $e) {
                    echo "<p style='color:orange'>⚠️ Error al insertar log: " . $e->getMessage() . "</p>";
                    echo "<pre>" . $e->getTraceAsString() . "</pre>";
                }
                
                // Probar redirect
                echo "<p>✅ Todo funcionó correctamente. El redirect debería funcionar.</p>";
                echo "<p><a href='web/admin/index.php'>Ir al dashboard</a></p>";
                
            } catch (Exception $e) {
                echo "<p style='color:red'>❌ Error en sesión: " . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error general: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

