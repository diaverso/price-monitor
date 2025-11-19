<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

/**
 * Clase wrapper para ejecutar Ulixee Hero (Node.js)
 * Hero es un navegador diseñado específicamente para scraping que evita detección de Akamai
 */
class HeroScraper {

    private $db;
    private $urlId;
    private $url;
    private $heroScript;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
        $this->heroScript = __DIR__ . '/../scrapers/hero_scraper.js';
    }

    /**
     * Extraer información del producto usando Ulixee Hero
     */
    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Verificar que existe el script Hero
            if (!file_exists($this->heroScript)) {
                return ['success' => false, 'error' => 'Script de Hero no encontrado'];
            }

            // Ejecutar script Node.js con Hero
            // Configurar variable de entorno para que Hero encuentre Chrome
            $command = sprintf(
                'CHROME_139_BIN=/var/www/.cache/ulixee/chrome/139.0.7258.154/chrome node %s %s 2>&1',
                escapeshellarg($this->heroScript),
                escapeshellarg($this->url)
            );

            error_log("HeroScraper: Ejecutando comando: $command");

            // Aumentar tiempo límite para scrapers lentos como IKEA
            set_time_limit(180); // 3 minutos

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                error_log("Hero scraper error (code $returnCode): $errorMsg");
                return ['success' => false, 'error' => 'Error ejecutando Hero: ' . $errorMsg];
            }

            // Parsear JSON de salida
            // Hero envía logs a stderr + JSON final a stdout
            $result = null;

            // Método 1: Intentar parsear cada línea desde el final
            for ($i = count($output) - 1; $i >= 0; $i--) {
                $line = trim($output[$i]);
                if (empty($line) || $line[0] !== '{') continue;

                $decoded = json_decode($line, true);
                if ($decoded !== null && isset($decoded['success'])) {
                    $result = $decoded;
                    break;
                }
            }

            // Método 2: Si falla, guardar output completo para debugging
            if (!$result) {
                $allOutput = implode("\n", $output);
                error_log("HeroScraper - No se encontró JSON válido. Output completo:\n" . $allOutput);
                return [
                    'success' => false,
                    'error' => 'No se pudo parsear respuesta de Hero. Ver logs: tail -f /var/log/apache2/error.log | grep HeroScraper'
                ];
            }

            // Si el scraper falló
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido en scraping con Hero'
                ];
            }

            // Validar que tengamos al menos precio
            if (!isset($result['price']) || $result['price'] === null || $result['price'] <= 0) {
                error_log("HeroScraper: Precio inválido para URL " . $this->url . ". Result: " . json_encode($result));
                return ['success' => false, 'error' => 'No se pudo extraer el precio del producto (precio: ' . ($result['price'] ?? 'null') . ')'];
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
                'method' => 'hero_' . $productData['store']
            ];

        } catch (Exception $e) {
            error_log("HeroScraper Exception: " . $e->getMessage());
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
        $stmt->execute([$this->urlId, $data['price'], 'hero_' . $data['store']]);

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
     * Verificar si una URL es compatible con Hero scraper
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
