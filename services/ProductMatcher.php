<?php

/**
 * Servicio para hacer matching de productos entre diferentes tiendas
 *
 * Compara productos usando algoritmos de similitud de texto y
 * extracción de características clave (marca, modelo, especificaciones)
 */
class ProductMatcher {

    // Palabras comunes a ignorar en comparaciones
    private $stopWords = [
        'de', 'la', 'el', 'en', 'con', 'para', 'por', 'un', 'una',
        'y', 'o', 'a', 'del', 'las', 'los', 'al',
        'smart', 'tv', 'display', 'pantalla', 'negro', 'blanco',
        'color', 'nuevo', 'original', 'oficial'
    ];

    // Marcas conocidas (en minúsculas) - Expandido en Fase 2
    private $knownBrands = [
        // TVs y Electrónica
        'lg', 'samsung', 'sony', 'philips', 'panasonic', 'toshiba',
        'xiaomi', 'tcl', 'hisense', 'sharp', 'grundig', 'thomson',
        // Informática
        'apple', 'dell', 'hp', 'lenovo', 'asus', 'acer', 'msi',
        'gigabyte', 'intel', 'amd', 'nvidia',
        // Periféricos
        'corsair', 'logitech', 'razer', 'hyperx', 'steelseries',
        'roccat', 'cooler master', 'nzxt', 'thermaltake',
        // Almacenamiento
        'kingston', 'seagate', 'wd', 'sandisk', 'crucial', 'samsung',
        'toshiba', 'transcend', 'lexar',
        // Audio
        'bose', 'jbl', 'sennheiser', 'beats', 'sony', 'audio-technica',
        'beyerdynamic', 'akg', 'shure', 'marshall',
        // Smartphones y Tablets
        'xiaomi', 'huawei', 'oppo', 'realme', 'oneplus', 'motorola',
        'nokia', 'zte', 'google', 'amazon',
        // Accesorios
        'anker', 'belkin', 'ugreen', 'baseus', 'aukey', 'ravpower',
        // Gaming
        'playstation', 'xbox', 'nintendo', 'steam',
        // Electrodomésticos
        'bosch', 'siemens', 'balay', 'teka', 'electrolux', 'whirlpool',
        'cecotec', 'tefal', 'moulinex', 'philips'
    ];

    /**
     * Encontrar el mejor match entre un producto original y una lista de candidatos
     *
     * @param array $originalProduct Producto original con 'name'
     * @param array $candidates Lista de productos candidatos con 'name'
     * @return array|null Mejor match con score o null si ninguno supera el umbral
     */
    public function findBestMatch($originalProduct, $candidates) {
        if (empty($candidates)) {
            return null;
        }

        $originalName = $originalProduct['name'] ?? '';
        if (empty($originalName)) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;
        $minScore = 70.0; // Score mínimo para considerar un match válido

        foreach ($candidates as $candidate) {
            $candidateName = $candidate['name'] ?? '';
            if (empty($candidateName)) {
                continue;
            }

            $score = $this->calculateSimilarity($originalName, $candidateName);

            if ($score > $bestScore && $score >= $minScore) {
                $bestScore = $score;
                $bestMatch = $candidate;
                $bestMatch['match_score'] = round($score, 2);
                $bestMatch['match_metadata'] = [
                    'original_name' => $originalName,
                    'matched_name' => $candidateName,
                    'algorithm' => 'composite'
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * Calcular similitud entre dos nombres de productos
     *
     * @param string $name1 Primer nombre
     * @param string $name2 Segundo nombre
     * @return float Score de similitud (0-100)
     */
    public function calculateSimilarity($name1, $name2) {
        // Normalizar nombres
        $norm1 = $this->normalizeName($name1);
        $norm2 = $this->normalizeName($name2);

        // Extraer características clave
        $features1 = $this->extractFeatures($norm1);
        $features2 = $this->extractFeatures($norm2);

        // Calcular diferentes métricas de similitud
        $scores = [];

        // 1. Similitud de marca (peso alto: 30%)
        $brandScore = $this->compareBrands($features1['brand'], $features2['brand']);
        $scores['brand'] = $brandScore * 0.30;

        // 2. Similitud de modelo/número (peso alto: 30%)
        $modelScore = $this->compareModels($features1['models'], $features2['models']);
        $scores['model'] = $modelScore * 0.30;

        // 3. Similitud de especificaciones numéricas (peso medio: 20%)
        $specsScore = $this->compareSpecs($features1['specs'], $features2['specs']);
        $scores['specs'] = $specsScore * 0.20;

        // 4. Similitud de palabras clave (peso bajo: 20%)
        $keywordsScore = $this->compareKeywords($features1['keywords'], $features2['keywords']);
        $scores['keywords'] = $keywordsScore * 0.20;

        // Score final es la suma ponderada
        $finalScore = array_sum($scores);

        error_log("Similitud: '$name1' vs '$name2' = " . round($finalScore, 2) . "% " . json_encode($scores));

        return $finalScore;
    }

    /**
     * Normalizar nombre de producto
     *
     * @param string $name Nombre a normalizar
     * @return string Nombre normalizado
     */
    private function normalizeName($name) {
        // Convertir a minúsculas
        $name = mb_strtolower($name, 'UTF-8');

        // Eliminar acentos
        $name = $this->removeAccents($name);

        // Normalizar espacios
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    /**
     * Eliminar acentos de un texto
     *
     * @param string $text Texto con acentos
     * @return string Texto sin acentos
     */
    private function removeAccents($text) {
        $unwanted = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ];
        return strtr($text, $unwanted);
    }

    /**
     * Extraer características clave de un nombre de producto
     *
     * @param string $name Nombre normalizado
     * @return array Características extraídas
     */
    private function extractFeatures($name) {
        $features = [
            'brand' => '',
            'models' => [],
            'specs' => [],
            'keywords' => []
        ];

        // Extraer marca
        foreach ($this->knownBrands as $brand) {
            if (strpos($name, $brand) !== false) {
                $features['brand'] = $brand;
                break;
            }
        }

        // Extraer modelos (códigos alfanuméricos como "65QNED84A6C", "SM-G991", etc.)
        preg_match_all('/[a-z0-9]{2,}[0-9]+[a-z0-9]*/i', $name, $matches);
        if (!empty($matches[0])) {
            foreach ($matches[0] as $model) {
                // Filtrar modelos que son demasiado cortos o comunes
                if (strlen($model) >= 4 && !in_array($model, $this->stopWords)) {
                    $features['models'][] = strtolower($model);
                }
            }
        }

        // Extraer especificaciones numéricas (mejorado en Fase 2)

        // Pulgadas: 55", 65", 24', etc.
        if (preg_match('/(\d+)\s*(?:pulgadas?|"|\'\'|inch|pol)/i', $name, $inches)) {
            $features['specs']['inches'] = $inches[1];
        }

        // Capacidad de almacenamiento: 256GB, 1TB, 512 GB, etc.
        if (preg_match('/(\d+)\s*(gb|tb|mb)(?:\s|$|[^a-z])/i', $name, $storage)) {
            $features['specs']['storage'] = strtolower($storage[1] . $storage[2]);
        }

        // RAM: 8gb ram, 16 GB, etc.
        if (preg_match('/(\d+)\s*gb\s*(?:ram|ddr)/i', $name, $ram)) {
            $features['specs']['ram'] = $ram[1] . 'gb';
        }

        // Resolución: 4K, Full HD, QHD, 1080p, 2160p, etc.
        if (preg_match('/(4k|8k|full\s*hd|fhd|qhd|uhd|hd|1080p|720p|2160p)/i', $name, $resolution)) {
            $features['specs']['resolution'] = strtolower(str_replace(' ', '', $resolution[1]));
        }

        // Frecuencia: 60Hz, 120Hz, 144Hz, etc.
        if (preg_match('/(\d+)\s*hz/i', $name, $hz)) {
            $features['specs']['frequency'] = $hz[1] . 'hz';
        }

        // Procesador: i5, i7, ryzen 5, etc.
        if (preg_match('/(i[3579]|ryzen\s*[3579]|snapdragon\s*\d+|mediatek)/i', $name, $cpu)) {
            $features['specs']['cpu'] = strtolower(str_replace(' ', '', $cpu[1]));
        }

        // Megapíxeles: 48MP, 108 MP, etc.
        if (preg_match('/(\d+)\s*mp/i', $name, $mp)) {
            $features['specs']['megapixels'] = $mp[1] . 'mp';
        }

        // Batería: 5000mAh, 4500 mAh, etc.
        if (preg_match('/(\d+)\s*mah/i', $name, $battery)) {
            $features['specs']['battery'] = $battery[1] . 'mah';
        }

        // Peso: 15kg, 2.5 kg, etc.
        if (preg_match('/(\d+(?:\.\d+)?)\s*kg/i', $name, $weight)) {
            $features['specs']['weight'] = $weight[1] . 'kg';
        }

        // Color (palabras clave especiales)
        $colors = ['negro', 'blanco', 'gris', 'azul', 'rojo', 'verde', 'rosa', 'dorado', 'plata', 'silver', 'black', 'white'];
        foreach ($colors as $color) {
            if (stripos($name, $color) !== false) {
                $features['specs']['color'] = strtolower($color);
                break;
            }
        }

        // Palabras clave (filtrar stop words)
        $words = explode(' ', $name);
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) >= 3 && !in_array($word, $this->stopWords) && !is_numeric($word)) {
                $features['keywords'][] = $word;
            }
        }

        return $features;
    }

    /**
     * Comparar marcas
     *
     * @param string $brand1 Marca 1
     * @param string $brand2 Marca 2
     * @return float Score (0-100)
     */
    private function compareBrands($brand1, $brand2) {
        if (empty($brand1) || empty($brand2)) {
            return 0;
        }

        return $brand1 === $brand2 ? 100 : 0;
    }

    /**
     * Comparar modelos
     *
     * @param array $models1 Modelos del producto 1
     * @param array $models2 Modelos del producto 2
     * @return float Score (0-100)
     */
    private function compareModels($models1, $models2) {
        if (empty($models1) || empty($models2)) {
            return 0;
        }

        $maxScore = 0;

        // Comparar cada modelo de 1 con cada modelo de 2
        foreach ($models1 as $m1) {
            foreach ($models2 as $m2) {
                // Similitud exacta
                if ($m1 === $m2) {
                    return 100;
                }

                // Similitud parcial usando Levenshtein
                $distance = levenshtein($m1, $m2);
                $maxLen = max(strlen($m1), strlen($m2));
                $similarity = (1 - $distance / $maxLen) * 100;

                if ($similarity > $maxScore) {
                    $maxScore = $similarity;
                }

                // Si uno contiene al otro
                if (strpos($m1, $m2) !== false || strpos($m2, $m1) !== false) {
                    $containmentScore = 80;
                    if ($containmentScore > $maxScore) {
                        $maxScore = $containmentScore;
                    }
                }
            }
        }

        return $maxScore;
    }

    /**
     * Comparar especificaciones (mejorado en Fase 2)
     *
     * @param array $specs1 Especificaciones del producto 1
     * @param array $specs2 Especificaciones del producto 2
     * @return float Score (0-100)
     */
    private function compareSpecs($specs1, $specs2) {
        if (empty($specs1) || empty($specs2)) {
            return 50; // Neutral si no hay specs
        }

        $matches = 0;
        $total = 0;
        $criticalMatches = 0;
        $criticalTotal = 0;

        // Especificaciones críticas (peso mayor)
        $criticalSpecs = ['inches', 'storage', 'ram', 'resolution'];

        // Especificaciones normales
        $normalSpecs = ['frequency', 'cpu', 'megapixels', 'battery', 'weight'];

        // Comparar especificaciones críticas
        foreach ($criticalSpecs as $type) {
            if (isset($specs1[$type]) && isset($specs2[$type])) {
                $criticalTotal++;
                if ($specs1[$type] === $specs2[$type]) {
                    $criticalMatches++;
                }
            }
        }

        // Comparar especificaciones normales
        foreach ($normalSpecs as $type) {
            if (isset($specs1[$type]) && isset($specs2[$type])) {
                $total++;
                if ($specs1[$type] === $specs2[$type]) {
                    $matches++;
                }
            }
        }

        // Color no es crítico pero suma
        if (isset($specs1['color']) && isset($specs2['color'])) {
            if ($specs1['color'] === $specs2['color']) {
                $matches += 0.5; // Peso menor
            }
            $total += 0.5;
        }

        // Calcular score ponderado
        if ($criticalTotal === 0 && $total === 0) {
            return 50; // Neutral si no hay specs comparables
        }

        // Las specs críticas valen 70%, las normales 30%
        $criticalScore = $criticalTotal > 0 ? ($criticalMatches / $criticalTotal) * 70 : 35;
        $normalScore = $total > 0 ? ($matches / $total) * 30 : 15;

        return $criticalScore + $normalScore;
    }

    /**
     * Comparar palabras clave
     *
     * @param array $keywords1 Palabras clave del producto 1
     * @param array $keywords2 Palabras clave del producto 2
     * @return float Score (0-100)
     */
    private function compareKeywords($keywords1, $keywords2) {
        if (empty($keywords1) || empty($keywords2)) {
            return 0;
        }

        // Calcular intersección (palabras en común)
        $common = array_intersect($keywords1, $keywords2);

        // Calcular unión (todas las palabras únicas)
        $union = array_unique(array_merge($keywords1, $keywords2));

        if (count($union) === 0) {
            return 0;
        }

        // Índice de Jaccard
        return (count($common) / count($union)) * 100;
    }

    /**
     * Extraer términos de búsqueda de un nombre de producto
     * Útil para generar queries optimizadas
     *
     * @param string $productName Nombre del producto
     * @return string Query de búsqueda optimizada
     */
    public function generateSearchQuery($productName) {
        $normalized = $this->normalizeName($productName);
        $features = $this->extractFeatures($normalized);

        $queryParts = [];

        // Añadir marca si existe
        if (!empty($features['brand'])) {
            $queryParts[] = $features['brand'];
        }

        // Añadir primer modelo si existe
        if (!empty($features['models'])) {
            $queryParts[] = $features['models'][0];
        }

        // Añadir pulgadas si existe
        if (isset($features['specs']['inches'])) {
            $queryParts[] = $features['specs']['inches'] . '"';
        }

        // Si no hay suficiente información, usar las primeras palabras clave importantes
        if (count($queryParts) < 2 && !empty($features['keywords'])) {
            $importantKeywords = array_slice($features['keywords'], 0, 3);
            $queryParts = array_merge($queryParts, $importantKeywords);
        }

        return implode(' ', $queryParts);
    }
}
