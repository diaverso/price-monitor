<?php
/**
 * API de Búsqueda Cross-Site
 * Busca el mismo producto en diferentes tiendas según categoría
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

$user_id = $_SESSION['user_id'];

/**
 * Función para extraer información del producto del nombre
 */
function extractProductInfo($productName) {
    $info = [
        'brand' => null,
        'model' => null,
        'category' => 'otros',
        'search_terms' => []
    ];

    $productName = trim($productName);

    // Detectar marca y categoría
    $brands = [
        'electronica' => ['Samsung', 'LG', 'Sony', 'Philips', 'Panasonic', 'TCL', 'Hisense', 'Sharp', 'Apple'],
        'informatica' => ['HP', 'Lenovo', 'Dell', 'Asus', 'Acer', 'MSI', 'Apple', 'Intel', 'AMD', 'NVIDIA', 'Corsair', 'Kingston', 'Logitech'],
        'deporte' => ['Nike', 'Adidas', 'Puma', 'Reebok', 'New Balance', 'Decathlon', 'Asics', 'Under Armour'],
        'hogar' => ['IKEA', 'Conforama', 'LEGO'],
        'moda' => ['Zara', 'Mango', 'H&M', 'Nike', 'Adidas', 'Michael Kors', 'Calvin Klein']
    ];

    foreach ($brands as $category => $brandList) {
        foreach ($brandList as $brand) {
            if (stripos($productName, $brand) !== false) {
                $info['brand'] = $brand;
                $info['category'] = $category;
                break 2;
            }
        }
    }

    // Extraer modelo (eliminar marca y palabras comunes)
    $model = $productName;
    if ($info['brand']) {
        $model = preg_replace('/\b' . preg_quote($info['brand'], '/') . '\b/i', '', $model);
    }

    // Eliminar palabras comunes que no son relevantes para la búsqueda
    $stopWords = ['para', 'con', 'sin', 'color', 'talla', 'tamaño', 'nuevo', 'pack', 'unidades', 'oficial', 'original'];
    $model = preg_replace('/\b(' . implode('|', $stopWords) . ')\b/i', '', $model);

    $info['model'] = trim($model);

    // Generar términos de búsqueda
    $info['search_terms'] = array_filter([
        $info['brand'] . ' ' . $info['model'], // Marca + Modelo
        $info['model'], // Solo modelo
        $productName // Nombre completo como fallback
    ]);

    return $info;
}

/**
 * Función para obtener tiendas relevantes según categoría
 */
function getRelevantStores($db, $category) {
    try {
        $stmt = $db->prepare("
            SELECT store_domain, store_name, search_url_pattern
            FROM store_categories
            WHERE is_active = 1
            AND JSON_CONTAINS(categories, JSON_QUOTE(?))
            ORDER BY store_name
        ");
        $stmt->execute([$category]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error obteniendo tiendas: " . $e->getMessage());
        return [];
    }
}

/**
 * Función para crear o actualizar un grupo de productos
 */
function createProductGroup($db, $productInfo, $originalUrlId) {
    try {
        // Verificar si ya existe un grupo para este producto
        $stmt = $db->prepare("
            SELECT id FROM product_groups
            WHERE name = ? OR (brand = ? AND model = ?)
            LIMIT 1
        ");
        $stmt->execute([
            $productInfo['name'],
            $productInfo['brand'],
            $productInfo['model']
        ]);

        $group = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($group) {
            $groupId = $group['id'];
        } else {
            // Crear nuevo grupo
            $stmt = $db->prepare("
                INSERT INTO product_groups (name, brand, model, category)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $productInfo['name'],
                $productInfo['brand'],
                $productInfo['model'],
                $productInfo['category']
            ]);
            $groupId = $db->lastInsertId();
        }

        // Añadir el producto original al grupo si no está
        $stmt = $db->prepare("
            INSERT IGNORE INTO product_group_items (group_id, monitored_url_id, is_primary, similarity_score)
            VALUES (?, ?, 1, 100.00)
        ");
        $stmt->execute([$groupId, $originalUrlId]);

        // Actualizar group_id en monitored_urls
        $stmt = $db->prepare("UPDATE monitored_urls SET group_id = ? WHERE id = ?");
        $stmt->execute([$groupId, $originalUrlId]);

        return $groupId;

    } catch (Exception $e) {
        error_log("Error creando grupo de producto: " . $e->getMessage());
        return null;
    }
}

// ============================================
// ENDPOINTS
// ============================================

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    $db = Database::getInstance()->getConnection();

    // GET: Obtener comparación de precios de un grupo
    if ($method === 'GET' && $action === 'comparison') {
        $groupId = $_GET['group_id'] ?? null;

        if (!$groupId) {
            throw new Exception('group_id es requerido');
        }

        // Verificar que el usuario tiene acceso a este grupo
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM product_group_items pgi
            INNER JOIN monitored_urls mu ON pgi.monitored_url_id = mu.id
            WHERE pgi.group_id = ? AND mu.user_id = ?
        ");
        $stmt->execute([$groupId, $user_id]);
        $access = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($access['count'] == 0) {
            throw new Exception('No tienes acceso a este grupo de productos');
        }

        // Obtener comparación de precios
        $stmt = $db->prepare("
            SELECT * FROM v_price_comparison
            WHERE group_id = ?
            ORDER BY current_price ASC
        ");
        $stmt->execute([$groupId]);
        $comparison = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener estadísticas
        $stmt = $db->prepare("
            SELECT * FROM v_best_prices
            WHERE group_id = ?
        ");
        $stmt->execute([$groupId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'group_id' => $groupId,
            'products' => $comparison,
            'stats' => $stats
        ]);
        exit;
    }

    // POST: Iniciar búsqueda cross-site
    if ($method === 'POST' && $action === 'search') {
        $data = json_decode(file_get_contents('php://input'), true);
        $urlId = $data['url_id'] ?? null;

        if (!$urlId) {
            throw new Exception('url_id es requerido');
        }

        // Obtener información del producto original
        $stmt = $db->prepare("
            SELECT id, url, product_name, current_price, product_image
            FROM monitored_urls
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$urlId, $user_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception('Producto no encontrado');
        }

        // Extraer información del producto
        $productInfo = extractProductInfo($product['product_name']);
        $productInfo['name'] = $product['product_name'];

        // Crear o actualizar grupo de productos
        $groupId = createProductGroup($db, $productInfo, $urlId);

        if (!$groupId) {
            throw new Exception('Error creando grupo de productos');
        }

        // Obtener tiendas relevantes
        $stores = getRelevantStores($db, $productInfo['category']);

        // Generar URLs de búsqueda para cada tienda
        $searchUrls = [];
        foreach ($stores as $store) {
            if (!$store['search_url_pattern']) {
                continue;
            }

            // Usar el primer término de búsqueda
            $searchTerm = urlencode($productInfo['search_terms'][0] ?? $product['product_name']);
            $searchUrl = str_replace('{query}', $searchTerm, $store['search_url_pattern']);

            $searchUrls[] = [
                'store_name' => $store['store_name'],
                'store_domain' => $store['store_domain'],
                'search_url' => $searchUrl,
                'search_term' => $productInfo['search_terms'][0] ?? $product['product_name']
            ];
        }

        echo json_encode([
            'success' => true,
            'group_id' => $groupId,
            'product_info' => $productInfo,
            'original_product' => $product,
            'search_urls' => $searchUrls,
            'message' => 'Grupo de productos creado. Añade las URLs encontradas manualmente.'
        ]);
        exit;
    }

    // POST: Añadir producto al grupo
    if ($method === 'POST' && $action === 'add-to-group') {
        $data = json_decode(file_get_contents('php://input'), true);
        $groupId = $data['group_id'] ?? null;
        $newUrl = $data['url'] ?? null;
        $targetPrice = $data['target_price'] ?? null;

        if (!$groupId || !$newUrl) {
            throw new Exception('group_id y url son requeridos');
        }

        // Verificar acceso al grupo
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM product_group_items pgi
            INNER JOIN monitored_urls mu ON pgi.monitored_url_id = mu.id
            WHERE pgi.group_id = ? AND mu.user_id = ?
        ");
        $stmt->execute([$groupId, $user_id]);
        $access = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($access['count'] == 0) {
            throw new Exception('No tienes acceso a este grupo');
        }

        // Añadir nueva URL a monitored_urls
        $stmt = $db->prepare("
            INSERT INTO monitored_urls (user_id, url, target_price, group_id)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $newUrl,
            $targetPrice ?? 0,
            $groupId
        ]);
        $newUrlId = $db->lastInsertId();

        // Añadir al grupo
        $stmt = $db->prepare("
            INSERT INTO product_group_items (group_id, monitored_url_id, is_primary, similarity_score)
            VALUES (?, ?, 0, 95.00)
        ");
        $stmt->execute([$groupId, $newUrlId]);

        echo json_encode([
            'success' => true,
            'message' => 'Producto añadido al grupo',
            'url_id' => $newUrlId
        ]);
        exit;
    }

    // GET: Obtener categorías disponibles
    if ($method === 'GET' && $action === 'categories') {
        $stmt = $db->query("
            SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(categories, CONCAT('$[', idx, ']'))) as category
            FROM store_categories
            CROSS JOIN (
                SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3
                UNION SELECT 4 UNION SELECT 5 UNION SELECT 6
            ) numbers
            WHERE JSON_EXTRACT(categories, CONCAT('$[', idx, ']')) IS NOT NULL
            ORDER BY category
        ");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
        exit;
    }

    throw new Exception('Acción no válida');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
