<?php
class TemuScraper {
    public static function isTemuURL($url) {
        return strpos($url, 'temu.com') !== false;
    }
}
