<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT i.*, c.nombre as categoria_name 
                FROM Ingredientes i
                JOIN Categorias c ON i.categoria_id = c.id
                ORDER BY i.nombre
            ");
            $ingredients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($ingredients);
            break;

        case 'get':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("
                SELECT i.*, c.nombre as categoria_name 
                FROM Ingredientes i
                JOIN Categorias c ON i.categoria_id = c.id
                WHERE i.id = ?
            ");
            $stmt->execute([$id]);
            $ingredient = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($ingredient);
            break;

        case 'create':
        case 'update':
            $id = $_POST['id'] ?? null;
            $data = [
                'nombre' => $_POST['nombre'],
                'categoria_id' => $_POST['categoria_id'],
                'presentacion' => $_POST['presentacion'],
                'precio_compra' => $_POST['precio_compra'],
                'peso_unitario' => $_POST['peso_unitario'] ?? null,
                'descripcion' => $_POST['descripcion'] ?? null
            ];

            if ($action === 'create') {
                $data['creado_en'] = date('Y-m-d H:i:s');
                $sql = "INSERT INTO Ingredientes SET " . implode('=?, ', array_keys($data)) . "=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                $id = $pdo->lastInsertId();
            } else {
                $sql = "UPDATE Ingredientes SET " . implode('=?, ', array_keys($data)) . "=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $values = array_values($data);
                $values[] = $id;
                $stmt->execute($values);
            }

            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'delete':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM Ingredientes WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => $stmt->rowCount() > 0]);
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>