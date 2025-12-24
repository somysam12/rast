<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Complete Password Update Diagnosis</h2>";

// Connect to cPanel MySQL
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
    echo "<p style='color:green'>✅ Connected to cPanel MySQL</p>";
} catch(Exception $e) {
    die("<p style='color:red'>❌ Connection failed: " . $e->getMessage() . "</p>");
}

// Check users table structure
echo "<h3>1. Table Structure</h3>";
$result = $pdo->query("DESCRIBE users");
$columns = $result->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td>" . $col['Field'] . "</td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . $col['Null'] . "</td>";
    echo "<td>" . $col['Key'] . "</td>";
    echo "<td>" . $col['Default'] . "</td>";
    echo "<td>" . $col['Extra'] . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check password column size
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'");
$pwdCol = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Password Column Type:</strong> " . $pwdCol['Type'] . "</p>";

// Check for triggers
echo "<h3>2. Check for Triggers</h3>";
$triggers = $pdo->query("SHOW TRIGGERS WHERE `table` = 'users'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($triggers)) {
    echo "<p style='color:green'>✅ No triggers found</p>";
} else {
    echo "<p style='color:red'>⚠️ Found " . count($triggers) . " trigger(s):</p>";
    foreach ($triggers as $t) {
        echo "<pre>" . print_r($t, true) . "</pre>";
    }
}

// Get admin user
echo "<h3>3. Admin User Info</h3>";
$stmt = $pdo->prepare("SELECT id, username, email, password, role FROM users WHERE username = 'admin' LIMIT 1");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    die("<p style='color:red'>❌ Admin user not found!</p>");
}

echo "<ul>";
echo "<li>ID: " . $admin['id'] . "</li>";
echo "<li>Username: " . $admin['username'] . "</li>";
echo "<li>Email: " . $admin['email'] . "</li>";
echo "<li>Role: " . $admin['role'] . "</li>";
echo "<li>Current Password Hash Length: " . strlen($admin['password']) . "</li>";
echo "<li>Hash Sample: " . substr($admin['password'], 0, 40) . "...</li>";
echo "</ul>";

// Test password hash
echo "<h3>4. Password Hash Test</h3>";
$testPass = "newpassword123";
$newHash = password_hash($testPass, PASSWORD_DEFAULT);
echo "<ul>";
echo "<li>Test Password: $testPass</li>";
echo "<li>Generated Hash Length: " . strlen($newHash) . "</li>";
echo "<li>Password Verify: " . (password_verify($testPass, $newHash) ? "✅ OK" : "❌ FAILED") . "</li>";
echo "</ul>";

// Try different update methods
echo "<h3>5. Update Methods Test</h3>";

// Method 1: Simple UPDATE
echo "<p><strong>Method 1: Simple UPDATE</strong></p>";
try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $result = $stmt->execute([$newHash, $admin['id']]);
    $affected = $stmt->rowCount();
    echo "<p>Result: " . ($result ? "✅ Executed" : "❌ Failed") . " | Rows Affected: $affected</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Method 2: UPDATE with WHERE username
echo "<p><strong>Method 2: UPDATE WHERE username = 'admin'</strong></p>";
try {
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = ?");
    $result = $stmt->execute([$newHash, 'admin']);
    $affected = $stmt->rowCount();
    echo "<p>Result: " . ($result ? "✅ Executed" : "❌ Failed") . " | Rows Affected: $affected</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Method 3: Direct query
echo "<p><strong>Method 3: Direct Query String</strong></p>";
try {
    $hash_escaped = addslashes($newHash);
    $query = "UPDATE users SET password = '$hash_escaped' WHERE username = 'admin'";
    $result = $pdo->exec($query);
    echo "<p>Result: ✅ Executed | Rows Affected: $result</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Verify what's actually in the database now
echo "<h3>6. Verification</h3>";
$stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
$stmt->execute();
$current = $stmt->fetch();
echo "<p>Current hash in DB: " . substr($current['password'], 0, 40) . "...</p>";
echo "<p>Hash changed: " . ($current['password'] !== $admin['password'] ? "✅ YES" : "❌ NO") . "</p>";

// Check if UPDATE privilege exists
echo "<h3>7. User Privileges Check</h3>";
$privs = $pdo->query("SHOW GRANTS FOR CURRENT_USER")->fetchAll(PDO::FETCH_NUM);
echo "<p>Current User Privileges:</p>";
foreach ($privs as $p) {
    echo "<p>" . htmlspecialchars($p[0]) . "</p>";
}

?>
