<?php
/**
 * GCODE Admin - Visualizador Completo con CRUD
 */
require_once 'tables_config.php';
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

// Función para obtener clave primaria
function getPrimaryKey($pdo, $tableName) {
    $stmt = $pdo->query("SHOW KEYS FROM `$tableName` WHERE Key_name = 'PRIMARY'");
    $result = $stmt->fetch();
    return $result ? $result['Column_name'] : null;
}

// Obtener parámetros
$tableName = $_GET['name'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$search = trim($_GET['search'] ?? '');
$action = $_GET['action'] ?? '';
$recordId = $_GET['id'] ?? '';

// Tablas permitidas - AQUÍ PUEDES AGREGAR MÁS TABLAS
//$allowedTables = ['users', 'products', 'admin_users', 'activity_log'];
//$allowedTables = ['users', 'products', 'admin_users', 'activity_log', 'Ingredientes','Categorias','Recetas','RecetaIngredientes'];

if (empty($tableName) || !in_array($tableName, $allowedTables)) {
    die('Tabla no válida');
}

$user = ['username' => $_SESSION['username']];
$error = '';
$success = '';
$data = [];
$columns = [];
$columnInfo = [];
$totalRecords = 0;
$totalPages = 0;
$primaryKey = null;

try {
    $pdo = getConnection();
    
    // Procesar acciones CRUD
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'delete':
                $id = $_POST['id'] ?? '';
                $pk = getPrimaryKey($pdo, $tableName);
                if ($pk && $id) {
                    $stmt = $pdo->prepare("DELETE FROM `$tableName` WHERE `$pk` = ?");
                    if ($stmt->execute([$id])) {
                        $success = "Registro eliminado correctamente";
                    } else {
                        $error = "No se pudo eliminar el registro";
                    }
                }
                break;
                
            case 'add':
                $fields = [];
                $values = [];
                $placeholders = [];
                
                foreach ($_POST as $key => $value) {
                    if ($key !== 'action' && !empty(trim($value))) {
                        $fields[] = "`$key`";
                        $values[] = trim($value);
                        $placeholders[] = '?';
                    }
                }
                
                if (!empty($fields)) {
                    $sql = "INSERT INTO `$tableName` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($values)) {
                        $success = "Registro agregado correctamente";
                    } else {
                        $error = "No se pudo agregar el registro";
                    }
                }
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? '';
                $pk = getPrimaryKey($pdo, $tableName);
                
                if ($pk && $id) {
                    $updates = [];
                    $values = [];
                    
                    foreach ($_POST as $key => $value) {
                        if ($key !== 'action' && $key !== 'id' && $key !== $pk) {
                            $updates[] = "`$key` = ?";
                            $values[] = trim($value);
                        }
                    }
                    
                    if (!empty($updates)) {
                        $values[] = $id; // Para el WHERE
                        $sql = "UPDATE `$tableName` SET " . implode(', ', $updates) . " WHERE `$pk` = ?";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute($values)) {
                            $success = "Registro actualizado correctamente";
                        } else {
                            $error = "No se pudo actualizar el registro";
                        }
                    }
                }
                break;
        }
    }
    
    // Verificar que la tabla existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$tableName]);
    if (!$stmt->fetch()) {
        throw new Exception("La tabla '$tableName' no existe");
    }
    
    // Obtener información de columnas
    $stmt = $pdo->prepare("DESCRIBE `$tableName`");
    $stmt->execute();
    $columnInfo = $stmt->fetchAll();
    $columns = array_column($columnInfo, 'Field');
    $primaryKey = getPrimaryKey($pdo, $tableName);
    
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
    
    // Obtener datos con paginación
    $offset = ($page - 1) * $limit;
    $dataSql = "SELECT * FROM `$tableName`" . $whereClause . " LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($searchParams);
    $data = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Obtener registro específico para editar
$editRecord = null;
if ($action === 'edit' && $recordId && $primaryKey) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `$tableName` WHERE `$primaryKey` = ?");
        $stmt->execute([$recordId]);
        $editRecord = $stmt->fetch();
    } catch (Exception $e) {
        $error = "No se pudo cargar el registro para editar";
    }
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
        body { background: #f8f9fa; }
        .navbar { background: linear-gradient(45deg, #2c3e50, #3498db) !important; }
        .table-container { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); overflow: hidden; }
        .btn-action { font-size: 0.75rem; margin: 0 1px; }
        .modal-lg { max-width: 800px; }
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
        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-table text-primary"></i> <?= htmlspecialchars($tableName) ?></h1>
                <p class="text-muted mb-0">
                    <?= number_format($totalRecords) ?> registros totales
                    <?= $search ? ' - Filtrado por: "' . htmlspecialchars($search) . '"' : '' ?>
                </p>
            </div>
            <div>
                <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addModal">
                    <i class="fas fa-plus"></i> Agregar Registro
                </button>
                <a href="index.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <!-- Search and Actions -->
        <div class="row mb-4">
            <div class="col-md-6">
                <form method="GET" class="d-flex">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($tableName) ?>">
                    <input type="text" name="search" class="form-control me-2" 
                           placeholder="🔍 Buscar..." value="<?= htmlspecialchars($search) ?>">
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
            <div class="col-md-6 text-end">
                <div class="btn-group">
                    <a href="export_simple.php?table=<?= urlencode($tableName) ?>&format=json" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-download"></i> JSON
                    </a>
                    <a href="export_simple.php?table=<?= urlencode($tableName) ?>&format=csv" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="table-container">
            <?php if (empty($data)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-4"></i>
                    <h4>No se encontraron registros</h4>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
                        <i class="fas fa-plus"></i> Agregar Primer Registro
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <th><?= htmlspecialchars($column) ?></th>
                                <?php endforeach; ?>
                                <th width="140">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <td>
                                            <?php 
                                            $value = $row[$column] ?? '';
                                            if (strlen($value) > 50) {
                                                echo '<span title="' . htmlspecialchars($value) . '">' . 
                                                     htmlspecialchars(substr($value, 0, 50)) . '...</span>';
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?name=<?= urlencode($tableName) ?>&action=edit&id=<?= urlencode($row[$primaryKey] ?? '') ?>" 
                                               class="btn btn-primary btn-action" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-danger btn-action" 
                                                    onclick="confirmDelete('<?= htmlspecialchars($row[$primaryKey] ?? '') ?>')" 
                                                    title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
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
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $i ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Modal Agregar -->
    <div class="modal fade" id="addModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus text-success"></i> Agregar Registro a <?= htmlspecialchars($tableName) ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <?php foreach ($columnInfo as $col): 
                                // Saltar columnas auto-increment y timestamps automáticos
                                if ($col['Extra'] === 'auto_increment' || 
                                    ($col['Default'] === 'CURRENT_TIMESTAMP' && strpos($col['Extra'], 'on update') !== false)) continue;
                            ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <?= htmlspecialchars($col['Field']) ?>
                                        <?php if ($col['Null'] === 'NO' && $col['Default'] === null): ?>
                                            <span class="text-danger">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if (strpos($col['Type'], 'text') !== false || strpos($col['Type'], 'longtext') !== false): ?>
                                        <textarea name="<?= htmlspecialchars($col['Field']) ?>" 
                                                  class="form-control" 
                                                  rows="3"
                                                  <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>></textarea>
                                    <?php elseif (strpos($col['Type'], 'date') !== false): ?>
                                        <input type="<?= strpos($col['Type'], 'datetime') !== false ? 'datetime-local' : 'date' ?>" 
                                               name="<?= htmlspecialchars($col['Field']) ?>" 
                                               class="form-control"
                                               <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>>
                                    <?php elseif (strpos($col['Type'], 'int') !== false || strpos($col['Type'], 'decimal') !== false): ?>
                                        <input type="number" 
                                               name="<?= htmlspecialchars($col['Field']) ?>" 
                                               class="form-control"
                                               step="<?= strpos($col['Type'], 'decimal') !== false ? '0.01' : '1' ?>"
                                               <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>>
                                    <?php elseif (strpos($col['Type'], 'email') !== false): ?>
                                        <input type="email" 
                                               name="<?= htmlspecialchars($col['Field']) ?>" 
                                               class="form-control"
                                               <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>>
                                    <?php else: ?>
                                        <input type="text" 
                                               name="<?= htmlspecialchars($col['Field']) ?>" 
                                               class="form-control"
                                               <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>>
                                    <?php endif; ?>
                                    
                                    <small class="form-text text-muted">
                                        Tipo: <?= htmlspecialchars($col['Type']) ?>
                                        <?= $col['Null'] === 'YES' ? ' (Opcional)' : ' (Requerido)' ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar -->
    <?php if ($action === 'edit' && $editRecord): ?>
        <div class="modal fade show" id="editModal" tabindex="-1" style="display: block;">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit text-primary"></i> Editar Registro
                        </h5>
                        <a href="?name=<?= urlencode($tableName) ?>" class="btn-close"></a>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($editRecord[$primaryKey]) ?>">
                            
                            <div class="row">
                                <?php foreach ($columnInfo as $col): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">
                                            <?= htmlspecialchars($col['Field']) ?>
                                            <?php if ($col['Field'] === $primaryKey): ?>
                                                <span class="badge bg-primary">PK</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php if ($col['Field'] === $primaryKey): ?>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($editRecord[$col['Field']]) ?>" readonly>
                                        <?php elseif (strpos($col['Type'], 'text') !== false): ?>
                                            <textarea name="<?= htmlspecialchars($col['Field']) ?>" class="form-control" rows="3"><?= htmlspecialchars($editRecord[$col['Field']] ?? '') ?></textarea>
                                        <?php elseif (strpos($col['Type'], 'datetime') !== false): ?>
                                            <input type="datetime-local" 
                                                   name="<?= htmlspecialchars($col['Field']) ?>" 
                                                   class="form-control"
                                                   value="<?= $editRecord[$col['Field']] ? date('Y-m-d\TH:i', strtotime($editRecord[$col['Field']])) : '' ?>">
                                        <?php elseif (strpos($col['Type'], 'date') !== false): ?>
                                            <input type="date" 
                                                   name="<?= htmlspecialchars($col['Field']) ?>" 
                                                   class="form-control"
                                                   value="<?= $editRecord[$col['Field']] ? date('Y-m-d', strtotime($editRecord[$col['Field']])) : '' ?>">
                                        <?php elseif (strpos($col['Type'], 'int') !== false || strpos($col['Type'], 'decimal') !== false): ?>
                                            <input type="number" 
                                                   name="<?= htmlspecialchars($col['Field']) ?>" 
                                                   class="form-control"
                                                   step="<?= strpos($col['Type'], 'decimal') !== false ? '0.01' : '1' ?>"
                                                   value="<?= htmlspecialchars($editRecord[$col['Field']] ?? '') ?>">
                                        <?php else: ?>
                                            <input type="text" 
                                                   name="<?= htmlspecialchars($col['Field']) ?>" 
                                                   class="form-control"
                                                   value="<?= htmlspecialchars($editRecord[$col['Field']] ?? '') ?>">
                                        <?php endif; ?>
                                        
                                        <small class="form-text text-muted">
                                            <?= htmlspecialchars($col['Type']) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="?name=<?= urlencode($tableName) ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Actualizar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Modal Confirmar Eliminación -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar este registro?</p>
                    <p class="text-muted"><strong>Esta acción no se puede deshacer.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>