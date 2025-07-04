<?php
/**
 * GCODE Admin - Clase de Base de Datos Segura
 */

require_once '../config/database.php';
require_once 'security.php';

class SecureDatabase {
    private $pdo;
    private $allowedTables;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getInstance()->getConnection();
        $this->allowedTables = ['users', 'products', 'orders', 'categories', 'settings'];
    }
    
    public function getAllTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Filtrar solo las tablas permitidas
        return array_intersect($tables, $this->allowedTables);
    }
    
    public function getTableData($tableName, $page = 1, $limit = 20, $search = '') {
        SecurityManager::validateTableName($tableName);
        list($page, $limit) = SecurityManager::validatePaginationParams($page, $limit);
        $search = validateSearchTerm($search);
        
        $offset = ($page - 1) * $limit;
        $params = [];
        
        // Construir consulta con búsqueda
        $sql = "SELECT * FROM `{$tableName}`";
        
        if (!empty($search)) {
            $columns = $this->getTableColumns($tableName);
            $searchConditions = [];
            
            foreach ($columns as $column) {
                $searchConditions[] = "`{$column}` LIKE ?";
                $params[] = "%{$search}%";
            }
            
            $sql .= " WHERE " . implode(' OR ', $searchConditions);
        }
        
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getTableCount($tableName, $search = '') {
        SecurityManager::validateTableName($tableName);
        $search = validateSearchTerm($search);
        
        $params = [];
        $sql = "SELECT COUNT(*) as total FROM `{$tableName}`";
        
        if (!empty($search)) {
            $columns = $this->getTableColumns($tableName);
            $searchConditions = [];
            
            foreach ($columns as $column) {
                $searchConditions[] = "`{$column}` LIKE ?";
                $params[] = "%{$search}%";
            }
            
            $sql .= " WHERE " . implode(' OR ', $searchConditions);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch()['total'];
    }
    
    public function getTableColumns($tableName) {
        SecurityManager::validateTableName($tableName);
        
        $stmt = $this->pdo->prepare("DESCRIBE `{$tableName}`");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $columns;
    }
    
    public function getPrimaryKey($tableName) {
        SecurityManager::validateTableName($tableName);
        
        $stmt = $this->pdo->prepare("SHOW KEYS FROM `{$tableName}` WHERE Key_name = 'PRIMARY'");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if (!$result) {
            throw new Exception("No se encontró clave primaria para la tabla {$tableName}");
        }
        
        return $result['Column_name'];
    }
    
    public function deleteRecord($tableName, $id) {
        SecurityManager::validateTableName($tableName);
        $id = validateId($id);
        
        $primaryKey = $this->getPrimaryKey($tableName);
        
        $stmt = $this->pdo->prepare("DELETE FROM `{$tableName}` WHERE `{$primaryKey}` = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            SecurityManager::logSecurityEvent('record_deleted', [
                'table' => $tableName,
                'id' => $id,
                'user' => $_SESSION['username'] ?? 'unknown'
            ]);
        }
        
        return $result;
    }
    
    public function exportTableData($tableName, $format = 'json') {
        SecurityManager::validateTableName($tableName);
        
        $stmt = $this->pdo->prepare("SELECT * FROM `{$tableName}`");
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        SecurityManager::logSecurityEvent('data_exported', [
            'table' => $tableName,
            'format' => $format,
            'records' => count($data),
            'user' => $_SESSION['username'] ?? 'unknown'
        ]);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($data);
            case 'xml':
                return $this->exportToXml($data, $tableName);
            case 'json':
            default:
                return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }
    
    private function exportToCsv($data) {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Escribir encabezados
        fputcsv($output, array_keys($data[0]));
        
        // Escribir datos
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    private function exportToXml($data, $tableName) {
        $xml = new SimpleXMLElement('<data/>');
        $xml->addAttribute('table', $tableName);
        $xml->addAttribute('exported', date('Y-m-d H:i:s'));
        
        foreach ($data as $row) {
            $record = $xml->addChild('record');
            foreach ($row as $key => $value) {
                $record->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }
}
?>