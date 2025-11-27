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
