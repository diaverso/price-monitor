<?php
class LegoScraper {
    public static function isLegoURL($url) {
        return strpos($url, 'lego.com') !== false;
    }
}
