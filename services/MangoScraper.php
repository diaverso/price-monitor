<?php
class MangoScraper {
    public static function isMangoURL($url) {
        return strpos($url, 'shop.mango.com') !== false;
    }
}
