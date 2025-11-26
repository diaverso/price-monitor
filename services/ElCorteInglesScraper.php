<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class ElCorteInglesScraper {

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
     * Extraer información completa del producto de El Corte Inglés
     */
    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Obtener el HTML de la página
            $this->html = $this->fetchHTML($this->url);
            if (!$this->html) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL de El Corte Inglés'];
            }

            // Extraer toda la información
            $productData = [
                'title' => $this->extractTitle(),
                'price' => $this->extractPrice(),
                'image' => $this->extractImage(),
                'discount' => $this->extractDiscount(),
                'original_price' => null
            ];

            // Validar que al menos tengamos el precio
            if (!$productData['price']) {
                return ['success' => false, 'error' => 'No se pudo extraer el precio del producto. Verifica la URL o la estructura de la página.'];
            }

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
                'original_price' => $productData['original_price'],
                'extraction_time_ms' => $extractionTime,
                'method' => 'elcorteingles_xpath'
            ];

        } catch (Exception $e) {
            error_log("ElCorteInglesScraper Error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener HTML de la página de El Corte Inglés
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
            CURLOPT_REFERER => 'https://www.elcorteingles.es/',
            CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/eci_' . md5($this->url) . '.txt',
            CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/eci_' . md5($this->url) . '.txt',
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
                'Upgrade-Insecure-Requests: 1'
            ]
        ];

        curl_setopt_array($ch, $options);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("cURL Error for El Corte Inglés: $error");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode for El Corte Inglés URL: $url");
            return false;
        }

        return $html ? $html : false;
    }

    /**
     * Extraer título del producto
     * XPath: //*[@id="product_detail_title"]
     */
    private function extractTitle() {
        return $this->extractByXPath('//*[@id="product_detail_title"]');
    }

    /**
     * Extraer precio del producto
     * XPath: /html/body/div[2]/main/div[3]/div[3]/div[2]/main/section[2]/div/div[3]/div[1]/section/div/div/span[1]
     */
    private function extractPrice() {
        // Clase específica: price-sale
        $pricePaths = [
            '//span[contains(@class, "price-sale")]',
            '//span[contains(@class, "price")]',
            '/html/body/div[2]/main/div[3]/div[3]/div[2]/main/section[2]/div/div[3]/div[1]/section/div/div/span[1]'
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
     * XPath: /html/body/div[2]/main/div[3]/div[3]/div[2]/main/section[1]/div/div/picture[1]/img
     * Busca el primer .jpg o .png
     */
    private function extractImage() {
        // Buscar img dentro de picture usando regex
        if (preg_match('/<picture[^>]*>.*?<img[^>]+>/is', $this->html, $pictureMatch)) {
            $imgTag = $pictureMatch[0];

            // Prioridad 1: src
            if (preg_match('/src=["\']([^"\']+\.(?:jpg|png)[^"\']*)["\']/', $imgTag, $match)) {
                return html_entity_decode($match[1]);
            }

            // Prioridad 2: data-src
            if (preg_match('/data-src=["\']([^"\']+\.(?:jpg|png)[^"\']*)["\']/', $imgTag, $match)) {
                return html_entity_decode($match[1]);
            }
        }

        // Fallback: buscar cualquier img con .jpg o .png
        if (preg_match('/<img[^>]+src=["\']([^"\']+\.(?:jpg|png)[^"\']*)["\'][^>]*>/i', $this->html, $match)) {
            return html_entity_decode($match[1]);
        }

        return null;
    }

    /**
     * Extraer porcentaje de descuento
     * XPath: /html/body/div[2]/main/div[3]/div[3]/div[2]/main/section[2]/div/div[3]/div[1]/section/div/div/span[3]
     * Solo si tiene descuento
     */
    private function extractDiscount() {
        $discountPaths = [
            '//span[contains(@class, "price-discount")]',
            '//span[contains(@class, "discount")]',
            '/html/body/div[2]/main/div[3]/div[3]/div[2]/main/section[2]/div/div[3]/div[1]/section/div/div/span[3]'
        ];

        foreach ($discountPaths as $path) {
            $discountText = $this->extractByXPath($path);
            if ($discountText) {
                // Extraer número del descuento (ej: "-15%" -> 15, "15% dto" -> 15)
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
        $stmt->execute([$this->urlId, $data['price'], 'elcorteingles']);

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
            error_log("Could not save extra El Corte Inglés data: " . $e->getMessage());
        }
    }

    /**
     * Método estático para verificar si una URL es de El Corte Inglés
     */
    public static function isElCorteInglesURL($url) {
        return strpos($url, 'elcorteingles.es') !== false;
    }
}
?>
