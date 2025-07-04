<?php
/**
 * GCODE Admin - Configuración Segura de Base de Datos
 * Versión: 2.0 Secure
 */

class DatabaseConfig {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->loadEnvironment();
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadEnvironment() {
        if (file_exists(__DIR__ . '/../.env')) {
            $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !startsWith($line, '#')) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
    
    private function connect() {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'Recetas';
        $username = $_ENV['DB_USER'] ?? 'recetas';
        $password = $_ENV['DB_PASS'] ?? 'gcode2025!';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];
        
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Error de conexión a la base de datos. Revise los logs.");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

function startsWith($string, $prefix) {
    return substr($string, 0, strlen($prefix)) === $prefix;
}
?>