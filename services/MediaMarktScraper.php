<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class MediaMarktScraper {

    private $db;
    private $urlId;
    private $url;

    public function __construct($urlId, $url) {
        $this->db = Database::getInstance()->getConnection();
        $this->urlId = $urlId;
        $this->url = $url;
    }

    /**
     * Método estático para verificar si una URL es de MediaMarkt
     */
    public static function isMediaMarktURL($url) {
        return strpos($url, 'mediamarkt.es') !== false || strpos($url, 'mediamarkt.com') !== false;
    }
}
?>
