<?php
// Performance & Caching Optimization - Add to all pages
function setCacheHeaders() {
    $path = $_SERVER['REQUEST_URI'] ?? '';
    
    // Cache static assets for 1 year
    if (preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot)$/i', $path)) {
        header('Cache-Control: public, max-age=31536000, immutable');
    }
    // Cache HTML pages for 1 hour (if not logged in)
    elseif (!isset($_SESSION['user_id'])) {
        header('Cache-Control: public, max-age=3600');
    }
    // No cache for authenticated users
    else {
        header('Cache-Control: private, no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
    }
    
    // Security headers
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Enable gzip if available
if (function_exists('ob_gzhandler')) {
    ob_start('ob_gzhandler');
}

setCacheHeaders();
?>
