<?php
/**
 * GCODE Admin - Visualizador de Tablas (Versión Corregida)
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
        $dbname = $config['DB_NAME'] ?? 'Recetas';
        $username = $config['DB_USER'] ?? 'recetas';
        $password = $config['DB_PASS'] ?? 'gcode2025!';
        
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
$tableName = $_GET['name'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$search = trim($_GET['search'] ?? '');

// Tablas permitidas
//$allowedTables = ['users', 'products', 'admin_users', 'activity_log'];
$allowedTables = ['users', 'products', 'admin_users', 'activity_log', 'Ingredientes','Categorias','Recetas','RecetaIngredientes'];

if (empty($tableName) || !in_array($tableName, $allowedTables)) {
    die('Tabla no válida');
}

$user = ['username' => $_SESSION['username']];
$error = '';
$data = [];
$columns = [];
$totalRecords = 0;
$totalPages = 0;

try {
    $pdo = getConnection();
    
    // Verificar que la tabla existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    if (!$stmt->fetch()) {
        throw new Exception("La tabla '$tableName' no existe");
    }
    
    // Obtener columnas
    $stmt = $pdo->prepare("DESCRIBE `$tableName`");
    $stmt->execute();
    $columnInfo = $stmt->fetchAll();
    $columns = array_column($columnInfo, 'Field');
    
    // Construir consulta con búsqueda
    $whereClause = '';
    $searchParams = [];
    
    if (!empty($search)) {
        $searchConditions = [];
        foreach ($columns as $column) {
            $searchConditions[] = "`$column` LIKE ?";
            $searchParams[] = "%$search%";
        }
        $whereClause = " WHERE " . implode(' OR ', $searchConditions);
    }
    
    // Contar total de registros
    $countSql = "SELECT COUNT(*) as total FROM `$tableName`" . $whereClause;
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($searchParams);
    $totalRecords = (int)$stmt->fetch()['total'];
    $totalPages = ceil($totalRecords / $limit);
    
    // Obtener datos con paginación - CORREGIDO: usar concatenación en lugar de parámetros para LIMIT/OFFSET
    $offset = ($page - 1) * $limit;
    $dataSql = "SELECT * FROM `$tableName`" . $whereClause . " LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($searchParams);
    $data = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCODE Admin - <?= htmlspecialchars($tableName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: #f8f9fa; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar { 
            background: linear-gradient(45deg, #2c3e50, #3498db) !important; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .table-container { 
            background: white; 
            border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.08); 
            overflow: hidden;
        }
        .search-box { 
            border-radius: 50px; 
        }
        .pagination .page-link { 
            border-radius: 50px; 
            margin: 0 2px; 
            border: none;
            color: #6c757d;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
        }
        .table-responsive { 
            max-height: 600px; 
            overflow-y: auto; 
        }
        .table th { 
            background-color: #f8f9fa; 
            position: sticky; 
            top: 0; 
            z-index: 10;
            border-bottom: 2px solid #dee2e6;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
        }
        .btn-export {
            border-radius: 20px;
        }
        .stats-info {
            background: linear-gradient(45deg, #28a745, #20c997);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
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
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                <br><br>
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        <?php else: ?>
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1>
                        <i class="fas fa-table text-primary"></i> 
                        <?= htmlspecialchars($tableName) ?>
                    </h1>
                    <p class="text-muted mb-0">
                        Mostrando <?= count($data) ?> de <?= number_format($totalRecords) ?> registros
                        <?= $search ? ' - Filtrado por: "' . htmlspecialchars($search) . '"' : '' ?>
                    </p>
                </div>
                <div>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <!-- Stats Info -->
            <?php if ($totalRecords > 0): ?>
                <div class="stats-info">
                    <div class="row">
                        <div class="col-md-3">
                            <strong><i class="fas fa-list"></i> Total:</strong> 
                            <?= number_format($totalRecords) ?> registros
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-columns"></i> Columnas:</strong> 
                            <?= count($columns) ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-file-alt"></i> Página:</strong> 
                            <?= $page ?> de <?= $totalPages ?>
                        </div>
                        <div class="col-md-3">
                            <strong><i class="fas fa-eye"></i> Mostrando:</strong> 
                            <?= $limit ?> por página
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search and Actions -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="name" value="<?= htmlspecialchars($tableName) ?>">
                        <input type="text" 
                               name="search" 
                               class="form-control search-box me-2" 
                               placeholder="🔍 Buscar en todos los campos..." 
                               value="<?= htmlspecialchars($search) ?>"
                               autocomplete="off">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-search"></i>
                        </button>
                        <?php if ($search): ?>
                            <a href="?name=<?= urlencode($tableName) ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="export_simple.php?table=<?= urlencode($tableName) ?>&format=json<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn btn-success btn-sm btn-export">
                            <i class="fas fa-download"></i> JSON
                        </a>
                        <a href="export_simple.php?table=<?= urlencode($tableName) ?>&format=csv<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn btn-success btn-sm btn-export">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="export_simple.php?table=<?= urlencode($tableName) ?>&format=xml<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn btn-success btn-sm btn-export">
                            <i class="fas fa-file-code"></i> XML
                        </a>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <?php if (empty($data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-4"></i>
                        <h4 class="text-muted">No se encontraron registros</h4>
                        <p class="text-muted">
                            <?php if ($search): ?>
                                No hay resultados para "<strong><?= htmlspecialchars($search) ?></strong>"
                                <br><br>
                                <a href="?name=<?= urlencode($tableName) ?>" class="btn btn-outline-primary">
                                    Ver todos los registros
                                </a>
                            <?php else: ?>
                                Esta tabla no contiene datos
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <th class="text-nowrap">
                                            <i class="fas fa-columns text-muted me-1"></i>
                                            <?= htmlspecialchars($column) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data as $index => $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <td>
                                                <?php 
                                                $value = $row[$column] ?? '';
                                                
                                                // Formatear según el tipo de columna
                                                if (is_null($value)) {
                                                    echo '<span class="text-muted fst-italic">NULL</span>';
                                                } elseif (strlen($value) > 100) {
                                                    echo '<span title="' . htmlspecialchars($value) . '" class="text-truncate d-inline-block" style="max-width: 200px;">' . 
                                                         htmlspecialchars(substr($value, 0, 100)) . '...</span>';
                                                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                                                    // Formatear fechas
                                                    echo '<span class="text-primary">' . htmlspecialchars($value) . '</span>';
                                                } elseif (is_numeric($value)) {
                                                    // Formatear números
                                                    echo '<span class="text-end font-monospace">' . htmlspecialchars($value) . '</span>';
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <!-- Primera página -->
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=1&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>" title="Primera página">
                                    <i class="fas fa-angle-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $page - 1 ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>" title="Página anterior">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Páginas numeradas -->
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $i ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <!-- Última página -->
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $page + 1 ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>" title="Página siguiente">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $totalPages ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>" title="Última página">
                                    <i class="fas fa-angle-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                    
                    <div class="text-center mt-2">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Página <?= $page ?> de <?= $totalPages ?> 
                            (<?= number_format($totalRecords) ?> registros totales)
                        </small>
                    </div>
                </nav>
            <?php endif; ?>

            <!-- Footer Info -->
            <div class="mt-4 p-3 bg-light rounded">
                <div class="row text-center">
                    <div class="col-md-4">
                        <small class="text-muted">
                            <i class="fas fa-table text-primary"></i>
                            <strong>Tabla:</strong> <?= htmlspecialchars($tableName) ?>
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">
                            <i class="fas fa-clock text-info"></i>
                            <strong>Consultado:</strong> <?= date('H:i:s') ?>
                        </small>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">
                            <i class="fas fa-user text-success"></i>
                            <strong>Usuario:</strong> <?= htmlspecialchars($user['username']) ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit search form on Enter
        document.querySelector('input[name="search"]').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.closest('form').submit();
            }
        });
        
        // Highlight search terms
        const searchTerm = '<?= addslashes($search) ?>';
        if (searchTerm) {
            // Simple highlight function
            document.querySelectorAll('td').forEach(cell => {
                if (cell.textContent.toLowerCase().includes(searchTerm.toLowerCase())) {
                    cell.innerHTML = cell.innerHTML.replace(
                        new RegExp(searchTerm, 'gi'), 
                        '<mark>$&</mark>'
                    );
                }
            });
        }
    </script>
</body>
</html>