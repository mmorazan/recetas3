<?php
/**
 * GCODE Admin - Ver Todas las Tablas
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

$user = ['username' => $_SESSION['username']];

try {
    $pdo = getConnection();
    
    // Obtener TODAS las tablas
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener información detallada de cada tabla
    $tableInfo = [];
    foreach ($allTables as $table) {
        try {
            // Contar registros
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $countStmt->fetch()['count'];
            
            // Obtener columnas
            $colStmt = $pdo->query("DESCRIBE `$table`");
            $columns = $colStmt->fetchAll();
            
            $tableInfo[$table] = [
                'count' => $count,
                'columns' => count($columns),
                'column_details' => $columns
            ];
        } catch (Exception $e) {
            $tableInfo[$table] = [
                'count' => 'Error',
                'columns' => 'Error',
                'column_details' => [],
                'error' => $e->getMessage()
            ];
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCODE Admin - Todas las Tablas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .navbar { background: linear-gradient(45deg, #2c3e50, #3498db) !important; }
        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: transform 0.3s;
        }
        .table-card:hover { transform: translateY(-2px); }
        .allowed { border-left: 4px solid #28a745; }
        .blocked { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-database"></i> GCODE Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-list"></i> Todas las Tablas de la Base de Datos</h1>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Total de tablas encontradas:</strong> <?= count($allTables) ?>
                <br><small>Las tablas marcadas en <span class="text-success">verde</span> están permitidas, las marcadas en <span class="text-danger">rojo</span> están bloqueadas por seguridad.</small>
            </div>

            <div class="row">
                <?php 
                $allowedTables = ['users', 'products', 'admin_users', 'activity_log'];
                foreach ($allTables as $table): 
                    $isAllowed = in_array($table, $allowedTables);
                    $info = $tableInfo[$table];
                ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="table-card <?= $isAllowed ? 'allowed' : 'blocked' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-table <?= $isAllowed ? 'text-success' : 'text-danger' ?>"></i>
                                        <?= htmlspecialchars($table) ?>
                                    </h5>
                                    <span class="badge <?= $isAllowed ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $isAllowed ? 'Permitida' : 'Bloqueada' ?>
                                    </span>
                                </div>
                                
                                <?php if (isset($info['error'])): ?>
                                    <div class="text-danger small">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Error: <?= htmlspecialchars($info['error']) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="text-muted small">Registros</div>
                                            <div class="fw-bold"><?= number_format($info['count']) ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small">Columnas</div>
                                            <div class="fw-bold"><?= $info['columns'] ?></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Mostrar primeras columnas -->
                                    <?php if (!empty($info['column_details'])): ?>
                                        <div class="small text-muted mb-3">
                                            <strong>Columnas:</strong>
                                            <?php 
                                            $columnNames = array_column($info['column_details'], 'Field');
                                            echo implode(', ', array_slice($columnNames, 0, 3));
                                            if (count($columnNames) > 3) echo '...';
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <?php if ($isAllowed): ?>
                                        <a href="table_view.php?name=<?= urlencode($table) ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Ver Datos
                                        </a>
                                        <a href="export_simple.php?table=<?= urlencode($table) ?>&format=json" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-download"></i> Exportar
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-lock"></i> Acceso Restringido
                                        </button>
                                        <small class="text-muted">
                                            Para acceder, añadir "<?= htmlspecialchars($table) ?>" a la lista de tablas permitidas
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Instrucciones para habilitar más tablas -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-unlock-alt"></i> ¿Cómo habilitar más tablas?</h5>
                </div>
                <div class="card-body">
                    <p>Para permitir el acceso a tablas adicionales, edita los siguientes archivos:</p>
                    
                    <h6>1. En <code>index.php</code> (línea ~80):</h6>
                    <pre class="bg-light p-2 rounded"><code>$allowedTables = ['users', 'products', 'admin_users', 'activity_log', 'tu_nueva_tabla'];</code></pre>
                    
                    <h6>2. En <code>table_view.php</code> (línea ~40):</h6>
                    <pre class="bg-light p-2 rounded"><code>$allowedTables = ['users', 'products', 'admin_users', 'activity_log', 'tu_nueva_tabla'];</code></pre>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-shield-alt"></i>
                        <strong>Nota de Seguridad:</strong> Solo añade tablas que sean seguras de mostrar. 
                        No incluyas tablas con información sensible como passwords, tokens, etc.
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>