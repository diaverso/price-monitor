<?php
require_once __DIR__ . '/BaseSearchScraper.php';

/**
 * Scraper de búsqueda para MediaMarkt.es
 *
 * Extrae productos de las páginas de búsqueda de MediaMarkt
 */
class MediaMarktSearchScraper extends BaseSearchScraper {

    private $baseUrl = 'https://www.mediamarkt.es';

    public function __construct($searchQuery) {
        parent::__construct($searchQuery);
        $this->storeName = 'MediaMarkt';
    }

    /**
     * Construir URL de búsqueda de MediaMarkt
     *
     * @param string $query Query de búsqueda
     * @return string URL completa de búsqueda
     */
    protected function buildSearchUrl($query) {
        $encodedQuery = urlencode($query);
        return $this->baseUrl . '/es/search.html?query=' . $encodedQuery;
    }

    /**
     * Extraer productos de la página de búsqueda de MediaMarkt
     *
     * @return array Lista de productos encontrados
     */
    protected function extractProducts() {
        $products = [];

        if (empty($this->html)) {
            return $products;
        }

        // Crear DOMDocument para parsear HTML
        $dom = new DOMDocument();
        @$dom->loadHTML($this->html);
        $xpath = new DOMXPath($dom);

        // MediaMarkt usa article o divs con clase product para cada producto
        // Intentar múltiples selectores
        $selectors = [
            '//article[contains(@class, "product")]',
            '//div[contains(@class, "product-wrapper")]',
            '//div[@data-test="mms-search-srp-productlist-item"]'
        ];

        $productNodes = null;
        foreach ($selectors as $selector) {
            $productNodes = $xpath->query($selector);
            if ($productNodes->length > 0) {
                error_log("[MediaMarkt] Usando selector: $selector");
                break;
            }
        }

        if (!$productNodes || $productNodes->length === 0) {
            error_log("[MediaMarkt] No se encontraron productos con ningún selector");
            return $products;
        }

        $count = 0;
        foreach ($productNodes as $node) {
            if ($count >= $this->maxResults) {
                break;
            }

            try {
                $product = $this->extractProductFromNode($xpath, $node);

                if ($product && $product['price'] > 0) {
                    $products[] = $product;
                    $count++;
                }

            } catch (Exception $e) {
                error_log("[MediaMarkt] Error extrayendo producto: " . $e->getMessage());
                continue;
            }
        }

        return $products;
    }

    /**
     * Extraer información de un nodo de producto
     *
     * @param DOMXPath $xpath XPath del documento
     * @param DOMNode $node Nodo del producto
     * @return array|null Información del producto o null si falla
     */
    private function extractProductFromNode($xpath, $node) {
        // Extraer nombre del producto
        $name = '';

        // Intentar diferentes selectores para el nombre
        $nameSelectors = [
            './/h2//a',
            './/h3//a',
            './/*[@data-test="product-title"]',
            './/*[contains(@class, "product-title")]//a'
        ];

        foreach ($nameSelectors as $selector) {
            $nameNodes = $xpath->query($selector, $node);
            if ($nameNodes->length > 0) {
                $name = $this->cleanText($nameNodes->item(0)->textContent);
                if (!empty($name)) {
                    break;
                }
            }
        }

        if (empty($name)) {
            return null;
        }

        // Extraer precio
        $price = null;

        // Intentar diferentes selectores de precio
        $priceSelectors = [
            './/*[@data-test="product-price"]',
            './/*[contains(@class, "price")]',
            './/*[@itemprop="price"]',
            './/span[contains(@class, "price")]'
        ];

        foreach ($priceSelectors as $selector) {
            $priceNodes = $xpath->query($selector, $node);
            if ($priceNodes->length > 0) {
                $priceText = $this->cleanText($priceNodes->item(0)->textContent);
                $price = $this->parsePrice($priceText);
                if ($price && $price > 0) {
                    break;
                }
            }
        }

        // Si no encontramos precio, intentar con data-price
        if (!$price || $price <= 0) {
            $dataPrice = $node->getAttribute('data-price');
            if (!empty($dataPrice)) {
                $price = floatval($dataPrice);
            }
        }

        if (!$price || $price <= 0) {
            return null;
        }

        // Extraer URL del producto
        $url = '';

        // Buscar enlace al producto
        $urlSelectors = [
            './/h2//a',
            './/h3//a',
            './/*[@data-test="product-title"]//a',
            './/a[contains(@class, "product-link")]'
        ];

        foreach ($urlSelectors as $selector) {
            $urlNodes = $xpath->query($selector, $node);
            if ($urlNodes->length > 0) {
                $href = $urlNodes->item(0)->getAttribute('href');
                if (!empty($href)) {
                    $url = $this->normalizeUrl($href, $this->baseUrl);
                    break;
                }
            }
        }

        if (empty($url)) {
            return null;
        }

        // Extraer imagen
        $image = '';

        $imageNodes = $xpath->query('.//img', $node);
        if ($imageNodes->length > 0) {
            $imgNode = $imageNodes->item(0);

            // Intentar diferentes atributos de imagen
            $imageAttrs = ['data-src', 'data-lazy-src', 'src'];
            foreach ($imageAttrs as $attr) {
                $image = $imgNode->getAttribute($attr);
                if (!empty($image) && strpos($image, 'http') === 0) {
                    break;
                }
            }

            // Normalizar URL de imagen
            if (!empty($image)) {
                $image = $this->normalizeUrl($image, $this->baseUrl);
            }
        }

        return [
            'name' => $name,
            'price' => $price,
            'url' => $url,
            'image' => $image,
            'store' => $this->storeName
        ];
    }

    /**
     * Override del método fetchHTML para añadir comportamiento específico de MediaMarkt
     *
     * @param string $url URL a visitar
     * @return string|false HTML de la página o false en error
     */
    protected function fetchHTML($url) {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
        $cookieFile = sys_get_temp_dir() . '/mm_search_' . md5($this->searchQuery) . '.txt';

        // Primera visita a la home para establecer cookies
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9',
                'Accept-Encoding: gzip, deflate, br'
            ]
        ]);
        curl_exec($ch);
        curl_close($ch);

        // Pausa para simular navegación humana
        usleep(rand(1000000, 2000000)); // 1-2 segundos

        // Ahora hacer la búsqueda
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_REFERER => $this->baseUrl,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: max-age=0',
                'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120"',
                'Sec-Ch-Ua-Mobile: ?0',
                'Sec-Ch-Ua-Platform: "Windows"',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: same-origin',
                'Sec-Fetch-User: ?1',
                'Upgrade-Insecure-Requests: 1'
            ]
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("[MediaMarkt] cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[MediaMarkt] HTTP Error: $httpCode");
            return false;
        }

        return $html;
    }
}
