<?php
/**
 * GCODE Admin - Eliminación Segura de Registros
 */

require_once 'config/auth.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

$auth = new AuthManager();
$auth->requireAuth();

// Verificar CSRF token
if (!isset($_GET['csrf_token']) || !$auth->validateCSRFToken($_GET['csrf_token'])) {
    die('Token CSRF inválido');
}

$tableName = $_GET['table'] ?? '';
$id = $_GET['id'] ?? '';

try {
    SecurityManager::validateTableName($tableName);
    $id = validateId($id);
    
    $db = new SecureDatabase();
    $result = $db->deleteRecord($tableName, $id);
    
    if ($result) {
        $_SESSION['success_message'] = 'Registro eliminado correctamente';
    } else {
        $_SESSION['error_message'] = 'No se pudo eliminar el registro';
    }
    
} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
}

header("Location: table.php?name=" . urlencode($tableName));
exit;
?>