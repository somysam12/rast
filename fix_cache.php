<?php
// Add cache control headers to all PHP files
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
echo "Cache headers added to new requests\n";
?>
