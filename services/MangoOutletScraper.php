<?php
class MangoOutletScraper {
    public static function isMangoOutletURL($url) {
        return strpos($url, 'mangooutlet.com') !== false;
    }
}
