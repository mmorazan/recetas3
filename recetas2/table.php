<?php
/**
 * GCODE Admin - Visualizador de Tablas Seguro
 */

require_once 'config/auth.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

$auth = new AuthManager();
$auth->requireAuth();

$db = new SecureDatabase();
$user = $auth->getCurrentUser();

// Obtener parámetros
$tableName = $_GET['name'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 20);
$search = $_GET['search'] ?? '';

try {
    SecurityManager::validateTableName($tableName);
    list($page, $limit) = SecurityManager::validatePaginationParams($page, $limit);
    $search = validateSearchTerm($search);
    
    // Obtener datos
    $data = $db->getTableData($tableName, $page, $limit, $search);
    $totalRecords = $db->getTableCount($tableName, $search);
    $totalPages = ceil($totalRecords / $limit);
    $columns = $db->getTableColumns($tableName);
    $primaryKey = $db->getPrimaryKey($tableName);
    
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
        body { background: #f8f9fa; }
        .navbar { background: linear-gradient(45deg, #2c3e50, #3498db) !important; }
        .table-container { background: white; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        .search-box { border-radius: 50px; }
        .pagination .page-link { border-radius: 50px; margin: 0 2px; }
        .btn-delete { font-size: 0.8rem; }
        .table-responsive { max-height: 600px; overflow-y: auto; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-database"></i> GCODE Admin</a>
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
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                <a href="index.php" class="btn btn-primary ms-3">← Volver al Dashboard</a>
            </div>
        <?php else: ?>
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="fas fa-table"></i> <?= htmlspecialchars($tableName) ?></h1>
                    <p class="text-muted mb-0">
                        Mostrando <?= count($data) ?> de <?= $totalRecords ?> registros
                        <?= $search ? ' - Filtrado por: "' . htmlspecialchars($search) . '"' : '' ?>
                    </p>
                </div>
                <div>
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
                        <input type="text" name="search" class="form-control search-box me-2" 
                               placeholder="Buscar en todos los campos..." 
                               value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <div class="btn-group">
                        <a href="export.php?table=<?= urlencode($tableName) ?>&format=json<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-download"></i> JSON
                        </a>
                        <a href="export.php?table=<?= urlencode($tableName) ?>&format=csv<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="export.php?table=<?= urlencode($tableName) ?>&format=xml<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-file-code"></i> XML
                        </a>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container p-0">
                <?php if (empty($data)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4>No se encontraron registros</h4>
                        <p class="text-muted">
                            <?= $search ? 'Intente con otros términos de búsqueda' : 'Esta tabla no contiene datos' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <th><?= htmlspecialchars($column) ?></th>
                                    <?php endforeach; ?>
                                    <th width="120">Acciones</th>
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
                                            <button class="btn btn-danger btn-sm btn-delete" 
                                                    onclick="confirmDelete('<?= urlencode($tableName) ?>', '<?= urlencode($row[$primaryKey]) ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $page - 1 ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $i ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?name=<?= urlencode($tableName) ?>&page=<?= $page + 1 ?>&limit=<?= $limit ?><?= $search ? '&search=' . urlencode($search) : '' ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Modal de confirmación -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro que desea eliminar este registro?</p>
                    <p class="text-muted">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(table, id) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            confirmBtn.href = 'delete.php?table=' + table + '&id=' + id + '&csrf_token=<?= $auth->generateCSRFToken() ?>';
            modal.show();
        }
    </script>
</body>
</html>