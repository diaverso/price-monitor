<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class AliExpressScraper {
    private $db;
    private $urlId;
    private $url;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
    }

    public static function isAliExpressURL($url) {
        return strpos($url, 'aliexpress.com') !== false ||
               strpos($url, 'aliexpress.es') !== false ||
               strpos($url, 'es.aliexpress.com') !== false;
    }
}
?>
