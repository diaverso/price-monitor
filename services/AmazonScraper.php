<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class AmazonScraper {

    private $db;
    private $urlId;
    private $url;
    private $html;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
    }

    /**
     * Extraer información completa del producto de Amazon.es
     */
    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Obtener el HTML de la página
            $this->html = $this->fetchHTML($this->url);
            if (!$this->html) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL de Amazon'];
            }

            // Extraer toda la información
            $productData = [
                'title' => $this->extractTitle(),
                'price' => $this->extractPrice(),
                'image' => $this->extractImage(),
                'discount' => $this->extractDiscount(),
                'discount_countdown' => $this->extractDiscountCountdown(),
                'original_price' => null
            ];

            // Calcular precio original si hay descuento
            if ($productData['discount'] && $productData['price']) {
                $productData['original_price'] = round(
                    $productData['price'] / (1 - ($productData['discount'] / 100)),
                    2
                );
            }

            $extractionTime = round((microtime(true) - $startTime) * 1000);

            // Guardar información en BD
            $this->saveProductInfo($productData);

            return [
                'success' => true,
                'price' => $productData['price'],
                'product_name' => $productData['title'],
                'product_image' => $productData['image'],
                'discount' => $productData['discount'],
                'discount_countdown' => $productData['discount_countdown'],
                'original_price' => $productData['original_price'],
                'extraction_time_ms' => $extractionTime,
                'method' => 'amazon_xpath'
            ];

        } catch (Exception $e) {
            error_log("AmazonScraper Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener HTML de la página de Amazon
     */
    private function fetchHTML($url) {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_REFERER => 'https://www.amazon.es/',
            CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/amazon_' . md5($this->url) . '.txt',
            CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/amazon_' . md5($this->url) . '.txt',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: max-age=0',
                'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'Sec-Ch-Ua-Mobile: ?0',
                'Sec-Ch-Ua-Platform: "Windows"',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Sec-Fetch-User: ?1',
                'Upgrade-Insecure-Requests: 1',
                'Device-Memory: 8',
                'Downlink: 10',
                'Ect: 4g',
                'Rtt: 50',
                'Viewport-Width: 1920'
            ]
        ];

        curl_setopt_array($ch, $options);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("cURL Error for Amazon: $error");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode for Amazon URL: $url");
            return false;
        }

        return $html ? $html : false;
    }

    /**
     * Extraer título del producto
     * XPath: //*[@id="productTitle"]
     */
    private function extractTitle() {
        return $this->extractByXPath('//*[@id="productTitle"]');
    }

    /**
     * Extraer precio del producto
     * XPath: /html/body/div[1]/div[1]/div/div[4]/div[4]/div[12]/div/div/div[1]/div/div[3]/div[1]/span[2]/span[2]/span[1]
     * También intenta selectores alternativos comunes de Amazon
     */
    private function extractPrice() {
        // Lista de XPaths posibles para el precio en Amazon
        $pricePaths = [
            '/html/body/div[1]/div[1]/div/div[4]/div[4]/div[12]/div/div/div[1]/div/div[3]/div[1]/span[2]/span[2]/span[1]',
            '/html/body/div[1]/div[1]/div[2]/div[4]/div[4]/div[12]/div/div/div[1]/div/div[3]/div[1]/span[2]/span[2]/span[1]',
            '//span[@class="a-price-whole"]',
            '//span[contains(@class, "a-price-whole")]',
            '//*[@id="priceblock_ourprice"]',
            '//*[@id="priceblock_dealprice"]',
            '//span[@id="price_inside_buybox"]'
        ];

        foreach ($pricePaths as $path) {
            $priceText = $this->extractByXPath($path);
            if ($priceText) {
                // Limpiar precio: quitar símbolos, espacios, y convertir coma a punto
                $priceText = preg_replace('/[^0-9,.]/', '', $priceText);
                $priceText = str_replace(',', '.', $priceText);

                $price = floatval($priceText);
                if ($price > 0) {
                    return $price;
                }
            }
        }

        return null;
    }

    /**
     * Extraer imagen del producto
     * Busca el primer src dentro de imgTagWrapperId o landingImage que contenga .jpg o .png
     * Usa regex en lugar de DOM para evitar dependencias
     */
    private function extractImage() {
        // Buscar img con id="landingImage" usando regex
        if (preg_match('/<img[^>]+id=["\']landingImage["\'][^>]*>/i', $this->html, $imgMatch)) {
            $imgTag = $imgMatch[0];

            // Prioridad 1: data-old-hires (mejor calidad)
            if (preg_match('/data-old-hires=["\']([^"\']+\.(?:jpg|png)[^"\']*)["\']/', $imgTag, $match)) {
                return html_entity_decode($match[1]);
            }

            // Prioridad 2: src
            if (preg_match('/src=["\']([^"\']+\.(?:jpg|png)[^"\']*)["\']/', $imgTag, $match)) {
                return html_entity_decode($match[1]);
            }

            // Prioridad 3: data-a-dynamic-image (JSON con URLs)
            if (preg_match('/data-a-dynamic-image=["\']({[^"\']+})["\']/', $imgTag, $match)) {
                $jsonData = html_entity_decode($match[1]);
                $imageData = json_decode($jsonData, true);
                if (is_array($imageData) && count($imageData) > 0) {
                    // Obtener la imagen de mayor resolución
                    $maxSize = 0;
                    $bestImage = null;
                    foreach ($imageData as $url => $dimensions) {
                        if (is_array($dimensions) && count($dimensions) >= 2) {
                            $size = $dimensions[0] * $dimensions[1];
                            if ($size > $maxSize) {
                                $maxSize = $size;
                                $bestImage = $url;
                            }
                        }
                    }
                    if ($bestImage) {
                        return $bestImage;
                    }
                }
            }
        }

        // Fallback: buscar cualquier imagen dentro de imgTagWrapperId
        if (preg_match('/id=["\']imgTagWrapperId["\'][^>]*>.*?<img[^>]+>/is', $this->html, $wrapperMatch)) {
            if (preg_match('/src=["\']([^"\']+\.(?:jpg|png)[^"\']*)["\']/', $wrapperMatch[0], $match)) {
                return html_entity_decode($match[1]);
            }
        }

        return null;
    }

    /**
     * Extraer porcentaje de descuento
     * Busca elementos con clases específicas de descuento de Amazon
     */
    private function extractDiscount() {
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        $xpath = new DOMXPath($dom);

        // Lista de XPaths para buscar descuentos
        $discountPaths = [
            // Clase específica de descuento
            '//span[contains(@class, "savingsPercentage")]',
            '//span[contains(@class, "savingPriceOverride")]',
            // XPaths absolutos
            '/html/body/div[1]/div[1]/div[2]/div[4]/div[4]/div[12]/div/div/div[3]/div[1]/span[2]',
            '/html/body/div[1]/div[1]/div/div[4]/div[4]/div[12]/div/div/div[3]/div[1]/span[2]',
            // Cualquier span que contenga porcentaje
            '//span[contains(text(), "%") and contains(text(), "-")]'
        ];

        foreach ($discountPaths as $path) {
            $nodes = $xpath->query($path);
            if ($nodes->length > 0) {
                $discountText = trim($nodes->item(0)->textContent);
                // Extraer número del descuento (ej: "-5%" -> 5, "-15 %" -> 15)
                preg_match('/(\d+)/', $discountText, $matches);
                if (isset($matches[1])) {
                    $discount = floatval($matches[1]);
                    if ($discount > 0) {
                        return $discount;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Extraer tiempo de cuenta atrás del descuento
     * XPath: //*[@id="dealBadgeSupportingText"]
     * Formato: <span data-target-time="2025-10-19T21:59:59Z">11:31:05</span>
     */
    private function extractDiscountCountdown() {
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        $xpath = new DOMXPath($dom);

        // Buscar el span con la cuenta atrás
        $countdownNodes = $xpath->query('//*[@id="dealBadgeSupportingText"]//span[@data-target-time]');

        if ($countdownNodes->length > 0) {
            $countdownNode = $countdownNodes->item(0);
            $targetTime = $countdownNode->getAttribute('data-target-time');

            if ($targetTime) {
                return [
                    'target_time' => $targetTime,
                    'timestamp' => strtotime($targetTime)
                ];
            }
        }

        return null;
    }

    /**
     * Extraer contenido usando XPath
     */
    private function extractByXPath($xpathQuery) {
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        $xpath = new DOMXPath($dom);

        $nodes = $xpath->query($xpathQuery);

        if ($nodes->length > 0) {
            return trim($nodes->item(0)->textContent);
        }

        return null;
    }

    /**
     * Guardar información del producto en BD
     */
    private function saveProductInfo($data) {
        // Preparar countdown data como JSON
        $countdownJson = $data['discount_countdown'] ? json_encode($data['discount_countdown']) : null;

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
        $stmt->execute([$this->urlId, $data['price'], 'amazon']);

        // Si hay columnas adicionales en monitored_urls, guardar imagen y descuento
        try {
            $stmt = $this->db->prepare("
                UPDATE monitored_urls
                SET
                    product_image = ?,
                    product_discount = ?,
                    product_original_price = ?,
                    last_scraped_data = ?
                WHERE id = ?
            ");

            $scrapedData = json_encode([
                'discount_countdown' => $data['discount_countdown'],
                'scraped_at' => date('Y-m-d H:i:s')
            ]);

            $stmt->execute([
                $data['image'],
                $data['discount'],
                $data['original_price'],
                $scrapedData,
                $this->urlId
            ]);
        } catch (PDOException $e) {
            // Si las columnas no existen, ignorar (para compatibilidad)
            error_log("Could not save extra Amazon data: " . $e->getMessage());
        }
    }

    /**
     * Método estático para verificar si una URL es de Amazon
     */
    public static function isAmazonURL($url) {
        return strpos($url, 'amazon.es') !== false ||
               strpos($url, 'amazon.com') !== false;
    }
}
?>
