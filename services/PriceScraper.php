<?php
class PriceScraper {

    /**
     * Extrae el precio de una URL
     * Intenta múltiples estrategias para encontrar el precio
     */
    public static function extractPrice($url) {
        try {
            // Configurar cURL para obtener el HTML
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                ]
            ]);

            $html = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$html) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL'];
            }

            // Estrategia 1: Buscar en meta tags Open Graph
            if (preg_match('/<meta[^>]+property=["\']og:price:amount["\'][^>]+content=["\']([\d.,]+)["\']/i', $html, $matches)) {
                return ['success' => true, 'price' => self::parsePrice($matches[1])];
            }

            // Estrategia 2: Buscar en schema.org JSON-LD
            if (preg_match('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $matches)) {
                $jsonData = json_decode($matches[1], true);
                if (isset($jsonData['offers']['price'])) {
                    return ['success' => true, 'price' => self::parsePrice($jsonData['offers']['price'])];
                }
                if (isset($jsonData['price'])) {
                    return ['success' => true, 'price' => self::parsePrice($jsonData['price'])];
                }
            }

            // Estrategia 3: Buscar patrones comunes de precio en HTML
            $pricePatterns = [
                // Patrones con símbolos de moneda
                '/[€$£¥]\s*([\d.,]+)/u',
                '/([\d.,]+)\s*[€$£¥]/u',
                // Patrones con clases CSS comunes
                '/class=["\'][^"\']*price[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/i',
                '/id=["\'][^"\']*price[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/i',
                // Patrones de precios decimales
                '/\b(\d{1,6}[.,]\d{2})\b/',
            ];

            $foundPrices = [];
            foreach ($pricePatterns as $pattern) {
                if (preg_match_all($pattern, $html, $matches)) {
                    foreach ($matches[1] as $match) {
                        $price = self::parsePrice($match);
                        if ($price > 0 && $price < 1000000) { // Filtro de precios razonables
                            $foundPrices[] = $price;
                        }
                    }
                }
            }

            // Si encontramos precios, devolver el más común o el primero
            if (!empty($foundPrices)) {
                $priceCount = array_count_values($foundPrices);
                arsort($priceCount);
                $mostCommon = array_key_first($priceCount);
                return ['success' => true, 'price' => $mostCommon];
            }

            return ['success' => false, 'error' => 'No se pudo extraer el precio'];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Parsea y limpia un string de precio
     */
    private static function parsePrice($priceString) {
        // Remover HTML tags
        $priceString = strip_tags($priceString);

        // Remover símbolos de moneda y espacios
        $priceString = preg_replace('/[€$£¥\s]/u', '', $priceString);

        // Reemplazar coma por punto (formato europeo)
        $priceString = str_replace(',', '.', $priceString);

        // Extraer solo números y punto decimal
        preg_match('/[\d.]+/', $priceString, $matches);

        return isset($matches[0]) ? floatval($matches[0]) : 0;
    }

    /**
     * Extrae información del producto (nombre y precio)
     */
    public static function extractProductInfo($url) {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                CURLOPT_TIMEOUT => 30,
            ]);

            $html = curl_exec($ch);
            curl_close($ch);

            if (!$html) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL'];
            }

            $productName = '';

            // Intentar obtener el título del producto
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $productName = trim(strip_tags($matches[1]));
            }

            // Intentar obtener de meta tags
            if (preg_match('/<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\']/i', $html, $matches)) {
                $productName = trim($matches[1]);
            }

            $priceResult = self::extractPrice($url);

            return [
                'success' => true,
                'product_name' => $productName,
                'price' => $priceResult['success'] ? $priceResult['price'] : null
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>
