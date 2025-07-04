<?php
// api/reports.php
require_once 'config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

$db = Database::getInstance()->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'getMostUsedIngredients') {
                try {
                    // Esta consulta asume una tabla `Recetas_Ingredientes` que enlaza `Recetas` con `Ingredientes`.
                    // Es CRÍTICO que esta consulta devuelva `id_receta` y `id_ingrediente`
                    // para poder realizar la edición en línea del uso de un ingrediente específico en una receta específica.
                    $stmt = $db->query("
                        SELECT 
                            RI.id_receta, 
                            I.id_ingrediente, 
                            I.nombre AS ingrediente_nombre,
                            I.presentacion,
                            I.precio_compra,
                            C.nombre AS categoria_nombre,
                            COUNT(RI.id_ingrediente) AS vecesUsado, 
                            SUM(RI.cantidad_usada) AS cantidad_total_usada,
                            SUM(RI.costo_uso) AS costo_total_uso,
                            I.imagen
                        FROM 
                            Recetas_Ingredientes RI
                        JOIN 
                            Ingredientes I ON RI.id_ingrediente = I.id_ingrediente
                        LEFT JOIN 
                            Categorias C ON I.id_categoria = C.id_categoria
                        GROUP BY 
                            RI.id_receta, I.id_ingrediente, I.nombre, I.presentacion, I.precio_compra, C.nombre, I.imagen
                        ORDER BY 
                            vecesUsado DESC, cantidad_total_usada DESC
                    ");
                    $reportData = $stmt->fetchAll();
                    $response['success'] = true;
                    $response['data'] = $reportData;
                } catch (PDOException $e) {
                    $response['message'] = 'Error al obtener el reporte de ingredientes: ' . $e->getMessage();
                    error_log("Error in reports.php (GET getMostUsedIngredients): " . $e->getMessage());
                }
            } else {
                $response['message'] = 'Acción GET no válida.';
            }
        } else {
            $response['message'] = 'Acción GET no especificada.';
        }
        break;

    case 'POST':
        $action = $_POST['action'] ?? null;
        if ($action === 'updateRecipeIngredient') {
            $idReceta = $_POST['id_receta'] ?? null;
            $idIngrediente = $_POST['id_ingrediente'] ?? null;
            $cantidadUsada = $_POST['cantidad_usada'] ?? null;
            $costoUso = $_POST['costo_uso'] ?? null; 

            if ($idReceta === null || $idIngrediente === null || $cantidadUsada === null || $costoUso === null) {
                $response['message'] = 'Datos incompletos para la actualización.';
                echo json_encode($response);
                exit;
            }

            try {
                $stmt = $db->prepare("UPDATE Recetas_Ingredientes SET cantidad_usada = ?, costo_uso = ? WHERE id_receta = ? AND id_ingrediente = ?");
                $stmt->execute([$cantidadUsada, $costoUso, $idReceta, $idIngrediente]);

                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Ingrediente de la receta actualizado con éxito.';
                } else {
                    $response['message'] = 'No se encontró el ingrediente en la receta o no se realizaron cambios.';
                    $response['success'] = true; 
                }
            } catch (PDOException $e) {
                $response['message'] = 'Error al actualizar el ingrediente de la receta: ' . $e->getMessage();
                error_log("Error in reports.php (POST updateRecipeIngredient): " . $e->getMessage());
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