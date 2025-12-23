<?php
$pages = [
    'index.php' => 'Homepage',
    'login.php' => 'Login Page',
    'register.php' => 'Registration',
    'health.php' => 'Health Check',
];

echo "Page Loading Test:\n";
echo "==================\n\n";

foreach ($pages as $page => $name) {
    $content = file_get_contents('http://localhost:5000/' . $page);
    $hasHTML = strpos($content, '<!DOCTYPE') !== false || strpos($content, '<html') !== false;
    $hasTitle = preg_match('/<title>(.*?)<\/title>/', $content, $matches);
    
    echo "$name ($page):\n";
    echo "  - Has HTML: " . ($hasHTML ? "✓" : "✗") . "\n";
    echo "  - Has Title: " . ($hasTitle ? "✓ (" . $matches[1] . ")" : "✗") . "\n";
    echo "  - Size: " . strlen($content) . " bytes\n\n";
}
?>
