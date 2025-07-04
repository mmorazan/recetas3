<?php
// api/categories.php
require_once 'config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

$db = Database::getInstance()->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'getAll') {
                try {
                    $stmt = $db->query("SELECT * FROM Categorias");
                    $response['success'] = true;
                    $response['data'] = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener categorías: ' . $e->getMessage();
                    error_log("Error in categories.php (GET getAll): " . $e->getMessage());
                }
            } elseif ($_GET['action'] === 'getById' && isset($_GET['id'])) {
                try {
                    $stmt = $db->prepare("SELECT * FROM Categorias WHERE id_categoria = ?");
                    $stmt->execute([$_GET['id']]);
                    $category = $stmt->fetch();
                    if ($category) {
                        $response['success'] = true;
                        $response['data'] = $category;
                    } else {
                        $response['message'] = 'Categoría no encontrada.';
                    }
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener categoría: ' . $e->getMessage();
                    error_log("Error in categories.php (GET getById): " . $e->getMessage());
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
            try {
                $stmt = $db->prepare("INSERT INTO Categorias (nombre) VALUES (?)");
                $stmt->execute([$nombre]);
                $response['success'] = true;
                $response['message'] = 'Categoría agregada con éxito.';
            } catch (PDOException $e) {
                $response['message'] = 'Error al agregar categoría: ' . $e->getMessage();
                error_log("Error in categories.php (POST add): " . $e->getMessage());
            }
        } elseif ($action === 'update') {
            $id_categoria = $_POST['id_categoria'] ?? null;
            $nombre = $_POST['nombre'] ?? null;
            try {
                $stmt = $db->prepare("UPDATE Categorias SET nombre = ? WHERE id_categoria = ?");
                $stmt->execute([$nombre, $id_categoria]);
                $response['success'] = true;
                $response['message'] = 'Categoría actualizada con éxito.';
            } catch (PDOException $e) {
                $response['message'] = 'Error al actualizar categoría: ' . $e->getMessage();
                error_log("Error in categories.php (POST update): " . $e->getMessage());
            }
        } elseif ($action === 'delete') {
            $id_categoria = $_POST['id_categoria'] ?? null;
            try {
                $stmt = $db->prepare("DELETE FROM Categorias WHERE id_categoria = ?");
                $stmt->execute([$id_categoria]);
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Categoría eliminada con éxito.';
                } else {
                    $response['message'] = 'Categoría no encontrada para eliminar.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Error al eliminar categoría: ' . $e->getMessage();
                error_log("Error in categories.php (POST delete): " . $e->getMessage());
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