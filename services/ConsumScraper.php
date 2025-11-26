<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class ConsumScraper {
    private $db;
    private $urlId;
    private $url;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
    }

    public static function isConsumURL($url) {
        return strpos($url, 'tienda.consum.es') !== false ||
               strpos($url, 'consum.es') !== false;
    }
}
?>
