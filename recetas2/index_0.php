<?php
/**
 * GCODE Admin - Dashboard Principal (Versión Corregida)
 */

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

// Obtener información del usuario actual
$user = ['username' => $_SESSION['username']];

// Obtener tablas disponibles (con seguridad)
//$allowedTables = ['users', 'products', 'admin_users', 'activity_log'];
$allowedTables = ['users', 'products', 'admin_users', 'activity_log', 'Ingredientes'];
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
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .table-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .table-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
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
                    <p class="mb-0">Bienvenido al sistema de administración seguro. Gestiona tus datos de forma fácil y segura.</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="stats-overview">
                        <h3><?= count($tables) ?></h3>
                        <p>Tablas Disponibles</p>
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
            <div class="col-12">
                <h2 class="mb-4"><i class="fas fa-table"></i> Tablas de la Base de Datos</h2>
            </div>
            
            <?php if (empty($tables)): ?>
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle"></i> No hay tablas disponibles para mostrar.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($tables as $table): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="table-card">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="table-icon me-3">
                                        <i class="fas fa-table"></i>
                                    </div>
                                    <div>
                                        <h5 class="card-title mb-1"><?= htmlspecialchars($table) ?></h5>
                                        <small class="text-muted">
                                            <?= isset($stats[$table]) ? number_format($stats[$table]) . ' registros' : 'Tabla de datos' ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="table_view.php?name=<?= urlencode($table) ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> Ver Datos
                                    </a>
                                    <a href="export_simple.php?table=<?= urlencode($table) ?>" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-download"></i> Exportar JSON
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-5">
            <div class="col-12">
                <h3><i class="fas fa-bolt"></i> Estado del Sistema</h3>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-shield-alt fa-2x text-success mb-3"></i>
                    <h6>Sistema Seguro</h6>
                    <small class="text-muted">Protección completa activada</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-database fa-2x text-info mb-3"></i>
                    <h6>Base de Datos</h6>
                    <small class="text-muted">Conexión estable</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-user-check fa-2x text-warning mb-3"></i>
                    <h6>Autenticación</h6>
                    <small class="text-muted">Sesión activa</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-clock fa-2x text-primary mb-3"></i>
                    <h6>Última Actividad</h6>
                    <small class="text-muted">Ahora mismo</small>
                </div>
            </div>
        </div>

        <!-- Sistema Info -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-info-circle text-primary"></i> Información del Sistema
                        </h5>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><strong>Usuario:</strong> <?= htmlspecialchars($user['username']) ?></li>
                                    <li><strong>Sesión iniciada:</strong> <?= date('H:i:s') ?></li>
                                    <li><strong>Tablas disponibles:</strong> <?= count($tables) ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li><strong>PHP:</strong> <?= PHP_VERSION ?></li>
                                    <li><strong>Estado BD:</strong> <span class="text-success">Conectada</span></li>
                                    <li><strong>Modo:</strong> Producción Segura</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>