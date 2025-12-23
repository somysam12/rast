<?php
$databaseUrl = getenv('DATABASE_URL') ?: getenv('SUPABASE_DATABASE_URL');
$parsedUrl = parse_url($databaseUrl);

define('DB_HOST', $parsedUrl['host']);
define('DB_PORT', $parsedUrl['port'] ?? 5432);
define('DB_USER', urldecode($parsedUrl['user']));
define('DB_PASS', urldecode($parsedUrl['pass']));
define('DB_NAME', ltrim($parsedUrl['path'], '/'));

function getDBConnection() {
    try {
        $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=prefer";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

function initializeDatabase() {
    $pdo = getDBConnection();
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        role VARCHAR(20) DEFAULT 'user',
        referral_code VARCHAR(20) UNIQUE,
        referred_by VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS mods (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS license_keys (
        id SERIAL PRIMARY KEY,
        mod_id INT NOT NULL REFERENCES mods(id) ON DELETE CASCADE,
        license_key VARCHAR(100) UNIQUE NOT NULL,
        duration INT NOT NULL,
        duration_type VARCHAR(20) DEFAULT 'days',
        price DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'available',
        sold_to INT REFERENCES users(id) ON DELETE SET NULL,
        sold_at TIMESTAMP NULL,
        device_id VARCHAR(255) NULL,
        activated_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS mod_apks (
        id SERIAL PRIMARY KEY,
        mod_id INT NOT NULL REFERENCES mods(id) ON DELETE CASCADE,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        amount DECIMAL(10,2) NOT NULL,
        type VARCHAR(20) NOT NULL,
        reference VARCHAR(100),
        description TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS referral_codes (
        id SERIAL PRIMARY KEY,
        code VARCHAR(20) UNIQUE NOT NULL,
        created_by INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        expires_at TIMESTAMP NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        session_id VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, session_id)
    )");
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance) VALUES (:username, :email, :password, :role, :balance)");
        $stmt->execute([
            ':username' => 'admin',
            ':email' => 'admin@example.com',
            ':password' => $adminPassword,
            ':role' => 'admin',
            ':balance' => 99999.00
        ]);
    }
}
?>
