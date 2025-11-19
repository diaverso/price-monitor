<?php
class MichaelKorsScraper {
    public static function isMichaelKorsURL($url) {
        return strpos($url, 'michaelkors.es') !== false;
    }
}
