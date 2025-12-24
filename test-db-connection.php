<?php
// Database Connection Test Script
// This script tests if the MySQL connection works on cPanel

$isReplit = file_exists('/home/runner/workspace');

if ($isReplit) {
    echo "Running on Replit - Using SQLite\n";
    exit;
}

echo "<h2>Database Connection Test</h2>";
echo "Testing MySQL connection on cPanel...<br><br>";

// cPanel Credentials
$host = 'localhost';
$database = 'silentmu_silentdb';
$username = 'silentmu_isam';
$password = '844121@luvkush';

echo "Attempting to connect with:<br>";
echo "Host: " . htmlspecialchars($host) . "<br>";
echo "Database: " . htmlspecialchars($database) . "<br>";
echo "Username: " . htmlspecialchars($username) . "<br>";
echo "Password: " . str_repeat('*', strlen($password)) . "<br><br>";

try {
    $pdo = new PDO(
        "mysql:host=" . $host . ";dbname=" . $database . ";charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<span style='color: green; font-weight: bold;'>✓ Connection Successful!</span><br><br>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "Users in database: " . $result['count'] . "<br>";
    
    // Test admin account
    $stmt = $pdo->query("SELECT username FROM users WHERE role = 'admin' LIMIT 1");
    $admin = $stmt->fetch();
    if ($admin) {
        echo "Admin account found: " . htmlspecialchars($admin['username']) . "<br>";
    }
    
} catch(PDOException $e) {
    echo "<span style='color: red; font-weight: bold;'>✗ Connection Failed!</span><br>";
    echo "Error: " . htmlspecialchars($e->getMessage()) . "<br><br>";
    echo "Common causes:<br>";
    echo "1. Database doesn't exist<br>";
    echo "2. Username/Password incorrect<br>";
    echo "3. User doesn't have database privileges<br>";
    echo "4. MySQL service not running<br>";
}
?>
