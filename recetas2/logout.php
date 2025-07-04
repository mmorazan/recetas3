<?php
/**
 * GCODE Admin - Logout Seguro
 */

session_start();

// Log del logout si hay sesión activa
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    // Intentar registrar el logout
    try {
        // Cargar configuración
        $config = [];
        if (file_exists('.env')) {
            $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                    list($key, $value) = explode('=', $line, 2);
                    $config[trim($key)] = trim($value);
                }
            }
        }
        
        $host = $config['DB_HOST'] ?? 'localhost';
        $dbname = $config['DB_NAME'] ?? 'gcode';
        $username = $config['DB_USER'] ?? 'gcode';
        $password = $config['DB_PASS'] ?? '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Registrar logout
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, username, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['username'],
            'logout',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        // No detener el logout si falla el log
        error_log("Error logging logout: " . $e->getMessage());
    }
}

// Destruir sesión completamente
$_SESSION = array();

// Eliminar cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destruir sesión
session_destroy();

// Redireccionar al login
header('Location: login.php?message=logout_success');
exit;
?>