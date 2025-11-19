<?php
class ZaraScraper {
    public static function isZaraURL($url) {
        return strpos($url, 'zara.com') !== false;
    }
}
