<?php
/**
 * GCODE Admin - Script de Diagnóstico
 * Guarda como diagnostico.php y accede desde el navegador
 */

echo "<h1>🔍 Diagnóstico GCODE Admin</h1>";

// 1. Verificar PHP
echo "<h2>1. PHP</h2>";
echo "Versión PHP: " . PHP_VERSION . "<br>";
echo "PDO disponible: " . (extension_loaded('pdo') ? '✅ SÍ' : '❌ NO') . "<br>";
echo "PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅ SÍ' : '❌ NO') . "<br>";

// 2. Verificar archivos
echo "<h2>2. Archivos</h2>";
$archivos = ['.env', 'config/database.php', 'config/auth.php'];
foreach ($archivos as $archivo) {
    $existe = file_exists($archivo);
    echo "$archivo: " . ($existe ? '✅ Existe' : '❌ No existe') . "<br>";
}

// 3. Verificar .env
echo "<h2>3. Configuración .env</h2>";
if (file_exists('.env')) {
    $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $config = [];
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }
    }
    
    echo "DB_HOST: " . ($config['DB_HOST'] ?? 'NO DEFINIDO') . "<br>";
    echo "DB_NAME: " . ($config['DB_NAME'] ?? 'NO DEFINIDO') . "<br>";
    echo "DB_USER: " . ($config['DB_USER'] ?? 'NO DEFINIDO') . "<br>";
    echo "DB_PASS: " . (isset($config['DB_PASS']) ? '***' : 'NO DEFINIDO') . "<br>";
} else {
    echo "❌ Archivo .env no encontrado<br>";
}

// 4. Probar conexión a BD
echo "<h2>4. Conexión a Base de Datos</h2>";
try {
    // Cargar configuración
    if (file_exists('.env')) {
        $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
    }
    
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'gcode';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? '';
    
    echo "Intentando conectar a: $host/$dbname con usuario: $username<br>";
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "✅ <strong>Conexión exitosa</strong><br>";
    
    // Verificar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas encontradas: " . implode(', ', $tables) . "<br>";
    
    // Verificar usuario admin
    if (in_array('admin_users', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users");
        $count = $stmt->fetch()['count'];
        echo "Usuarios admin: $count<br>";
    }
    
} catch (PDOException $e) {
    echo "❌ <strong>Error de conexión:</strong> " . $e->getMessage() . "<br>";
    echo "<br><strong>Posibles soluciones:</strong><br>";
    echo "1. Verificar que MySQL esté ejecutándose<br>";
    echo "2. Verificar credenciales en .env<br>";
    echo "3. Ejecutar setup.sql<br>";
}

// 5. Verificar permisos
echo "<h2>5. Permisos de Archivos</h2>";
$archivos_permisos = ['index.php', 'login.php', '.env'];
foreach ($archivos_permisos as $archivo) {
    if (file_exists($archivo)) {
        $permisos = substr(sprintf('%o', fileperms($archivo)), -4);
        echo "$archivo: $permisos<br>";
    }
}

// 6. Información del servidor
echo "<h2>6. Información del Servidor</h2>";
echo "Servidor: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script: " . $_SERVER['SCRIPT_FILENAME'] . "<br>";

// 7. Prueba básica de sesiones
echo "<h2>7. Prueba de Sesiones</h2>";
session_start();
$_SESSION['test'] = 'funcionando';
echo "Sesiones: " . (isset($_SESSION['test']) ? '✅ Funcionando' : '❌ No funcionan') . "<br>";

echo "<hr>";
echo "<h2>🎯 Siguientes Pasos</h2>";
echo "<ol>";
echo "<li>Si hay errores de conexión BD, revisar credenciales en .env</li>";
echo "<li>Si faltan tablas, ejecutar: <code>mysql -u root -p &lt; setup.sql</code></li>";
echo "<li>Si todo está bien, acceder a <a href='login.php'>login.php</a></li>";
echo "</ol>";

echo "<div style='background: #f0f0f0; padding: 10px; margin-top: 20px;'>";
echo "<strong>Credenciales por defecto:</strong><br>";
echo "Usuario: admin<br>";
echo "Contraseña: admin123";
echo "</div>";
?>