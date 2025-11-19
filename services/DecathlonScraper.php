<?php
class DecathlonScraper {
    public static function isDecathlonURL($url) {
        return strpos($url, 'decathlon.es') !== false;
    }
}
