<?php
/**
 * Página de Login
 */

require_once __DIR__ . '/config/paths.php';
require_once __DIR__ . '/utils/session.php';

// Si ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    redirect('index.php');
}

$error = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($usuario) || empty($password)) {
        $error = 'Por favor ingrese usuario y contraseña';
    } else {
        // Verificar credenciales
        require_once BASE_DIR . '/config/database.php';
        
        try {
            $db = getConnection();
            $stmt = $db->prepare("
                SELECT id, usuario, password, nombre_completo, email, rol, estado_activo
                FROM control_usuarios 
                WHERE usuario = ?
            ");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'Usuario o contraseña incorrectos';
            } elseif (!$user['estado_activo']) {
                $error = 'Tu cuenta ha sido desactivada. Contacta al administrador.';
            } elseif (!password_verify($password, $user['password'])) {
                $error = 'Usuario o contraseña incorrectos';
            } else {
                // Login exitoso
                initSession();
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'usuario' => $user['usuario'],
                    'nombre_completo' => $user['nombre_completo'],
                    'email' => $user['email'],
                    'rol' => $user['rol']
                ];
                $_SESSION['login_time'] = time();
                $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                // Actualizar último acceso
                $updateStmt = $db->prepare("UPDATE control_usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                // Registrar log
                $logStmt = $db->prepare("
                    INSERT INTO control_logs (id_usuario, accion, modulo, detalle, ip_address, user_agent)
                    VALUES (?, 'login', 'auth', 'Login exitoso', ?, ?)
                ");
                $logStmt->execute([
                    $user['id'],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                ]);
                
                redirect('index.php');
            }
            
        } catch (Exception $e) {
            error_log("Error en login: " . $e->getMessage());
            $error = 'Error al conectar con el servidor. Intente más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - ByBot</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #55A5C8;
            --primary-dark: #35719E;
            --success: #9AD082;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 50%, var(--success) 100%);
            background-size: 200% 200%;
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }
        
        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 15px 50px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            padding: 2rem;
            text-align: center;
            color: white;
        }
        
        .login-header h1 {
            font-weight: 800;
            margin: 0;
            font-size: 2rem;
        }
        
        .login-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-floating .form-control {
            border-radius: 0.5rem;
            border: 2px solid #e9ecef;
            padding: 1rem 0.75rem;
        }
        
        .form-floating .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(85, 165, 200, 0.25);
        }
        
        .form-floating label {
            padding: 1rem 0.75rem;
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(85, 165, 200, 0.4);
        }
        
        .input-group-text {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
            border-left: 4px solid var(--bs-danger);
        }
        
        .logo-icon {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="bi bi-robot"></i>
                </div>
                <h1>ByBot</h1>
                <p>Panel de Administración</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="usuario" name="usuario" 
                               placeholder="Usuario" value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" 
                               required autofocus>
                        <label for="usuario"><i class="bi bi-person me-2"></i>Usuario</label>
                    </div>
                    
                    <div class="form-floating mb-4">
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Contraseña" required>
                        <label for="password"><i class="bi bi-lock me-2"></i>Contraseña</label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <small class="text-muted">
                        ByBot v<?= APP_VERSION ?? '1.0.0' ?> &copy; <?= date('Y') ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

