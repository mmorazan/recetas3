<?php
/**
 * GCODE Admin - Sistema de Autenticación Seguro
 */

require_once 'database.php';

class AuthManager {
    private $pdo;
    private $sessionTimeout = 3600; // 1 hora
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900; // 15 minutos
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getInstance()->getConnection();
        $this->startSecureSession();
    }
    
    private function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => $this->sessionTimeout,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            session_start();
        }
    }
    
    public function login($username, $password) {
        // Verificar rate limiting
        if (!$this->checkRateLimit($username)) {
            $this->logActivity('login_blocked_rate_limit', null, $username);
            return ['success' => false, 'message' => 'Demasiados intentos. Intente más tarde.'];
        }
        
        // Validar entrada
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Usuario y contraseña son requeridos.'];
        }
        
        // Buscar usuario
        $stmt = $this->pdo->prepare("
            SELECT id, username, password_hash, active, failed_attempts, locked_until 
            FROM admin_users 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $this->logActivity('login_failed_user_not_found', null, $username);
            return ['success' => false, 'message' => 'Credenciales inválidas.'];
        }
        
        // Verificar si está bloqueado
        if ($user['locked_until'] && time() < strtotime($user['locked_until'])) {
            return ['success' => false, 'message' => 'Cuenta temporalmente bloqueada.'];
        }
        
        // Verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            $this->incrementFailedAttempts($user['id']);
            $this->logActivity('login_failed_wrong_password', $user['id'], $username);
            return ['success' => false, 'message' => 'Credenciales inválidas.'];
        }
        
        // Verificar si está activo
        if (!$user['active']) {
            return ['success' => false, 'message' => 'Cuenta desactivada.'];
        }
        
        // Login exitoso
        $this->resetFailedAttempts($user['id']);
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        $this->logActivity('login_success', $user['id']);
        
        return ['success' => true, 'message' => 'Login exitoso'];
    }
    
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity('logout', $_SESSION['user_id']);
        }
        
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    public function isAuthenticated() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        // Verificar timeout
        if (time() - $_SESSION['login_time'] > $this->sessionTimeout) {
            $this->logout();
            return false;
        }
        
        // Actualizar tiempo de actividad
        $_SESSION['login_time'] = time();
        return true;
    }
    
    public function requireAuth() {
        if (!$this->isAuthenticated()) {
            header('Location: login.php');
            exit;
        }
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    private function checkRateLimit($username) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM activity_log 
            WHERE action = 'login_failed_wrong_password' 
            AND (username = ? OR ip_address = ?) 
            AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$username, $_SERVER['REMOTE_ADDR']]);
        $result = $stmt->fetch();
        
        return $result['attempts'] < $this->maxLoginAttempts;
    }
    
    private function incrementFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE admin_users 
            SET failed_attempts = failed_attempts + 1,
                locked_until = CASE 
                    WHEN failed_attempts >= ? THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    ELSE locked_until 
                END
            WHERE id = ?
        ");
        $stmt->execute([$this->maxLoginAttempts - 1, $userId]);
    }
    
    private function resetFailedAttempts($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE admin_users 
            SET failed_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    private function logActivity($action, $userId = null, $username = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_log (user_id, username, action, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $username,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
    
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT id, username FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
}
?>