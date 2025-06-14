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
                SELECT r.*, m.nombre as menu_name 
                FROM Recetas r
                LEFT JOIN Menu m ON r.menu_id = m.id
                ORDER BY r.nombre
            ");
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($recipes);
            break;

        case 'get':
            $id = $_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM Recetas WHERE id = ?");
            $stmt->execute([$id]);
            $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode($recipe);
            break;

        case 'create':
        case 'update':
            $id = $_POST['id'] ?? null;
            $data = [
                'nombre' => $_POST['nombre'],
                'menu_id' => $_POST['menu_id'],
                'precio_venta' => $_POST['precio_venta'],
                'porciones' => $_POST['porciones'],
                'tiempo_preparacion' => $_POST['tiempo_preparacion'],
                'instrucciones' => $_POST['instrucciones']
            ];

            // Handle image upload
            if (!empty($_FILES['imagen']['name'])) {
                $uploadDir = '../uploads/recipes/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = uniqid() . '_' . basename($_FILES['imagen']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $targetPath)) {
                    $data['imagen'] = 'uploads/recipes/' . $fileName;
                }
            }

            if ($action === 'create') {
                $data['creado_en'] = date('Y-m-d H:i:s');
                $sql = "INSERT INTO Recetas SET " . implode('=?, ', array_keys($data)) . "=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($data));
                $id = $pdo->lastInsertId();
            } else {
                $sql = "UPDATE Recetas SET " . implode('=?, ', array_keys($data)) . "=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $values = array_values($data);
                $values[] = $id;
                $stmt->execute($values);
            }

            // Handle ingredients
            $ingredients = json_decode($_POST['ingredients'], true);
            $pdo->beginTransaction();
            
            try {
                // Delete existing ingredients if updating
                if ($action === 'update') {
                    $pdo->prepare("DELETE FROM RecetaIngredientes WHERE receta_id = ?")->execute([$id]);
                }
                
                // Insert new ingredients
                foreach ($ingredients as $ingredient) {
                    $stmt = $pdo->prepare("
                        INSERT INTO RecetaIngredientes 
                        (receta_id, ingrediente_id, cantidad, porcentaje_merma) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $id,
                        $ingredient['ingrediente_id'],
                        $ingredient['cantidad'],
                        $ingredient['porcentaje_merma']
                    ]);
                }
                
                $pdo->commit();
                echo json_encode(['success' => true, 'id' => $id]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'delete':
            $id = $_POST['id'];
            
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM RecetaIngredientes WHERE receta_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM Recetas WHERE id = ?")->execute([$id]);
                $pdo->commit();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no válida']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>