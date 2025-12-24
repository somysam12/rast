<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔧 Automatic Password Update Fix</h2>";

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
    echo "<p style='color:green'>✅ Connected to Database</p>";
} catch(Exception $e) {
    die("<p style='color:red'>❌ Connection failed: " . $e->getMessage() . "</p>");
}

// Step 1: Check password column size
echo "<h3>Step 1: Check Password Column</h3>";
$result = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'password'")->fetch(PDO::FETCH_ASSOC);
echo "<p>Current Type: <strong>" . $result['Type'] . "</strong></p>";

// If column is too small, expand it
if (strpos($result['Type'], 'VARCHAR(60)') !== false || strpos($result['Type'], 'VARCHAR(64)') !== false) {
    echo "<p style='color:red'>⚠️ Password column too small! Expanding to VARCHAR(255)...</p>";
    try {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL");
        echo "<p style='color:green'>✅ Column expanded successfully</p>";
    } catch (Exception $e) {
        echo "<p style='color:red'>❌ Failed to expand: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:green'>✅ Password column is adequate (" . $result['Type'] . ")</p>";
}

// Step 2: Check for triggers
echo "<h3>Step 2: Check for Triggers</h3>";
$triggers = $pdo->query("SHOW TRIGGERS WHERE `table` = 'users'")->fetchAll(PDO::FETCH_ASSOC);
if (empty($triggers)) {
    echo "<p style='color:green'>✅ No blocking triggers found</p>";
} else {
    echo "<p style='color:red'>⚠️ Found triggers - these might block updates:</p>";
    foreach ($triggers as $t) {
        echo "<p>Trigger: " . $t['Trigger'] . " (" . $t['Event'] . " " . $t['Timing'] . ")</p>";
    }
}

// Step 3: Update admin password
echo "<h3>Step 3: Update Admin Password</h3>";
$newPassword = "newadmin" . date('YmdHi');  // Create unique temporary password
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

echo "<p>Attempting to update admin password...</p>";

try {
    // Try method 1: Direct ID update
    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE username = 'admin'");
    $result = $stmt->execute([$newHash]);
    $affected = $stmt->rowCount();
    
    echo "<p>Update Executed: " . ($result ? "✅ YES" : "❌ NO") . "</p>";
    echo "<p>Rows Affected: <strong>$affected</strong></p>";
    
    if ($affected > 0) {
        echo "<p style='color:green; font-weight:bold'>✅ PASSWORD UPDATED SUCCESSFULLY!</p>";
        echo "<p><strong>New Temporary Password: $newPassword</strong></p>";
        echo "<p><strong>Hash Length: " . strlen($newHash) . " characters</strong></p>";
        
        // Verify
        $verify = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
        $verify->execute();
        $data = $verify->fetch();
        echo "<p>Verification: " . (password_verify($newPassword, $data['password']) ? "✅ WORKS" : "❌ FAILED") . "</p>";
    } else {
        echo "<p style='color:red'>⚠️ No rows affected - checking why...</p>";
        
        // Check if admin exists
        $check = $pdo->query("SELECT COUNT(*) as cnt FROM users WHERE username = 'admin'");
        $row = $check->fetch();
        echo "<p>Admin user exists: " . ($row['cnt'] > 0 ? "YES" : "NO") . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Step 4: Direct test update
echo "<h3>Step 4: Alternative Update Methods</h3>";

// Method A: Using raw PDO exec
echo "<p><strong>Method A: Using PDO exec</strong></p>";
try {
    $hash = password_hash("test" . time(), PASSWORD_DEFAULT);
    $hash_escaped = addslashes($hash);
    $pdo->exec("UPDATE users SET password = '$hash_escaped' WHERE username = 'admin' LIMIT 1");
    echo "<p style='color:green'>✅ Executed successfully</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Error: " . $e->getMessage() . "</p>";
}

// Method B: Check if there are any constraints
echo "<p><strong>Method B: Check Constraints</strong></p>";
$constraints = $pdo->query("SELECT CONSTRAINT_NAME, TABLE_NAME FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS WHERE TABLE_NAME = 'users'")->fetchAll();
if (empty($constraints)) {
    echo "<p style='color:green'>✅ No CHECK constraints found</p>";
} else {
    echo "<p style='color:red'>⚠️ Found constraints:</p>";
    foreach ($constraints as $c) {
        echo "<p>" . $c['CONSTRAINT_NAME'] . "</p>";
    }
}

// Step 5: Final admin verification
echo "<h3>Step 5: Final Admin User Status</h3>";
$admin = $pdo->query("SELECT id, username, email, role, password FROM users WHERE username = 'admin'")->fetch();
echo "<pre>";
echo "ID: " . $admin['id'] . "\n";
echo "Username: " . $admin['username'] . "\n";
echo "Email: " . $admin['email'] . "\n";
echo "Role: " . $admin['role'] . "\n";
echo "Password Hash: " . substr($admin['password'], 0, 30) . "...\n";
echo "Hash Length: " . strlen($admin['password']) . "\n";
echo "</pre>";

?>
