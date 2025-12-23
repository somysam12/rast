<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== System Health Check ===\n\n";

// Check environment
echo "1. Environment Check:\n";
echo "  DATABASE_URL: " . (getenv('DATABASE_URL') ? "SET" : "NOT SET") . "\n";

// Check database connection
echo "\n2. Database Check:\n";
try {
    require_once 'config/database.php';
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT 1");
    echo "  ✓ Database connection: OK\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    echo "  ✓ Users table: OK (" . $stmt->fetchColumn() . " users)\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM mods");
    echo "  ✓ Mods table: OK (" . $stmt->fetchColumn() . " mods)\n";
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

// Check session
echo "\n3. Session Check:\n";
session_start();
echo "  Session ID: " . session_id() . "\n";
echo "  User ID in session: " . ($_SESSION['user_id'] ?? "NOT SET") . "\n";

// Check includes
echo "\n4. Include Files:\n";
echo "  auth.php: " . (file_exists('includes/auth.php') ? "✓" : "✗") . "\n";
echo "  functions.php: " . (file_exists('includes/functions.php') ? "✓" : "✗") . "\n";
echo "  database.php: " . (file_exists('config/database.php') ? "✓" : "✗") . "\n";

echo "\n=== End Health Check ===\n";
?>
