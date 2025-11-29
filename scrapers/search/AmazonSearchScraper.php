<?php
require_once __DIR__ . '/BaseSearchScraper.php';

/**
 * Scraper de búsqueda para Amazon.es
 *
 * Extrae productos de las páginas de búsqueda de Amazon
 */
class AmazonSearchScraper extends BaseSearchScraper {

    private $baseUrl = 'https://www.amazon.es';

    public function __construct($searchQuery) {
        parent::__construct($searchQuery);
        $this->storeName = 'Amazon';
    }

    /**
     * Construir URL de búsqueda de Amazon
     *
     * @param string $query Query de búsqueda
     * @return string URL completa de búsqueda
     */
    protected function buildSearchUrl($query) {
        $encodedQuery = urlencode($query);
        return $this->baseUrl . '/s?k=' . $encodedQuery;
    }

    /**
     * Extraer productos de la página de búsqueda de Amazon
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

        // Amazon usa data-component-type="s-search-result" para cada producto
        $productNodes = $xpath->query('//div[@data-component-type="s-search-result"]');

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
                error_log("[Amazon] Error extrayendo producto: " . $e->getMessage());
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
        $nameNodes = $xpath->query('.//h2//span[@class="a-size-base-plus a-color-base a-text-normal" or @class="a-size-medium a-color-base a-text-normal"]', $node);
        $name = '';
        if ($nameNodes->length > 0) {
            $name = $this->cleanText($nameNodes->item(0)->textContent);
        }

        if (empty($name)) {
            return null;
        }

        // Extraer precio
        $price = null;

        // Intentar precio entero + decimal
        $priceWholeNodes = $xpath->query('.//span[@class="a-price-whole"]', $node);
        $priceFractionNodes = $xpath->query('.//span[@class="a-price-fraction"]', $node);

        if ($priceWholeNodes->length > 0 && $priceFractionNodes->length > 0) {
            $priceWhole = $this->cleanText($priceWholeNodes->item(0)->textContent);
            $priceFraction = $this->cleanText($priceFractionNodes->item(0)->textContent);
            $priceText = $priceWhole . ',' . $priceFraction;
            $price = $this->parsePrice($priceText);
        }

        // Si no se encontró precio, intentar otra estructura
        if (!$price) {
            $priceNodes = $xpath->query('.//span[@class="a-price"]//span[@class="a-offscreen"]', $node);
            if ($priceNodes->length > 0) {
                $priceText = $this->cleanText($priceNodes->item(0)->textContent);
                $price = $this->parsePrice($priceText);
            }
        }

        if (!$price || $price <= 0) {
            return null;
        }

        // Extraer URL del producto
        $urlNodes = $xpath->query('.//h2//a[@class="a-link-normal s-underline-text s-underline-link-text s-link-style a-text-normal"]', $node);
        $url = '';
        if ($urlNodes->length > 0) {
            $href = $urlNodes->item(0)->getAttribute('href');
            $url = $this->normalizeUrl($href, $this->baseUrl);

            // Limpiar URL de Amazon (quitar parámetros innecesarios excepto el ASIN)
            if (preg_match('/\/dp\/([A-Z0-9]{10})/', $url, $matches)) {
                $url = $this->baseUrl . '/dp/' . $matches[1];
            }
        }

        if (empty($url)) {
            return null;
        }

        // Extraer imagen
        $image = '';
        $imageNodes = $xpath->query('.//img[@class="s-image"]', $node);
        if ($imageNodes->length > 0) {
            $image = $imageNodes->item(0)->getAttribute('src');
            // Amazon a veces usa data-src para lazy loading
            if (empty($image)) {
                $image = $imageNodes->item(0)->getAttribute('data-src');
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
     * Override del método fetchHTML para añadir comportamiento específico de Amazon
     *
     * @param string $url URL a visitar
     * @return string|false HTML de la página o false en error
     */
    protected function fetchHTML($url) {
        // Primero visitar la home de Amazon para establecer cookies
        $homeUrl = $this->baseUrl;
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

        $cookieFile = sys_get_temp_dir() . '/amazon_search_' . md5($this->searchQuery) . '.txt';

        // Primera visita a la home
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $homeUrl,
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

        // Pequeña pausa para simular navegación humana
        usleep(rand(500000, 1500000)); // 0.5 - 1.5 segundos

        // Ahora hacer la búsqueda usando las cookies establecidas
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
            CURLOPT_REFERER => $homeUrl,
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
            error_log("[Amazon] cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[Amazon] HTTP Error: $httpCode");
            return false;
        }

        return $html;
    }
}
