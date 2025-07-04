<?php
/**
 * GCODE Admin - Exportación Segura de Datos
 */

require_once 'config/auth.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

$auth = new AuthManager();
$auth->requireAuth();

$tableName = $_GET['table'] ?? '';
$format = $_GET['format'] ?? 'json';
$search = $_GET['search'] ?? '';

try {
    SecurityManager::validateTableName($tableName);
    $search = validateSearchTerm($search);
    
    if (!in_array($format, ['json', 'csv', 'xml'])) {
        throw new InvalidArgumentException("Formato no válido: " . $format);
    }
    
    $db = new SecureDatabase();
    $data = $db->exportTableData($tableName, $format);
    
    // Headers para descarga
    $filename = $tableName . '_' . date('Y-m-d_H-i-s') . '.' . $format;
    
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            break;
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            break;
        case 'json':
        default:
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            break;
    }
    
    echo $data;
    
} catch (Exception $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>Error de Exportación</h1>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="index.php">Volver al Dashboard</a>';
}
?>