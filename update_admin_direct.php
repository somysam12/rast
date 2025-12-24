<?php
// DIRECT ADMIN UPDATE - Bypasses all app logic
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Direct Admin Update Tool</h1>";

// Set the new admin password here
$NEW_PASSWORD = "admin123";  // CHANGE THIS TO YOUR DESIRED PASSWORD

// Get current environment
$isReplit = file_exists('/home/runner/workspace');

if ($isReplit) {
    // REPLIT: Use SQLite
    echo "<p>Environment: <strong>Replit (SQLite)</strong></p>";
    $pdo = new PDO("sqlite:/home/runner/workspace/data/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    // CPANEL: Use MySQL with hardcoded credentials
    echo "<p>Environment: <strong>cPanel (MySQL)</strong></p>";
    $pdo = new PDO(
        "mysql:host=localhost;dbname=silentmu_silentdb;charset=utf8mb4",
        "silentmu_isam",
        "844121@LuvKush",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

echo "<p style='color:green'>✅ Connected to database</p>";

// Hash the password
$hashed = password_hash($NEW_PASSWORD, PASSWORD_DEFAULT);
echo "<p>Password: <strong>$NEW_PASSWORD</strong></p>";
echo "<p>Hash Length: <strong>" . strlen($hashed) . "</strong></p>";

// Get current admin
$stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("<p style='color:red'>❌ Admin user not found!</p>");
}

echo "<p>Found Admin: <strong>" . $admin['username'] . "</strong> (ID: " . $admin['id'] . ")</p>";

// UPDATE THE PASSWORD DIRECTLY
try {
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $result = $updateStmt->execute([$hashed, $admin['id']]);
    $affected = $updateStmt->rowCount();
    
    echo "<p>Update Query Executed: " . ($result ? "✅ YES" : "❌ NO") . "</p>";
    echo "<p>Rows Affected: <strong>$affected</strong></p>";
    
    if ($affected > 0) {
        // Verify it worked
        $verify = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $verify->execute([$admin['id']]);
        $data = $verify->fetch();
        
        $works = password_verify($NEW_PASSWORD, $data['password']);
        echo "<p>Verification: " . ($works ? "✅ PASSWORD WORKS!" : "❌ FAILED") . "</p>";
        
        echo "<hr>";
        echo "<h2 style='color:green'>✅ ADMIN PASSWORD UPDATED!</h2>";
        echo "<p><strong>Username:</strong> admin</p>";
        echo "<p><strong>New Password:</strong> $NEW_PASSWORD</p>";
        echo "<p>You can now login with these credentials.</p>";
    } else {
        echo "<p style='color:red'>❌ No rows affected - database issue!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

?>
