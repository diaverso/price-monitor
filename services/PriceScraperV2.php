<?php
require_once __DIR__ . '/../config/database.php';

class PriceScraperV2 {

    private $db;
    private $urlId;
    private $url;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
    }

    /**
     * Extrae el precio de una URL usando patrones almacenados
     */
    public function extractPrice() {
        $startTime = microtime(true);

        try {
            // Obtener HTML
            $html = $this->fetchHTML($this->url);
            if (!$html) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL'];
            }

            // Obtener dominio
            $domain = parse_url($this->url, PHP_URL_HOST);

            // Intentar con funciones personalizadas primero
            $customResult = $this->tryCustomFunctions($html);
            if ($customResult['success']) {
                $extractionTime = round((microtime(true) - $startTime) * 1000);
                return array_merge($customResult, ['extraction_time_ms' => $extractionTime]);
            }

            // Intentar con patrones almacenados
            $patternResult = $this->tryStoredPatterns($html, $domain);
            if ($patternResult['success']) {
                $extractionTime = round((microtime(true) - $startTime) * 1000);
                return array_merge($patternResult, ['extraction_time_ms' => $extractionTime]);
            }

            // Intentar con estrategias genéricas
            $genericResult = $this->tryGenericStrategies($html);
            $extractionTime = round((microtime(true) - $startTime) * 1000);

            if ($genericResult['success']) {
                return array_merge($genericResult, ['extraction_time_ms' => $extractionTime]);
            }

            return ['success' => false, 'error' => 'No se pudo extraer el precio', 'extraction_time_ms' => $extractionTime];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtener HTML de la URL
     */
    private function fetchHTML($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            ]
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $html) ? $html : false;
    }

    /**
     * Intentar con funciones personalizadas de la BD
     */
    private function tryCustomFunctions($html) {
        $stmt = $this->db->prepare("
            SELECT * FROM scraping_functions
            WHERE url_id = ? AND is_active = 1
            ORDER BY priority DESC
        ");
        $stmt->execute([$this->urlId]);
        $functions = $stmt->fetchAll();

        foreach ($functions as $func) {
            $price = $this->applySelector($html, $func['selector_type'], $func['selector_value']);
            if ($price) {
                return [
                    'success' => true,
                    'price' => $price,
                    'method' => 'custom_function',
                    'function_name' => $func['function_name']
                ];
            }
        }

        return ['success' => false];
    }

    /**
     * Intentar con patrones almacenados en BD
     */
    private function tryStoredPatterns($html, $domain) {
        // Buscar patrones específicos del dominio
        $stmt = $this->db->prepare("
            SELECT * FROM scraping_patterns
            WHERE is_active = 1
            AND (domain_pattern LIKE ? OR domain_pattern = '%')
            ORDER BY success_count DESC, domain_pattern DESC
        ");
        $stmt->execute(["%{$domain}%"]);
        $patterns = $stmt->fetchAll();

        foreach ($patterns as $pattern) {
            $price = $this->applySelector($html, $pattern['selector_type'], $pattern['selector_value']);

            if ($price) {
                // Actualizar estadísticas del patrón
                $this->updatePatternStats($pattern['id'], true);

                return [
                    'success' => true,
                    'price' => $price,
                    'method' => 'stored_pattern',
                    'pattern_name' => $pattern['pattern_name']
                ];
            } else {
                $this->updatePatternStats($pattern['id'], false);
            }
        }

        return ['success' => false];
    }

    /**
     * Estrategias genéricas (método original)
     */
    private function tryGenericStrategies($html) {
        // Estrategia 1: Meta tags Open Graph
        if (preg_match('/<meta[^>]+property=["\']og:price:amount["\'][^>]+content=["\']([\d.,]+)["\']/i', $html, $matches)) {
            return ['success' => true, 'price' => $this->parsePrice($matches[1]), 'method' => 'og_meta'];
        }

        // Estrategia 2: Schema.org JSON-LD
        if (preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if (isset($jsonData['offers']['price'])) {
                return ['success' => true, 'price' => $this->parsePrice($jsonData['offers']['price']), 'method' => 'schema_json'];
            }
            if (isset($jsonData['price'])) {
                return ['success' => true, 'price' => $this->parsePrice($jsonData['price']), 'method' => 'schema_json'];
            }
        }

        // Estrategia 3: Patrones comunes en HTML
        $pricePatterns = [
            '/[€$£¥]\s*([\d.,]+)/u',
            '/([\d.,]+)\s*[€$£¥]/u',
            '/class=["\'][^"\']*price[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/i',
            '/id=["\'][^"\']*price[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/i',
            '/\b(\d{1,6}[.,]\d{2})\b/',
        ];

        $foundPrices = [];
        foreach ($pricePatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    $price = $this->parsePrice($match);
                    if ($price > 0 && $price < 1000000) {
                        $foundPrices[] = $price;
                    }
                }
            }
        }

        if (!empty($foundPrices)) {
            $priceCount = array_count_values($foundPrices);
            arsort($priceCount);
            $mostCommon = array_key_first($priceCount);
            return ['success' => true, 'price' => $mostCommon, 'method' => 'generic_pattern'];
        }

        return ['success' => false];
    }

    /**
     * Aplicar selector según tipo
     */
    private function applySelector($html, $type, $value) {
        switch ($type) {
            case 'css':
                return $this->applyCSSSelector($html, $value);

            case 'xpath':
                return $this->applyXPathSelector($html, $value);

            case 'regex':
                if (preg_match($value, $html, $matches)) {
                    return $this->parsePrice($matches[1] ?? $matches[0]);
                }
                return false;

            case 'json_path':
                return $this->applyJSONPath($html, $value);

            default:
                return false;
        }
    }

    /**
     * Aplicar selector CSS (simplificado)
     */
    private function applyCSSSelector($html, $selector) {
        // Crear DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // Convertir selector CSS a XPath (simplificado)
        $xpathQuery = $this->cssToXPath($selector);
        $nodes = $xpath->query($xpathQuery);

        if ($nodes->length > 0) {
            $text = $nodes->item(0)->textContent;
            return $this->parsePrice($text);
        }

        return false;
    }

    /**
     * Aplicar selector XPath
     */
    private function applyXPathSelector($html, $xpath) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpathObj = new DOMXPath($dom);

        $nodes = $xpathObj->query($xpath);
        if ($nodes->length > 0) {
            $text = $nodes->item(0)->textContent;
            return $this->parsePrice($text);
        }

        return false;
    }

    /**
     * Aplicar JSON Path
     */
    private function applyJSONPath($html, $path) {
        if (preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);

            // Parsear path simple (ej: $.offers.price)
            $pathParts = explode('.', trim($path, '$.'));
            $value = $jsonData;

            foreach ($pathParts as $part) {
                if (isset($value[$part])) {
                    $value = $value[$part];
                } else {
                    return false;
                }
            }

            return $this->parsePrice($value);
        }

        return false;
    }

    /**
     * Convertir selector CSS simple a XPath
     */
    private function cssToXPath($css) {
        // Conversión básica
        $css = str_replace('.', "[contains(@class,'", $css);
        $css = str_replace('#', "[@id='", $css);
        $css = str_replace("'", "')]", $css);

        if (strpos($css, '[') === 0) {
            return "//*" . $css;
        }

        return "//" . $css;
    }

    /**
     * Parsear precio
     */
    private function parsePrice($priceString) {
        $priceString = strip_tags($priceString);
        $priceString = preg_replace('/[€$£¥\s]/u', '', $priceString);
        $priceString = str_replace(',', '.', $priceString);
        preg_match('/[\d.]+/', $priceString, $matches);
        return isset($matches[0]) ? floatval($matches[0]) : 0;
    }

    /**
     * Actualizar estadísticas de patrón
     */
    private function updatePatternStats($patternId, $success) {
        if ($success) {
            $stmt = $this->db->prepare("
                UPDATE scraping_patterns
                SET success_count = success_count + 1, last_used = NOW()
                WHERE id = ?
            ");
        } else {
            $stmt = $this->db->prepare("
                UPDATE scraping_patterns
                SET fail_count = fail_count + 1
                WHERE id = ?
            ");
        }
        $stmt->execute([$patternId]);
    }

    /**
     * Guardar función personalizada para esta URL
     */
    public function saveCustomFunction($functionName, $selectorType, $selectorValue, $description = '', $priority = 0) {
        $stmt = $this->db->prepare("
            INSERT INTO scraping_functions (url_id, function_name, selector_type, selector_value, description, priority)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $this->urlId,
            $functionName,
            $selectorType,
            $selectorValue,
            $description,
            $priority
        ]);
    }
}
?>
