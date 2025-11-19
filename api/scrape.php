<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../services/PcComponentesScraper.php';
require_once '../services/AmazonScraper.php';
require_once '../services/ElCorteInglesScraper.php';
require_once '../services/CoolmodScraper.php';
require_once '../services/MediaMarktScraper.php';
require_once '../services/MercadonaScraper.php';
require_once '../services/AliExpressScraper.php';
require_once '../services/ConsumScraper.php';
require_once '../services/ZaraScraper.php';
require_once '../services/ZalandoScraper.php';
require_once '../services/TemuScraper.php';
require_once '../services/LegoScraper.php';
require_once '../services/DecathlonScraper.php';
require_once '../services/MangoOutletScraper.php';
require_once '../services/MichaelKorsScraper.php';
require_once '../services/MangoScraper.php';
require_once '../services/IkeaScraper.php';
require_once '../services/SeleniumScraper.php';
require_once '../services/HeroScraper.php';
require_once '../services/PriceScraper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

$db = Database::getInstance()->getConnection();
$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $urlId = intval($input['url_id'] ?? 0);

    if ($urlId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de URL invalido']);
        exit;
    }

    $stmt = $db->prepare("SELECT id, url, product_name FROM monitored_urls WHERE id = ? AND user_id = ?");
    $stmt->execute([$urlId, $userId]);
    $urlData = $stmt->fetch();

    if (!$urlData) {
        echo json_encode(['success' => false, 'message' => 'URL no encontrada']);
        exit;
    }

    $url = $urlData['url'];

    try {
        $result = null;

        if (AmazonScraper::isAmazonURL($url)) {
            error_log("Amazon: Intentando con cURL scraper...");
            $scraper = new AmazonScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Amazon cURL falló, intentando con Selenium...");
                $scraper = new SeleniumScraper($urlId, $url, true);
                $result = $scraper->extractProductInfo();

                if (!$result['success']) {
                    error_log("Amazon Selenium headless falló, intentando sin headless...");
                    $scraper = new SeleniumScraper($urlId, $url, false);
                    $result = $scraper->extractProductInfo();
                }
            }
        }
        elseif (PcComponentesScraper::isPcComponentesURL($url)) {
            error_log("PcComponentes: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("PcComponentes Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (ElCorteInglesScraper::isElCorteInglesURL($url)) {
            error_log("El Corte Inglés: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("El Corte Inglés Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (CoolmodScraper::isCoolmodURL($url)) {
            error_log("Coolmod: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Coolmod Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (MediaMarktScraper::isMediaMarktURL($url)) {
            error_log("MediaMarkt: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("MediaMarkt Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (MercadonaScraper::isMercadonaURL($url)) {
            error_log("Mercadona: Usando API pública");
            $scraper = new MercadonaScraper($urlId, $url);
            $result = $scraper->extractProductInfo();
        }
        elseif (AliExpressScraper::isAliExpressURL($url)) {
            error_log("AliExpress: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("AliExpress Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (ConsumScraper::isConsumURL($url)) {
            error_log("Consum: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Consum Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (ZaraScraper::isZaraURL($url)) {
            error_log("Zara: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Zara Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (ZalandoScraper::isZalandoURL($url)) {
            error_log("Zalando: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Zalando Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (TemuScraper::isTemuURL($url)) {
            error_log("Temu: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Temu Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (LegoScraper::isLegoURL($url)) {
            error_log("LEGO: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("LEGO Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (DecathlonScraper::isDecathlonURL($url)) {
            error_log("Decathlon: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Decathlon Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (MangoOutletScraper::isMangoOutletURL($url)) {
            error_log("Mango Outlet: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Mango Outlet Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (MichaelKorsScraper::isMichaelKorsURL($url)) {
            error_log("Michael Kors: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result['success']) {
                error_log("Michael Kors Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (MangoScraper::isMangoURL($url)) {
            error_log("Mango: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result["success"]) {
                error_log("Mango Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        elseif (IkeaScraper::isIkeaURL($url)) {
            error_log("IKEA: Usando Ulixee Hero");
            $scraper = new HeroScraper($urlId, $url);
            $result = $scraper->extractProductInfo();

            if (!$result["success"]) {
                error_log("IKEA Hero falló, intentando Selenium");
                $scraper = new SeleniumScraper($urlId, $url, false);
                $result = $scraper->extractProductInfo();
            }
        }
        else {
            error_log("Tienda desconocida: Usando scraper genérico");
            $result = PriceScraper::extractPrice($url);
            if ($result['success']) {
                $stmt = $db->prepare("UPDATE monitored_urls SET current_price = ?, last_checked = NOW() WHERE id = ?");
                $stmt->execute([$result['price'], $urlId]);
                $stmt = $db->prepare("INSERT INTO price_history (url_id, price) VALUES (?, ?)");
                $stmt->execute([$urlId, $result['price']]);
            }
        }

        if ($result['success']) {
            echo json_encode([
                'success' => true,
                'message' => 'Datos actualizados correctamente',
                'data' => $result
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Error al extraer datos: ' . ($result['error'] ?? 'Error desconocido')
            ]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
    }
}
?>
