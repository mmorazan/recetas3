<?php
/**
 * GCODE Admin - Dashboard Principal con Ver y Editar
 */

require_once 'tables_config.php';
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

// Verificar timeout de sesión (1 hora)
$session_timeout = 3600;
if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $session_timeout) {
    session_destroy();
    header('Location: login.php?message=session_expired');
    exit;
}

// Actualizar tiempo de actividad
$_SESSION['login_time'] = time();

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

// Obtener información del usuario actual
$user = ['username' => $_SESSION['username']];

// Obtener tablas disponibles (con seguridad) - PUEDES AGREGAR MÁS TABLAS AQUÍ
//$allowedTables = ['users', 'products', 'admin_users', 'activity_log','Ingredientes','Categorias'];
$tables = [];

try {
    $pdo = getConnection();
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Filtrar solo las tablas permitidas
    $tables = array_intersect($allTables, $allowedTables);
} catch (Exception $e) {
    $error = "Error al obtener tablas: " . $e->getMessage();
}

// Obtener estadísticas básicas
$stats = [];
try {
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
        $stats[$table] = $stmt->fetch()['count'];
    }
} catch (Exception $e) {
    // Ignorar errores de estadísticas
}

// Obtener información adicional de las tablas
$tableDetails = [];
try {
    foreach ($tables as $table) {
        // Obtener número de columnas
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll();
        
        // Obtener clave primaria
        $stmt = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        $primaryKey = $stmt->fetch();
        
        $tableDetails[$table] = [
            'columns' => count($columns),
            'has_primary_key' => $primaryKey ? true : false,
            'column_names' => array_column($columns, 'Field')
        ];
    }
} catch (Exception $e) {
    // Ignorar errores
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCODE Admin - Dashboard</title>
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
        .main-container {
            margin-top: 2rem;
        }
        .welcome-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .table-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        .table-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(45deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            margin-bottom: 1rem;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .btn-action {
            border-radius: 25px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-view {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            color: white;
        }
        .btn-view:hover {
            background: linear-gradient(45deg, #0056b3, #004085);
            color: white;
            transform: translateY(-1px);
        }
        .btn-edit {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            border: none;
            color: white;
        }
        .btn-edit:hover {
            background: linear-gradient(45deg, #1e7e34, #155724);
            color: white;
            transform: translateY(-1px);
        }
        .btn-export {
            background: linear-gradient(45deg, #ffc107, #e0a800);
            border: none;
            color: #212529;
        }
        .btn-export:hover {
            background: linear-gradient(45deg, #e0a800, #d39e00);
            color: #212529;
            transform: translateY(-1px);
        }
        .table-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .badge-info {
            background: linear-gradient(45deg, #17a2b8, #138496);
            border: none;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-database"></i> GCODE Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                </span>
                <a href="ver_tablas.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-list"></i> Ver Todas
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
            </div>
        </div>
    </nav>

    <div class="container main-container">
        <!-- Welcome Section -->
        <div class="welcome-card">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                    <p class="mb-0">Panel de administración seguro con funciones completas de gestión de datos.</p>
                    <small class="opacity-75">Conectado como: <?= htmlspecialchars($user['username']) ?></small>
                </div>
                <div class="col-md-4 text-end">
                    <div class="stats-overview">
                        <h3><?= count($tables) ?></h3>
                        <p class="mb-0">Tablas Habilitadas</p>
                        <small class="opacity-75"><?= array_sum($stats) ?> registros totales</small>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Tables Grid -->
        <div class="row">
            <div class="col-12 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h2><i class="fas fa-table"></i> Gestión de Tablas</h2>
                    <div class="btn-group">
                        <a href="ver_tablas.php" class="btn btn-outline-info">
                            <i class="fas fa-eye"></i> Ver Todas las Tablas
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (empty($tables)): ?>
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-3x mb-3"></i>
                            <h4>No hay tablas disponibles</h4>
                            <p>Configure las tablas permitidas o verifique la conexión a la base de datos.</p>
                            <a href="ver_tablas.php" class="btn btn-primary">Ver Configuración</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($tables as $table): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="table-card">
                            <div class="card-body p-4">
                                <!-- Icono y Título -->
                                <div class="text-center">
                                    <div class="table-icon mx-auto">
                                        <i class="fas fa-table"></i>
                                    </div>
                                    <h5 class="card-title mb-1"><?= htmlspecialchars($table) ?></h5>
                                    <span class="badge badge-info"><?= ucfirst($table) ?></span>
                                </div>
                                
                                <!-- Información de la tabla -->
                                <div class="table-info">
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-muted small">Registros</div>
                                            <div class="fw-bold text-primary">
                                                <?= isset($stats[$table]) ? number_format($stats[$table]) : '0' ?>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Columnas</div>
                                            <div class="fw-bold text-info">
                                                <?= isset($tableDetails[$table]) ? $tableDetails[$table]['columns'] : '0' ?>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Estado</div>
                                            <div class="fw-bold text-success">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($tableDetails[$table]['column_names'])): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <strong>Campos:</strong> 
                                                <?= implode(', ', array_slice($tableDetails[$table]['column_names'], 0, 3)) ?>
                                                <?= count($tableDetails[$table]['column_names']) > 3 ? '...' : '' ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Botones de Acción -->
                                <div class="d-grid gap-2">
                                    <!-- Botón Ver Datos -->
                                    <a href="table_view.php?name=<?= urlencode($table) ?>" class="btn btn-view btn-action">
                                        <i class="fas fa-eye"></i> Ver Datos
                                    </a>
                                    
                                    <!-- Botón Editar Datos -->
                                    <a href="table_view_complete.php?name=<?= urlencode($table) ?>" class="btn btn-edit btn-action">
                                        <i class="fas fa-edit"></i> Editar Datos
                                    </a>
                                    
                                    <!-- Botones de Exportación -->
                                    <div class="btn-group">
                                        <a href="export_simple.php?table=<?= urlencode($table) ?>&format=json" 
                                           class="btn btn-export btn-sm" title="Exportar JSON">
                                            <i class="fas fa-download"></i> JSON
                                        </a>
                                        <a href="export_simple.php?table=<?= urlencode($table) ?>&format=csv" 
                                           class="btn btn-export btn-sm" title="Exportar CSV">
                                            <i class="fas fa-file-csv"></i> CSV
                                        </a>
                                        <a href="export_simple.php?table=<?= urlencode($table) ?>&format=xml" 
                                           class="btn btn-export btn-sm" title="Exportar XML">
                                            <i class="fas fa-file-code"></i> XML
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Estado del Sistema -->
        <div class="row mt-5">
            <div class="col-12">
                <h3><i class="fas fa-chart-line"></i> Estado del Sistema</h3>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-shield-alt fa-2x text-success mb-3"></i>
                    <h6>Seguridad</h6>
                    <small class="text-muted">Sistema protegido</small>
                    <div class="mt-2">
                        <span class="badge bg-success">Activo</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-database fa-2x text-info mb-3"></i>
                    <h6>Base de Datos</h6>
                    <small class="text-muted">Conexión estable</small>
                    <div class="mt-2">
                        <span class="badge bg-info">MySQL</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-users fa-2x text-warning mb-3"></i>
                    <h6>Sesión</h6>
                    <small class="text-muted">Usuario autenticado</small>
                    <div class="mt-2">
                        <span class="badge bg-warning text-dark">Activa</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-clock fa-2x text-primary mb-3"></i>
                    <h6>Última Actividad</h6>
                    <small class="text-muted">Tiempo real</small>
                    <div class="mt-2">
                        <span class="badge bg-primary"><?= date('H:i:s') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones Rápidas -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-bolt text-primary"></i> Acciones Rápidas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <a href="ver_tablas.php" class="btn btn-outline-primary mb-2">
                                        <i class="fas fa-list"></i> Ver Todas las Tablas
                                    </a>
                                    <small class="text-muted">Explorar todas las tablas disponibles en la base de datos</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <a href="diagnostico.php" class="btn btn-outline-info mb-2">
                                        <i class="fas fa-stethoscope"></i> Diagnóstico del Sistema
                                    </a>
                                    <small class="text-muted">Verificar el estado y configuración del sistema</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid">
                                    <a href="login_debug.php" class="btn btn-outline-secondary mb-2">
                                        <i class="fas fa-bug"></i> Herramientas de Debug
                                    </a>
                                    <small class="text-muted">Herramientas de desarrollo y depuración</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Info -->
        <div class="mt-5 p-4 bg-white rounded shadow-sm">
            <div class="row text-center">
                <div class="col-md-3">
                    <small class="text-muted">
                        <i class="fas fa-server text-primary"></i><br>
                        <strong>Servidor:</strong> <?= php_uname('n') ?>
                    </small>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">
                        <i class="fab fa-php text-info"></i><br>
                        <strong>PHP:</strong> <?= PHP_VERSION ?>
                    </small>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">
                        <i class="fas fa-calendar text-success"></i><br>
                        <strong>Fecha:</strong> <?= date('d/m/Y') ?>
                    </small>
                </div>
                <div class="col-md-3">
                    <small class="text-muted">
                        <i class="fas fa-clock text-warning"></i><br>
                        <strong>Hora:</strong> <span id="currentTime"><?= date('H:i:s') ?></span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Actualizar hora en tiempo real
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-ES');
            document.getElementById('currentTime').textContent = timeString;
        }
        
        setInterval(updateTime, 1000);
        
        // Animación al cargar las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.table-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>