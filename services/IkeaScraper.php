<?php
class IkeaScraper {
    public static function isIkeaURL($url) {
        return strpos($url, 'ikea.com') !== false;
    }
}
