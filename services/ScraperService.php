<?php
/**
 * Servicio centralizado para scraping de productos
 * Maneja la lógica de selección de scraper según la tienda
 */
class ScraperService {

    /**
     * Extrae información del producto de una URL usando el scraper apropiado
     *
     * @param string $url URL del producto
     * @param int $urlId ID de la URL monitoreada
     * @return array Resultado del scraping con estructura ['success' => bool, 'data' => array]
     */
    public static function scrapeProductInfo($url, $urlId) {
        try {
            $result = null;

            if (AmazonScraper::isAmazonURL($url)) {
                error_log("[ScraperService] Amazon: Intentando con cURL scraper...");
                $scraper = new AmazonScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Amazon cURL falló, intentando con Selenium...");
                    $scraper = new SeleniumScraper($urlId, $url, true);
                    $result = $scraper->extractProductInfo();

                    if (!$result['success']) {
                        error_log("[ScraperService] Amazon Selenium headless falló, intentando sin headless...");
                        $scraper = new SeleniumScraper($urlId, $url, false);
                        $result = $scraper->extractProductInfo();
                    }
                }
            }
            elseif (PcComponentesScraper::isPcComponentesURL($url)) {
                error_log("[ScraperService] PcComponentes: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] PcComponentes Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (ElCorteInglesScraper::isElCorteInglesURL($url)) {
                error_log("[ScraperService] El Corte Inglés: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] El Corte Inglés Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (CoolmodScraper::isCoolmodURL($url)) {
                error_log("[ScraperService] Coolmod: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Coolmod Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (MediaMarktScraper::isMediaMarktURL($url)) {
                error_log("[ScraperService] MediaMarkt: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] MediaMarkt Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (MercadonaScraper::isMercadonaURL($url)) {
                error_log("[ScraperService] Mercadona: Usando API pública");
                $scraper = new MercadonaScraper($urlId, $url);
                $result = $scraper->extractProductInfo();
            }
            elseif (AliExpressScraper::isAliExpressURL($url)) {
                error_log("[ScraperService] AliExpress: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] AliExpress Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (ConsumScraper::isConsumURL($url)) {
                error_log("[ScraperService] Consum: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Consum Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (ZaraScraper::isZaraURL($url)) {
                error_log("[ScraperService] Zara: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Zara Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (ZalandoScraper::isZalandoURL($url)) {
                error_log("[ScraperService] Zalando: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Zalando Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (TemuScraper::isTemuURL($url)) {
                error_log("[ScraperService] Temu: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Temu Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (LegoScraper::isLegoURL($url)) {
                error_log("[ScraperService] LEGO: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] LEGO Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (DecathlonScraper::isDecathlonURL($url)) {
                error_log("[ScraperService] Decathlon: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Decathlon Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (MangoOutletScraper::isMangoOutletURL($url)) {
                error_log("[ScraperService] Mango Outlet: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Mango Outlet Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (MichaelKorsScraper::isMichaelKorsURL($url)) {
                error_log("[ScraperService] Michael Kors: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Michael Kors Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (MangoScraper::isMangoURL($url)) {
                error_log("[ScraperService] Mango: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] Mango Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (IkeaScraper::isIkeaURL($url)) {
                error_log("[ScraperService] IKEA: Usando Ulixee Hero");
                $scraper = new HeroScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("[ScraperService] IKEA Hero falló, intentando Selenium");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
            elseif (FnacScraper::isFnacURL($url)) {
                // Paso 1: FnacScraper cURL (más rápido)
                error_log("[ScraperService] Fnac: Intentando FnacScraper con cURL...");
                $scraper = new FnacScraper($urlId, $url);
                $result = $scraper->extractProductInfo();

                // Paso 2: AdvancedScraper con camoufox (Firefox anti-detect, funciona para Fnac)
                if (!$result['success']) {
                    error_log("[ScraperService] Fnac cURL falló, intentando Camoufox (Firefox anti-detect)...");
                    $scraper = new AdvancedScraper($urlId, $url, 'camoufox');
                    $result = $scraper->extractProductInfo();
                }

                // Paso 3: Hero (Node.js, fallback final con extractFnac())
                if (!$result['success']) {
                    error_log("[ScraperService] Fnac Camoufox falló, intentando Hero...");
                    $scraper = new HeroScraper($urlId, $url);
                    $result = $scraper->extractProductInfo();
                }
            }
            else {
                // Tienda desconocida: scraper genérico + fallback avanzado
                error_log("[ScraperService] Tienda desconocida: Usando scraper genérico");
                $result = PriceScraper::extractProductInfo($url);

                if (!$result || !$result['success']) {
                    error_log("[ScraperService] Scraper genérico falló, intentando AdvancedScraper...");
                    $scraper = new AdvancedScraper($urlId, $url);
                    $result = $scraper->extractProductInfo();
                }
            }

            return $result;

        } catch (Exception $e) {
            error_log("[ScraperService] Exception: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
