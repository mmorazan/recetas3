<?php
/**
 * GCODE Admin - Login con Debug
 * Usa este archivo para diagnosticar el problema
 */

// Activar mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🔍 Login con Debug</h1>";

// Verificar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>📝 Datos recibidos:</h2>";
    echo "Usuario: " . htmlspecialchars($_POST['username'] ?? 'NO ENVIADO') . "<br>";
    echo "Password: " . (isset($_POST['password']) ? 'SÍ (oculto)' : 'NO ENVIADO') . "<br><br>";
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo "❌ <strong>Error:</strong> Usuario y contraseña son requeridos<br>";
    } else {
        echo "<h2>🔗 Probando conexión a BD...</h2>";
        
        try {
            // Cargar configuración .env
            $config = [];
            if (file_exists('.env')) {
                echo "✅ Archivo .env encontrado<br>";
                $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                        list($key, $value) = explode('=', $line, 2);
                        $config[trim($key)] = trim($value);
                    }
                }
            } else {
                echo "❌ Archivo .env NO encontrado<br>";
            }
            
            $host = $config['DB_HOST'] ?? 'localhost';
            $dbname = $config['DB_NAME'] ?? 'gcode';
            $db_user = $config['DB_USER'] ?? 'root';
            $db_pass = $config['DB_PASS'] ?? '';
            
            echo "Conectando a: $host/$dbname con usuario: $db_user<br>";
            
            // Conectar a BD
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            echo "✅ <strong>Conexión a BD exitosa</strong><br><br>";
            
            echo "<h2>👤 Buscando usuario...</h2>";
            
            // Buscar usuario
            $stmt = $pdo->prepare("SELECT id, username, password_hash, active FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                echo "❌ <strong>Usuario '$username' no encontrado</strong><br>";
                echo "<br>Usuarios disponibles en la BD:<br>";
                
                $stmt = $pdo->query("SELECT username FROM admin_users");
                $usuarios = $stmt->fetchAll();
                foreach ($usuarios as $u) {
                    echo "- " . htmlspecialchars($u['username']) . "<br>";
                }
            } else {
                echo "✅ Usuario encontrado: " . htmlspecialchars($user['username']) . "<br>";
                echo "Estado activo: " . ($user['active'] ? 'SÍ' : 'NO') . "<br>";
                
                if (!$user['active']) {
                    echo "❌ <strong>La cuenta está desactivada</strong><br>";
                } else {
                    echo "<br><h2>🔐 Verificando contraseña...</h2>";
                    
                    // Verificar contraseña
                    if (password_verify($password, $user['password_hash'])) {
                        echo "✅ <strong>Contraseña correcta</strong><br>";
                        
                        echo "<br><h2>🎯 Iniciando sesión...</h2>";
                        
                        // Iniciar sesión
                        session_start();
                        session_regenerate_id(true);
                        
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['login_time'] = time();
                        
                        echo "✅ <strong>Sesión iniciada correctamente</strong><br>";
                        echo "ID de sesión: " . session_id() . "<br>";
                        echo "Usuario en sesión: " . $_SESSION['username'] . "<br>";
                        
                        echo "<br><div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
                        echo "<strong>🎉 LOGIN EXITOSO</strong><br>";
                        echo "<a href='index.php' style='color: #155724;'>→ Ir al Dashboard</a>";
                        echo "</div>";
                        
                    } else {
                        echo "❌ <strong>Contraseña incorrecta</strong><br>";
                        echo "<br><em>Probando con credenciales por defecto...</em><br>";
                        
                        // Probar contraseña por defecto
                        if (password_verify('admin123', $user['password_hash'])) {
                            echo "✅ La contraseña por defecto 'admin123' funciona<br>";
                        } else {
                            echo "❌ La contraseña por defecto tampoco funciona<br>";
                            echo "<br><strong>Solución:</strong> Resetear contraseña:<br>";
                            echo "<code>UPDATE admin_users SET password_hash = '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE username = 'admin';</code>";
                        }
                    }
                }
            }
            
        } catch (PDOException $e) {
            echo "❌ <strong>Error de base de datos:</strong> " . $e->getMessage() . "<br>";
            echo "<br><strong>Posibles soluciones:</strong><br>";
            echo "1. Verificar que MySQL esté ejecutándose<br>";
            echo "2. Verificar credenciales en .env<br>";
            echo "3. Ejecutar setup.sql<br>";
        } catch (Exception $e) {
            echo "❌ <strong>Error general:</strong> " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<hr>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCODE Admin - Login Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4>🔍 Login con Debug</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" name="username" class="form-control" 
                                       value="admin" required>
                                <small class="text-muted">Por defecto: admin</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña</label>
                                <input type="password" name="password" class="form-control" 
                                       value="admin123" required>
                                <small class="text-muted">Por defecto: admin123</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                🔍 Probar Login
                            </button>
                        </form>
                        
                        <div class="mt-4">
                            <h6>🔧 Enlaces útiles:</h6>
                            <a href="diagnostico.php" class="btn btn-secondary btn-sm">Diagnóstico General</a>
                            <a href="login.php" class="btn btn-outline-primary btn-sm">Login Original</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>