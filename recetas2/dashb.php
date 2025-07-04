<?php
/**
 * Dashboard simple
 */

session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: login_simple.php');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard Simple</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="alert alert-success">
            <h2>🎉 ¡Sistema Funcionando!</h2>
            <p>Bienvenido, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
            <p>El login básico funciona correctamente.</p>
        </div>
        
        <div class="card">
            <div class="card-body">
                <h5>✅ Diagnóstico Exitoso</h5>
                <p>Si ves este mensaje, significa que:</p>
                <ul>
                    <li>✅ PHP funciona correctamente</li>
                    <li>✅ Las sesiones funcionan</li>
                    <li>✅ El servidor web está configurado</li>
                </ul>
                
                <hr>
                
                <h6>🔧 Próximos pasos:</h6>
                <ol>
                    <li>Verificar que la base de datos funcione</li>
                    <li>Revisar el archivo config/auth.php</li>
                    <li>Corregir el login original</li>
                </ol>
                
                <a href="login_debug.php" class="btn btn-primary">Probar Login con BD</a>
                <a href="diagnostico.php" class="btn btn-secondary">Diagnóstico Completo</a>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="logout_simple.php" class="btn btn-outline-danger">Cerrar Sesión</a>
        </div>
    </div>
</body>
</html>