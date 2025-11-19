<?php
class ZalandoScraper {
    public static function isZalandoURL($url) {
        return strpos($url, 'zalando.es') !== false;
    }
}
