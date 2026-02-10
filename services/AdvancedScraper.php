<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Advanced Scraper - Wrapper PHP para scrapers Python avanzados
 * Integra Scrapling, Crawlee y Camoufox como cadena de fallback
 *
 * Estrategias disponibles:
 * - scrapling_fetcher: HTTP rápido con TLS fingerprint de Chrome
 * - scrapling_stealthy: Bypass Cloudflare Turnstile + anti-bot avanzado
 * - camoufox: Anti-detección a nivel C++ de Firefox
 * - crawlee_playwright: PlaywrightCrawler con session pool
 * - null: Prueba todas las estrategias en orden (fallback automático)
 */
class AdvancedScraper {

    private $db;
    private $urlId;
    private $url;
    private $pythonScript;
    private $strategy;

    /**
     * @param int $urlId ID de la URL monitoreada
     * @param string $url URL del producto
     * @param string|null $strategy Estrategia específica o null para fallback automático
     */
    public function __construct($urlId, $url, $strategy = null) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
        $this->strategy = $strategy;
        $this->pythonScript = __DIR__ . '/../scrapers/advanced_scraper.py';
    }

    /**
     * Extraer información del producto usando scrapers Python avanzados
     */
    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Verificar que existe el script Python
            if (!file_exists($this->pythonScript)) {
                return ['success' => false, 'error' => 'Script Python avanzado no encontrado: ' . $this->pythonScript];
            }

            // Construir comando: python3 advanced_scraper.py <url> [strategy]
            $strategyArg = $this->strategy ? escapeshellarg($this->strategy) : '';
            $command = sprintf(
                'HOME=/var/www python3 %s %s %s 2>&1',
                escapeshellarg($this->pythonScript),
                escapeshellarg($this->url),
                $strategyArg
            );

            error_log("[AdvancedScraper] Ejecutando: $command");

            // 4 minutos para permitir múltiples fallbacks internos
            set_time_limit(240);

            exec($command, $output, $returnCode);

            // Parsear JSON de salida (misma lógica que HeroScraper)
            // El script envía logs a stderr + JSON final a stdout
            $result = null;

            // Buscar JSON válido desde el final del output
            for ($i = count($output) - 1; $i >= 0; $i--) {
                $line = trim($output[$i]);
                if (empty($line) || $line[0] !== '{') continue;

                $decoded = json_decode($line, true);
                if ($decoded !== null && isset($decoded['success'])) {
                    $result = $decoded;
                    break;
                }
            }

            // Si no encontramos JSON válido
            if (!$result) {
                $allOutput = implode("\n", $output);
                error_log("[AdvancedScraper] No se encontró JSON válido. Output:\n" . substr($allOutput, 0, 2000));
                return [
                    'success' => false,
                    'error' => 'No se pudo parsear respuesta del scraper avanzado'
                ];
            }

            // Si el scraper reportó fallo
            if (!$result['success']) {
                error_log("[AdvancedScraper] Scraper falló: " . ($result['error'] ?? 'Error desconocido'));
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido en scraping avanzado'
                ];
            }

            // Validar precio
            if (!isset($result['price']) || $result['price'] === null || $result['price'] <= 0) {
                error_log("[AdvancedScraper] Precio inválido para URL: " . $this->url);
                return ['success' => false, 'error' => 'Precio inválido extraído'];
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
            $this->saveProductInfo($productData, $result['method'] ?? 'advanced');

            return [
                'success' => true,
                'price' => $productData['price'],
                'product_name' => $productData['title'],
                'product_image' => $productData['image'],
                'discount' => $productData['discount'],
                'original_price' => $productData['original_price'],
                'extraction_time_ms' => $extractionTime,
                'method' => $result['method'] ?? 'advanced_' . $productData['store']
            ];

        } catch (Exception $e) {
            error_log("[AdvancedScraper] Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Guardar información del producto en BD
     * (Misma lógica que HeroScraper::saveProductInfo)
     */
    private function saveProductInfo($data, $method = 'advanced') {
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
        $stmt->execute([$this->urlId, $data['price'], $method]);

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
            error_log("[AdvancedScraper] No se pudo guardar datos extra: " . $e->getMessage());
        }
    }

    /**
     * Este scraper soporta cualquier URL (es un fallback universal)
     */
    public static function isSupportedURL($url) {
        return true;
    }
}
?>
