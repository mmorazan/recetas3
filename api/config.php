<?php
// api/config.php

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
            error_log("Database connection error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error Interno del Servidor: Falló la conexión a la base de datos.']);
            exit();
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

class ImageUploader {
    private $uploadDir = '../uploads/'; // Directorio uploads en la raíz del proyecto (relativo a api/)
    private $maxFileSize = 5 * 1024 * 1024; // 5MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    private $allowedExtensions = ['jpg', 'jpeg', 'png'];
    
    public function __construct() {
        $this->createDirectories();
    }
    
    private function createDirectories() {
        $dirs = [
            $this->uploadDir,
            $this->uploadDir . 'recetas', 
            $this->uploadDir . 'ingredientes'
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0777, true)) {
                    error_log("Failed to create directory: $dir");
                }
            }
        }
    }
    
    public function upload($file, $targetSubdir = '') {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'No se ha subido ningún archivo o ha ocurrido un error.'];
        }

        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'El archivo es demasiado grande (máximo 5MB).'];
        }

        $fileMimeType = mime_content_type($file['tmp_name']);
        if (!in_array($fileMimeType, $this->allowedTypes)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido. Solo JPG, JPEG, PNG.'];
        }

        $fileName = basename($file['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $this->allowedExtensions)) {
            return ['success' => false, 'message' => 'Extensión de archivo no permitida.'];
        }

        $newFileName = uniqid() . '.' . $fileExtension;
        $destination = $this->uploadDir . ($targetSubdir ? $targetSubdir . '/' : '') . $newFileName;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => true, 'fileName' => $newFileName];
        } else {
            return ['success' => false, 'message' => 'Error al mover el archivo subido.'];
        }
    }
}
?>