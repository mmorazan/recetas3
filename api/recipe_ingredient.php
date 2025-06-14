<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'list':
            $recipeId = $_GET['recipe_id'];
            $stmt = $pdo->prepare("
                SELECT ri.*, i.nombre as ingrediente_name 
                FROM RecetaIngredientes ri
                JOIN Ingredientes i ON ri.ingrediente_id = i.id
                WHERE ri.receta_id = ?
            ");
            $stmt->execute([$recipeId]);
            $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($ingredients);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>