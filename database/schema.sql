-- ============================================
-- MONITOR DE PRECIOS - ESQUEMA UNIFICADO
-- Sistema completo de monitoreo automático de precios
-- ============================================
--
-- Este archivo contiene TODO lo necesario para la base de datos:
-- - Creación de base de datos
-- - 11 tablas con todas las columnas actualizadas
-- - Migraciones idempotentes (se puede ejecutar múltiples veces)
-- - Vistas y eventos programados
-- - Datos iniciales (patrones de scraping)
--
-- INSTALACIÓN NUEVA: Ejecuta este archivo completo
-- ACTUALIZACIÓN: También ejecuta este archivo (es idempotente)
--
-- Comando: mysql -u root -p < database/schema.sql
-- ============================================

-- Crear base de datos con configuración UTF-8
CREATE DATABASE IF NOT EXISTS price_monitor
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE price_monitor;

-- ============================================
-- TABLA DE USUARIOS
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    failed_login_attempts INT DEFAULT 0,
    locked_until TIMESTAMP NULL DEFAULT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_locked_until (locked_until),

    CHECK (CHAR_LENGTH(username) >= 3),
    CHECK (CHAR_LENGTH(password) >= 60),
    CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añadir columnas de seguridad si no existen
SET @dbname = 'price_monitor';
SET @tablename = 'users';
SET @columnname = 'failed_login_attempts';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ failed_login_attempts existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " INT DEFAULT 0")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'locked_until';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ locked_until existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " TIMESTAMP NULL DEFAULT NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'last_login';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ last_login existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " TIMESTAMP NULL DEFAULT NULL")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- TABLA DE URLs MONITORIZADAS
-- ============================================
CREATE TABLE IF NOT EXISTS monitored_urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    url VARCHAR(2048) NOT NULL,
    product_name VARCHAR(255),
    current_price DECIMAL(10, 2),
    target_price DECIMAL(10, 2) NOT NULL,
    last_checked TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'paused', 'error') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_last_checked (last_checked),

    CHECK (target_price > 0),
    CHECK (current_price IS NULL OR current_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añadir columnas nuevas si no existen
SET @tablename = 'monitored_urls';

-- product_image
SET @columnname = 'product_image';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ product_image existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(2048) NULL COMMENT 'URL de la imagen del producto'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- product_discount
SET @columnname = 'product_discount';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ product_discount existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DECIMAL(5,2) NULL COMMENT 'Porcentaje de descuento actual'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- product_original_price
SET @columnname = 'product_original_price';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ product_original_price existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DECIMAL(10,2) NULL COMMENT 'Precio original sin descuento'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- target_discount_percentage
SET @columnname = 'target_discount_percentage';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ target_discount_percentage existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DECIMAL(5,2) NULL DEFAULT NULL COMMENT 'Descuento objetivo en %'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- last_scraped_data
SET @columnname = 'last_scraped_data';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ last_scraped_data existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " JSON NULL COMMENT 'Últimos datos extraídos'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- error_count
SET @columnname = 'error_count';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ error_count existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " INT DEFAULT 0")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- TABLA DE MÉTODOS DE NOTIFICACIÓN
-- ============================================
CREATE TABLE IF NOT EXISTS notification_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    method ENUM('sms', 'telegram', 'whatsapp', 'email') NOT NULL,
    contact_info VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (url_id) REFERENCES monitored_urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id),
    INDEX idx_method (method),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_url_method_contact (url_id, method, contact_info)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE HISTORIAL DE PRECIOS
-- ============================================
CREATE TABLE IF NOT EXISTS price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (url_id) REFERENCES monitored_urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id),
    INDEX idx_checked_at (checked_at),
    INDEX idx_url_date (url_id, checked_at),

    CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Añadir columnas de scraping si no existen
SET @tablename = 'price_history';

SET @columnname = 'scraping_method';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ scraping_method existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(100) NULL COMMENT 'Método de extracción'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'raw_data';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ raw_data existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " TEXT NULL COMMENT 'Datos crudos'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = 'extraction_time_ms';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ extraction_time_ms existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " INT NULL COMMENT 'Tiempo de extracción'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- TABLA DE LOG DE NOTIFICACIONES
-- ============================================
CREATE TABLE IF NOT EXISTS notifications_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    method ENUM('sms', 'telegram', 'whatsapp', 'email') NOT NULL,
    old_price DECIMAL(10, 2),
    new_price DECIMAL(10, 2) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent', 'failed') DEFAULT 'sent',
    error_message TEXT,

    FOREIGN KEY (url_id) REFERENCES monitored_urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id),
    INDEX idx_sent_at (sent_at),
    INDEX idx_status (status),
    INDEX idx_method (method),

    CHECK (new_price >= 0),
    CHECK (old_price IS NULL OR old_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE IMÁGENES DE PRODUCTOS
-- ============================================
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    image_url VARCHAR(2048) NOT NULL,
    image_local_path VARCHAR(512),
    downloaded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (url_id) REFERENCES monitored_urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE FUNCIONES DE SCRAPING
-- ============================================
CREATE TABLE IF NOT EXISTS scraping_functions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url_id INT NOT NULL,
    function_name VARCHAR(100) NOT NULL,
    selector_type ENUM('css', 'xpath', 'regex', 'json_path') NOT NULL,
    selector_value TEXT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (url_id) REFERENCES monitored_urls(id) ON DELETE CASCADE,
    INDEX idx_url_id (url_id),
    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE PATRONES DE SCRAPING
-- ============================================
CREATE TABLE IF NOT EXISTS scraping_patterns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pattern_name VARCHAR(100) NOT NULL UNIQUE,
    domain_pattern VARCHAR(255),
    selector_type ENUM('css', 'xpath', 'regex', 'json_path') NOT NULL,
    selector_value TEXT NOT NULL,
    description TEXT,
    success_count INT DEFAULT 0,
    fail_count INT DEFAULT 0,
    last_used TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_domain_pattern (domain_pattern),
    INDEX idx_is_active (is_active),
    INDEX idx_success_count (success_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar patrones de scraping (IGNORE si ya existen)
INSERT IGNORE INTO scraping_patterns (pattern_name, domain_pattern, selector_type, selector_value, description) VALUES
('Amazon ES Price', '%amazon.es%', 'css', '.a-price-whole', 'Precio principal de Amazon España'),
('Amazon ES Offer', '%amazon.es%', 'css', '#priceblock_ourprice', 'Precio oferta Amazon'),
('PcComponentes Price', '%pccomponentes.com%', 'xpath', '//*[@id="pdp-price-current-integer"]', 'Precio PcComponentes con XPath'),
('PcComponentes Title', '%pccomponentes.com%', 'xpath', '//*[@id="pdp-title"]', 'Título del producto PcComponentes'),
('PcComponentes Image', '%pccomponentes.com%', 'xpath', '/html/body/div[2]/main/div[2]/div[3]/div[2]/div[2]/div[1]/div/ul/li[1]/img', 'Primera imagen PcComponentes'),
('PcComponentes Discount', '%pccomponentes.com%', 'xpath', '//*[@id="pdp-price-discount"]', 'Descuento PcComponentes'),
('MediaMarkt', '%mediamarkt.es%', 'css', '.price', 'Precio MediaMarkt'),
('El Corte Inglés', '%elcorteingles.es%', 'css', '.prices-price', 'Precio El Corte Inglés'),
('eBay', '%ebay.es%', 'css', '.x-price-primary', 'Precio eBay'),
('Generic Price Class', '%', 'css', '.price', 'Clase CSS genérica de precio'),
('Generic Price ID', '%', 'css', '#price', 'ID genérico de precio'),
('Schema.org JSON', '%', 'json_path', '$.offers.price', 'Precio en formato Schema.org'),
('Open Graph Price', '%', 'regex', 'property="og:price:amount" content="([0-9.,]+)"', 'Meta tag Open Graph');

-- ============================================
-- TABLA DE SESIONES
-- ============================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE INTENTOS DE LOGIN
-- ============================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_ip_address (ip_address),
    INDEX idx_attempted_at (attempted_at),
    INDEX idx_username_ip (username, ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- VISTAS
-- ============================================

-- Vista de URLs activas
CREATE OR REPLACE VIEW v_active_urls AS
SELECT
    mu.id,
    mu.url,
    mu.product_name,
    mu.current_price,
    mu.target_price,
    mu.product_discount,
    mu.target_discount_percentage,
    mu.last_checked,
    mu.status,
    u.id as user_id,
    u.username,
    u.email
FROM monitored_urls mu
INNER JOIN users u ON mu.user_id = u.id
WHERE mu.status = 'active';

-- Vista de historial
CREATE OR REPLACE VIEW v_price_history AS
SELECT
    ph.id,
    ph.price,
    ph.checked_at,
    ph.scraping_method,
    mu.id as url_id,
    mu.product_name,
    mu.url,
    u.username
FROM price_history ph
INNER JOIN monitored_urls mu ON ph.url_id = mu.id
INNER JOIN users u ON mu.user_id = u.id;

-- ============================================
-- EVENTOS PROGRAMADOS
-- ============================================

-- Limpiar intentos de login antiguos
DROP EVENT IF EXISTS cleanup_login_attempts;
CREATE EVENT cleanup_login_attempts
ON SCHEDULE EVERY 1 DAY
DO
DELETE FROM login_attempts
WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Limpiar sesiones expiradas
DROP EVENT IF EXISTS cleanup_expired_sessions;
CREATE EVENT cleanup_expired_sessions
ON SCHEDULE EVERY 1 HOUR
DO
DELETE FROM sessions
WHERE last_activity < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- ============================================
-- CONFIRMACIÓN
-- ============================================
SELECT
    '✓ Esquema completo instalado/actualizado' as Status,
    DATABASE() as DatabaseName,
    @@character_set_database as Charset,
    @@collation_database as Collation;

SELECT CONCAT('✓ ', COUNT(*), ' tablas creadas') as Tablas
FROM information_schema.tables
WHERE table_schema = 'price_monitor';

SELECT CONCAT('✓ ', COUNT(*), ' patrones de scraping') as Patrones
FROM scraping_patterns;

-- ============================================
-- INSTRUCCIONES
-- ============================================
/*
INSTALACIÓN / ACTUALIZACIÓN:
    mysql -u root -p < database/schema.sql

Este archivo es idempotente: puede ejecutarse múltiples veces.
- Instalación nueva: Crea toda la base de datos
- Actualización: Añade columnas nuevas sin afectar datos existentes

OPCIONAL - Usuario seguro:
    1. Genera contraseña: openssl rand -base64 32
    2. Ejecuta: CREATE USER 'price_monitor_user'@'localhost' IDENTIFIED BY 'tu_contraseña';
    3. Permisos: GRANT SELECT, INSERT, UPDATE, DELETE ON price_monitor.* TO 'price_monitor_user'@'localhost';
    4. Aplica: FLUSH PRIVILEGES;

VERIFICAR:
    SHOW TABLES;
    DESCRIBE monitored_urls;
    SELECT COUNT(*) FROM scraping_patterns;
*/
