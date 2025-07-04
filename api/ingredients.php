<?php
// api/ingredients.php
require_once 'config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

$db = Database::getInstance()->getConnection();
$imageUploader = new ImageUploader();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'getAll') {
                try {
                    $stmt = $db->query("SELECT I.*, C.nombre AS categoria_nombre, P.nombre AS proveedor_nombre FROM Ingredientes I LEFT JOIN Categorias C ON I.id_categoria = C.id_categoria LEFT JOIN Proveedores P ON I.id_proveedor = P.id_proveedor");
                    $response['success'] = true;
                    $response['data'] = $stmt->fetchAll();
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener ingredientes: ' . $e->getMessage();
                    error_log("Error in ingredients.php (GET getAll): " . $e->getMessage());
                }
            } elseif ($_GET['action'] === 'getById' && isset($_GET['id'])) {
                try {
                    $stmt = $db->prepare("SELECT * FROM Ingredientes WHERE id_ingrediente = ?");
                    $stmt->execute([$_GET['id']]);
                    $ingredient = $stmt->fetch();
                    if ($ingredient) {
                        $response['success'] = true;
                        $response['data'] = $ingredient;
                    } else {
                        $response['message'] = 'Ingrediente no encontrado.';
                    }
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener ingrediente: ' . $e->getMessage();
                    error_log("Error in ingredients.php (GET getById): " . $e->getMessage());
                }
            } else {
                $response['message'] = 'Acción GET no válida o ID no especificado.';
            }
        } else {
            $response['message'] = 'Acción GET no especificada.';
        }
        break;

    case 'POST': // Para agregar un nuevo ingrediente
        $action = $_POST['action'] ?? null;
        if ($action === 'add') {
            $nombre = $_POST['nombre'] ?? null;
            $presentacion = $_POST['presentacion'] ?? null;
            $precio_compra = $_POST['precio_compra'] ?? null;
            $id_proveedor = $_POST['id_proveedor'] ?? null;
            $id_categoria = $_POST['id_categoria'] ?? null;
            $imagen = null;

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $imageUploader->upload($_FILES['imagen'], 'ingredientes');
                if ($uploadResult['success']) {
                    $imagen = $uploadResult['fileName'];
                } else {
                    $response['message'] = 'Error al subir imagen: ' . $uploadResult['message'];
                    echo json_encode($response);
                    exit;
                }
            }

            try {
                $stmt = $db->prepare("INSERT INTO Ingredientes (nombre, presentacion, precio_compra, id_proveedor, id_categoria, imagen) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $presentacion, $precio_compra, $id_proveedor, $id_categoria, $imagen]);
                $response['success'] = true;
                $response['message'] = 'Ingrediente agregado con éxito.';
            } catch (PDOException $e) {
                $response['message'] = 'Error al agregar ingrediente: ' . $e->getMessage();
                error_log("Error in ingredients.php (POST add): " . $e->getMessage());
            }
        } elseif ($action === 'update') { // Para actualizar un ingrediente existente
            $id_ingrediente = $_POST['id_ingrediente'] ?? null;
            $nombre = $_POST['nombre'] ?? null;
            $presentacion = $_POST['presentacion'] ?? null;
            $precio_compra = $_POST['precio_compra'] ?? null;
            $id_proveedor = $_POST['id_proveedor'] ?? null;
            $id_categoria = $_POST['id_categoria'] ?? null;
            $current_imagen = $_POST['current_imagen'] ?? null; // Si no se sube nueva imagen, usar la existente

            $imagen = $current_imagen; // Por defecto, mantener la imagen actual

            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $imageUploader->upload($_FILES['imagen'], 'ingredientes');
                if ($uploadResult['success']) {
                    $imagen = $uploadResult['fileName'];
                    // Opcional: Eliminar la imagen antigua si ya no se usa
                    // if ($current_imagen && file_exists('../uploads/ingredientes/' . $current_imagen)) {
                    //     unlink('../uploads/ingredientes/' . $current_imagen);
                    // }
                } else {
                    $response['message'] = 'Error al subir nueva imagen: ' . $uploadResult['message'];
                    echo json_encode($response);
                    exit;
                }
            }

            try {
                $stmt = $db->prepare("UPDATE Ingredientes SET nombre = ?, presentacion = ?, precio_compra = ?, id_proveedor = ?, id_categoria = ?, imagen = ? WHERE id_ingrediente = ?");
                $stmt->execute([$nombre, $presentacion, $precio_compra, $id_proveedor, $id_categoria, $imagen, $id_ingrediente]);
                $response['success'] = true;
                $response['message'] = 'Ingrediente actualizado con éxito.';
            } catch (PDOException $e) {
                $response['message'] = 'Error al actualizar ingrediente: ' . $e->getMessage();
                error_log("Error in ingredients.php (POST update): " . $e->getMessage());
            }
        } elseif ($action === 'delete') { // Para eliminar un ingrediente
            $id_ingrediente = $_POST['id_ingrediente'] ?? null;
            try {
                // Opcional: Primero obtener la imagen para eliminarla del servidor
                // $stmt = $db->prepare("SELECT imagen FROM Ingredientes WHERE id_ingrediente = ?");
                // $stmt->execute([$id_ingrediente]);
                // $imgToDelete = $stmt->fetchColumn();
                
                $stmt = $db->prepare("DELETE FROM Ingredientes WHERE id_ingrediente = ?");
                $stmt->execute([$id_ingrediente]);
                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Ingrediente eliminado con éxito.';
                    // Opcional: Eliminar archivo de imagen
                    // if ($imgToDelete && file_exists('../uploads/ingredientes/' . $imgToDelete)) {
                    //     unlink('../uploads/ingredientes/' . $imgToDelete);
                    // }
                } else {
                    $response['message'] = 'Ingrediente no encontrado para eliminar.';
                }
            } catch (PDOException $e) {
                $response['message'] = 'Error al eliminar ingrediente: ' . $e->getMessage();
                error_log("Error in ingredients.php (POST delete): " . $e->getMessage());
            }
        } else {
            $response['message'] = 'Acción POST no válida.';
        }
        break;

    default:
        $response['message'] = 'Método de solicitud no permitido.';
        http_response_code(405); // Método no permitido
        break;
}

echo json_encode($response);
?>