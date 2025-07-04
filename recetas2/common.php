<?php
/**
 * GCODE Admin - Configuración Común Simplificada
 * Colocar este archivo en la RAÍZ del proyecto (mismo nivel que index.php)
 */

// Activar errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Variables globales para evitar redefiniciones
if (!defined('GCODE_COMMON_LOADED')) {
    define('GCODE_COMMON_LOADED', true);

    // Función para cargar configuración .env
    function loadEnvironment() {
        static $config = null;
        
        if ($config === null) {
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
        }
        
        return $config;
    }

    // Función para conectar a BD
    function getConnection() {
        static $pdo = null;
        
        if ($pdo === null) {
            $config = loadEnvironment();
            
            $host = $config['DB_HOST'] ?? 'localhost';
            $dbname = $config['DB_NAME'] ?? 'gcode';
            $username = $config['DB_USER'] ?? 'gcode';
            $password = $config['DB_PASS'] ?? '';
            
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                die("Error de conexión: " . $e->getMessage());
            }
        }
        
        return $pdo;
    }

    // Función para verificar autenticación
    function requireAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
            header('Location: login.php');
            exit;
        }
        
        // Verificar timeout (1 hora)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 3600) {
            session_destroy();
            header('Location: login.php?message=session_expired');
            exit;
        }
        
        // Actualizar tiempo
        $_SESSION['login_time'] = time();
        
        return [
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ];
    }

    // Tablas permitidas - CONFIGURAR AQUÍ
    function getAllowedTables() {
        return [
            'users', 
            'products', 
            'admin_users', 
            'activity_log',
            'categories',
            'orders'
        ];
    }

    // Función para validar tabla
    function validateTable($tableName) {
        $allowedTables = getAllowedTables();
        
        if (empty($tableName) || !in_array($tableName, $allowedTables)) {
            throw new Exception("Tabla no permitida: " . $tableName);
        }
        
        return true;
    }

    // Función para obtener clave primaria
    function getPrimaryKey($tableName) {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['Column_name'] : null;
    }

    // Función para validar paginación
    function validatePaginationParams($page, $limit) {
        $page = max(1, (int)$page);
        $limit = min(100, max(1, (int)$limit));
        return [$page, $limit];
    }

    // Función para validar búsqueda
    function validateSearchTerm($term) {
        $term = trim($term ?? '');
        return strlen($term) > 100 ? substr($term, 0, 100) : $term;
    }

    // Función simple para logging
    function logActivity($action, $userId = null, $username = null) {
        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, username, action, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId ?? $_SESSION['user_id'] ?? null,
                $username ?? $_SESSION['username'] ?? 'unknown',
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Ignorar errores de log
        }
    }
}

// Mensaje de confirmación
echo "<!-- GCODE Common.php cargado correctamente -->\n";
?>