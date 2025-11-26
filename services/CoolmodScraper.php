<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class CoolmodScraper {

    private $db;
    private $urlId;
    private $url;
    private $html;
    private $proxies = [];
    private $useProxy = false;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;

        // Cargar configuración de proxys
        $this->loadProxyConfig();
    }

    /**
     * Cargar configuración de proxys desde .env
     */
    private function loadProxyConfig() {
        $proxyList = Config::get('PROXIES', '');
        $this->useProxy = Config::get('PROXY_ROTATION', 'false') === 'true';

        if ($this->useProxy && !empty($proxyList)) {
            $this->proxies = array_map('trim', explode(',', $proxyList));
        }
    }

    /**
     * Obtener un proxy aleatorio de la lista
     */
    private function getRandomProxy() {
        if (empty($this->proxies)) {
            return null;
        }
        return $this->proxies[array_rand($this->proxies)];
    }

    /**
     * Extraer información completa del producto de Coolmod
     */
    public function extractProductInfo() {
        $startTime = microtime(true);

        try {
            // Obtener la página del producto
            $this->html = $this->fetchHTML($this->url);
            if (!$this->html) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL'];
            }

            // Extraer toda la información
            $productData = [
                'title' => $this->extractTitle(),
                'price' => $this->extractPrice(),
                'image' => $this->extractImage(),
                'discount' => $this->extractDiscount(),
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
                'original_price' => $productData['original_price'],
                'extraction_time_ms' => $extractionTime,
                'method' => 'coolmod_xpath'
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener HTML de la página
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
            CURLOPT_REFERER => 'https://www.coolmod.com/',
            CURLOPT_COOKIEJAR => sys_get_temp_dir() . '/coolmod_' . md5($this->url) . '.txt',
            CURLOPT_COOKIEFILE => sys_get_temp_dir() . '/coolmod_' . md5($this->url) . '.txt',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7',
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

        // Agregar proxy si está habilitado
        if ($this->useProxy) {
            $proxy = $this->getRandomProxy();
            if ($proxy) {
                $options[CURLOPT_PROXY] = $proxy;
                error_log("Usando proxy: $proxy");
            }
        }

        curl_setopt_array($ch, $options);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("cURL Error: $error");
            return false;
        }

        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode for URL: $url");
            return false;
        }

        return $html ? $html : false;
    }

    /**
     * Extraer título del producto
     * XPath: /html/body/main/section[1]/div[3]/div[2]/div[2]/h1
     */
    private function extractTitle() {
        return $this->extractByXPath('/html/body/main/section[1]/div[3]/div[2]/div[2]/h1');
    }

    /**
     * Extraer precio del producto
     * XPath: /html/body/main/section[1]/div[3]/div[2]/div[4]/div[1]/div/span
     */
    private function extractPrice() {
        $priceText = $this->extractByXPath('/html/body/main/section[1]/div[3]/div[2]/div[4]/div[1]/div/span');

        if (!$priceText) {
            return null;
        }

        // Limpiar y convertir a número
        $priceText = preg_replace('/[^0-9,.]/', '', $priceText);
        $priceText = str_replace(',', '.', $priceText);

        return floatval($priceText);
    }

    /**
     * Extraer imagen del producto
     * XPath: /html/body/main/section[1]/div[3]/div[1]/div/div[1]/div/div/div[1]/figure/img
     */
    private function extractImage() {
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        $xpath = new DOMXPath($dom);

        // Intentar varios XPaths para la imagen
        $imagePaths = [
            '/html/body/main/section[1]/div[3]/div[1]/div/div[1]/div/div/div[1]/figure/img',
            '//figure//img[contains(@src, "/images/product/")]',
            '//img[contains(@src, "/images/product/large/")]',
            '//meta[@property="og:image"]'
        ];

        foreach ($imagePaths as $imagePath) {
            $nodes = $xpath->query($imagePath);

            if ($nodes->length > 0) {
                $imgNode = $nodes->item(0);
                $src = null;

                // Si es meta tag og:image
                if ($imgNode->nodeName === 'meta') {
                    $src = $imgNode->getAttribute('content');
                } else {
                    // Intentar obtener src, data-src, data-lazy-src
                    $src = $imgNode->getAttribute('src');
                    if (!$src || strpos($src, 'data:image') === 0) {
                        $src = $imgNode->getAttribute('data-src');
                    }
                    if (!$src) {
                        $src = $imgNode->getAttribute('data-lazy-src');
                    }
                }

                // Validar que sea una URL de imagen válida (.jpg, .png, .webp)
                if ($src && (strpos($src, '.jpg') !== false || strpos($src, '.png') !== false || strpos($src, '.webp') !== false)) {
                    // Si es relativa, convertir a absoluta
                    if (strpos($src, 'http') !== 0) {
                        if (strpos($src, '//') === 0) {
                            $src = 'https:' . $src;
                        } else {
                            $src = 'https://www.coolmod.com' . $src;
                        }
                    }
                    return $src;
                }
            }
        }

        return null;
    }

    /**
     * Extraer descuento
     * XPath: /html/body/main/section[1]/div[3]/div[2]/div[1]/div/div/div
     */
    private function extractDiscount() {
        $discountText = $this->extractByXPath('/html/body/main/section[1]/div[3]/div[2]/div[1]/div/div/div');

        if (!$discountText) {
            return null;
        }

        // Extraer número del descuento (ej: "-15%" -> 15)
        preg_match('/(\d+)/', $discountText, $matches);

        return isset($matches[1]) ? floatval($matches[1]) : null;
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
                product_image = ?,
                product_discount = ?,
                product_original_price = ?,
                last_scraped_data = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $jsonData = json_encode([
            'title' => $data['title'],
            'price' => $data['price'],
            'image' => $data['image'],
            'discount' => $data['discount'],
            'original_price' => $data['original_price'],
            'scraped_at' => date('Y-m-d H:i:s')
        ]);

        $stmt->execute([
            $data['title'],
            $data['title'],
            $data['title'],
            $data['image'],
            $data['discount'],
            $data['original_price'],
            $jsonData,
            $this->urlId
        ]);

        // Guardar imagen si existe
        if ($data['image']) {
            $stmt = $this->db->prepare("
                INSERT INTO product_images (url_id, image_url)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE image_url = VALUES(image_url)
            ");
            $stmt->execute([$this->urlId, $data['image']]);
        }
    }

    /**
     * Método estático para verificar si una URL es de Coolmod
     */
    public static function isCoolmodURL($url) {
        return strpos($url, 'coolmod.com') !== false;
    }

    /**
     * Descargar imagen del producto localmente (opcional)
     */
    public function downloadProductImage($imageUrl, $localPath) {
        $imageData = file_get_contents($imageUrl);

        if ($imageData) {
            // Crear directorio si no existe
            $dir = dirname($localPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Guardar imagen
            file_put_contents($localPath, $imageData);

            // Actualizar BD con ruta local
            $stmt = $this->db->prepare("
                UPDATE product_images
                SET image_local_path = ?, downloaded_at = NOW()
                WHERE url_id = ? AND image_url = ?
            ");
            $stmt->execute([$localPath, $this->urlId, $imageUrl]);

            return true;
        }

        return false;
    }
}
?>
