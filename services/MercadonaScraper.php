<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class MercadonaScraper {
    private $db;
    private $urlId;
    private $url;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
    }

    public static function isMercadonaURL($url) {
        return strpos($url, 'tienda.mercadona.es') !== false ||
               strpos($url, 'mercadona.es') !== false;
    }

    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Extraer el ID del producto de la URL
            if (preg_match('/\/product\/(\d+)\//', $this->url, $matches)) {
                $productId = $matches[1];
            } else {
                return ['success' => false, 'error' => 'No se pudo extraer el ID del producto'];
            }

            // Llamar a la API de Mercadona
            $abiUrl = "https://tienda.mercadona.es/api/products/{$productId}/";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $abiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return ['success' => false, 'error' => "API devolvió código: {$httpCode}"];
            }

            $data = json_decode($response, true);

            if (!$data) {
                return ['success' => false, 'error' => 'No se pudo parsear la respuesta'];
            }

            // Extraer datos
            $title = $data['display_name'] ?? $data['details']['description'] ?? null;
            $price = isset($data['price_instructions']['unit_price']) ?
                     floatval(str_replace(',', '.', $data['price_instructions']['unit_price'])) : null;
            $originalPrice = null;
            $discount = null;

            // Precio anterior
            if (isset($data['price_instructions']['previous_unit_price'])) {
                $prevPrice = trim($data['price_instructions']['previous_unit_price']);
                if (!empty($prevPrice)) {
                    $originalPrice = floatval(str_replace(',', '.', $prevPrice));

                    if ($originalPrice > 0 && $price > 0 && $originalPrice > $price) {
                        $discount = round((($originalPrice - $price) / $originalPrice) * 100);
                    }
                }
            }

            // Imagen
            $image = $data['thumbnail'] ?? ($data['photos'][0]['regular'] ?? null);

            if (!$price || $price <= 0) {
                return ['success' => false, 'error' => 'No se pudo extraer el precio'];
            }

            if (!$title) {
                return ['success' => false, 'error' => 'No se pudo extraer el título'];
            }

            $extractionTime = round((microtime(true) - $startTime) * 1000);

            // Actualizar base de datos
            $stmt = $this->db->prepare("
                UPDATE monitored_urls
                SET current_price = ?,
                    product_name = ?,
                    product_image = ?,
                    product_discount = ?,
                    product_original_price = ?,
                    last_checked = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$price, $title, $image, $discount, $originalPrice, $this->urlId]);

            // Insertar en historial
            $stmt = $this->db->prepare("INSERT INTO price_history (url_id, price) VALUES (?, ?)");
            $stmt->execute([$this->urlId, $price]);

            error_log("MercadonaScraper: Éxito en {$extractionTime}ms");

            return [
                'success' => true,
                'title' => $title,
                'price' => $price,
                'original_price' => $originalPrice,
                'discount' => $discount,
                'image' => $image,
                'extraction_time' => $extractionTime
            ];

        } catch (Exception $e) {
            error_log("MercadonaScraper Error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Error: ' . $e->getMessage()];
        }
    }
}
?>
