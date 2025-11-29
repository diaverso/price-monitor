<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

/**
 * Clase base para scrapers de páginas de búsqueda
 *
 * Esta clase proporciona funcionalidad común para extraer productos
 * de páginas de búsqueda de diferentes tiendas online.
 */
abstract class BaseSearchScraper {

    protected $db;
    protected $storeName;
    protected $searchQuery;
    protected $html;
    protected $maxResults = 10; // Máximo número de resultados por defecto

    /**
     * Constructor
     *
     * @param string $searchQuery Query de búsqueda
     */
    public function __construct($searchQuery) {
        $this->db = Database::getInstance()->getConnection();
        $this->searchQuery = $searchQuery;
    }

    /**
     * Ejecutar búsqueda y retornar productos encontrados
     *
     * @return array Lista de productos con estructura:
     *   [
     *     'name' => string,
     *     'price' => float,
     *     'url' => string,
     *     'image' => string (opcional),
     *     'store' => string
     *   ]
     */
    public function search() {
        try {
            $startTime = microtime(true);

            // Generar URL de búsqueda específica de cada tienda
            $searchUrl = $this->buildSearchUrl($this->searchQuery);

            // Obtener HTML de la página de búsqueda
            $this->html = $this->fetchHTML($searchUrl);

            if (!$this->html) {
                error_log("[$this->storeName] No se pudo obtener HTML de búsqueda");
                return [];
            }

            // Extraer productos de la página
            $products = $this->extractProducts();

            $executionTime = round((microtime(true) - $startTime) * 1000);

            error_log("[$this->storeName] Búsqueda completada: " . count($products) . " productos en {$executionTime}ms");

            return $products;

        } catch (Exception $e) {
            error_log("[$this->storeName] Error en búsqueda: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Construir URL de búsqueda específica de cada tienda
     * Debe ser implementado por cada scraper específico
     *
     * @param string $query Query de búsqueda
     * @return string URL completa de búsqueda
     */
    abstract protected function buildSearchUrl($query);

    /**
     * Extraer productos de la página de búsqueda
     * Debe ser implementado por cada scraper específico
     *
     * @return array Lista de productos
     */
    abstract protected function extractProducts();

    /**
     * Obtener HTML de una URL con headers realistas
     *
     * @param string $url URL a visitar
     * @return string|false HTML de la página o false en error
     */
    protected function fetchHTML($url) {
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
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_ENCODING => 'gzip, deflate, br',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Cache-Control: max-age=0',
                'Sec-Ch-Ua: "Not_A Brand";v="8", "Chromium";v="120"',
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

        if (curl_errno($ch)) {
            error_log("[$this->storeName] cURL Error: " . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[$this->storeName] HTTP Error: $httpCode");
            return false;
        }

        return $html;
    }

    /**
     * Extraer precio de texto (ej: "1.299,99 €" -> 1299.99)
     *
     * @param string $priceText Texto del precio
     * @return float|null Precio como float o null si no se pudo extraer
     */
    protected function parsePrice($priceText) {
        if (empty($priceText)) {
            return null;
        }

        // Limpiar el texto
        $priceText = trim($priceText);

        // Extraer números, comas y puntos
        preg_match('/([0-9.,]+)/', $priceText, $matches);

        if (!isset($matches[1])) {
            return null;
        }

        $price = $matches[1];

        // Formato español: punto como separador de miles, coma como decimal
        // Ej: 1.299,99 -> 1299.99
        $price = str_replace('.', '', $price); // Quitar separador de miles
        $price = str_replace(',', '.', $price); // Convertir decimal

        return floatval($price);
    }

    /**
     * Normalizar URL (asegurar que tenga protocolo completo)
     *
     * @param string $url URL a normalizar
     * @param string $baseUrl URL base para URLs relativas
     * @return string URL normalizada
     */
    protected function normalizeUrl($url, $baseUrl) {
        if (empty($url)) {
            return '';
        }

        // Si ya tiene protocolo, retornar tal cual
        if (preg_match('/^https?:\/\//', $url)) {
            return $url;
        }

        // Si empieza con //, añadir protocolo
        if (substr($url, 0, 2) === '//') {
            return 'https:' . $url;
        }

        // Si es relativa, añadir base
        if (substr($url, 0, 1) === '/') {
            return rtrim($baseUrl, '/') . $url;
        }

        return $baseUrl . '/' . ltrim($url, '/');
    }

    /**
     * Limpiar texto (eliminar espacios extra, saltos de línea, etc.)
     *
     * @param string $text Texto a limpiar
     * @return string Texto limpio
     */
    protected function cleanText($text) {
        if (empty($text)) {
            return '';
        }

        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Eliminar tags HTML residuales
        $text = strip_tags($text);

        // Normalizar espacios en blanco
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Obtener nombre de la tienda
     *
     * @return string Nombre de la tienda
     */
    public function getStoreName() {
        return $this->storeName;
    }

    /**
     * Establecer máximo de resultados
     *
     * @param int $max Número máximo de resultados
     */
    public function setMaxResults($max) {
        $this->maxResults = max(1, intval($max));
    }
}
