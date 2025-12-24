<?php
// Direct Password Update Test Script
// Upload this to cPanel to debug password update issues

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Password Update Test</h2>";

// Database connection
$host = 'localhost';
$database = 'silentmu_silentdb';
$username = 'silentmu_isam';
$password = '844121@LuvKush';

try {
    $pdo = new PDO(
        "mysql:host=" . $host . ";dbname=" . $database . ";charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<p style='color:green'>✅ Database connected</p>";
} catch(Exception $e) {
    echo "<p style='color:red'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Get admin user
$stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = 'admin'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "<p style='color:red'>❌ Admin user not found</p>";
    exit;
}

echo "<p><strong>Current Admin:</strong></p>";
echo "<ul>";
echo "<li>ID: " . $user['id'] . "</li>";
echo "<li>Username: " . $user['username'] . "</li>";
echo "<li>Current Password Hash: " . substr($user['password'], 0, 30) . "...</li>";
echo "<li>Current Hash Length: " . strlen($user['password']) . "</li>";
echo "</ul>";

// Test new password
$newPassword = "testpass123";
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

echo "<p><strong>New Password Test:</strong></p>";
echo "<ul>";
echo "<li>New Password: $newPassword</li>";
echo "<li>New Hash: " . substr($newHash, 0, 30) . "...</li>";
echo "<li>New Hash Length: " . strlen($newHash) . "</li>";
echo "<li>Verification Test: " . (password_verify($newPassword, $newHash) ? "✅ PASS" : "❌ FAIL") . "</li>";
echo "</ul>";

// Attempt update
echo "<p><strong>Database Update Attempt:</strong></p>";

try {
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $result = $updateStmt->execute([$newHash, $user['id']]);
    $affected = $updateStmt->rowCount();
    
    echo "<ul>";
    echo "<li>Query Executed: " . ($result ? "✅ YES" : "❌ NO") . "</li>";
    echo "<li>Rows Affected: " . $affected . "</li>";
    echo "</ul>";
    
    if ($affected > 0) {
        echo "<p style='color:green'>✅ UPDATE SUCCESSFUL!</p>";
        
        // Verify the update
        $verifyStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $verifyStmt->execute([$user['id']]);
        $updated = $verifyStmt->fetch();
        
        echo "<p><strong>Verification:</strong></p>";
        echo "<ul>";
        echo "<li>New Hash in DB: " . substr($updated['password'], 0, 30) . "...</li>";
        echo "<li>Password Verify: " . (password_verify($newPassword, $updated['password']) ? "✅ WORKS" : "❌ FAILED") . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color:red'>❌ UPDATE FAILED - No rows affected!</p>";
        echo "<p>This might indicate:</p>";
        echo "<ul>";
        echo "<li>User ID doesn't exist</li>";
        echo "<li>Database TRIGGER preventing update</li>";
        echo "<li>Database constraint issue</li>";
        echo "<li>MySQL user permissions issue</li>";
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ UPDATE ERROR: " . $e->getMessage() . "</p>";
}

?>
