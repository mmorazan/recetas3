<?php
/**
 * GCODE Admin - Configuración Común
 * Incluir este archivo en todos los PHP para evitar repetir código
 */

// Configuración de errores (cambiar según el entorno)
$debug_mode = true; // Cambiar a false en producción

if ($debug_mode) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Función para cargar configuración .env
function loadEnvironment() {
    static $config = null;
    
    if ($config === null) {
        $config = [];
        $envFile = __DIR__ . '/../.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                    list($key, $value) = explode('=', $line, 2);
                    $config[trim($key)] = trim($value);
                }
            }
        }
    }
    
    return $config;
}

// Función para conectar a BD (singleton)
function getConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = loadEnvironment();
        
        $host = $config['DB_HOST'] ?? 'localhost';
        $dbname = $config['DB_NAME'] ?? 'Recetas';
        $username = $config['DB_USER'] ?? 'recetas';
        $password = $config['DB_PASS'] ?? 'gcode2025!';
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Error de conexión a la base de datos. Revise los logs.");
        }
    }
    
    return $pdo;
}

// Función para obtener clave primaria de una tabla
function getPrimaryKey($tableName) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
    $stmt->execute();
    $result = $stmt->fetch();
    return $result ? $result['Column_name'] : null;
}

// Función para validar tabla permitida
function validateTable($tableName) {
    $allowedTables = getAllowedTables();
    
    if (empty($tableName) || !is_string($tableName)) {
        throw new InvalidArgumentException("Nombre de tabla inválido");
    }
    
    if (!in_array($tableName, $allowedTables)) {
        throw new InvalidArgumentException("Tabla no permitida: " . $tableName);
    }
    
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
        throw new InvalidArgumentException("Nombre de tabla contiene caracteres inválidos");
    }
    
    return true;
}

// Función centralizada para obtener tablas permitidas
function getAllowedTables() {
    // CONFIGURAR AQUÍ LAS TABLAS PERMITIDAS
    return [
        'users', 
        'products', 
        'admin_users', 
        'activity_log',
        'categories',
        'orders',
		'Ingredientes',
		'Recetas',
		'Menu'
        // Agregar más tablas según necesites
    ];
}

// Función para validar ID
function validateId($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException("ID inválido");
    }
    return $id;
}

// Función para validar término de búsqueda
function validateSearchTerm($term) {
    if (empty($term)) {
        return '';
    }
    
    $term = trim($term);
    if (strlen($term) > 100) {
        throw new InvalidArgumentException("Término de búsqueda demasiado largo");
    }
    
    return $term;
}

// Función para validar parámetros de paginación
function validatePaginationParams($page, $limit) {
    $page = filter_var($page, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'default' => 1]
    ]);
    
    $limit = filter_var($limit, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 20]
    ]);
    
    return [$page, $limit];
}

// Función para sanitizar entrada
function sanitizeInput($input, $type = 'string') {
    if ($input === null) {
        return null;
    }
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);
        case 'string':
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

// Función para obtener IP del cliente
function getClientIP() {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Función para logging de actividades
function logActivity($action, $userId = null, $username = null, $details = []) {
    try {
        $pdo = getConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, username, action, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $username ?? $_SESSION['username'] ?? 'unknown',
            $action,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // No detener la aplicación si falla el log
        error_log("Error logging activity: " . $e->getMessage());
    }
}

// Función para logging de eventos de seguridad
function logSecurityEvent($event, $details = []) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    error_log("SECURITY EVENT: " . json_encode($logData));
}

// Función para verificar autenticación
function requireAuth() {
    session_start();
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
    
    // Verificar timeout de sesión (1 hora por defecto)
    $session_timeout = 3600;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_timeout) {
        session_destroy();
        header('Location: login.php?message=session_expired');
        exit;
    }
    
    // Actualizar tiempo de actividad
    $_SESSION['login_time'] = time();
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username']
    ];
}

// Función para verificar si está autenticado (sin redireccionar)
function isAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }
    
    // Verificar timeout
    $session_timeout = 3600;
    if (time() - $_SESSION['login_time'] > $session_timeout) {
        return false;
    }
    
    return true;
}

// Función para generar token CSRF
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

// Función para validar token CSRF
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para mostrar mensajes de sesión
function getSessionMessage($type = 'error') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $message = $_SESSION[$type . '_message'] ?? '';
    if ($message) {
        unset($_SESSION[$type . '_message']);
    }
    
    return $message;
}

// Función para establecer mensajes de sesión
function setSessionMessage($message, $type = 'error') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION[$type . '_message'] = $message;
}

// Función para renderizar el header HTML común
function renderHeader($title = 'GCODE Admin', $additionalCSS = '') {
    echo '<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar { 
            background: linear-gradient(45deg, #2c3e50, #3498db) !important; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        ' . $additionalCSS . '
    </style>
</head>
<body>';
}

// Función para renderizar la navegación común
function renderNavigation($user = null) {
    if (!$user) {
        $userInfo = requireAuth();
        $user = $userInfo;
    }
    
    echo '<nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-database"></i> GCODE Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> ' . htmlspecialchars($user['username']) . '
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>';
}

// Función para renderizar el footer HTML común
function renderFooter($additionalJS = '') {
    echo '
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll(".alert");
            alerts.forEach(function(alert) {
                if (alert.classList.contains("alert-success") || alert.classList.contains("alert-info")) {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) bsAlert.close();
                }
            });
        }, 5000);
        
        ' . $additionalJS . '
    </script>
</body>
</html>';
}

// Auto-incluir funciones si no están en modo de solo definiciones
if (!defined('COMMON_FUNCTIONS_ONLY')) {
    // Este archivo se puede incluir normalmente
}
?>