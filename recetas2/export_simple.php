<?php
/**
 * GCODE Admin - Exportación de Datos
 */

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Función para cargar configuración .env
function loadEnvironment() {
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
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

// Obtener parámetros
$tableName = $_GET['table'] ?? '';
$format = $_GET['format'] ?? 'json';
$search = trim($_GET['search'] ?? '');

// Tablas permitidas
$allowedTables = ['users', 'products', 'admin_users', 'activity_log', 'Ingredientes','Categorias','Recetas','RecetaIngredientes'];
$allowedFormats = ['json', 'csv', 'xml'];

if (empty($tableName) || !in_array($tableName, $allowedTables)) {
    die('Tabla no válida');
}

if (!in_array($format, $allowedFormats)) {
    die('Formato no válido');
}

try {
    $pdo = getConnection();
    
    // Construir consulta con búsqueda
    $where = '';
    $params = [];
    
    if (!empty($search)) {
        // Obtener columnas para la búsqueda
        $stmt = $pdo->prepare("DESCRIBE `$tableName`");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $searchConditions = [];
        foreach ($columns as $column) {
            $searchConditions[] = "`$column` LIKE ?";
            $params[] = "%$search%";
        }
        $where = " WHERE " . implode(' OR ', $searchConditions);
    }
    
    // Obtener datos
    $sql = "SELECT * FROM `$tableName`" . $where;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Log de la exportación
    try {
        $logStmt = $pdo->prepare("INSERT INTO activity_log (user_id, username, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $logStmt->execute([
            $_SESSION['user_id'],
            $_SESSION['username'],
            "export_{$format}_{$tableName}",
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        // No detener la exportación si falla el log
    }
    
    // Generar nombre de archivo
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$tableName}_{$timestamp}.{$format}";
    
    // Exportar según el formato
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            if (!empty($data)) {
                $output = fopen('php://output', 'w');
                
                // Escribir BOM para UTF-8
                fwrite($output, "\xEF\xBB\xBF");
                
                // Escribir encabezados
                fputcsv($output, array_keys($data[0]));
                
                // Escribir datos
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
                
                fclose($output);
            }
            break;
            
        case 'xml':
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<data table="' . htmlspecialchars($tableName) . '" exported="' . date('c') . '">' . "\n";
            
            foreach ($data as $row) {
                echo '  <record>' . "\n";
                foreach ($row as $key => $value) {
                    echo '    <' . htmlspecialchars($key) . '>' . htmlspecialchars($value) . '</' . htmlspecialchars($key) . '>' . "\n";
                }
                echo '  </record>' . "\n";
            }
            
            echo '</data>';
            break;
            
        case 'json':
        default:
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $export = [
                'table' => $tableName,
                'exported_at' => date('c'),
                'total_records' => count($data),
                'exported_by' => $_SESSION['username'],
                'data' => $data
            ];
            
            echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
    }
    
} catch (Exception $e) {
    // Mostrar error en HTML
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>';
    echo '<html><head><title>Error de Exportación</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light">';
    echo '<div class="container mt-5">';
    echo '<div class="alert alert-danger">';
    echo '<h4>Error de Exportación</h4>';
    echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<a href="table_view.php?name=' . urlencode($tableName) . '" class="btn btn-primary">Volver a la Tabla</a>';
    echo '<a href="index.php" class="btn btn-secondary ms-2">Ir al Dashboard</a>';
    echo '</div>';
    echo '</div>';
    echo '</body></html>';
}
?>