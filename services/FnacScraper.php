<?php
/**
 * Fnac Scraper - Scraper para Fnac España
 * Usa cURL con headers específicos para evitar bloqueos
 */

class FnacScraper {
    private $urlId;
    private $url;
    private $db;

    /**
     * Verificar si la URL es de Fnac
     */
    public static function isFnacURL($url) {
        return strpos($url, 'fnac.es') !== false;
    }

    public function __construct($urlId, $url) {
        $this->urlId = $urlId;
        $this->url = $url;
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Extraer información del producto
     */
    public function extractProductInfo() {
        try {
            // Obtener HTML con cURL
            $html = $this->fetchPage();

            if (empty($html)) {
                return [
                    'success' => false,
                    'error' => 'No se pudo cargar la página de Fnac'
                ];
            }

            // Parsear HTML
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);

            // Extraer datos
            $title = $this->extractTitle($xpath);
            $price = $this->extractPrice($xpath);
            $image = $this->extractImage($xpath);
            $originalPrice = $this->extractOriginalPrice($xpath);
            $discount = $this->extractDiscount($xpath);

            // Validar datos mínimos
            if (empty($title) || $price === null || $price <= 0) {
                return [
                    'success' => false,
                    'error' => 'No se pudieron extraer los datos del producto'
                ];
            }

            // Guardar en base de datos
            $this->saveToDatabase($title, $price, $image, $originalPrice, $discount);

            return [
                'success' => true,
                'data' => [
                    'title' => $title,
                    'price' => $price,
                    'image' => $image,
                    'original_price' => $originalPrice,
                    'discount' => $discount
                ]
            ];

        } catch (Exception $e) {
            error_log("[FnacScraper] Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtener HTML de la página con cURL
     */
    private function fetchPage() {
        $ch = curl_init();

        // Generar ID de sesión realista
        $sessionId = bin2hex(random_bytes(16));

        // Headers más completos para evitar detección (Chrome real)
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
            'Accept-Encoding: gzip, deflate, br',
            'DNT: 1',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
            'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
            'Cache-Control: max-age=0',
            'Referer: https://www.fnac.es/'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEJAR => '/tmp/fnac_cookies_' . $this->urlId . '.txt',
            CURLOPT_COOKIEFILE => '/tmp/fnac_cookies_' . $this->urlId . '.txt',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            CURLOPT_AUTOREFERER => true
        ]);

        // Primer intento
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $error");
        }

        // Si 403, intentar con página principal primero
        if ($httpCode == 403) {
            error_log("[FnacScraper] 403 recibido, cargando página principal");

            curl_setopt($ch, CURLOPT_URL, 'https://www.fnac.es/');
            curl_exec($ch);
            sleep(2);

            curl_setopt($ch, CURLOPT_URL, $this->url);
            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        }

        curl_close($ch);

        if ($httpCode != 200) {
            throw new Exception("HTTP error: $httpCode");
        }

        return $html;
    }

    /**
     * Extraer título del producto
     */
    private function extractTitle($xpath) {
        // Selector principal: h1.f-productHeader__heading
        $nodes = $xpath->query("//h1[contains(@class, 'f-productHeader__heading')]");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        // Alternativa: cualquier h1 con data-automation-id
        $nodes = $xpath->query("//h1[@data-automation-id='product-title-label']");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        // Última alternativa: primer h1 de la página
        $nodes = $xpath->query("//h1");
        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    /**
     * Extraer precio actual
     */
    private function extractPrice($xpath) {
        // Selector principal: span.f-faPriceBox__price.userPrice
        $nodes = $xpath->query("//span[contains(@class, 'f-faPriceBox__price') and contains(@class, 'userPrice')]");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->textContent);
            return $this->parsePrice($priceText);
        }

        // Alternativa: cualquier precio no tachado
        $nodes = $xpath->query("//span[contains(@class, 'price') and not(contains(@class, 'striked'))]");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->textContent);
            return $this->parsePrice($priceText);
        }

        return null;
    }

    /**
     * Extraer precio original (tachado)
     */
    private function extractOriginalPrice($xpath) {
        $nodes = $xpath->query("//strong[contains(@class, 'f-faPriceBox__price--striked')]");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->textContent);
            return $this->parsePrice($priceText);
        }

        return null;
    }

    /**
     * Extraer imagen del producto
     */
    private function extractImage($xpath) {
        // Selector principal: img.f-productMedias__viewItem--main
        $nodes = $xpath->query("//img[contains(@class, 'f-productMedias__viewItem--main')]");
        if ($nodes->length > 0) {
            $src = $nodes->item(0)->getAttribute('src');
            if (!empty($src)) {
                return $this->normalizeImageUrl($src);
            }
        }

        // Alternativa: primera imagen del producto
        $nodes = $xpath->query("//div[contains(@class, 'productMedias')]//img[@src]");
        if ($nodes->length > 0) {
            $src = $nodes->item(0)->getAttribute('src');
            if (!empty($src)) {
                return $this->normalizeImageUrl($src);
            }
        }

        return null;
    }

    /**
     * Extraer descuento
     */
    private function extractDiscount($xpath) {
        // Selector principal: span.f-faPriceBox__stimuliOpcLabel
        $nodes = $xpath->query("//span[contains(@class, 'f-faPriceBox__stimuliOpcLabel')]");
        if ($nodes->length > 0) {
            $discountText = trim($nodes->item(0)->textContent);
            // Extraer número (ej: "-13%" -> 13)
            if (preg_match('/-?(\d+)/', $discountText, $matches)) {
                return (int)$matches[1];
            }
        }

        return null;
    }

    /**
     * Parsear precio de texto a float
     */
    private function parsePrice($priceText) {
        if (empty($priceText)) {
            return null;
        }

        // Remover símbolos y espacios
        $priceText = preg_replace('/[^\d,.]/', '', $priceText);
        $priceText = str_replace(',', '.', $priceText);

        $price = floatval($priceText);
        return ($price > 0) ? $price : null;
    }

    /**
     * Normalizar URL de imagen
     */
    private function normalizeImageUrl($src) {
        if (strpos($src, 'http') === 0) {
            return $src;
        }
        if (strpos($src, '//') === 0) {
            return 'https:' . $src;
        }
        return 'https://www.fnac.es' . $src;
    }

    /**
     * Guardar datos en la base de datos
     */
    private function saveToDatabase($title, $price, $image, $originalPrice, $discount) {
        try {
            // Actualizar información del producto
            $stmt = $this->db->prepare("
                UPDATE monitored_urls
                SET product_name = ?,
                    current_price = ?,
                    product_image = ?,
                    last_checked = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $price, $image, $this->urlId]);

            // Insertar historial de precio
            $stmt = $this->db->prepare("
                INSERT INTO price_history (url_id, price, checked_at)
                VALUES (?, ?, NOW())
            ");
            $stmt->execute([$this->urlId, $price]);

            error_log("[FnacScraper] Datos guardados - ID: {$this->urlId}, Precio: {$price}€");

        } catch (PDOException $e) {
            error_log("[FnacScraper] Error BD: " . $e->getMessage());
            throw new Exception("Error guardando en base de datos");
        }
    }
}
?>
