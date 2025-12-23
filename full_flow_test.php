<?php
// Test full authentication flow
echo "=== Full Authentication Flow Test ===\n\n";

// Step 1: Get login page (creates session)
echo "Step 1: Accessing login page...\n";
$response = file_get_contents('http://localhost:5000/login.php');
preg_match('/<title>(.*?)<\/title>/', $response, $match);
echo "  ✓ Login page loaded: " . $match[1] . "\n\n";

// Step 2: Simulate login and follow redirects
echo "Step 2: Testing login credentials (admin/admin123)...\n";

// Use curl for proper session handling
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:5000/login.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'username=admin&password=admin123');
curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/test_cookies.txt');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  Response Code: $http_code\n";
echo "  Expected: 302 (redirect)\n";
if ($http_code == 302) {
    echo "  ✓ Login successful, redirecting...\n\n";
} else {
    echo "  ✗ Login failed\n";
}

// Step 3: Test dashboard access
echo "Step 3: Testing admin dashboard access (with session)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:5000/admin_dashboard.php');
curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/test_cookies.txt');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

preg_match('/<title>(.*?)<\/title>/', $response, $match);
echo "  Response Code: $http_code\n";
if ($http_code == 200 && !empty($match)) {
    echo "  Page Title: " . $match[1] . "\n";
    echo "  ✓ Admin Dashboard accessible\n";
} else {
    echo "  ✗ Dashboard inaccessible\n";
}

echo "\n=== Test Complete ===\n";
?>
