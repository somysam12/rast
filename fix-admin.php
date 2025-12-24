<?php
/**
 * ADMIN ACCOUNT FIX SCRIPT
 * Run this ONCE then delete it
 * This fixes the password hash issue
 */

require_once 'config/database.php';

$isReplit = file_exists('/home/runner/workspace');

// Only run on cPanel (not Replit)
if ($isReplit) {
    die("This script is for cPanel deployment only!");
}

echo "<h1>Admin Account Fix</h1>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: blue;'><strong>✓ Database Connected</strong></p>";
    
    // Check if users table exists
    try {
        $pdo->query("SELECT 1 FROM users LIMIT 1");
        echo "<p style='color: blue;'><strong>✓ Users Table Exists</strong></p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Users Table Missing!</strong></p>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
        echo "<p><strong>Solution:</strong> Import fresh_database.sql in phpMyAdmin first!</p>";
        exit;
    }
    
    // Delete any existing admin accounts
    $pdo->query("DELETE FROM users WHERE username = 'admin'");
    echo "<p style='color: green;'><strong>✓ Cleared old admin accounts</strong></p>";
    
    // Generate proper bcrypt hash for password 'admin123'
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    // Create new admin account
    $stmt = $pdo->prepare("
        INSERT INTO users (username, email, password, role, balance, status, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->execute([
        'admin',
        'admin@example.com',
        $passwordHash,
        'admin',
        99999.00,
        'active'
    ]);
    
    echo "<p style='color: green;'><strong>✓ Admin account created successfully!</strong></p>";
    
    // Verify admin was created
    $verify = $pdo->query("SELECT username, role FROM users WHERE username = 'admin' LIMIT 1")->fetch();
    if ($verify) {
        echo "<p style='color: green;'><strong>✓ Verified: Admin account exists</strong></p>";
        echo "<p><strong>Login with:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Username:</strong> admin</li>";
        echo "<li><strong>Password:</strong> admin123</li>";
        echo "</ul>";
    }
    
    echo "<p style='color: red; margin-top: 20px;'><strong>⚠️  IMPORTANT:</strong> Delete this file now!</p>";
    echo "<p><code>You can delete fix-admin.php from cPanel File Manager</code></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>✗ Database Error:</strong></p>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Check:</strong></p>";
    echo "<ul>";
    echo "<li>Database credentials in config/database.php</li>";
    echo "<li>Database name: silentmu_silentdb</li>";
    echo "<li>Username: silentmu_isam</li>";
    echo "<li>Password: 844121@LuvKush</li>";
    echo "</ul>";
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
