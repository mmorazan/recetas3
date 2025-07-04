<?php
// config.php - Configuración de la base de datos
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=localhost;dbname=Recetas;charset=utf8mb4",
                "recetas",
                "gcode2025!",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
}

// Clase para manejar subida de imágenes
class ImageUploader {
    private $uploadDir = 'uploads/';
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    private $allowedExtensions = ['jpg', 'jpeg', 'png'];
    
    public function __construct() {
        $this->createDirectories();
    }
    
    private function createDirectories() {
        $dirs = [
            $this->uploadDir,
            $this->uploadDir . 'menu/',
            $this->uploadDir . 'categorias/',
            $this->uploadDir . 'recetas/',
            $this->uploadDir . 'ingredientes/',
            $this->uploadDir . 'thumbs/',
            $this->uploadDir . 'thumbs/menu/',
            $this->uploadDir . 'thumbs/categorias/',
            $this->uploadDir . 'thumbs/recetas/',
            $this->uploadDir . 'thumbs/ingredientes/'
        ];
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    public function uploadImage($file, $type, $id = null) {
        try {
            // Validaciones
            if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
                throw new Exception("No se seleccionó ningún archivo");
            }
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error en la subida del archivo: " . $this->getUploadErrorMessage($file['error']));
            }
            
            if ($file['size'] > $this->maxFileSize) {
                throw new Exception("El archivo es demasiado grande. Máximo 5MB permitido");
            }
            
            $mimeType = mime_content_type($file['tmp_name']);
            if (!in_array($mimeType, $this->allowedTypes)) {
                throw new Exception("Tipo de archivo no permitido. Solo JPG y PNG");
            }
            
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedExtensions)) {
                throw new Exception("Extensión de archivo no permitida");
            }
            
            // Generar nombre único
            $fileName = $this->generateFileName($extension, $type, $id);
            $fullPath = $this->uploadDir . $type . '/' . $fileName;
            $thumbPath = $this->uploadDir . 'thumbs/' . $type . '/' . $fileName;
            
            // Subir archivo original
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new Exception("Error al guardar el archivo");
            }
            
            // Crear thumbnail
            $this->createThumbnail($fullPath, $thumbPath);
            
            // Registrar en base de datos
            $this->logImageUpload($type, $id, $fileName, $fullPath, $file['size'], $mimeType);
            
            return $fileName;
            
        } catch (Exception $e) {
            error_log("Error en upload de imagen: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function generateFileName($extension, $type, $id = null) {
        $prefix = $type . '_';
        if ($id) {
            $prefix .= $id . '_';
        }
        return $prefix . uniqid() . '.' . $extension;
    }
    
    private function createThumbnail($sourcePath, $thumbPath, $maxWidth = 150, $maxHeight = 150) {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) return false;
            
            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];
            
            // Calcular nuevas dimensiones manteniendo proporción
            $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
            $newWidth = round($sourceWidth * $ratio);
            $newHeight = round($sourceHeight * $ratio);
            
            // Crear imagen source
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                default:
                    return false;
            }
            
            if (!$sourceImage) return false;
            
            // Crear thumbnail
            $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
            
            // Para PNG con transparencia
            if ($mimeType === 'image/png') {
                imagealphablending($thumbImage, false);
                imagesavealpha($thumbImage, true);
                $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                imagefill($thumbImage, 0, 0, $transparent);
            }
            
            imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
            
            // Guardar thumbnail
            switch ($mimeType) {
                case 'image/jpeg':
                    imagejpeg($thumbImage, $thumbPath, 85);
                    break;
                case 'image/png':
                    imagepng($thumbImage, $thumbPath);
                    break;
            }
            
            imagedestroy($sourceImage);
            imagedestroy($thumbImage);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error creando thumbnail: " . $e->getMessage());
            return false;
        }
    }
    
    private function logImageUpload($type, $id, $fileName, $fullPath, $fileSize, $mimeType) {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                INSERT INTO ImagenesSubidas (tabla_origen, registro_id, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$type, $id, $fileName, $fullPath, $fileSize, $mimeType]);
        } catch (Exception $e) {
            error_log("Error logging image upload: " . $e->getMessage());
        }
    }
    
    public function deleteImage($fileName, $type) {
        try {
            $fullPath = $this->uploadDir . $type . '/' . $fileName;
            $thumbPath = $this->uploadDir . 'thumbs/' . $type . '/' . $fileName;
            
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error eliminando imagen: " . $e->getMessage());
            return false;
        }
    }
    
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return "El archivo es demasiado grande";
            case UPLOAD_ERR_PARTIAL:
                return "El archivo se subió parcialmente";
            case UPLOAD_ERR_NO_FILE:
                return "No se subió ningún archivo";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Falta carpeta temporal";
            case UPLOAD_ERR_CANT_WRITE:
                return "Error al escribir archivo";
            case UPLOAD_ERR_EXTENSION:
                return "Extensión no permitida";
            default:
                return "Error desconocido";
        }
    }
}

// Clase para manejar el menú
class MenuManager {
    private $db;
    private $imageUploader;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->imageUploader = new ImageUploader();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM Menu ORDER BY nombre");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM Menu WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($nombre, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            $imageName = null;
            if ($imageFile && $imageFile['tmp_name']) {
                $imageName = $this->imageUploader->uploadImage($imageFile, 'menu');
            }
            
            $stmt = $this->db->prepare("INSERT INTO Menu (nombre, imagen) VALUES (?, ?)");
            $result = $stmt->execute([$nombre, $imageName]);
            
            if ($result && $imageName) {
                $menuId = $this->db->lastInsertId();
                $this->updateImageLog($imageName, 'menu', $menuId);
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            if (isset($imageName)) {
                $this->imageUploader->deleteImage($imageName, 'menu');
            }
            throw $e;
        }
    }
    
    public function update($id, $nombre, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            // Obtener imagen actual
            $currentData = $this->getById($id);
            $currentImage = $currentData['imagen'];
            
            $imageName = $currentImage; // Mantener imagen actual por defecto
            
            // Si se subió nueva imagen
            if ($imageFile && $imageFile['tmp_name']) {
                // Eliminar imagen anterior
                if ($currentImage) {
                    $this->imageUploader->deleteImage($currentImage, 'menu');
                }
                
                // Subir nueva imagen
                $imageName = $this->imageUploader->uploadImage($imageFile, 'menu', $id);
            }
            
            $stmt = $this->db->prepare("UPDATE Menu SET nombre = ?, imagen = ? WHERE id = ?");
            $result = $stmt->execute([$nombre, $imageName, $id]);
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            // Obtener imagen para eliminar
            $menu = $this->getById($id);
            
            $stmt = $this->db->prepare("DELETE FROM Menu WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            // Eliminar imagen si existe
            if ($result && $menu['imagen']) {
                $this->imageUploader->deleteImage($menu['imagen'], 'menu');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function updateImageLog($fileName, $type, $id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE ImagenesSubidas 
                SET registro_id = ? 
                WHERE nombre_archivo = ? AND tabla_origen = ?
            ");
            $stmt->execute([$id, $fileName, $type]);
        } catch (Exception $e) {
            error_log("Error updating image log: " . $e->getMessage());
        }
    }
}

// Clase para manejar categorías
class CategoriasManager {
    private $db;
    private $imageUploader;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->imageUploader = new ImageUploader();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM Categorias ORDER BY nombre");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM Categorias WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($nombre, $descripcion, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            $imageName = null;
            if ($imageFile && $imageFile['tmp_name']) {
                $imageName = $this->imageUploader->uploadImage($imageFile, 'categorias');
            }
            
            $stmt = $this->db->prepare("INSERT INTO Categorias (nombre, descripcion, imagen, creado_en) VALUES (?, ?, ?, NOW())");
            $result = $stmt->execute([$nombre, $descripcion, $imageName]);
            
            if ($result && $imageName) {
                $categoriaId = $this->db->lastInsertId();
                $this->updateImageLog($imageName, 'categorias', $categoriaId);
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            if (isset($imageName)) {
                $this->imageUploader->deleteImage($imageName, 'categorias');
            }
            throw $e;
        }
    }
    
    public function update($id, $nombre, $descripcion, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            // Obtener imagen actual
            $currentData = $this->getById($id);
            $currentImage = $currentData['imagen'];
            
            $imageName = $currentImage; // Mantener imagen actual por defecto
            
            // Si se subió nueva imagen
            if ($imageFile && $imageFile['tmp_name']) {
                // Eliminar imagen anterior
                if ($currentImage) {
                    $this->imageUploader->deleteImage($currentImage, 'categorias');
                }
                
                // Subir nueva imagen
                $imageName = $this->imageUploader->uploadImage($imageFile, 'categorias', $id);
            }
            
            $stmt = $this->db->prepare("UPDATE Categorias SET nombre = ?, descripcion = ?, imagen = ? WHERE id = ?");
            $result = $stmt->execute([$nombre, $descripcion, $imageName, $id]);
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            // Verificar si hay ingredientes que usen esta categoría
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM Ingredientes WHERE categoria_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                return false; // No se puede eliminar
            }
            
            // Obtener imagen para eliminar
            $categoria = $this->getById($id);
            
            $stmt = $this->db->prepare("DELETE FROM Categorias WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            // Eliminar imagen si existe
            if ($result && $categoria['imagen']) {
                $this->imageUploader->deleteImage($categoria['imagen'], 'categorias');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function updateImageLog($fileName, $type, $id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE ImagenesSubidas 
                SET registro_id = ? 
                WHERE nombre_archivo = ? AND tabla_origen = ?
            ");
            $stmt->execute([$id, $fileName, $type]);
        } catch (Exception $e) {
            error_log("Error updating image log: " . $e->getMessage());
        }
    }
}

// Clase para manejar ingredientes
class IngredientesManager {
    private $db;
    private $imageUploader;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->imageUploader = new ImageUploader();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT i.*, c.nombre as categoria_nombre 
            FROM Ingredientes i 
            LEFT JOIN Categorias c ON i.categoria_id = c.id 
            ORDER BY i.nombre
        ");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT i.*, c.nombre as categoria_nombre 
            FROM Ingredientes i 
            LEFT JOIN Categorias c ON i.categoria_id = c.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            // Insertar ingrediente
            $stmt = $this->db->prepare("
                INSERT INTO Ingredientes (nombre, categoria_id, presentacion, precio_compra, peso_unitario, descripcion, imagen, creado_en) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $imageName = null;
            if ($imageFile && $imageFile['tmp_name']) {
                $imageName = $this->imageUploader->uploadImage($imageFile, 'ingredientes');
            }
            
            $result = $stmt->execute([
                $data['nombre'],
                $data['categoria_id'],
                $data['presentacion'],
                $data['precio_compra'],
                $data['peso_unitario'],
                $data['descripcion'],
                $imageName
            ]);
            
            if ($result && $imageName) {
                $ingredienteId = $this->db->lastInsertId();
                // Actualizar el log con el ID correcto
                $this->updateImageLog($imageName, 'ingredientes', $ingredienteId);
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            if (isset($imageName)) {
                $this->imageUploader->deleteImage($imageName, 'ingredientes');
            }
            throw $e;
        }
    }
    
    public function update($id, $data, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            // Obtener imagen actual
            $currentData = $this->getById($id);
            $currentImage = $currentData['imagen'];
            
            $imageName = $currentImage; // Mantener imagen actual por defecto
            
            // Si se subió nueva imagen
            if ($imageFile && $imageFile['tmp_name']) {
                // Eliminar imagen anterior
                if ($currentImage) {
                    $this->imageUploader->deleteImage($currentImage, 'ingredientes');
                }
                
                // Subir nueva imagen
                $imageName = $this->imageUploader->uploadImage($imageFile, 'ingredientes', $id);
            }
            
            $stmt = $this->db->prepare("
                UPDATE Ingredientes 
                SET nombre = ?, categoria_id = ?, presentacion = ?, precio_compra = ?, peso_unitario = ?, descripcion = ?, imagen = ?
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['nombre'],
                $data['categoria_id'],
                $data['presentacion'],
                $data['precio_compra'],
                $data['peso_unitario'],
                $data['descripcion'],
                $imageName,
                $id
            ]);
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            // Verificar si hay recetas que usen este ingrediente
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM RecetaIngredientes WHERE ingrediente_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                return false; // No se puede eliminar
            }
            
            // Obtener imagen para eliminar
            $ingrediente = $this->getById($id);
            
            $stmt = $this->db->prepare("DELETE FROM Ingredientes WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            // Eliminar imagen si existe
            if ($result && $ingrediente['imagen']) {
                $this->imageUploader->deleteImage($ingrediente['imagen'], 'ingredientes');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function updateImageLog($fileName, $type, $id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE ImagenesSubidas 
                SET registro_id = ? 
                WHERE nombre_archivo = ? AND tabla_origen = ?
            ");
            $stmt->execute([$id, $fileName, $type]);
        } catch (Exception $e) {
            error_log("Error updating image log: " . $e->getMessage());
        }
    }
    
    public function getPresentaciones() {
        return ['Libra', 'Unidad', 'Gramos', 'Onza', 'Kilogramo', 'Litro', 'Mililitro', 'Cucharada'];
    }
    
    // Nuevo método para reporte: Ingredientes por Categoría
    public function getIngredientesPorCategoria() {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT c.nombre AS categoria, 
                       COUNT(i.id) AS total_ingredientes,
                       AVG(i.precio_compra) AS precio_promedio,
                       MIN(i.precio_compra) AS precio_minimo,
                       MAX(i.precio_compra) AS precio_maximo
                FROM Categorias c
                LEFT JOIN Ingredientes i ON c.id = i.categoria_id
                GROUP BY c.id
                ORDER BY c.nombre
            ");
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en getIngredientesPorCategoria: " . $e->getMessage());
            return [];
        }
    }
}

// Clase para manejar recetas
class RecetasManager {
    private $db;
    private $imageUploader;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->imageUploader = new ImageUploader();
    }
    
    public function getAll() {
        $stmt = $this->db->query("SELECT * FROM Recetas ORDER BY nombre");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM Recetas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getRecetaConIngredientes($id) {
        $receta = $this->getById($id);
        if (!$receta) return null;
        
        $stmt = $this->db->prepare("
            SELECT ri.*, i.nombre as ingrediente_nombre, i.presentacion, i.precio_compra
            FROM RecetaIngredientes ri
            JOIN Ingredientes i ON ri.ingrediente_id = i.id
            WHERE ri.receta_id = ?
            ORDER BY i.nombre
        ");
        $stmt->execute([$id]);
        $receta['ingredientes'] = $stmt->fetchAll();
        
        // Actualizar los costos calculados con los precios actuales de los ingredientes
        foreach ($receta['ingredientes'] as &$ingrediente) {
            $ingrediente['costo_calculado'] = $this->calcularCostoIngrediente($ingrediente, $ingrediente['cantidad'], $ingrediente['porcentaje_merma']);
        }
        
        // Actualizar el costo total de la receta
        $this->actualizarCostoTotalReceta($id);
        
        return $receta;
    }
    
    public function create($data, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            $imageName = null;
            if ($imageFile && $imageFile['tmp_name']) {
                $imageName = $this->imageUploader->uploadImage($imageFile, 'recetas');
            }
            
            $stmt = $this->db->prepare("
                INSERT INTO Recetas (nombre, imagen, precio_venta, instrucciones, tiempo_preparacion, porciones, menu_id, descripcion, creado_en) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $data['nombre'],
                $imageName,
                $data['precio_venta'],
                $data['instrucciones'],
                $data['tiempo_preparacion'],
                $data['porciones'],
                $data['menu_id'],  // Nuevo campo
                $data['descripcion'] // Nuevo campo descripción
            ]);
            
            if ($result && $imageName) {
                $recetaId = $this->db->lastInsertId();
                $this->updateImageLog($imageName, 'recetas', $recetaId);
            }
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            if (isset($imageName)) {
                $this->imageUploader->deleteImage($imageName, 'recetas');
            }
            throw $e;
        }
    }
    
    public function update($id, $data, $imageFile = null) {
        try {
            $this->db->beginTransaction();
            
            // Obtener imagen actual
            $currentData = $this->getById($id);
            $currentImage = $currentData['imagen'];
            
            $imageName = $currentImage; // Mantener imagen actual por defecto
            
            // Si se subió nueva imagen
            if ($imageFile && $imageFile['tmp_name']) {
                // Eliminar imagen anterior
                if ($currentImage) {
                    $this->imageUploader->deleteImage($currentImage, 'recetas');
                }
                
                // Subir nueva imagen
                $imageName = $this->imageUploader->uploadImage($imageFile, 'recetas', $id);
            }
            
            $stmt = $this->db->prepare("
                UPDATE Recetas 
                SET nombre = ?, imagen = ?, precio_venta = ?, instrucciones = ?, tiempo_preparacion = ?, porciones = ?, menu_id = ?, descripcion = ?
                WHERE id = ?
            ");
            
            $result = $stmt->execute([
                $data['nombre'],
                $imageName,
                $data['precio_venta'],
                $data['instrucciones'],
                $data['tiempo_preparacion'],
                $data['porciones'],
                $data['menu_id'],  // Nuevo campo
                $data['descripcion'], // Nuevo campo descripción
                $id
            ]);
            
            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    public function delete($id) {
        try {
            // Obtener imagen para eliminar
            $receta = $this->getById($id);
            
            // Los ingredientes de la receta se eliminan automáticamente por CASCADE
            $stmt = $this->db->prepare("DELETE FROM Recetas WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            // Eliminar imagen si existe
            if ($result && $receta['imagen']) {
                $this->imageUploader->deleteImage($receta['imagen'], 'recetas');
            }
            
            return $result;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    private function updateImageLog($fileName, $type, $id) {
        try {
            $stmt = $this->db->prepare("
                UPDATE ImagenesSubidas 
                SET registro_id = ? 
                WHERE nombre_archivo = ? AND tabla_origen = ?
            ");
            $stmt->execute([$id, $fileName, $type]);
        } catch (Exception $e) {
            error_log("Error updating image log: " . $e->getMessage());
        }
    }
    
    public function addIngrediente($receta_id, $ingrediente_id, $cantidad, $porcentaje_merma = 0) {
        try {
            // Debug: Verificar valores recibidos
            error_log("addIngrediente - Receta ID: $receta_id, Ingrediente ID: $ingrediente_id, Cantidad: $cantidad, Merma: $porcentaje_merma");
            
            // Verificar que la receta exista
            $stmt = $this->db->prepare("SELECT id FROM Recetas WHERE id = ?");
            $stmt->execute([$receta_id]);
            if (!$stmt->fetch()) {
                throw new Exception("Receta no encontrada con ID: $receta_id");
            }
            
            // Verificar que el ingrediente exista y obtener sus datos
            $stmt = $this->db->prepare("SELECT id, nombre, precio_compra, presentacion, peso_unitario FROM Ingredientes WHERE id = ?");
            $stmt->execute([$ingrediente_id]);
            $ingrediente = $stmt->fetch();
            if (!$ingrediente) {
                throw new Exception("Ingrediente no encontrado con ID: $ingrediente_id");
            }
            
            // Verificar si el ingrediente ya está en la receta
            $stmt = $this->db->prepare("SELECT id FROM RecetaIngredientes WHERE receta_id = ? AND ingrediente_id = ?");
            $stmt->execute([$receta_id, $ingrediente_id]);
            if ($stmt->fetch()) {
                throw new Exception("El ingrediente '{$ingrediente['nombre']}' ya está agregado a esta receta");
            }
            
            // Calcular costo del ingrediente
            $costo_calculado = $this->calcularCostoIngrediente($ingrediente, $cantidad, $porcentaje_merma);
            
            // Insertar el ingrediente en la receta CON el costo ya calculado
            $stmt = $this->db->prepare("
                INSERT INTO RecetaIngredientes (receta_id, ingrediente_id, cantidad, porcentaje_merma, costo_calculado, creado_en) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $result = $stmt->execute([$receta_id, $ingrediente_id, $cantidad, $porcentaje_merma, $costo_calculado]);
            
            if ($result) {
                $receta_ingrediente_id = $this->db->lastInsertId();
                
                // Actualizar el costo total de la receta
                $this->actualizarCostoTotalReceta($receta_id);
                
                error_log("Ingrediente agregado exitosamente con costo: $costo_calculado");
                return $receta_ingrediente_id;
            }
            
            throw new Exception("No se pudo insertar el ingrediente en la base de datos");
            
        } catch (PDOException $e) {
            error_log("Error PDO en addIngrediente: " . $e->getMessage());
            throw new Exception("Error de base de datos: " . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error en addIngrediente: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function updateIngrediente($receta_ingrediente_id, $cantidad, $porcentaje_merma = 0) {
        try {
            // Obtener datos del ingrediente para recalcular el costo
            $stmt = $this->db->prepare("
                SELECT ri.receta_id, i.precio_compra, i.presentacion, i.peso_unitario
                FROM RecetaIngredientes ri
                JOIN Ingredientes i ON ri.ingrediente_id = i.id
                WHERE ri.id = ?
            ");
            $stmt->execute([$receta_ingrediente_id]);
            $data = $stmt->fetch();
            
            if (!$data) {
                throw new Exception("Registro de ingrediente no encontrado");
            }
            
            // Calcular nuevo costo
            $ingrediente = [
                'precio_compra' => $data['precio_compra'],
                'presentacion' => $data['presentacion'],
                'peso_unitario' => $data['peso_unitario']
            ];
            $costo_calculado = $this->calcularCostoIngrediente($ingrediente, $cantidad, $porcentaje_merma);
            
            // Actualizar cantidad, merma y costo
            $stmt = $this->db->prepare("
                UPDATE RecetaIngredientes 
                SET cantidad = ?, porcentaje_merma = ?, costo_calculado = ?
                WHERE id = ?
            ");
            $result = $stmt->execute([$cantidad, $porcentaje_merma, $costo_calculado, $receta_ingrediente_id]);
            
            if ($result) {
                // Actualizar costo total de la receta
                $this->actualizarCostoTotalReceta($data['receta_id']);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error en updateIngrediente: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function removeIngrediente($receta_ingrediente_id) {
        try {
            // Obtener ID de la receta antes de eliminar
            $stmt = $this->db->prepare("SELECT receta_id FROM RecetaIngredientes WHERE id = ?");
            $stmt->execute([$receta_ingrediente_id]);
            $receta_id = $stmt->fetchColumn();
            
            if (!$receta_id) {
                throw new Exception("Ingrediente no encontrado");
            }
            
            // Eliminar el ingrediente
            $stmt = $this->db->prepare("DELETE FROM RecetaIngredientes WHERE id = ?");
            $result = $stmt->execute([$receta_ingrediente_id]);
            
            if ($result) {
                // Actualizar costo total de la receta
                $this->actualizarCostoTotalReceta($receta_id);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error en removeIngrediente: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Función para calcular el costo de un ingrediente
    private function calcularCostoIngrediente($ingrediente, $cantidad, $porcentaje_merma) {
        $precio_compra = floatval($ingrediente['precio_compra']);
        $cantidad = floatval($cantidad);
        $porcentaje_merma = floatval($porcentaje_merma);
        $presentacion = $ingrediente['presentacion'];
        $peso_unitario = floatval($ingrediente['peso_unitario'] ?? 1);
        
        $costo_final = 0;
        
        switch ($presentacion) {
            case 'Unidad':
                // Para unidades, dividir precio entre peso unitario y multiplicar por cantidad
                $costo_final = ($precio_compra / $peso_unitario) * $cantidad;
                break;
            case 'Libra':
            case 'Kilogramo':
            case 'Gramos':
            case 'Onza':
            case 'Litro':
            case 'Mililitro':
            case 'Cucharada':
            default:
                // Para el resto, precio por unidad de medida * cantidad
                $costo_final = $precio_compra * $cantidad;
                break;
        }
        
        // Aplicar porcentaje de merma si es mayor a 0
        if ($porcentaje_merma > 0) {
            $costo_final = $costo_final * (1 + ($porcentaje_merma / 100));
        }
        
        return round($costo_final, 2);
    }
    
    // Función para actualizar el costo total de una receta
    private function actualizarCostoTotalReceta($receta_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(costo_calculado), 0) as costo_total
                FROM RecetaIngredientes 
                WHERE receta_id = ?
            ");
            $stmt->execute([$receta_id]);
            $costo_total = $stmt->fetchColumn();
            
            $stmt = $this->db->prepare("UPDATE Recetas SET costo_total = ? WHERE id = ?");
            $stmt->execute([$costo_total, $receta_id]);
            
            error_log("Costo total actualizado para receta $receta_id: $costo_total");
        } catch (Exception $e) {
            error_log("Error actualizando costo total: " . $e->getMessage());
        }
    }
    
    public function getLastInsertId() {
        return $this->db->lastInsertId();
    }
    
    // Nuevo método para reporte: Recetas por Menú (modificado)
    public function getRecetasPorMenu() {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT m.nombre AS menu, 
                       r.id AS receta_id,
                       r.nombre AS receta, 
                       r.descripcion,  -- Nuevo campo
                       r.precio_venta, 
                       r.costo_total, 
                       (r.precio_venta - r.costo_total) AS ganancia, 
                       r.porciones
                FROM Recetas r
                JOIN Menu m ON r.menu_id = m.id
                ORDER BY m.nombre, r.nombre
            ");
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en getRecetasPorMenu: " . $e->getMessage());
            return [];
        }
    }

    // Nuevo método para reporte: Rentabilidad de Recetas
    public function getRentabilidadRecetas() {
        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->prepare("
                SELECT nombre, descripcion, precio_venta, costo_total, 
                       (precio_venta - costo_total) AS ganancia,
                       ROUND(((precio_venta - costo_total) / precio_venta) * 100, 2) AS margen_ganancia,
                       porciones
                FROM Recetas
                ORDER BY ganancia DESC
            ");
            
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en getRentabilidadRecetas: " . $e->getMessage());
            return [];
        }
    }
}

// Manejo de requests AJAX y acciones
session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? 'dashboard';
$table = $_GET['table'] ?? $_POST['table'] ?? '';
$id = $_GET['id'] ?? $_POST['id'] ?? null;

// Instanciar managers
$menuManager = new MenuManager();
$categoriasManager = new CategoriasManager();
$ingredientesManager = new IngredientesManager();
$recetasManager = new RecetasManager();
$imageUploader = new ImageUploader();

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        switch ($action) {
            case 'create':
                $result = false;
                $lastId = null;
                switch ($table) {
                    case 'menu':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $menuManager->create($_POST['nombre'], $imageFile);
                        break;
                    case 'categorias':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $categoriasManager->create($_POST['nombre'], $_POST['descripcion'], $imageFile);
                        break;
                    case 'ingredientes':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $ingredientesManager->create($_POST, $imageFile);
                        break;
                    case 'recetas':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $recetasManager->create($_POST, $imageFile);
                        if ($result) {
                            $lastId = $recetasManager->getLastInsertId();
                        }
                        break;
                }
                echo json_encode(['success' => $result, 'id' => $lastId]);
                exit;
                
            case 'update':
                $result = false;
                switch ($table) {
                    case 'menu':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $menuManager->update($id, $_POST['nombre'], $imageFile);
                        break;
                    case 'categorias':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $categoriasManager->update($id, $_POST['nombre'], $_POST['descripcion'], $imageFile);
                        break;
                    case 'ingredientes':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $ingredientesManager->update($id, $_POST, $imageFile);
                        break;
                    case 'recetas':
                        $imageFile = isset($_FILES['imagen']) ? $_FILES['imagen'] : null;
                        $result = $recetasManager->update($id, $_POST, $imageFile);
                        break;
                }
                echo json_encode(['success' => $result]);
                exit;
                
            case 'delete':
                $result = false;
                switch ($table) {
                    case 'menu':
                        $result = $menuManager->delete($id);
                        break;
                    case 'categorias':
                        $result = $categoriasManager->delete($id);
                        break;
                    case 'ingredientes':
                        $result = $ingredientesManager->delete($id);
                        break;
                    case 'recetas':
                        $result = $recetasManager->delete($id);
                        break;
                }
                echo json_encode(['success' => $result]);
                exit;
                
            case 'add_ingrediente_receta':
                try {
                    // Debug: Log de datos recibidos
                    error_log("POST data: " . print_r($_POST, true));
                    
                    if (!isset($_POST['receta_id']) || empty($_POST['receta_id'])) {
                        throw new Exception("ID de receta no especificado");
                    }
                    if (!isset($_POST['ingrediente_id']) || empty($_POST['ingrediente_id'])) {
                        throw new Exception("ID de ingrediente no especificado");
                    }
                    if (!isset($_POST['cantidad']) || empty($_POST['cantidad'])) {
                        throw new Exception("Cantidad no especificada");
                    }
                    
                    $receta_id = intval($_POST['receta_id']);
                    $ingrediente_id = intval($_POST['ingrediente_id']);
                    $cantidad = floatval($_POST['cantidad']);
                    $porcentaje_merma = isset($_POST['porcentaje_merma']) ? floatval($_POST['porcentaje_merma']) : 0;
                    
                    // Validaciones adicionales
                    if ($receta_id <= 0) {
                        throw new Exception("ID de receta inválido: $receta_id");
                    }
                    if ($ingrediente_id <= 0) {
                        throw new Exception("ID de ingrediente inválido: $ingrediente_id");
                    }
                    if ($cantidad <= 0) {
                        throw new Exception("La cantidad debe ser mayor que cero: $cantidad");
                    }
                    
                    $result = $recetasManager->addIngrediente($receta_id, $ingrediente_id, $cantidad, $porcentaje_merma);
                    
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Ingrediente agregado correctamente', 'id' => $result]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'No se pudo agregar el ingrediente']);
                    }
                } catch (Exception $e) {
                    error_log("Error en add_ingrediente_receta: " . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'update_ingrediente_receta':
                try {
                    if (!isset($_POST['receta_ingrediente_id']) || !isset($_POST['cantidad'])) {
                        throw new Exception("Datos incompletos para actualizar ingrediente");
                    }
                    
                    $receta_ingrediente_id = intval($_POST['receta_ingrediente_id']);
                    $cantidad = floatval($_POST['cantidad']);
                    $porcentaje_merma = isset($_POST['porcentaje_merma']) ? floatval($_POST['porcentaje_merma']) : 0;
                    
                    if ($receta_ingrediente_id <= 0) {
                        throw new Exception("ID de ingrediente inválido");
                    }
                    if ($cantidad <= 0) {
                        throw new Exception("La cantidad debe ser mayor que cero");
                    }
                    
                    $result = $recetasManager->updateIngrediente($receta_ingrediente_id, $cantidad, $porcentaje_merma);
                    echo json_encode(['success' => $result]);
                } catch (Exception $e) {
                    error_log("Error en update_ingrediente_receta: " . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'remove_ingrediente_receta':
                try {
                    if (!isset($_POST['receta_ingrediente_id'])) {
                        throw new Exception("ID de ingrediente no especificado");
                    }
                    
                    $receta_ingrediente_id = intval($_POST['receta_ingrediente_id']);
                    
                    if ($receta_ingrediente_id <= 0) {
                        throw new Exception("ID de ingrediente inválido");
                    }
                    
                    $result = $recetasManager->removeIngrediente($receta_ingrediente_id);
                    echo json_encode(['success' => $result]);
                } catch (Exception $e) {
                    error_log("Error en remove_ingrediente_receta: " . $e->getMessage());
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Para requests AJAX de datos
if ($action === 'get_data' && isset($_GET['table'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['table']) {
        case 'menu':
            echo json_encode($menuManager->getAll());
            break;
        case 'categorias':
            echo json_encode($categoriasManager->getAll());
            break;
        case 'ingredientes':
            echo json_encode($ingredientesManager->getAll());
            break;
        case 'recetas':
            echo json_encode($recetasManager->getAll());
            break;
        case 'receta_detalle':
            echo json_encode($recetasManager->getRecetaConIngredientes($_GET['id']));
            break;
        case 'reporte_recetas_por_menu':
            echo json_encode($recetasManager->getRecetasPorMenu());
            break;
        case 'reporte_ingredientes_por_categoria':
            echo json_encode($ingredientesManager->getIngredientesPorCategoria());
            break;
        case 'reporte_rentabilidad_recetas':
            echo json_encode($recetasManager->getRentabilidadRecetas());
            break;
        case 'menus': // Nuevo endpoint para obtener menús
            echo json_encode($menuManager->getAll());
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Administración de Recetas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: white !important;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            vertical-align: middle;
        }
        .table td {
            vertical-align: middle;
        }
        .content-area {
            display: none;
        }
        .content-area.active {
            display: block;
        }
        
        /* Estilos específicos para imágenes en tablas */
        .table img.img-thumbnail {
            border: 2px solid #e9ecef;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 30px;
            height: 30px;
            object-fit: cover;
        }
        
        .table img.img-thumbnail:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 10;
            position: relative;
        }
        
        .table .btn-group .btn {
            border-radius: 4px;
            margin: 0 1px;
        }
        
        /* Mejoras para móviles */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 10px;
                max-width: calc(100% - 20px);
            }
            .modal-dialog-centered {
                min-height: calc(100vh - 20px);
            }
            .sidebar {
                min-height: auto;
            }
            
            /* Tablas responsive en móviles */
            .table-responsive {
                font-size: 0.85rem;
            }
            
            .table img.img-thumbnail {
                width: 25px !important;
                height: 25px !important;
            }
            
            .btn-group .btn {
                padding: 0.15rem 0.3rem;
                font-size: 0.7rem;
            }
        }
        
        /* Sistema de notificaciones toast */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .custom-toast {
            min-width: 300px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-left: 4px solid;
            animation: slideInRight 0.3s ease-out;
        }
        
        .custom-toast.success {
            border-left-color: #28a745;
        }
        
        .custom-toast.error {
            border-left-color: #dc3545;
        }
        
        .custom-toast.warning {
            border-left-color: #ffc107;
        }
        
        .custom-toast.info {
            border-left-color: #17a2b8;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        .custom-toast.hiding {
            animation: slideOutRight 0.3s ease-in;
        }
        
        /* Estilos para búsqueda */
        .search-container {
            position: relative;
            max-width: 300px;
        }
        
        .search-input {
            padding-right: 40px;
        }
        
        .search-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .table-row-hidden {
            display: none !important;
        }
        
        /* Mejoras de responsive */
        @media (max-width: 576px) {
            .col-actions {
                min-width: 120px;
            }
            .btn-sm {
                padding: 0.25rem 0.4rem;
                font-size: 0.75rem;
            }
        }
        
        /* Estilos para reportes */
        .report-container {
            margin-bottom: 30px;
        }
        .report-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .report-title {
            font-weight: 600;
            color: #333;
        }
        .report-table th {
            background-color: #f8f9fa;
        }
        .profit-positive {
            color: #28a745;
            font-weight: 600;
        }
        .profit-negative {
            color: #dc3545;
            font-weight: 600;
        }
        .item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        
        /* Nuevos estilos para la sección de ingredientes en recetas */
        #ingredientesReceta .row > div {
            padding: 0.25rem;
        }
        #ingredientesReceta input {
            max-width: 100%;
        }
        
        /* Estilos para formularios compactos */
        .compact-form .row {
            margin-bottom: 0.5rem; /* Menos espacio entre filas */
        }
        .compact-form .mb-3 {
            margin-bottom: 0.5rem !important; /* Menos espacio inferior */
        }
        .compact-form .form-label {
            margin-bottom: 0.25rem; /* Menos espacio debajo de etiquetas */
        }
        .compact-form .form-control, .compact-form .form-select {
            padding: 0.375rem 0.5rem; /* Menos padding vertical */
        }
        
        /* Estilo para descripciones pequeñas en reportes */
        .small-description {
            font-size: 10px;
            color: #6c757d;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .small-description:hover {
            white-space: normal;
            overflow: visible;
            background: white;
            position: absolute;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 0.5rem;
            border-radius: 4px;
        }
        
        /* Estilos específicos para la tabla de recetas */
        #recetasTable th:nth-child(1), 
        #recetasTable td:nth-child(1) {
            width: 10%;
        }
        #recetasTable th:nth-child(2), 
        #recetasTable td:nth-child(2) {
            width: 5%;
        }
        #recetasTable th:nth-child(3), 
        #recetasTable td:nth-child(3) {
            width: 20%;
        }
        #recetasTable th:nth-child(4), 
        #recetasTable td:nth-child(4) {
            width: 15%;
        }
        #recetasTable th:nth-child(5), 
        #recetasTable td:nth-child(5),
        #recetasTable th:nth-child(6), 
        #recetasTable td:nth-child(6),
        #recetasTable th:nth-child(7), 
        #recetasTable td:nth-child(7) {
            width: 10%;
        }
        #recetasTable th:nth-child(8), 
        #recetasTable td:nth-child(8) {
            width: 5%;
        }
        
        /* Estilo para grupos de menú en la tabla */
        .table-group {
            background-color: #f0f2f5 !important;
        }
        .table-group td {
            font-size: 1.1rem;
            padding: 8px 12px !important;
        }
        
        /* Nuevos estilos para búsqueda de ingredientes */
        .search-ingredient-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        .search-ingredient-input {
            padding-right: 35px;
        }
        
        .search-ingredient-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
    </style>
</head>
	
 <!-- Barra de Navegacion -->
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar p-0">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-utensils me-2"></i>
                        Administrador de Menú de Restaurantes
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#" data-section="dashboard">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </a>
                        <a class="nav-link" href="#" data-section="menu">
                            <i class="fas fa-list me-2"></i>Secciones del Menú
                        </a>
                        <a class="nav-link" href="#" data-section="categorias">
                            <i class="fas fa-tags me-2"></i>Categorías de Ingredientes
                        </a>
                        <a class="nav-link" href="#" data-section="ingredientes">
                            <i class="fas fa-seedling me-2"></i>Ingredientes
                        </a>
                        <a class="nav-link" href="#" data-section="recetas">
                            <i class="fas fa-book me-2"></i>Platillos
                        </a>
                        <a class="nav-link" href="#" data-section="reportes">
                            <i class="fas fa-chart-bar me-2"></i>Reportes
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 p-4">
                
                <!-- Dashboard -->
                <div id="dashboard" class="content-area active">
                    <h2 class="mb-4">Dashboard</h2>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-list fa-2x text-primary mb-2"></i>
                                    <h5 class="card-title">Menú</h5>
                                    <p class="card-text display-6" id="menu-count">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-tags fa-2x text-success mb-2"></i>
                                    <h5 class="card-title">Categorías</h5>
                                    <p class="card-text display-6" id="categorias-count">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-seedling fa-2x text-warning mb-2"></i>
                                    <h5 class="card-title">Ingredientes</h5>
                                    <p class="card-text display-6" id="ingredientes-count">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card text-center">
                                <div class="card-body">
                                    <i class="fas fa-book fa-2x text-danger mb-2"></i>
                                    <h5 class="card-title">Recetas</h5>
                                    <p class="card-text display-6" id="recetas-count">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Menú Section -->
                <div id="menu" class="content-area">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                        <h2>Gestión de Menú</h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="search-container">
                                <input type="text" class="form-control search-input" id="searchMenu" placeholder="Buscar menú...">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#menuModal">
                                <i class="fas fa-plus me-2"></i>Agregar Menú
                            </button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="menuTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
											
                                            <th>Imagen y Nombre</th>
                                            <th class="col-actions">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categorías Section -->
                <div id="categorias" class="content-area">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                        <h2>Gestión de Categorías</h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="search-container">
                                <input type="text" class="form-control search-input" id="searchCategorias" placeholder="Buscar categoría...">
                             <i class="fas fa-search search-icon"></i>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoriasModal">
                                <i class="fas fa-plus me-2"></i>Agregar Categoría
                            </button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="categoriasTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
											
                                            <th>Imagen y Nombre</th>
                                            <th>Descripción</th>
                                            <th class="col-actions">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ingredientes Section -->
                <div id="ingredientes" class="content-area">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                        <h2>Gestión de Ingredientes</h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="search-container">
                                <input type="text" class="form-control search-input" id="searchIngredientes" placeholder="Buscar ingrediente...">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ingredientesModal">
                                <i class="fas fa-plus me-2"></i>Agregar Ingrediente
                            </button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="ingredientesTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Imagen y Nombre</th>
                                            <th>Categoría</th>
                                            <th>Presentación</th>
                                            <th>Precio</th>
                                            <th class="col-actions">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recetas Section -->
                <div id="recetas" class="content-area">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
                        <h2>Gestión de Recetas</h2>
                        <div class="d-flex gap-2 flex-wrap">
                            <div class="search-container">
                                <input type="text" class="form-control search-input" id="searchRecetas" placeholder="Buscar receta...">
                                <i class="fas fa-search search-icon"></i>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recetasModal">
                                <i class="fas fa-plus me-2"></i>Agregar Receta
                            </button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="recetasTable">
                                    <thead>
                                        <tr>
                                            <th>Acciones</th>
                                            <th>ID</th>
                                            <th>Receta</th>
                                            <th>Descripción</th>
                                            <th>Precio Venta</th>
                                            <th>Costo Total</th>
                                            <th>Ganancia</th>
                                            <th>Margen (%)</th>
                                            <th>Porciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reportes Section -->
                <div id="reportes" class="content-area">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="fas fa-chart-bar me-2"></i>Reportes</h2>
                    </div>
                    
                    <!-- Reporte de Recetas por Menú -->
                    <div class="report-container">
                        <div class="report-header">
                            <h3 class="report-title"><i class="fas fa-book me-2"></i>Recetas por Menú</h3>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div id="recetasPorMenuReport">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2">Cargando reporte de recetas...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reporte de Ingredientes por Categoría -->
                    <div class="report-container">
                        <div class="report-header">
                            <h3 class="report-title"><i class="fas fa-seedling me-2"></i>Ingredientes por Categoría</h3>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div id="ingredientesPorCategoriaReport">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2">Cargando reporte de ingredientes...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reporte de Rentabilidad de Recetas -->
                    <div class="report-container">
                        <div class="report-header">
                            <h3 class="report-title"><i class="fas fa-chart-line me-2"></i>Rentabilidad de Recetas</h3>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <div id="rentabilidadRecetasReport">
                                    <div class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando...</span>
                                        </div>
                                        <p class="mt-2">Cargando reporte de rentabilidad...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenedor de notificaciones toast -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Modal Menú -->
    <div class="modal fade" id="menuModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestionar Menú</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="menuForm" enctype="multipart/form-data">
                    <div class="modal-body compact-form">
                        <input type="hidden" name="table" value="menu">
                        <input type="hidden" name="id" id="menuId">
                        
                        <!-- Sección de imagen -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="menuImagen" class="form-label">Imagen del Menú</label>
                                <input type="file" class="form-control" name="imagen" id="menuImagen" 
                                       accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this, 'menuImagePreview')">
                                <small class="form-text text-muted">JPG, PNG. Máximo 5MB</small>
                            </div>
                            <div class="col-md-8">
                                <div id="menuImagePreview" class="mt-2">
                                    <img id="menuImageDisplay" src="" alt="Vista previa" 
                                         style="max-width: 200px; max-height: 150px; display: none;" class="img-thumbnail">
                                    <div id="menuNoImage" class="text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p>Sin imagen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="menuNombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="menuNombre" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Categorías -->
    <div class="modal fade" id="categoriasModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestionar Categoría</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="categoriasForm" enctype="multipart/form-data">
                    <div class="modal-body compact-form">
                        <input type="hidden" name="table" value="categorias">
                        <input type="hidden" name="id" id="categoriaId">
                        
                        <!-- Sección de imagen -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="categoriaImagen" class="form-label">Imagen de la Categoría</label>
                                <input type="file" class="form-control" name="imagen" id="categoriaImagen" 
                                       accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this, 'categoriaImagePreview')">
                                <small class="form-text text-muted">JPG, PNG. Máximo 5MB</small>
                            </div>
                            <div class="col-md-8">
                                <div id="categoriaImagePreview" class="mt-2">
                                    <img id="categoriaImageDisplay" src="" alt="Vista previa" 
                                         style="max-width: 200px; max-height: 150px; display: none;" class="img-thumbnail">
                                    <div id="categoriaNoImage" class="text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p>Sin imagen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="categoriaNombre" class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="categoriaNombre" required>
                        </div>
                        <div class="mb-3">
                            <label for="categoriaDescripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="categoriaDescripcion" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ingredientes -->
    <div class="modal fade" id="ingredientesModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestionar Ingrediente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="ingredientesForm" enctype="multipart/form-data">
                    <div class="modal-body compact-form">
                        <input type="hidden" name="table" value="ingredientes">
                        <input type="hidden" name="id" id="ingredienteId">
                        
                        <!-- Sección de imagen -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="ingredienteImagen" class="form-label">Imagen del Ingrediente</label>
                                <input type="file" class="form-control" name="imagen" id="ingredienteImagen" 
                                       accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this, 'ingredienteImagePreview')">
                                <small class="form-text text-muted">JPG, PNG. Máximo 5MB</small>
                            </div>
                            <div class="col-md-8">
                                <div id="ingredienteImagePreview" class="mt-2">
                                    <img id="ingredienteImageDisplay" src="" alt="Vista previa" 
                                         style="max-width: 200px; max-height: 150px; display: none;" class="img-thumbnail">
                                    <div id="ingredienteNoImage" class="text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p>Sin imagen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ingredienteNombre" class="form-label">Nombre</label>
                                <input type="text" class="form-control" name="nombre" id="ingredienteNombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ingredienteCategoria" class="form-label">Categoría</label>
                                <select class="form-control" name="categoria_id" id="ingredienteCategoria" required>
                                    <option value="">Seleccionar categoría</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ingredientePresentacion" class="form-label">Presentación</label>
                                <select class="form-control" name="presentacion" id="ingredientePresentacion" required>
                                    <option value="">Seleccionar presentación</option>
                                    <option value="Libra">Libra</option>
                                    <option value="Unidad">Unidad</option>
                                    <option value="Gramos">Gramos</option>
                                    <option value="Onza">Onza</option>
                                    <option value="Kilogramo">Kilogramo</option>
                                    <option value="Litro">Litro</option>
                                    <option value="Mililitro">Mililitro</option>
                                    <option value="Cucharada">Cucharada</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="ingredientePrecio" class="form-label">Precio de Compra</label>
                                <input type="number" step="0.01" class="form-control" name="precio_compra" id="ingredientePrecio" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="ingredientePeso" class="form-label">Peso Unitario (gramos)</label>
                                <input type="number" step="0.01" class="form-control" name="peso_unitario" id="ingredientePeso">
                                <small class="form-text text-muted">Solo para presentación por unidad</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="ingredienteDescripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="ingredienteDescripcion" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Recetas -->
    <div class="modal fade" id="recetasModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Gestionar Receta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="recetasForm" enctype="multipart/form-data">
                    <div class="modal-body compact-form">
                        <input type="hidden" name="table" value="recetas">
                        <input type="hidden" name="id" id="recetaId">
                        
                        <!-- Sección de imagen -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="recetaImagen" class="form-label">Imagen de la Receta</label>
                                <input type="file" class="form-control" name="imagen" id="recetaImagen" 
                                       accept="image/jpeg,image/png,image/jpg" onchange="previewImage(this, 'recetaImagePreview')">
                                <small class="form-text text-muted">JPG, PNG. Máximo 5MB</small>
                            </div>
                            <div class="col-md-8">
                                <div id="recetaImagePreview" class="mt-2">
                                    <img id="recetaImageDisplay" src="" alt="Vista previa" 
                                         style="max-width: 300px; max-height: 200px; display: none;" class="img-thumbnail">
                                    <div id="recetaNoImage" class="text-muted">
                                        <i class="fas fa-image fa-3x mb-2"></i>
                                        <p>Sin imagen</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Nuevo campo: Selección de menú -->
                        <div class="mb-3">
                            <label for="recetaMenu" class="form-label">Menú</label>
                            <select class="form-control" name="menu_id" id="recetaMenu" required>
                                <option value="">Seleccionar menú</option>
                                <!-- Opciones se llenarán con JavaScript -->
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="recetaNombre" class="form-label">Nombre de la Receta</label>
                                <input type="text" class="form-control" name="nombre" id="recetaNombre" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="recetaPrecio" class="form-label">Precio de Venta</label>
                                <input type="number" step="0.01" class="form-control" name="precio_venta" id="recetaPrecio" required>
                            </div>
                        </div>
                        
                        <!-- Nuevo campo: Descripción -->
                        <div class="mb-3">
                            <label for="recetaDescripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" id="recetaDescripcion" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="recetaTiempo" class="form-label">Tiempo de Preparación (minutos)</label>
                                <input type="number" class="form-control" name="tiempo_preparacion" id="recetaTiempo">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="recetaPorciones" class="form-label">Porciones</label>
                                <input type="number" class="form-control" name="porciones" id="recetaPorciones" value="1">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="recetaInstrucciones" class="form-label">Instrucciones</label>
                            <textarea class="form-control" name="instrucciones" id="recetaInstrucciones" rows="3"></textarea>
                        </div>
                        
                        <!-- Sección de ingredientes (solo para edición) -->
                        <div id="ingredientesSection" style="display: none;">
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">
                                    <i class="fas fa-seedling me-2"></i>Ingredientes de la Receta
                                </h6>
                                <span class="badge bg-info" id="ingredientesCount">0 ingredientes</span>
                            </div>
                            
                            <div class="card bg-light p-3 mb-3">
                                <!-- Campo de búsqueda agregado -->
                                <div class="search-ingredient-container mb-2">
                                    <input type="text" class="form-control form-control-sm search-ingredient-input" 
                                           id="searchIngredient" placeholder="Buscar ingrediente..."
                                           onkeyup="filterIngredients()">
                                    <i class="fas fa-search search-ingredient-icon"></i>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label small">Ingrediente</label>
                                        <select class="form-control form-control-sm" id="selectIngrediente" 
                                                size="8" style="height: 160px;"
                                                onchange="showIngredientDetails(this.value)">
                                            <option value="">Seleccionar ingrediente</option>
                                        </select>
                                        <div id="selectedIngredientImage" class="mt-2" style="display: none;">
                                            <img id="selectedIngredientImg" src="" alt="" style="max-width: 80px; max-height: 60px;" class="img-thumbnail">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Cantidad</label>
                                        <input type="number" step="0.001" class="form-control form-control-sm" 
                                               id="cantidadIngrediente" placeholder="Ej: 2.5">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">% Merma</label>
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               id="porcentajeMerma" placeholder="Ej: 5">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Precio Compra</label>
                                        <input type="text" class="form-control form-control-sm" 
                                               id="precioCompra" placeholder="Precio" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="button" class="btn btn-success btn-sm w-100 d-block" onclick="agregarIngredienteReceta()">
                                            <i class="fas fa-plus"></i> Agregar
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="ingredientesReceta" class="border rounded p-3 bg-white"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let currentSection = 'dashboard';
        let editingItem = null;
        let categorias = [];
        let ingredientes = [];
        let menus = []; // Nuevo: lista de menús
        let currentRecetaId = null;

        // Sistema de notificaciones toast
        function showToast(message, type = 'success', duration = 2000) {
            const toastContainer = document.getElementById('toastContainer');
            const toastId = 'toast_' + Date.now();
            
            const iconMap = {
                success: 'fas fa-check-circle',
                error: 'fas fa-exclamation-circle',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle'
            };
            
            const colorMap = {
                success: '#28a745',
                error: '#dc3545',
                warning: '#ffc107',
                info: '#17a2b8'
            };

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `custom-toast ${type} p-3 mb-2`;
            toast.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="${iconMap[type]} me-2" style="color: ${colorMap[type]}"></i>
                    <div class="flex-grow-1">${message}</div>
                    <button type="button" class="btn-close btn-close-sm ms-2" onclick="closeToast('${toastId}')"></button>
                </div>
            `;

            toastContainer.appendChild(toast);

            // Auto cerrar después del tiempo especificado
            setTimeout(() => {
                closeToast(toastId);
            }, duration);
        }

        function closeToast(toastId) {
            const toast = document.getElementById(toastId);
            if (toast) {
                toast.classList.add('hiding');
                setTimeout(() => {
                    toast.remove();
                }, 300);
            }
        }

        // En la función setupSearchFunctionality (busca esta función en tu código)
        function setupSearchFunctionality() {
            // Configurar búsqueda para cada tabla
            const searchConfigs = [
                { inputId: 'searchMenu', tableId: 'menuTable', columns: [1] },
                { inputId: 'searchCategorias', tableId: 'categoriasTable', columns: [1, 2] },
                { inputId: 'searchIngredientes', tableId: 'ingredientesTable', columns: [1, 2] },
                { inputId: 'searchRecetas', tableId: 'recetasTable', columns: [1, 2] } // Modificado para buscar por ID (1) y Nombre (2)
            ];

            searchConfigs.forEach(config => {
                const searchInput = document.getElementById(config.inputId);
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        filterTable(config.tableId, this.value, config.columns);
                    });
                }
            });
        }

        // La función filterTable debería funcionar correctamente como está
        function filterTable(tableId, searchText, searchColumns) {
            const table = document.getElementById(tableId);
            const tbody = table.querySelector('tbody');
            const rows = tbody.querySelectorAll('tr');

            searchText = searchText.toLowerCase();

            rows.forEach(row => {
                let shouldShow = false;
                
                if (searchText === '') {
                    shouldShow = true;
                } else {
                    searchColumns.forEach(columnIndex => {
                        const cell = row.cells[columnIndex];
                        if (cell && cell.textContent.toLowerCase().includes(searchText)) {
                            shouldShow = true;
                        }
                    });
                }

                if (shouldShow) {
                    row.classList.remove('table-row-hidden');
                } else {
                    row.classList.add('table-row-hidden');
                }
            });
        }
        
        // Función para filtrar ingredientes en el select
        function filterIngredients() {
            const searchText = document.getElementById('searchIngredient').value.toLowerCase();
            const select = document.getElementById('selectIngrediente');
            const options = select.getElementsByTagName('option');
            
            for (let i = 0; i < options.length; i++) {
                const text = options[i].textContent.toLowerCase();
                if (text.includes(searchText)) {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        }
        
        // Modificar la función loadIngredientes para mejorar el formato
        function loadIngredientes() {
            fetch('?action=get_data&table=ingredientes')
                .then(response => response.json())
                .then(data => {
                    ingredientes = data;
                    const select = document.getElementById('selectIngrediente');
                    select.innerHTML = '<option value="">Seleccionar ingrediente</option>';
                    data.forEach(ing => {
                        const imageBadge = ing.imagen ? ' 📷' : '';
                        select.innerHTML += `
                            <option value="${ing.id}" 
                                    data-imagen="${ing.imagen || ''}"
                                    data-presentacion="${ing.presentacion}"
                                    data-precio="${ing.precio_compra}">
                                ${ing.nombre} (${ing.presentacion})${imageBadge}
                            </option>
                        `;
                    });
                });
        }
        
        // Función para mostrar detalles del ingrediente seleccionado
        function showIngredientDetails(ingredienteId) {
            const imageContainer = document.getElementById('selectedIngredientImage');
            const imageElement = document.getElementById('selectedIngredientImg');
            const precioCompraInput = document.getElementById('precioCompra');
            
            if (ingredienteId && ingredientes.length > 0) {
                const ingrediente = ingredientes.find(ing => ing.id == ingredienteId);
                if (ingrediente) {
                    // Mostrar imagen si existe
                    if (ingrediente.imagen) {
                        imageElement.src = `uploads/thumbs/ingredientes/${ingrediente.imagen}`;
                        imageElement.alt = ingrediente.nombre;
                        imageContainer.style.display = 'block';
                    } else {
                        imageContainer.style.display = 'none';
                    }
                    
                    // Mostrar precio de compra
                    precioCompraInput.value = ingrediente.precio_compra ? 
                        parseFloat(ingrediente.precio_compra).toFixed(2) : '0.00';
                } else {
                    imageContainer.style.display = 'none';
                    precioCompraInput.value = '';
                }
            } else {
                imageContainer.style.display = 'none';
                precioCompraInput.value = '';
            }
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            loadCategorias();
            loadIngredientes();
            loadMenus(); // Cargar menús para el combo de recetas
            setupSearchFunctionality();
            
            // Event listeners para navegación
            document.querySelectorAll('[data-section]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    showSection(this.dataset.section);
                });
            });

            // Event listeners para formularios
            setupFormHandlers();
        });

        // Navegación entre secciones
        function showSection(section) {
            // Ocultar todas las secciones
            document.querySelectorAll('.content-area').forEach(area => {
                area.classList.remove('active');
            });

            // Mostrar la sección seleccionada
            document.getElementById(section).classList.add('active');

            // Actualizar navegación
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            document.querySelector(`[data-section="${section}"]`).classList.add('active');

            // Cargar datos según la sección
            currentSection = section;
            switch(section) {
                case 'dashboard':
                    loadDashboardData();
                    break;
                case 'menu':
                    loadMenuData();
                    break;
                case 'categorias':
                    loadCategoriasData();
                    break;
                case 'ingredientes':
                    loadIngredientesData();
                    break;
                case 'recetas':
                    loadRecetasData();
                    break;
                case 'reportes':
                    loadRecetasPorMenuReport();
                    loadIngredientesPorCategoriaReport();
                    loadRentabilidadRecetasReport();
                    break;
            }
        }

        // Cargar datos del dashboard
        function loadDashboardData() {
            fetch('?action=get_data&table=menu')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('menu-count').textContent = data.length;
                });

            fetch('?action=get_data&table=categorias')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('categorias-count').textContent = data.length;
                });

            fetch('?action=get_data&table=ingredientes')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('ingredientes-count').textContent = data.length;
                });

            fetch('?action=get_data&table=recetas')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('recetas-count').textContent = data.length;
                });
        }

        // Cargar datos de menú
        function loadMenuData() {
            fetch('?action=get_data&table=menu')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#menuTable tbody');
                    tbody.innerHTML = '';
                    data.forEach(item => {
                        const imageCell = item.imagen ? 
                            `<img src="uploads/thumbs/menu/${item.imagen}" alt="${item.nombre}" class="img-thumbnail me-2">` : 
                            '<i class="fas fa-image text-muted me-2"></i>';
                            
                        tbody.innerHTML += `
                            <tr>
                                <td>${item.id}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        ${imageCell}
                                        <span>${item.nombre}</span>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editItem('menu', ${item.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteItem('menu', ${item.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                });
        }

        // Cargar datos de categorías
        function loadCategoriasData() {
            fetch('?action=get_data&table=categorias')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#categoriasTable tbody');
                    tbody.innerHTML = '';
                    data.forEach(item => {
                        const imageCell = item.imagen ? 
                            `<img src="uploads/thumbs/categorias/${item.imagen}" alt="${item.nombre}" class="img-thumbnail me-2">` : 
                            '<i class="fas fa-image text-muted me-2"></i>';
                            
                        tbody.innerHTML += `
                            <tr>
                                <td>${item.id}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        ${imageCell}
                                        <span>${item.nombre}</span>
                                    </div>
                                </td>
                                <td>${item.descripcion || ''}</td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editItem('categorias', ${item.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteItem('categorias', ${item.id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                });
        }

        // Cargar datos de ingredientes
        function loadIngredientesData() {
            fetch('?action=get_data&table=ingredientes')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#ingredientesTable tbody');
                    tbody.innerHTML = '';
                    data.forEach(item => {
                        const imageCell = item.imagen ? 
                            `<img src="uploads/thumbs/ingredientes/${item.imagen}" alt="${item.nombre}" class="img-thumbnail me-2">` : 
                            '<i class="fas fa-image text-muted me-2"></i>';
                            
                        tbody.innerHTML += `
                            <tr>
                                <td>${item.id}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        ${imageCell}
                                        <span>${item.nombre}</span>
                                    </div>
                                </td>
                                <td>${item.categoria_nombre || ''}</td>
                                <td>${item.presentacion}</td>
                                <td>${parseFloat(item.precio_compra).toFixed(2)}</td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editItem('ingredientes', ${item.id})" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteItem('ingredientes', ${item.id})" title="Eliminar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                });
        }

        // Cargar datos de recetas
        function loadRecetasData() {
            fetch('?action=get_data&table=recetas')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.querySelector('#recetasTable tbody');
                    tbody.innerHTML = '';
                    
                    // Agrupar recetas por menú
                    const recetasPorMenu = {};
                    data.forEach(item => {
                        const menuNombre = menus.find(m => m.id == item.menu_id)?.nombre || 'Sin menú';
                        if (!recetasPorMenu[menuNombre]) {
                            recetasPorMenu[menuNombre] = [];
                        }
                        recetasPorMenu[menuNombre].push(item);
                    });
                    
                    // Mostrar recetas agrupadas
                    Object.keys(recetasPorMenu).forEach(menuNombre => {
                        // Agregar fila de grupo de menú
                        tbody.innerHTML += `
                            <tr class="table-group">
                                <td colspan="9" class="bg-light fw-bold">
                                    <i class="fas fa-list me-2"></i>${menuNombre}
                                </td>
                            </tr>
                        `;
                        
                        // Agregar recetas de este menú
                        recetasPorMenu[menuNombre].forEach(item => {
                            const costoTotal = parseFloat(item.costo_total || 0);
                            const precioVenta = parseFloat(item.precio_venta || 0);
                            const ganancia = precioVenta - costoTotal;
                            const margen = precioVenta > 0 ? (ganancia / precioVenta * 100) : 0;
                            
                            const gananciaClass = ganancia >= 0 ? 'profit-positive' : 'profit-negative';
                            const margenClass = margen >= 0 ? 'profit-positive' : 'profit-negative';
                            
                            const imageCell = item.imagen ? 
                                `<img src="uploads/thumbs/recetas/${item.imagen}" alt="${item.nombre}" class="img-thumbnail me-2">` : 
                                '<i class="fas fa-image text-muted me-2"></i>';
                            
                            tbody.innerHTML += `
                                <tr>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editItem('recetas', ${item.id})" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteItem('recetas', ${item.id})" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                    <td>${item.id}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            ${imageCell}
                                            <span>${item.nombre}</span>
                                        </div>
                                    </td>
                                    <td class="small-description" title="${item.descripcion || 'Sin descripción'}">
                                        ${item.descripcion || 'Sin descripción'}
                                    </td>
                                    <td>${precioVenta.toFixed(2)}</td>
                                    <td>${costoTotal.toFixed(2)}</td>
                                    <td class="${gananciaClass}">${ganancia.toFixed(2)}</td>
                                    <td class="${margenClass}">${margen.toFixed(2)}%</td>
                                    <td>${item.porciones}</td>
                                </tr>
                            `;
                        });
                    });
                });
        }

        // Cargar categorías para selects
        function loadCategorias() {
            fetch('?action=get_data&table=categorias')
                .then(response => response.json())
                .then(data => {
                    categorias = data;
                    const select = document.getElementById('ingredienteCategoria');
                    select.innerHTML = '<option value="">Seleccionar categoría</option>';
                    data.forEach(cat => {
                        select.innerHTML += `<option value="${cat.id}">${cat.nombre}</option>`;
                    });
                });
        }

        // Cargar menús para selects
        function loadMenus() {
            fetch('?action=get_data&table=menu')
                .then(response => response.json())
                .then(data => {
                    menus = data;
                    const select = document.getElementById('recetaMenu');
                    select.innerHTML = '<option value="">Seleccionar menú</option>';
                    data.forEach(menu => {
                        select.innerHTML += `<option value="${menu.id}">${menu.nombre}</option>`;
                    });
                });
        }

        // Configurar manejadores de formularios
        function setupFormHandlers() {
            // Form handlers para cada tabla
            ['menu', 'categorias', 'ingredientes', 'recetas'].forEach(table => {
                const form = document.getElementById(`${table}Form`);
                if (form) {
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        submitForm(table, form);
                    });
                }
            });
        }

        // Enviar formulario
        function submitForm(table, form) {
            const formData = new FormData(form); // Usar FormData para manejar archivos
            const action = editingItem ? 'update' : 'create';
            
            formData.append('action', action);
            if (editingItem) {
                formData.append('id', editingItem);
            }

            // Mostrar indicador de carga en el botón
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Guardando...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                body: formData // No establecer Content-Type para FormData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Para recetas nuevas, obtener el ID y permitir agregar ingredientes
                    if (table === 'recetas' && !editingItem && data.id) {
                        currentRecetaId = data.id;
                        editingItem = data.id;
                        document.getElementById('recetaId').value = data.id;
                        document.getElementById('ingredientesSection').style.display = 'block';
                        showToast('Receta creada con éxito. Ahora puede agregar ingredientes.', 'success', 3000);
                        return; // No cerrar el modal para permitir agregar ingredientes
                    }
                    
                    bootstrap.Modal.getInstance(document.getElementById(`${table}Modal`)).hide();
                    form.reset();
                    
                    // Limpiar previsualización de imágenes
                    clearImagePreviews();
                    
                    editingItem = null;
                    currentRecetaId = null;
                    
                    // Recargar datos
                    switch(table) {
                        case 'menu':
                            loadMenuData();
                            loadMenus(); // Actualizar lista de menús
                            break;
                        case 'categorias':
                            loadCategoriasData();
                            loadCategorias(); // Actualizar selects
                            break;
                        case 'ingredientes':
                            loadIngredientesData();
                            loadIngredientes(); // Actualizar selects
                            break;
                        case 'recetas':
                            loadRecetasData();
                            break;
                    }
                    
                    loadDashboardData(); // Actualizar contadores
                    
                    const actionText = editingItem ? 'actualizada' : 'creada';
                    showToast(`${table.charAt(0).toUpperCase() + table.slice(1)} ${actionText} con éxito`, 'success');
                } else {
                    showToast('Error: ' + (data.error || 'No se pudo completar la operación'), 'error', 4000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexión', 'error', 4000);
            })
            .finally(() => {
                // Restaurar botón
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }
        
        // Funciones para manejo de imágenes
        function previewImage(input, previewContainerId) {
            const previewContainer = document.getElementById(previewContainerId);
            const imageDisplay = previewContainer.querySelector('img');
            const noImageDiv = previewContainer.querySelector('[id$="NoImage"]');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imageDisplay.src = e.target.result;
                    imageDisplay.style.display = 'block';
                    if (noImageDiv) {
                        noImageDiv.style.display = 'none';
                    }
                };
                
                reader.readAsDataURL(input.files[0]);
            } else {
                clearImagePreview(previewContainerId);
            }
        }
        
        function clearImagePreview(previewContainerId) {
            const previewContainer = document.getElementById(previewContainerId);
            const imageDisplay = previewContainer.querySelector('img');
            const noImageDiv = previewContainer.querySelector('[id$="NoImage"]');
            
            imageDisplay.style.display = 'none';
            imageDisplay.src = '';
            if (noImageDiv) {
                noImageDiv.style.display = 'block';
            }
        }
        
        function clearImagePreviews() {
            clearImagePreview('menuImagePreview');
            clearImagePreview('categoriaImagePreview');
            clearImagePreview('ingredienteImagePreview');
            clearImagePreview('recetaImagePreview');
        }
        
        function loadImageForEdit(imageName, type, previewContainerId) {
            if (imageName) {
                const previewContainer = document.getElementById(previewContainerId);
                const imageDisplay = previewContainer.querySelector('img');
                const noImageDiv = previewContainer.querySelector('[id$="NoImage"]');
                
                imageDisplay.src = `uploads/${type}/${imageName}`;
                imageDisplay.style.display = 'block';
                if (noImageDiv) {
                    noImageDiv.style.display = 'none';
                }
            }
        }

        // Editar elemento
        function editItem(table, id) {
            editingItem = id;
            
            // Obtener datos del elemento
            fetch(`?action=get_data&table=${table}`)
                .then(response => response.json())
                .then(data => {
                    const item = data.find(i => i.id == id);
                    if (!item) return;

                    // Llenar formulario según la tabla
                    switch(table) {
                        case 'menu':
                            document.getElementById('menuId').value = item.id;
                            document.getElementById('menuNombre').value = item.nombre;
                            
                            // Cargar imagen si existe
                            if (item.imagen) {
                                loadImageForEdit(item.imagen, 'menu', 'menuImagePreview');
                            } else {
                                clearImagePreview('menuImagePreview');
                            }
                            break;
                        case 'categorias':
                            document.getElementById('categoriaId').value = item.id;
                            document.getElementById('categoriaNombre').value = item.nombre;
                            document.getElementById('categoriaDescripcion').value = item.descripcion || '';
                            
                            // Cargar imagen si existe
                            if (item.imagen) {
                                loadImageForEdit(item.imagen, 'categorias', 'categoriaImagePreview');
                            } else {
                                clearImagePreview('categoriaImagePreview');
                            }
                            break;
                        case 'ingredientes':
                            document.getElementById('ingredienteId').value = item.id;
                            document.getElementById('ingredienteNombre').value = item.nombre;
                            document.getElementById('ingredienteCategoria').value = item.categoria_id;
                            document.getElementById('ingredientePresentacion').value = item.presentacion;
                            document.getElementById('ingredientePrecio').value = item.precio_compra;
                            document.getElementById('ingredientePeso').value = item.peso_unitario || '';
                            document.getElementById('ingredienteDescripcion').value = item.descripcion || '';
                            
                            // Cargar imagen si existe
                            if (item.imagen) {
                                loadImageForEdit(item.imagen, 'ingredientes', 'ingredienteImagePreview');
                            } else {
                                clearImagePreview('ingredienteImagePreview');
                            }
                            break;
                        case 'recetas':
                            document.getElementById('recetaId').value = item.id;
                            document.getElementById('recetaNombre').value = item.nombre;
                            document.getElementById('recetaPrecio').value = item.precio_venta;
                            document.getElementById('recetaTiempo').value = item.tiempo_preparacion || '';
                            document.getElementById('recetaPorciones').value = item.porciones;
                            document.getElementById('recetaInstrucciones').value = item.instrucciones || '';
                            document.getElementById('recetaMenu').value = item.menu_id || '';
                            document.getElementById('recetaDescripcion').value = item.descripcion || ''; // Nuevo campo
                            
                            // Cargar imagen si existe
                            if (item.imagen) {
                                loadImageForEdit(item.imagen, 'recetas', 'recetaImagePreview');
                            } else {
                                clearImagePreview('recetaImagePreview');
                            }
                            
                            // Mostrar sección de ingredientes para edición
                            document.getElementById('ingredientesSection').style.display = 'block';
                            currentRecetaId = item.id;
                            
                            loadRecetaIngredientes(item.id);
                            break;
                    }

                    // Mostrar modal
                    new bootstrap.Modal(document.getElementById(`${table}Modal`)).show();
                });
        }

        // Eliminar elemento
        function deleteItem(table, id) {
            if (!confirm('¿Está seguro de que desea eliminar este elemento?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('table', table);
            formData.append('id', id);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recargar datos
                    switch(table) {
                        case 'menu':
                            loadMenuData();
                            loadMenus(); // Actualizar lista de menús
                            break;
                        case 'categorias':
                            loadCategoriasData();
                            loadCategorias();
                            break;
                        case 'ingredientes':
                            loadIngredientesData();
                            loadIngredientes();
                            break;
                        case 'recetas':
                            loadRecetasData();
                            break;
                    }
                    
                    loadDashboardData();
                    showToast('Elemento eliminado con éxito', 'success');
                } else {
                    showToast('No se puede eliminar este elemento. Puede estar siendo usado por otros registros.', 'warning', 4000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexión', 'error', 4000);
            });
        }

        // Limpiar formularios al abrir modales para crear
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('show.bs.modal', function() {
                if (!editingItem) {
                    const form = this.querySelector('form');
                    if (form) {
                        form.reset();
                        
                        // Limpiar previsualizaciones de imagen
                        clearImagePreviews();
                        
                        // Ocultar sección de ingredientes para nuevas recetas
                        const ingredientesSection = document.getElementById('ingredientesSection');
                        if (ingredientesSection) {
                            ingredientesSection.style.display = 'none';
                        }
                        
                        // Ocultar imagen de ingrediente seleccionado
                        const selectedIngredientImage = document.getElementById('selectedIngredientImage');
                        if (selectedIngredientImage) {
                            selectedIngredientImage.style.display = 'none';
                        }
                    }
                }
            });
            
            modal.addEventListener('hidden.bs.modal', function() {
                editingItem = null;
                currentRecetaId = null;
                
                // Limpiar previsualizaciones al cerrar
                clearImagePreviews();
                
                // Ocultar imagen de ingrediente seleccionado
                const selectedIngredientImage = document.getElementById('selectedIngredientImage');
                if (selectedIngredientImage) {
                    selectedIngredientImage.style.display = 'none';
                }
                
                // Limpiar búsquedas al cerrar modales
                const searchInputs = document.querySelectorAll('.search-input');
                searchInputs.forEach(input => {
                    if (input.value) {
                        input.value = '';
                        const tableId = input.id.replace('search', '').toLowerCase() + 'Table';
                        // Determinar columnas según la tabla (todas ahora tienen imagen)
                        let columns = [1]; // default para menu y recetas (solo nombre)
                        if (tableId === 'categoriasTable' || tableId === 'ingredientesTable') {
                            columns = [1, 2]; // nombre y descripción/categoría
                        }
                        filterTable(tableId, '', columns);
                    }
                });
            });
        });

        // Funciones para manejo de ingredientes en recetas
        function loadRecetaIngredientes(recetaId) {
            fetch(`?action=get_data&table=receta_detalle&id=${recetaId}`)
                .then(response => response.json())
                .then(recipe => {
                    const container = document.getElementById('ingredientesReceta');
                    container.innerHTML = '';
                    
                    if (recipe && recipe.ingredientes && recipe.ingredientes.length > 0) {
                        // Mostrar encabezados
                        container.innerHTML = `
                            <div class="row mb-2 fw-bold">
                                <div class="col-md-3">Ingrediente</div>
                                <div class="col-md-2">Cantidad</div>
                                <div class="col-md-2">Precio Compra</div>
                                <div class="col-md-1">% Merma</div>
                                <div class="col-md-2">Costo</div>
                                <div class="col-md-2">Acciones</div>
                            </div>
                        `;
                        
                        let costoTotal = 0;
                        recipe.ingredientes.forEach(ing => {
                            // Usar el precio actual del ingrediente para calcular el costo
                            const costo = parseFloat(ing.costo_calculado || 0);
                            costoTotal += costo;
                            
                            container.innerHTML += `
                                <div class="row mb-2 align-items-center" data-ingrediente-id="${ing.id}">
                                    <div class="col-md-3">
                                        <small class="text-muted">${ing.ingrediente_nombre}</small>
                                    </div>
                                    <div class="col-md-2">
                                        <input type="number" step="0.001" class="form-control form-control-sm" 
                                               value="${ing.cantidad}" 
                                               onchange="updateIngredienteReceta(${ing.id}, this.value, this.parentNode.nextElementSibling.nextElementSibling.querySelector('input').value)">
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-muted">${parseFloat(ing.precio_compra).toFixed(2)}</small>
                                    </div>
                                    <div class="col-md-1">
                                        <input type="number" step="0.01" class="form-control form-control-sm" 
                                               value="${ing.porcentaje_merma}" 
                                               onchange="updateIngredienteReceta(${ing.id}, this.parentNode.previousElementSibling.previousElementSibling.querySelector('input').value, this.value)">
                                    </div>
                                    <div class="col-md-2">
                                        <small class="text-success fw-bold">${costo.toFixed(2)}</small>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-danger btn-sm w-100 d-block" onclick="removeIngredienteReceta(${ing.id})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        
                        // Mostrar costo total, precio venta y ganancia
                        const salePrice = parseFloat(recipe.precio_venta || 0);
                        const profit = salePrice - costoTotal;
                        const profitClass = profit >= 0 ? 'text-success' : 'text-danger';
                        
                        container.innerHTML += `
                            <hr>
                            <div class="row">
                                <div class="col-md-8">
                                    <strong>Costo Total de la Receta:</strong>
                                </div>
                                <div class="col-md-4">
                                    <strong class="text-primary">${costoTotal.toFixed(2)}</strong>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <strong>Precio de Venta:</strong>
                                </div>
                                <div class="col-md-4">
                                    <strong class="text-primary">${salePrice.toFixed(2)}</strong>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8">
                                    <strong>Ganancia:</strong>
                                </div>
                                <div class="col-md-4">
                                    <strong class="${profitClass}">${profit.toFixed(2)}</strong>
                                </div>
                            </div>
                        `;
                        
                        // Botón para finalizar si es una receta nueva
                        if (!editingItem || (editingItem && !document.getElementById('recetaNombre').value)) {
                            container.innerHTML += `
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <button type="button" class="btn btn-success" onclick="finalizarReceta()">
                                            <i class="fas fa-check"></i> Finalizar Receta
                                        </button>
                                    </div>
                                </div>
                            `;
                        }
                    } else {
                        container.innerHTML = '<p class="text-muted">No hay ingredientes agregados a esta receta.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('ingredientesReceta').innerHTML = '<p class="text-danger">Error al cargar ingredientes</p>';
                });
        }
        
        function finalizarReceta() {
            bootstrap.Modal.getInstance(document.getElementById('recetasModal')).hide();
            editingItem = null;
            currentRecetaId = null;
            loadRecetasData();
            loadDashboardData();
            showToast('Receta finalizada con éxito', 'success');
        }
        
        function agregarIngredienteReceta() {
            const ingredienteId = document.getElementById('selectIngrediente').value;
            const cantidad = document.getElementById('cantidadIngrediente').value;
            const porcentajeMerma = document.getElementById('porcentajeMerma').value || 0;
            
            // Debug
            console.log('Agregando ingrediente:', {
                ingredienteId: ingredienteId,
                cantidad: cantidad,
                porcentajeMerma: porcentajeMerma,
                currentRecetaId: currentRecetaId
            });
            
            // Validaciones
            if (!ingredienteId || ingredienteId === '') {
                alert('Por favor, seleccione un ingrediente');
                document.getElementById('selectIngrediente').focus();
                return;
            }
            
            if (!cantidad || cantidad === '' || parseFloat(cantidad) <= 0) {
                alert('Por favor, especifique una cantidad válida mayor que cero');
                document.getElementById('cantidadIngrediente').focus();
                return;
            }
            
            if (!currentRecetaId || currentRecetaId === '' || currentRecetaId <= 0) {
                alert('Error: No hay una receta seleccionada. Por favor, guarde la receta primero.');
                return;
            }

            // Mostrar indicador de carga
            const btnAgregar = document.querySelector('button[onclick="agregarIngredienteReceta()"]');
            const originalText = btnAgregar.innerHTML;
            btnAgregar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            btnAgregar.disabled = true;

            const formData = new FormData();
            formData.append('action', 'add_ingrediente_receta');
            formData.append('receta_id', currentRecetaId);
            formData.append('ingrediente_id', ingredienteId);
            formData.append('cantidad', cantidad);
            formData.append('porcentaje_merma', porcentajeMerma);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Limpiar campos
                    document.getElementById('selectIngrediente').value = '';
                    document.getElementById('cantidadIngrediente').value = '';
                    document.getElementById('porcentajeMerma').value = '';
                    document.getElementById('precioCompra').value = '';
                    
                    // Recargar ingredientes de la receta
                    loadRecetaIngredientes(currentRecetaId);
                    
                    showToast('Ingrediente agregado con éxito', 'success');
                } else {
                    showToast('Error al agregar ingrediente: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error en fetch:', error);
                showToast('Error de conexión al agregar ingrediente', 'error');
            })
            .finally(() => {
                // Restaurar botón
                btnAgregar.innerHTML = originalText;
                btnAgregar.disabled = false;
            });
        }

        function removeIngredienteReceta(recetaIngredienteId) {
            if (!confirm('¿Eliminar este ingrediente de la receta?')) return;

            const formData = new FormData();
            formData.append('action', 'remove_ingrediente_receta');
            formData.append('receta_ingrediente_id', recetaIngredienteId);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadRecetaIngredientes(currentRecetaId);
                    showToast('Ingrediente eliminado correctamente', 'success');
                } else {
                    showToast('Error al eliminar ingrediente: ' + (data.error || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexión al eliminar ingrediente', 'error');
            });
        }

        function updateIngredienteReceta(recetaIngredienteId, cantidad, porcentajeMerma) {
            // Validaciones
            if (!cantidad || parseFloat(cantidad) <= 0) {
                showToast('La cantidad debe ser mayor que cero', 'error');
                loadRecetaIngredientes(currentRecetaId); // Recargar para restaurar valor anterior
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_ingrediente_receta');
            formData.append('receta_ingrediente_id', recetaIngredienteId);
            formData.append('cantidad', cantidad);
            formData.append('porcentaje_merma', porcentajeMerma || 0);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recargar para mostrar el nuevo costo calculado
                    loadRecetaIngredientes(currentRecetaId);
                    showToast('Ingrediente actualizado', 'success');
                } else {
                    showToast('Error al actualizar ingrediente: ' + (data.error || 'Error desconocido'), 'error');
                    loadRecetaIngredientes(currentRecetaId); // Recargar para restaurar valor anterior
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexión al actualizar ingrediente', 'error');
                loadRecetaIngredientes(currentRecetaId); // Recargar para restaurar valor anterior
            });
        }

        function viewReceta(id) {
            editItem('recetas', id);
        }

        // Funciones para cargar los reportes
        function loadRecetasPorMenuReport() {
            const container = document.getElementById('recetasPorMenuReport');
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando reporte de recetas...</p>
                </div>
            `;

            fetch('?action=get_data&table=reporte_recetas_por_menu')
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-center">No hay datos disponibles</p>';
                        return;
                    }

                    // Agrupar recetas por menú
                    const grouped = {};
                    data.forEach(item => {
                        if (!grouped[item.menu]) {
                            grouped[item.menu] = [];
                        }
                        grouped[item.menu].push(item);
                    });

                    let html = '';
                    Object.keys(grouped).forEach(menu => {
                        const recetas = grouped[menu];
                        html += `
                            <div class="report-menu-group mb-4">
                                <h4 class="report-menu-header mb-3">${menu}</h4>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Receta</th>
                                                <th>Descripción</th> <!-- Nueva columna -->
                                                <th>Precio Venta</th>
                                                <th>Costo</th>
                                                <th>Ganancia</th>
                                                <th>Porciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                        `;
                        
                        recetas.forEach(receta => {
                            const ganancia = parseFloat(receta.ganancia).toFixed(2);
                            html += `
                                <tr>
                                    <td>${receta.receta}</td>
                                    <td class="small-description" title="${receta.descripcion || 'Sin descripción'}">${receta.descripcion || 'Sin descripción'}</td>
                                    <td>${parseFloat(receta.precio_venta).toFixed(2)}</td>
                                    <td>${parseFloat(receta.costo_total).toFixed(2)}</td>
                                    <td class="${ganancia >= 0 ? 'profit-positive' : 'profit-negative'}">${ganancia}</td>
                                    <td>${receta.porciones}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        `;
                    });

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="text-danger">Error al cargar el reporte de recetas por menú</p>';
                });
        }

        function loadIngredientesPorCategoriaReport() {
            const container = document.getElementById('ingredientesPorCategoriaReport');
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando reporte de ingredientes...</p>
                </div>
            `;

            fetch('?action=get_data&table=reporte_ingredientes_por_categoria')
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-center">No hay datos disponibles</p>';
                        return;
                    }

                    let html = `
                        <table class="table table-striped report-table">
                            <thead>
                                <tr>
                                    <th>Categoría</th>
                                    <th>Total Ingredientes</th>
                                    <th>Precio Promedio</th>
                                    <th>Precio Mínimo</th>
                                    <th>Precio Máximo</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.forEach(item => {
                        html += `
                            <tr>
                                <td>${item.categoria}</td>
                                <td>${item.total_ingredientes}</td>
                                <td>${item.precio_promedio ? parseFloat(item.precio_promedio).toFixed(2) : 'N/A'}</td>
                                <td>${item.precio_minimo ? parseFloat(item.precio_minimo).toFixed(2) : 'N/A'}</td>
                                <td>${item.precio_maximo ? parseFloat(item.precio_maximo).toFixed(2) : 'N/A'}</td>
                            </tr>
                        `;
                    });

                    html += `
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <p class="text-muted">Total de categorías: ${data.length}</p>
                        </div>
                    `;

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="text-danger">Error al cargar el reporte de ingredientes por categoría</p>';
                });
        }

        function loadRentabilidadRecetasReport() {
            const container = document.getElementById('rentabilidadRecetasReport');
            container.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando reporte de rentabilidad...</p>
                </div>
            `;

            fetch('?action=get_data&table=reporte_rentabilidad_recetas')
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        container.innerHTML = '<p class="text-center">No hay datos disponibles</p>';
                        return;
                    }

                    // Calcular totales
                    let totalGanancia = 0;
                    let totalVentas = 0;
                    data.forEach(item => {
                        totalGanancia += parseFloat(item.ganancia);
                        totalVentas += parseFloat(item.precio_venta);
                    });

                    let html = `
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Total Ganancia</h5>
                                        <h3 class="text-success">$${totalGanancia.toFixed(2)}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Total Ventas</h5>
                                        <h3 class="text-primary">$${totalVentas.toFixed(2)}</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5>Margen Promedio</h5>
                                        <h3 class="text-info">${(totalGanancia > 0 ? (totalGanancia / totalVentas * 100).toFixed(2) : 0)}%</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <table class="table table-striped report-table">
                            <thead>
                                <tr>
                                    <th>Receta</th>
                                    <th>Descripción</th> <!-- Nueva columna -->
                                    <th>Precio Venta</th>
                                    <th>Costo</th>
                                    <th>Ganancia</th>
                                    <th>Margen</th>
                                    <th>Porciones</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;

                    data.forEach(item => {
                        const ganancia = parseFloat(item.ganancia).toFixed(2);
                        const margen = parseFloat(item.margen_ganancia).toFixed(2);
                        
                        html += `
                            <tr>
                                <td>${item.nombre}</td>
                                <td class="small-description" title="${item.descripcion || 'Sin descripción'}">${item.descripcion || 'Sin descripción'}</td>
                                <td>${parseFloat(item.precio_venta).toFixed(2)}</td>
                                <td>${parseFloat(item.costo_total).toFixed(2)}</td>
                                <td class="${ganancia >= 0 ? 'profit-positive' : 'profit-negative'}">${ganancia}</td>
                                <td class="${margen >= 0 ? 'profit-positive' : 'profit-negative'}">${margen}%</td>
                                <td>${item.porciones}</td>
                            </tr>
                        `;
                    });

                    html += `
                            </tbody>
                        </table>
                        <div class="mt-3">
                            <p class="text-muted">Total de recetas: ${data.length}</p>
                        </div>
                    `;

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p class="text-danger">Error al cargar el reporte de rentabilidad</p>';
                });
        }
    </script>
</body>
</html>