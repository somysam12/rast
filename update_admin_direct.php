<?php
// DIRECT ADMIN UPDATE - Bypasses all app logic
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Direct Admin Update Tool</h1>";

// Set the new admin credentials here
$NEW_USERNAME = "ishashwat";
$NEW_EMAIL = "somysam29@gmail.com";
$NEW_PASSWORD = "844121@luv";

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
echo "<p>Username: <strong>$NEW_USERNAME</strong></p>";
echo "<p>Email: <strong>$NEW_EMAIL</strong></p>";
echo "<p>Password: <strong>$NEW_PASSWORD</strong></p>";
echo "<p>Hash Length: <strong>" . strlen($hashed) . "</strong></p>";

// Get current admin (can be any admin)
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("<p style='color:red'>❌ Admin user not found!</p>");
}

$admin_id = $admin['id'];
echo "<p>Found Admin ID: <strong>$admin_id</strong></p>";

// UPDATE THE USERNAME, EMAIL, AND PASSWORD DIRECTLY
try {
    $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
    $result = $updateStmt->execute([$NEW_USERNAME, $NEW_EMAIL, $hashed, $admin_id]);
    $affected = $updateStmt->rowCount();
    
    echo "<p>Update Query Executed: " . ($result ? "✅ YES" : "❌ NO") . "</p>";
    echo "<p>Rows Affected: <strong>$affected</strong></p>";
    
    if ($affected > 0) {
        // Verify it worked
        $verify = $pdo->prepare("SELECT username, email, password FROM users WHERE id = ?");
        $verify->execute([$admin_id]);
        $data = $verify->fetch();
        
        $pwd_works = password_verify($NEW_PASSWORD, $data['password']);
        echo "<p>Verification: " . ($pwd_works ? "✅ ALL CREDENTIALS SET!" : "❌ FAILED") . "</p>";
        
        echo "<hr>";
        echo "<h2 style='color:green'>✅ ADMIN UPDATED!</h2>";
        echo "<p><strong>Username:</strong> " . $data['username'] . "</p>";
        echo "<p><strong>Email:</strong> " . $data['email'] . "</p>";
        echo "<p><strong>Password:</strong> $NEW_PASSWORD</p>";
        echo "<p>You can now login with these credentials.</p>";
    } else {
        echo "<p style='color:red'>❌ No rows affected - database issue!</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

?>
