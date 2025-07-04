<?php
/**
 * Verificar que las tablas de la configuración existen en la BD
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Cargar configuración de tablas
require_once 'tables_config.php';

// Función de conexión (copia la que ya tienes funcionando)
function getConnection() {
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
    
    $host = $config['DB_HOST'] ?? 'localhost';
    $dbname = $config['DB_NAME'] ?? 'gcode';
    $username = $config['DB_USER'] ?? 'gcode';
    $password = $config['DB_PASS'] ?? '';
    
    try {
        return new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

try {
    $pdo = getConnection();
    
    // Obtener todas las tablas de la BD
    $stmt = $pdo->query("SHOW TABLES");
    $realTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Obtener estadísticas de cada tabla permitida
    $tableStats = [];
    foreach ($allowedTables as $table) {
        if (in_array($table, $realTables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $stmt->fetch()['count'];
            
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll();
            
            $tableStats[$table] = [
                'exists' => true,
                'records' => $count,
                'columns' => count($columns),
                'column_names' => array_column($columns, 'Field')
            ];
        } else {
            $tableStats[$table] = ['exists' => false];
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
    <title>Verificar Tablas - GCODE Admin</title>
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
        }
        .exists { border-left: 4px solid #28a745; }
        .not-exists { border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-database"></i> GCODE Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-check-circle"></i> Verificación de Tablas</h1>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Verificando tablas configuradas en tables_config.php</strong><br>
                Total de tablas en la BD: <?= count($realTables) ?> | 
                Tablas configuradas: <?= count($allowedTables) ?>
            </div>

            <div class="row">
                <?php foreach ($allowedTables as $table): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="table-card <?= $tableStats[$table]['exists'] ? 'exists' : 'not-exists' ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-table <?= $tableStats[$table]['exists'] ? 'text-success' : 'text-danger' ?>"></i>
                                        <?= htmlspecialchars($table) ?>
                                    </h5>
                                    <span class="badge <?= $tableStats[$table]['exists'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $tableStats[$table]['exists'] ? 'Existe' : 'No Existe' ?>
                                    </span>
                                </div>
                                
                                <?php if ($tableStats[$table]['exists']): ?>
                                    <div class="row text-center mb-3">
                                        <div class="col-6">
                                            <div class="text-muted small">Registros</div>
                                            <div class="fw-bold text-primary"><?= number_format($tableStats[$table]['records']) ?></div>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-muted small">Columnas</div>
                                            <div class="fw-bold text-info"><?= $tableStats[$table]['columns'] ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="small text-muted mb-3">
                                        <strong>Campos:</strong>
                                        <?php 
                                        $columnNames = $tableStats[$table]['column_names'];
                                        echo implode(', ', array_slice($columnNames, 0, 3));
                                        if (count($columnNames) > 3) echo '...';
                                        ?>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="table_view.php?name=<?= urlencode($table) ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> Ver Datos
                                        </a>
                                        <a href="table_view_complete.php?name=<?= urlencode($table) ?>" class="btn btn-success btn-sm">
                                            <i class="fas fa-edit"></i> Gestionar
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <small>Esta tabla no existe en la base de datos. Remover de tables_config.php o crearla.</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Todas las Tablas en la Base de Datos</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($realTables as $table): ?>
                            <div class="col-md-3 mb-2">
                                <span class="badge <?= in_array($table, $allowedTables) ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= htmlspecialchars($table) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <span class="badge bg-success">Verde</span> = Configuradas en tables_config.php |
                        <span class="badge bg-secondary">Gris</span> = No configuradas
                    </small>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>