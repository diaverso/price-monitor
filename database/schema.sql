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
-- ============================================
-- SISTEMA DE BÚSQUEDA CROSS-SITE
-- Agrupa productos similares de diferentes tiendas
-- ============================================

USE price_monitor;

-- ============================================
-- TABLA DE GRUPOS DE PRODUCTOS
-- Agrupa productos similares de diferentes tiendas
-- ============================================
CREATE TABLE IF NOT EXISTS product_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(500) NOT NULL COMMENT 'Nombre/modelo del producto',
    brand VARCHAR(100) NULL COMMENT 'Marca extraída',
    model VARCHAR(200) NULL COMMENT 'Modelo extraído',
    category ENUM('electronica', 'informatica', 'hogar', 'moda', 'deporte', 'alimentacion', 'otros') DEFAULT 'otros',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_brand (brand),
    INDEX idx_model (model),
    INDEX idx_category (category),
    FULLTEXT idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE RELACIÓN PRODUCTO-GRUPO
-- Relaciona URLs monitorizadas con grupos de productos
-- ============================================
CREATE TABLE IF NOT EXISTS product_group_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    monitored_url_id INT NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE COMMENT 'Si es el producto original de la búsqueda',
    similarity_score DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Porcentaje de similitud (0-100)',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (group_id) REFERENCES product_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (monitored_url_id) REFERENCES monitored_urls(id) ON DELETE CASCADE,

    INDEX idx_group_id (group_id),
    INDEX idx_monitored_url_id (monitored_url_id),
    INDEX idx_is_primary (is_primary),

    UNIQUE KEY unique_group_url (group_id, monitored_url_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLA DE CATEGORÍAS DE TIENDAS
-- Define qué tiendas vender qué categorías
-- ============================================
CREATE TABLE IF NOT EXISTS store_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_domain VARCHAR(100) NOT NULL COMMENT 'Dominio de la tienda (amazon.es, pccomponentes.com, etc)',
    store_name VARCHAR(100) NOT NULL COMMENT 'Nombre amigable',
    categories JSON NOT NULL COMMENT 'Array de categorías que vende',
    search_url_pattern VARCHAR(500) NULL COMMENT 'Patrón de URL de búsqueda con {query}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_store_domain (store_domain),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_domain (store_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DATOS INICIALES: CATEGORÍAS DE TIENDAS
-- ============================================

-- Electrónica e Informática
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern) VALUES
('amazon.es', 'Amazon España', '["electronica", "informatica", "hogar", "moda", "deporte", "alimentacion"]', 'https://www.amazon.es/s?k={query}'),
('pccomponentes.com', 'PcComponentes', '["electronica", "informatica"]', 'https://www.pccomponentes.com/buscar/?query={query}'),
('mediamarkt.es', 'MediaMarkt', '["electronica", "informatica", "hogar"]', 'https://www.mediamarkt.es/es/search.html?query={query}'),
('coolmod.com', 'Coolmod', '["electronica", "informatica"]', 'https://www.coolmod.com/buscar/?q={query}'),
('elcorteingles.es', 'El Corte Inglés', '["electronica", "informatica", "hogar", "moda", "deporte", "alimentacion"]', 'https://www.elcorteingles.es/buscar/?s={query}')
ON DUPLICATE KEY UPDATE
    store_name = VALUES(store_name),
    categories = VALUES(categories),
    search_url_pattern = VALUES(search_url_pattern);

-- Moda
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern) VALUES
('shop.mango.com', 'Mango', '["moda"]', 'https://shop.mango.com/es/search?q={query}'),
('mangooutlet.com', 'Mango Outlet', '["moda"]', 'https://shop.mangooutlet.com/es/search?q={query}'),
('michaelkors.es', 'Michael Kors', '["moda"]', 'https://www.michaelkors.es/search/?q={query}'),
('zalando.es', 'Zalando', '["moda"]', 'https://www.zalando.es/catalog/?q={query}')
ON DUPLICATE KEY UPDATE
    store_name = VALUES(store_name),
    categories = VALUES(categories),
    search_url_pattern = VALUES(search_url_pattern);

-- Deporte
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern) VALUES
('decathlon.es', 'Decathlon', '["deporte"]', 'https://www.decathlon.es/es/search?Ntt={query}')
ON DUPLICATE KEY UPDATE
    store_name = VALUES(store_name),
    categories = VALUES(categories),
    search_url_pattern = VALUES(search_url_pattern);

-- Hogar y Muebles
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern) VALUES
('ikea.com', 'IKEA', '["hogar"]', 'https://www.ikea.com/es/es/search/?q={query}'),
('lego.com', 'LEGO', '["hogar", "otros"]', 'https://www.lego.com/es-es/search?q={query}')
ON DUPLICATE KEY UPDATE
    store_name = VALUES(store_name),
    categories = VALUES(categories),
    search_url_pattern = VALUES(search_url_pattern);

-- Alimentación
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern) VALUES
('mercadona.es', 'Mercadona', '["alimentacion"]', NULL),
('consum.es', 'Consum', '["alimentacion"]', NULL)
ON DUPLICATE KEY UPDATE
    store_name = VALUES(store_name),
    categories = VALUES(categories);

-- Marketplaces Generales
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern) VALUES
('aliexpress.com', 'AliExpress', '["electronica", "informatica", "hogar", "moda", "deporte", "otros"]', 'https://www.aliexpress.com/wholesale?SearchText={query}'),
('temu.com', 'Temu', '["electronica", "informatica", "hogar", "moda", "deporte", "otros"]', 'https://www.temu.com/search_result.html?search_key={query}')
ON DUPLICATE KEY UPDATE
    store_name = VALUES(store_name),
    categories = VALUES(categories),
    search_url_pattern = VALUES(search_url_pattern);

-- ============================================
-- VISTA: Comparador de precios por grupo
-- ============================================
CREATE OR REPLACE VIEW v_price_comparison AS
SELECT
    pg.id as group_id,
    pg.name as product_name,
    pg.brand,
    pg.model,
    pg.category,
    mu.id as url_id,
    mu.url,
    mu.product_name as store_product_name,
    mu.current_price,
    mu.product_discount,
    mu.product_image,
    mu.last_checked,
    pgi.is_primary,
    pgi.similarity_score,
    CASE
        WHEN mu.url LIKE '%amazon%' THEN 'Amazon'
        WHEN mu.url LIKE '%pccomponentes%' THEN 'PcComponentes'
        WHEN mu.url LIKE '%mediamarkt%' THEN 'MediaMarkt'
        WHEN mu.url LIKE '%coolmod%' THEN 'Coolmod'
        WHEN mu.url LIKE '%elcorteingles%' THEN 'El Corte Inglés'
        WHEN mu.url LIKE '%decathlon%' THEN 'Decathlon'
        WHEN mu.url LIKE '%ikea%' THEN 'IKEA'
        WHEN mu.url LIKE '%mango%' THEN 'Mango'
        ELSE 'Otra tienda'
    END as store_name
FROM product_groups pg
INNER JOIN product_group_items pgi ON pg.id = pgi.group_id
INNER JOIN monitored_urls mu ON pgi.monitored_url_id = mu.id
WHERE mu.status = 'active'
ORDER BY pg.id, mu.current_price ASC;

-- ============================================
-- VISTA: Mejor precio por grupo
-- ============================================
CREATE OR REPLACE VIEW v_best_prices AS
SELECT
    group_id,
    product_name,
    brand,
    model,
    category,
    MIN(current_price) as best_price,
    MAX(current_price) as highest_price,
    AVG(current_price) as avg_price,
    COUNT(*) as store_count,
    GROUP_CONCAT(CONCAT(store_name, ':', current_price) ORDER BY current_price SEPARATOR ' | ') as price_list
FROM v_price_comparison
WHERE current_price IS NOT NULL
GROUP BY group_id, product_name, brand, model, category;

-- ============================================
-- ÍNDICES ADICIONALES PARA OPTIMIZACIÓN
-- ============================================

-- Añadir columna group_id a monitored_urls si no existe (para acceso rápido)
SET @columnname = 'group_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = 'monitored_urls' AND table_schema = 'price_monitor' AND column_name = @columnname) > 0,
  "SELECT '✓ group_id existe en monitored_urls' as Info",
  "ALTER TABLE monitored_urls ADD COLUMN group_id INT NULL COMMENT 'ID del grupo de productos', ADD INDEX idx_group_id (group_id), ADD FOREIGN KEY (group_id) REFERENCES product_groups(id) ON DELETE SET NULL"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- STORED PROCEDURES
-- ============================================

DELIMITER //

-- Procedimiento para extraer marca y modelo de un nombre de producto
DROP PROCEDURE IF EXISTS extract_product_info//
CREATE PROCEDURE extract_product_info(
    IN product_name VARCHAR(500),
    OUT brand VARCHAR(100),
    OUT model VARCHAR(200),
    OUT category VARCHAR(50)
)
BEGIN
    -- Marcas conocidas de electrónica
    SET brand = NULL;
    SET model = product_name;
    SET category = 'otros';

    -- Detectar marca (primeras palabras comunes)
    IF product_name REGEXP '^(Samsung|LG|Sony|Philips|Panasonic|TCL|Hisense|Sharp)' THEN
        SET brand = SUBSTRING_INDEX(product_name, ' ', 1);
        SET category = 'electronica';
    ELSEIF product_name REGEXP '(iPhone|iPad|MacBook|iMac|Apple)' THEN
        SET brand = 'Apple';
        SET category = 'electronica';
    ELSEIF product_name REGEXP '(HP|Lenovo|Dell|Asus|Acer|MSI)' THEN
        SET brand = SUBSTRING_INDEX(product_name, ' ', 1);
        SET category = 'informatica';
    ELSEIF product_name REGEXP '(Nike|Adidas|Puma|Reebok|New Balance)' THEN
        SET brand = SUBSTRING_INDEX(product_name, ' ', 1);
        SET category = 'deporte';
    ELSEIF product_name REGEXP '(IKEA|Conforama)' THEN
        SET brand = SUBSTRING_INDEX(product_name, ' ', 1);
        SET category = 'hogar';
    END IF;

    -- Extraer modelo (eliminar marca y palabras comunes)
    IF brand IS NOT NULL THEN
        SET model = TRIM(SUBSTRING(product_name, LENGTH(brand) + 2));
    END IF;
END//

DELIMITER ;

SELECT '✓ Sistema de búsqueda cross-site instalado correctamente' as status;
-- ============================================
-- MIGRACIÓN: AUTO SCRAPING CROSS-SITE
-- Añade columnas para tracking de auto-matching
-- ============================================

USE price_monitor;

-- Añadir columnas a product_group_items para auto-matching
SET @dbname = 'price_monitor';
SET @tablename = 'product_group_items';

-- Columna: auto_matched
SET @columnname = 'auto_matched';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ auto_matched existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " BOOLEAN DEFAULT FALSE COMMENT 'Si fue añadido automáticamente por auto-search'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Columna: match_score
SET @columnname = 'match_score';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ match_score existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DECIMAL(5,2) DEFAULT NULL COMMENT 'Score de similitud del auto-matching (0-100)'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Columna: match_metadata
SET @columnname = 'match_metadata';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
   WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  "SELECT '✓ match_metadata existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " JSON DEFAULT NULL COMMENT 'Metadata del matching (nombres comparados, algoritmo usado, etc)'")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Añadir índice para auto_matched
SET @indexname = 'idx_auto_matched';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
   WHERE table_name = @tablename AND table_schema = @dbname AND index_name = @indexname) > 0,
  "SELECT '✓ índice idx_auto_matched existe' as Info",
  CONCAT("ALTER TABLE ", @tablename, " ADD INDEX ", @indexname, " (auto_matched)")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SELECT '✓ Migración de auto-scraping completada correctamente' as status;
