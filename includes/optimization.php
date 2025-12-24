<?php
// Performance optimization
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Enable OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Gzip compression
if (!headers_sent() && isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
    if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
        ob_start('ob_gzhandler');
    }
}
?>
