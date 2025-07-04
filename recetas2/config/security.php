<?php
/**
 * GCODE Admin - Funciones de Seguridad
 */

class SecurityManager {
    private static $allowedTables = [
        'users', 'products', 'orders', 'categories', 'settings'
    ];
    
    public static function validateTableName($tableName) {
        if (empty($tableName) || !is_string($tableName)) {
            throw new InvalidArgumentException("Nombre de tabla inválido");
        }
        
        if (!in_array($tableName, self::$allowedTables)) {
            throw new InvalidArgumentException("Tabla no permitida: " . $tableName);
        }
        
        // Validación adicional de caracteres
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tableName)) {
            throw new InvalidArgumentException("Nombre de tabla contiene caracteres inválidos");
        }
        
        return true;
    }
    
    public static function sanitizeInput($input, $type = 'string') {
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
    
    public static function validatePaginationParams($page, $limit) {
        $page = filter_var($page, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'default' => 1]
        ]);
        
        $limit = filter_var($limit, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 20]
        ]);
        
        return [$page, $limit];
    }
    
    public static function getClientIP() {
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
    
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        error_log("SECURITY EVENT: " . json_encode($logData));
    }
}

// Funciones de validación específicas
function validateId($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException("ID inválido");
    }
    return $id;
}

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
?>