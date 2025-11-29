<?php
require_once __DIR__ . '/BaseSearchScraper.php';

/**
 * Scraper de búsqueda para PcComponentes
 *
 * Extrae productos de las páginas de búsqueda de PcComponentes
 */
class PcComponentesSearchScraper extends BaseSearchScraper {

    private $baseUrl = 'https://www.pccomponentes.com';

    public function __construct($searchQuery) {
        parent::__construct($searchQuery);
        $this->storeName = 'PcComponentes';
    }

    /**
     * Override del método search para usar Hero (navegador anti-detección)
     *
     * @return array Lista de productos encontrados
     */
    public function search() {
        try {
            $heroScript = __DIR__ . '/../hero_search_scraper.js';

            if (!file_exists($heroScript)) {
                error_log("[PcComponentes] Hero search script no encontrado, usando cURL fallback");
                return parent::search();
            }

            // Ejecutar Hero search script
            $command = sprintf(
                'HOME=/var/www node %s %s %s 2>&1',
                escapeshellarg($heroScript),
                escapeshellarg('pccomponentes'),
                escapeshellarg($this->searchQuery)
            );

            error_log("[PcComponentes] Usando Hero para búsqueda: {$this->searchQuery}");

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                error_log("[PcComponentes] Hero error (code $returnCode): $errorMsg");
                return parent::search(); // Fallback a cURL
            }

            // Parsear JSON de salida
            $result = null;
            for ($i = count($output) - 1; $i >= 0; $i--) {
                $line = trim($output[$i]);
                if (empty($line) || $line[0] !== '{') continue;

                $decoded = json_decode($line, true);
                if ($decoded !== null && isset($decoded['success'])) {
                    $result = $decoded;
                    break;
                }
            }

            if (!$result || !$result['success']) {
                error_log("[PcComponentes] No se pudo parsear respuesta de Hero");
                return parent::search(); // Fallback a cURL
            }

            error_log("[PcComponentes] Hero encontró " . count($result['products']) . " productos");

            return $result['products'];

        } catch (Exception $e) {
            error_log("[PcComponentes] Exception usando Hero: " . $e->getMessage());
            return parent::search(); // Fallback a cURL
        }
    }

    /**
     * Construir URL de búsqueda de PcComponentes
     *
     * @param string $query Query de búsqueda
     * @return string URL completa de búsqueda
     */
    protected function buildSearchUrl($query) {
        // PcComponentes usa URLs amigables para búsquedas
        // Formato: /search/?query=termino+de+busqueda
        $encodedQuery = urlencode($query);
        return $this->baseUrl . '/search/?query=' . $encodedQuery;
    }

    /**
     * Extraer productos de la página de búsqueda de PcComponentes
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

        // PcComponentes usa article con id que empieza con "article-"
        $productNodes = $xpath->query('//article[starts-with(@id, "article-")]');

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
                error_log("[PcComponentes] Error extrayendo producto: " . $e->getMessage());
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

        // Intentar con h3 a
        $nameNodes = $xpath->query('.//h3/a', $node);
        if ($nameNodes->length > 0) {
            $name = $this->cleanText($nameNodes->item(0)->textContent);
        }

        // Si no, intentar con data-name
        if (empty($name)) {
            $dataName = $node->getAttribute('data-name');
            if (!empty($dataName)) {
                $name = $this->cleanText($dataName);
            }
        }

        if (empty($name)) {
            return null;
        }

        // Extraer precio
        $price = null;

        // PcComponentes suele tener el precio en data-price
        $dataPrice = $node->getAttribute('data-price');
        if (!empty($dataPrice)) {
            $price = floatval($dataPrice);
        }

        // Si no está en data-price, buscar en el HTML
        if (!$price || $price <= 0) {
            // Intentar con span.precio
            $priceNodes = $xpath->query('.//span[contains(@class, "precio")]', $node);
            if ($priceNodes->length > 0) {
                $priceText = $this->cleanText($priceNodes->item(0)->textContent);
                $price = $this->parsePrice($priceText);
            }
        }

        // Otra alternativa común en PcComponentes
        if (!$price || $price <= 0) {
            $priceNodes = $xpath->query('.//*[@id[contains(., "precio")]]', $node);
            if ($priceNodes->length > 0) {
                $priceText = $this->cleanText($priceNodes->item(0)->textContent);
                $price = $this->parsePrice($priceText);
            }
        }

        if (!$price || $price <= 0) {
            return null;
        }

        // Extraer URL del producto
        $url = '';

        // Primero intentar con h3 a (más confiable)
        $urlNodes = $xpath->query('.//h3/a', $node);
        if ($urlNodes->length > 0) {
            $href = $urlNodes->item(0)->getAttribute('href');
            $url = $this->normalizeUrl($href, $this->baseUrl);
        }

        // Si no, buscar cualquier enlace principal
        if (empty($url)) {
            $urlNodes = $xpath->query('.//a[contains(@class, "product")]', $node);
            if ($urlNodes->length > 0) {
                $href = $urlNodes->item(0)->getAttribute('href');
                $url = $this->normalizeUrl($href, $this->baseUrl);
            }
        }

        if (empty($url)) {
            return null;
        }

        // Extraer imagen
        $image = '';

        // PcComponentes suele usar img con data-src para lazy loading
        $imageNodes = $xpath->query('.//img', $node);
        if ($imageNodes->length > 0) {
            $imgNode = $imageNodes->item(0);

            // Intentar data-src primero (lazy loading)
            $image = $imgNode->getAttribute('data-src');

            // Si no, usar src
            if (empty($image)) {
                $image = $imgNode->getAttribute('src');
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
     * Override del método fetchHTML para añadir comportamiento específico de PcComponentes
     *
     * @param string $url URL a visitar
     * @return string|false HTML de la página o false en error
     */
    protected function fetchHTML($url) {
        $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
        $cookieFile = sys_get_temp_dir() . '/pcc_search_' . md5($this->searchQuery) . '.txt';

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

        // Pausa para simular navegación humana (más corta que en el scraper de productos)
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
            error_log("[PcComponentes] cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[PcComponentes] HTTP Error: $httpCode");
            return false;
        }

        return $html;
    }
}
