<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Clase wrapper para ejecutar scrapers con Selenium (Python)
 * Esta clase ejecuta el scraper Python universal que maneja Amazon, PcComponentes y El Corte Inglés
 */
class SeleniumScraper {

    private $db;
    private $urlId;
    private $url;
    private $pythonScript;
    private $headless;

    public function __construct($urlId, $url, $headless = true) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
        $this->headless = $headless;
        $this->pythonScript = __DIR__ . '/../scrapers/selenium_scraper.py';
    }

    /**
     * Extraer información del producto usando Selenium
     */
    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Verificar que existe el script Python
            if (!file_exists($this->pythonScript)) {
                return ['success' => false, 'error' => 'Script de Selenium no encontrado'];
            }

            // Ejecutar script Python con o sin headless
            $headlessFlag = $this->headless ? '' : '--no-headless';
            $command = sprintf(
                'python3 %s %s %s 2>&1',
                escapeshellarg($this->pythonScript),
                escapeshellarg($this->url),
                $headlessFlag
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                error_log("Selenium scraper error: $errorMsg");
                return ['success' => false, 'error' => 'Error ejecutando Selenium: ' . $errorMsg];
            }

            // Parsear JSON de salida
            $jsonOutput = implode('', $output);
            $result = json_decode($jsonOutput, true);

            if (!$result) {
                error_log("Invalid JSON from Selenium: $jsonOutput");
                return ['success' => false, 'error' => 'Respuesta inválida del scraper'];
            }

            // Si el scraper falló
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido en scraping'
                ];
            }

            // Validar que tengamos al menos precio
            if (!isset($result['price']) || $result['price'] <= 0) {
                return ['success' => false, 'error' => 'No se pudo extraer el precio del producto'];
            }

            $extractionTime = round((microtime(true) - $startTime) * 1000);

            // Preparar datos
            $productData = [
                'title' => $result['title'] ?? null,
                'price' => $result['price'],
                'image' => $result['image'] ?? null,
                'discount' => $result['discount'] ?? null,
                'original_price' => $result['original_price'] ?? null,
                'store' => $result['store'] ?? 'unknown'
            ];

            // Guardar en BD
            $this->saveProductInfo($productData);

            return [
                'success' => true,
                'price' => $productData['price'],
                'product_name' => $productData['title'],
                'product_image' => $productData['image'],
                'discount' => $productData['discount'],
                'original_price' => $productData['original_price'],
                'extraction_time_ms' => $extractionTime,
                'method' => 'selenium_' . $productData['store']
            ];

        } catch (Exception $e) {
            error_log("SeleniumScraper Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Guardar información del producto en BD
     */
    private function saveProductInfo($data) {
        // Actualizar información del producto
        $stmt = $this->db->prepare("
            UPDATE monitored_urls
            SET
                product_name = IF(? IS NOT NULL AND ? != '', ?, product_name),
                current_price = ?,
                last_checked = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['title'],
            $data['title'],
            $data['title'],
            $data['price'],
            $this->urlId
        ]);

        // Guardar en historial
        $stmt = $this->db->prepare("
            INSERT INTO price_history (url_id, price, scraping_method)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$this->urlId, $data['price'], 'selenium_' . $data['store']]);

        // Guardar imagen y descuento
        try {
            $stmt = $this->db->prepare("
                UPDATE monitored_urls
                SET
                    product_image = ?,
                    product_discount = ?,
                    product_original_price = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $data['image'],
                $data['discount'],
                $data['original_price'],
                $this->urlId
            ]);
        } catch (PDOException $e) {
            error_log("Could not save extra product data: " . $e->getMessage());
        }
    }

    /**
     * Verificar si una URL es compatible con Selenium scraper
     * (Amazon, PcComponentes, El Corte Inglés)
     */
    public static function isSupportedURL($url) {
        $supportedDomains = [
            'amazon.es',
            'amazon.com',
            'pccomponentes.com',
            'elcorteingles.es'
        ];

        foreach ($supportedDomains as $domain) {
            if (strpos($url, $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtener tienda desde URL
     */
    public static function getStore($url) {
        if (strpos($url, 'amazon') !== false) return 'amazon';
        if (strpos($url, 'pccomponentes') !== false) return 'pccomponentes';
        if (strpos($url, 'elcorteingles') !== false) return 'elcorteingles';
        return 'unknown';
    }
}
?>
