<?php
// Create Admin Account with Proper Password Hash
// Run this ONCE then delete it

require_once 'config/database.php';

$isReplit = file_exists('/home/runner/workspace');

if ($isReplit) {
    echo "This script is for cPanel only!";
    exit;
}

try {
    $pdo = getDBConnection();
    
    // Delete existing admin if any
    $pdo->query("DELETE FROM users WHERE username = 'admin'");
    
    // Create new admin with password 'admin123'
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance) 
                          VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $password, 'admin', 99999.00]);
    
    echo "<h2 style='color: green;'>✓ Admin account created successfully!</h2>";
    echo "<p><strong>Username:</strong> admin</p>";
    echo "<p><strong>Password:</strong> admin123</p>";
    echo "<p style='color: red;'><strong>IMPORTANT:</strong> Delete this file after creation!</p>";
    
} catch(Exception $e) {
    echo "<h2 style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>
