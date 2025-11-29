<?php
require_once __DIR__ . '/../scrapers/search/AmazonSearchScraper.php';
require_once __DIR__ . '/../scrapers/search/PcComponentesSearchScraper.php';
require_once __DIR__ . '/../scrapers/search/MediaMarktSearchScraper.php';
require_once __DIR__ . '/../scrapers/search/ElCorteInglesSearchScraper.php';
require_once __DIR__ . '/ProductMatcher.php';

/**
 * Servicio principal para búsqueda automática cross-site
 *
 * Coordina la búsqueda en múltiples tiendas y el matching de productos
 */
class AutoCrossSiteSearch {

    private $db;
    private $matcher;
    private $availableScrapers = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->matcher = new ProductMatcher();

        // Registrar scrapers disponibles
        $this->registerScrapers();
    }

    /**
     * Registrar scrapers de búsqueda disponibles (expandido en Fase 2)
     */
    private function registerScrapers() {
        $this->availableScrapers = [
            'Amazon' => AmazonSearchScraper::class,
            'PcComponentes' => PcComponentesSearchScraper::class,
            'MediaMarkt' => MediaMarktSearchScraper::class,
            'El Corte Inglés' => ElCorteInglesSearchScraper::class
        ];
    }

    /**
     * Buscar un producto en otras tiendas automáticamente
     *
     * @param int $productUrlId ID de la URL del producto original
     * @param int $userId ID del usuario
     * @param array $targetStores Tiendas donde buscar (null = todas)
     * @param int $groupId ID del grupo donde añadir productos (null = no añadir)
     * @return array Resultados de la búsqueda
     */
    public function searchProduct($productUrlId, $userId, $targetStores = null, $groupId = null) {
        $startTime = microtime(true);

        try {
            // Obtener información del producto original
            $originalProduct = $this->getProductInfo($productUrlId);

            if (!$originalProduct) {
                return [
                    'success' => false,
                    'error' => 'Producto original no encontrado'
                ];
            }

            error_log("Auto-search iniciado para: " . $originalProduct['name']);

            // Generar query de búsqueda optimizada
            $searchQuery = $this->matcher->generateSearchQuery($originalProduct['name']);

            error_log("Query de búsqueda generada: $searchQuery");

            // Determinar en qué tiendas buscar
            $storesToSearch = $this->determineTargetStores($originalProduct['store'], $targetStores);

            // Realizar búsquedas (en paralelo usando fork si está disponible)
            $searchResults = $this->searchInStores($searchQuery, $storesToSearch);

            // Hacer matching de productos
            $matches = $this->matchProducts($originalProduct, $searchResults);

            // Si hay un groupId, añadir los mejores matches al grupo
            if ($groupId && !empty($matches)) {
                $this->addMatchesToGroup($groupId, $matches, $originalProduct, $userId);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'original_product' => $originalProduct,
                'search_query' => $searchQuery,
                'stores_searched' => $storesToSearch,
                'matches' => $matches,
                'execution_time_ms' => $executionTime
            ];

        } catch (Exception $e) {
            error_log("AutoCrossSiteSearch Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información de un producto desde la BD
     *
     * @param int $monitoredUrlId ID de la URL monitoreada
     * @return array|null Información del producto
     */
    private function getProductInfo($monitoredUrlId) {
        $stmt = $this->db->prepare("
            SELECT
                id,
                url,
                product_name as name,
                current_price as price,
                product_image as image
            FROM monitored_urls
            WHERE id = ?
        ");

        $stmt->execute([$monitoredUrlId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        // Extraer tienda del dominio (expandido en Fase 2)
        $domain = parse_url($result['url'], PHP_URL_HOST);
        $store = 'Desconocida';

        if (strpos($domain, 'amazon') !== false) {
            $store = 'Amazon';
        } elseif (strpos($domain, 'pccomponentes') !== false) {
            $store = 'PcComponentes';
        } elseif (strpos($domain, 'mediamarkt') !== false) {
            $store = 'MediaMarkt';
        } elseif (strpos($domain, 'elcorteingles') !== false) {
            $store = 'El Corte Inglés';
        } elseif (strpos($domain, 'coolmod') !== false) {
            $store = 'Coolmod';
        } elseif (strpos($domain, 'zalando') !== false) {
            $store = 'Zalando';
        } elseif (strpos($domain, 'decathlon') !== false) {
            $store = 'Decathlon';
        }

        return [
            'url_id' => $result['id'],
            'url' => $result['url'],
            'store' => $store,
            'name' => $result['name'] ?? '',
            'price' => $result['price'] ?? 0,
            'image' => $result['image'] ?? ''
        ];
    }

    /**
     * Determinar en qué tiendas buscar
     *
     * @param string $originalStore Tienda del producto original
     * @param array|null $targetStores Tiendas específicas o null para todas
     * @return array Lista de tiendas donde buscar
     */
    private function determineTargetStores($originalStore, $targetStores) {
        // Si se especifican tiendas objetivo, usarlas
        if ($targetStores && is_array($targetStores)) {
            return array_intersect($targetStores, array_keys($this->availableScrapers));
        }

        // Sino, buscar en todas excepto la original
        $stores = array_keys($this->availableScrapers);

        return array_filter($stores, function($store) use ($originalStore) {
            return strcasecmp($store, $originalStore) !== 0;
        });
    }

    /**
     * Buscar en múltiples tiendas
     *
     * @param string $searchQuery Query de búsqueda
     * @param array $stores Lista de tiendas
     * @return array Resultados agrupados por tienda
     */
    private function searchInStores($searchQuery, $stores) {
        $results = [];

        foreach ($stores as $store) {
            if (!isset($this->availableScrapers[$store])) {
                continue;
            }

            error_log("Buscando en $store...");

            try {
                $scraperClass = $this->availableScrapers[$store];
                $scraper = new $scraperClass($searchQuery);
                $scraper->setMaxResults(10);

                $products = $scraper->search();

                $results[$store] = $products;

                error_log("$store: " . count($products) . " productos encontrados");

                // Rate limiting: esperar 1 segundo entre tiendas
                if (count($stores) > 1) {
                    sleep(1);
                }

            } catch (Exception $e) {
                error_log("Error buscando en $store: " . $e->getMessage());
                $results[$store] = [];
            }
        }

        return $results;
    }

    /**
     * Hacer matching de productos
     *
     * @param array $originalProduct Producto original
     * @param array $searchResults Resultados de búsqueda por tienda
     * @return array Matches encontrados con scores
     */
    private function matchProducts($originalProduct, $searchResults) {
        $matches = [];

        foreach ($searchResults as $store => $products) {
            if (empty($products)) {
                continue;
            }

            // Encontrar el mejor match en esta tienda
            $bestMatch = $this->matcher->findBestMatch($originalProduct, $products);

            if ($bestMatch) {
                $matches[] = $bestMatch;
                error_log("Match encontrado en $store: {$bestMatch['name']} (score: {$bestMatch['match_score']}%)");
            }
        }

        // Ordenar por score descendente
        usort($matches, function($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        return $matches;
    }

    /**
     * Añadir matches a un grupo de productos
     *
     * @param int $groupId ID del grupo
     * @param array $matches Productos matched
     * @param array $originalProduct Producto original
     * @param int $userId ID del usuario
     * @return int Número de productos añadidos
     */
    private function addMatchesToGroup($groupId, $matches, $originalProduct, $userId) {
        $added = 0;

        foreach ($matches as $match) {
            try {
                // Primero crear/obtener la URL del producto
                $monitoredUrlId = $this->createOrGetProductUrl($match, $userId);

                if (!$monitoredUrlId) {
                    error_log("No se pudo crear URL para: " . $match['name']);
                    continue;
                }

                // Verificar si ya está en el grupo
                $stmt = $this->db->prepare("
                    SELECT id FROM product_group_items
                    WHERE group_id = ? AND monitored_url_id = ?
                ");
                $stmt->execute([$groupId, $monitoredUrlId]);

                if ($stmt->fetch()) {
                    error_log("Producto ya existe en el grupo: " . $match['name']);
                    continue;
                }

                // Añadir al grupo con metadata de matching
                $stmt = $this->db->prepare("
                    INSERT INTO product_group_items
                    (group_id, monitored_url_id, is_primary, auto_matched, match_score, match_metadata, added_at)
                    VALUES (?, ?, FALSE, TRUE, ?, ?, NOW())
                ");

                $metadata = json_encode($match['match_metadata'] ?? []);

                $stmt->execute([
                    $groupId,
                    $monitoredUrlId,
                    $match['match_score'],
                    $metadata
                ]);

                // Actualizar group_id en monitored_urls
                $stmt = $this->db->prepare("
                    UPDATE monitored_urls
                    SET group_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$groupId, $monitoredUrlId]);

                error_log("Producto añadido al grupo: " . $match['name']);
                $added++;

            } catch (Exception $e) {
                error_log("Error añadiendo match al grupo: " . $e->getMessage());
            }
        }

        return $added;
    }

    /**
     * Crear o obtener ID de URL de producto en monitored_urls
     *
     * @param array $product Información del producto
     * @param int $userId ID del usuario (necesario para crear monitored_url)
     * @return int|null ID de la URL monitoreada
     */
    private function createOrGetProductUrl($product, $userId) {
        // Verificar si la URL ya existe para este usuario
        $stmt = $this->db->prepare("
            SELECT id FROM monitored_urls
            WHERE url = ? AND user_id = ?
        ");
        $stmt->execute([$product['url'], $userId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return $existing['id'];
        }

        // Crear nueva URL monitoreada
        $stmt = $this->db->prepare("
            INSERT INTO monitored_urls
            (user_id, url, product_name, current_price, product_image, target_price, status, created_at)
            VALUES (?, ?, ?, ?, ?, 0, 'active', NOW())
        ");

        $stmt->execute([
            $userId,
            $product['url'],
            $product['name'] ?? '',
            $product['price'] ?? 0,
            $product['image'] ?? ''
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Obtener lista de tiendas disponibles para búsqueda
     *
     * @return array Lista de tiendas disponibles
     */
    public function getAvailableStores() {
        return array_keys($this->availableScrapers);
    }
}
