<?php
/**
 * Login súper simple para diagnosticar
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validación básica
    if ($username === 'admin' && $password === 'admin123') {
        session_start();
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = 'admin';
        
        echo "✅ Login exitoso - <a href='dashboard_simple.php'>Ir al Dashboard</a>";
        exit;
    } else {
        echo "❌ Credenciales incorrectas";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Simple</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card" style="max-width: 400px; margin: 0 auto;">
            <div class="card-body">
                <h3>🔒 Login Simple</h3>
                <form method="POST">
                    <div class="mb-3">
                        <input type="text" name="username" class="form-control" 
                               placeholder="Usuario (admin)" value="admin">
                    </div>
                    <div class="mb-3">
                        <input type="password" name="password" class="form-control" 
                               placeholder="Contraseña (admin123)" value="admin123">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>