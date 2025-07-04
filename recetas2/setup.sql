-- GCODE Admin - Setup Inicial de Base de Datos
-- Ejecutar estos comandos para configurar el sistema

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS gcode CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gcode;

-- Tabla de usuarios administradores
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NULL,
    full_name VARCHAR(100) NULL,
    active TINYINT(1) DEFAULT 1,
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_active (active),
    INDEX idx_locked_until (locked_until)
) ENGINE=InnoDB;

-- Usuario administrador por defecto
-- Username: admin, Password: admin123
INSERT IGNORE INTO admin_users (username, password_hash, full_name, email) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador', 'admin@example.com');

-- Tabla de log de actividades
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(64) NULL,
    record_id VARCHAR(100) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_ip_address (ip_address)
) ENGINE=InnoDB;

-- Tabla de configuraciones del sistema
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT NULL,
    description TEXT NULL,
    is_public TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_config_key (config_key),
    INDEX idx_is_public (is_public)
) ENGINE=InnoDB;

-- Configuraciones por defecto
INSERT IGNORE INTO system_config (config_key, config_value, description, is_public) VALUES
('app_name', 'GCODE Admin', 'Nombre de la aplicación', 1),
('app_version', '2.0', 'Versión actual', 1),
('max_login_attempts', '5', 'Máximo intentos de login', 0),
('session_timeout', '3600', 'Timeout de sesión en segundos', 0),
('pagination_limit', '20', 'Registros por página por defecto', 1),
('enable_logging', '1', 'Habilitar logging de actividades', 0);

-- Tablas de ejemplo para testing (opcional)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    category_id INT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT NULL,
    parent_id INT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Datos de ejemplo
INSERT IGNORE INTO users (name, email, phone) VALUES
('Juan Pérez', 'juan@example.com', '+1234567890'),
('María García', 'maria@example.com', '+1234567891'),
('Carlos López', 'carlos@example.com', '+1234567892');

INSERT IGNORE INTO categories (name, slug, description) VALUES
('Electrónicos', 'electronicos', 'Productos electrónicos y gadgets'),
('Ropa', 'ropa', 'Vestimenta y accesorios'),
('Hogar', 'hogar', 'Artículos para el hogar');

INSERT IGNORE INTO products (name, description, price, stock, category_id) VALUES
('Smartphone Galaxy', 'Teléfono inteligente última generación', 699.99, 50, 1),
('Laptop Pro', 'Laptop profesional para desarrollo', 1299.99, 25, 1),
('Camiseta Cotton', 'Camiseta 100% algodón', 29.99, 100, 2),
('Mesa de Centro', 'Mesa moderna para sala', 199.99, 15, 3);

-- Crear índices adicionales para optimización
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_products_price ON products(price);
CREATE INDEX idx_products_stock ON products(stock);
CREATE INDEX idx_products_category ON products(category_id);

-- Verificar que todo se creó correctamente
SELECT 'Setup completado exitosamente' as status;
SELECT COUNT(*) as admin_users_count FROM admin_users;
SELECT COUNT(*) as config_count FROM system_config;
SELECT COUNT(*) as sample_users FROM users;
SELECT COUNT(*) as sample_products FROM products;