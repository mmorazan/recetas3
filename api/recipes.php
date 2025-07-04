<?php
// api/recipes.php
require_once 'config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

$db = Database::getInstance()->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'getAll') {
                try {
                    // Puedes añadir JOINS para mostrar el nombre de los ingredientes, etc.
                    $stmt = $db->query("SELECT * FROM Recetas");
                    $response['success'] = true;
                    $response['data'] = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener recetas: ' . $e->getMessage();
                    error_log("Error in recipes.php (GET getAll): " . $e->getMessage());
                }
            } elseif ($_GET['action'] === 'getById' && isset($_GET['id'])) {
                try {
                    $stmt = $db->prepare("SELECT * FROM Recetas WHERE id_receta = ?");
                    $stmt->execute([$_GET['id']]);
                    $recipe = $stmt->fetch();
                    if ($recipe) {
                        $response['success'] = true;
                        $response['data'] = $recipe;
                    } else {
                        $response['message'] = 'Receta no encontrada.';
                    }
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener receta: ' . $e->getMessage();
                    error_log("Error in recipes.php (GET getById): " . $e->getMessage());
                }
            } else {
                $response['message'] = 'Acción GET no válida o ID no especificado.';
            }
        } else {
            $response['message'] = 'Acción GET no especificada.';
        }
        break;

    case 'POST':
        $action = $_POST['action'] ?? null;
        if ($action === 'add') {
            $nombre = $_POST['nombre'] ?? null;
            $descripcion = $_POST['descripcion'] ?? null;
            // Otros campos de receta...

            try {
                $stmt = $db->prepare("INSERT INTO Recetas (nombre, descripcion /*, ... otros campos */) VALUES (?, ?)");
                $stmt->execute([$nombre, $descripcion]);
                $response['success'] = true;
                $response['message'] = 'Receta agregada con éxito.';
            } catch (PDOException $e) {
                $response['message'] = 'Error al agregar receta: ' . $e->getMessage();
                error_log("Error in recipes.php (POST add): " . $e->getMessage());
            }
        } elseif ($action === 'update') {
            $id_receta = $_POST['id_receta'] ?? null;
            $nombre = $_POST['nombre'] ?? null;
            $descripcion = $_POST['descripcion'] ?? null;
            // Otros campos de receta...

            try {
                $stmt = $db->prepare("UPDATE Recetas SET nombre = ?, descripcion = ? WHERE id_receta = ?");
                $stmt->execute([$nombre, $descripcion, $id_receta]);
                $response['success'] = true;
                $response['message'] = 'Receta actualizada con éxito.';
            } catch (PDOException $e) {
                $response['message'] = 'Error al actualizar receta: ' . $e->getMessage();
                error_log("Error in recipes.php (POST update): " . $e->getMessage());
            }
        } elseif ($action === 'delete') {
            $id_receta = $_POST['id_receta'] ?? null;
            try {
                $stmt = $db->prepare("DELETE FROM Recetas WHERE id_receta = ?");
                $stmt->execute([$id_receta]);
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Receta eliminada con éxito.';
                } else {
                    $response['message'] = 'Receta no encontrada para eliminar.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Error al eliminar receta: ' . $e->getMessage();
                error_log("Error in recipes.php (POST delete): " . $e->getMessage());
            }
        } else {
            $response['message'] = 'Acción POST no válida.';
        }
        break;

    default:
        $response['message'] = 'Método de solicitud no permitido.';
        http_response_code(405);
        break;
}

echo json_encode($response);
?>