<?php
// Set timezone to Indian Standard Time (IST)
date_default_timezone_set('Asia/Kolkata');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Cache-Control: public, max-age=3600');
}
?>
