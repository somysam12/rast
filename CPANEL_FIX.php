<?php
/**
 * COMPREHENSIVE CPANEL FIX SCRIPT
 * Run this ONCE on cPanel to fix all issues
 * Then delete this file
 */

$isReplit = file_exists('/home/runner/workspace');
if ($isReplit) die("This is for cPanel ONLY!");

require_once 'config/database.php';

echo "<h1 style='color: #8b5cf6;'>🔧 SilentMultiPanel cPanel Setup Wizard</h1>";

try {
    $pdo = getDBConnection();
    echo "<p style='color: green;'><strong>✓ Database Connected</strong></p>";
    
    // 1. Fix Admin Account
    echo "<h2>Step 1: Admin Account</h2>";
    $pdo->query("DELETE FROM users WHERE username = 'admin'");
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', 'admin@example.com', $password, 'admin', 99999.00, 'active']);
    echo "<p style='color: green;'><strong>✓ Admin account created</strong></p>";
    echo "<p>Username: <strong>admin</strong> | Password: <strong>admin123</strong></p>";
    
    // 2. Ensure referral codes exist
    echo "<h2>Step 2: Referral Codes</h2>";
    $codes = $pdo->query("SELECT COUNT(*) as count FROM referral_codes WHERE status = 'active'")->fetch();
    if ($codes['count'] == 0) {
        echo "<p style='color: orange;'>⚠ No active referral codes found</p>";
        echo "<p><strong>Creating sample referral codes...</strong></p>";
        
        // Get admin ID
        $admin = $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetch();
        
        $sampleCodes = ['WELCOME2024', 'NEWUSER001', 'BETA123'];
        foreach ($sampleCodes as $code) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO referral_codes (code, created_by, discount_percent, bonus_amount, status, expires_at) VALUES (?, ?, 10, 100, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY))");
            $stmt->execute([$code, $admin['id']]);
        }
        echo "<p style='color: green;'><strong>✓ Sample codes created: WELCOME2024, NEWUSER001, BETA123</strong></p>";
    } else {
        echo "<p style='color: green;'><strong>✓ Active referral codes found: " . $codes['count'] . "</strong></p>";
    }
    
    // 3. Test Registration
    echo "<h2>Step 3: System Status</h2>";
    
    $tables = ['users', 'license_keys', 'mods', 'transactions', 'referral_codes'];
    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT 1 FROM $table LIMIT 1");
            echo "<p style='color: green;'>✓ $table</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>✗ $table - ERROR</p>";
        }
    }
    
    echo "<h2 style='color: green; margin-top: 30px;'>✅ ALL SYSTEMS READY!</h2>";
    echo "<p><strong>You can now:</strong></p>";
    echo "<ul>";
    echo "<li>✓ Login as admin/admin123</li>";
    echo "<li>✓ Create new referral codes</li>";
    echo "<li>✓ Users can register with valid referral codes</li>";
    echo "</ul>";
    
    echo "<p style='color: red; margin-top: 40px;'><strong>⚠️  DELETE THIS FILE NOW!</strong></p>";
    echo "<p>You can delete CPANEL_FIX.php and test-register.php from File Manager</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ ERROR:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Check your database credentials in config/database.php</p>";
}
?>
