<?php
// Auto-detect database type based on environment
// Supports: PostgreSQL (Render), MySQL (cPanel), SQLite (Local Replit)

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $databaseUrl = getenv('DATABASE_URL');
        
        // PostgreSQL Connection (Render Production)
        if (!empty($databaseUrl) && strpos($databaseUrl, 'postgresql') !== false) {
            $dbUrl = parse_url($databaseUrl);
            
            $dsn = "pgsql:host=" . $dbUrl['host']
                   . ";port=" . ($dbUrl['port'] ?? 5432)
                   . ";dbname=" . ltrim($dbUrl['path'], '/');
            
            $pdo = new PDO(
                $dsn,
                $dbUrl['user'],
                $dbUrl['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5
                ]
            );
        }
        // MySQL Connection (cPanel / Traditional Hosting)
        else {
            // Try with environment variables first, fallback to defaults
            $host = getenv('DB_HOST') ?: 'localhost';
            $database = getenv('DB_NAME') ?: 'silentmu_isam';
            $username = getenv('DB_USER') ?: 'silentmu_isam';
            $password = getenv('DB_PASS') ?: 'silentmu_isam';
            
            $dsn = "mysql:host=" . $host 
                   . ";dbname=" . $database
                   . ";charset=utf8mb4";
            
            $pdo = new PDO(
                $dsn,
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            return $pdo;
        }
        
        // SQLite Connection (Local Development on Replit)
        if (true) {
            $dataDir = '/home/runner/workspace/data';
            
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }
            
            $dbPath = $dataDir . '/database.db';
            
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("PRAGMA foreign_keys = ON");
            $pdo->exec("PRAGMA journal_mode = WAL");
            $pdo->exec("PRAGMA synchronous = NORMAL");
        }
        
        return $pdo;
    } catch(PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        die("Database connection failed. Please check your configuration.\n");
    }
}

function initializeDatabase() {
    $databaseUrl = getenv('DATABASE_URL');
    $isPostgres = !empty($databaseUrl) && strpos($databaseUrl, 'postgresql') !== false;
    $isMysql = !empty(getenv('DB_HOST'));
    
    // Skip file check for hosted databases
    if (!$isPostgres && !$isMysql && file_exists('/home/runner/workspace/data/.initialized')) {
        return;
    }
    
    $pdo = getDBConnection();
    
    // Check if tables already exist (for hosted databases)
    if ($isPostgres || $isMysql) {
        try {
            if ($isPostgres) {
                $stmt = $pdo->query("SELECT to_regclass('public.users')");
                if ($stmt->fetchColumn() !== null) {
                    return; // Tables exist
                }
            } else {
                $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
                return; // If query succeeds, tables exist
            }
        } catch (Exception $e) {
            // Tables don't exist, continue with creation
        }
    }
    
    // SQL compatible with all three database types
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            role VARCHAR(50) DEFAULT 'user',
            referral_code VARCHAR(255) UNIQUE,
            referred_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS mods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS license_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mod_id INT NOT NULL,
            license_key VARCHAR(255) UNIQUE NOT NULL,
            duration INT NOT NULL,
            duration_type VARCHAR(50) DEFAULT 'days',
            price DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) DEFAULT 'available',
            sold_to INT,
            sold_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_sold_to (sold_to)
        )",
        
        "CREATE TABLE IF NOT EXISTS mod_apks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mod_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path TEXT NOT NULL,
            file_size BIGINT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference VARCHAR(255),
            description TEXT,
            status VARCHAR(50) DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id)
        )",
        
        "CREATE TABLE IF NOT EXISTS referral_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(255) UNIQUE NOT NULL,
            created_by INT NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS key_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            key_id INT NOT NULL,
            request_type VARCHAR(50) NOT NULL,
            mod_name VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (key_id) REFERENCES license_keys(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_user_id (user_id)
        )",
        
        "CREATE TABLE IF NOT EXISTS key_confirmations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            request_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES key_requests(id) ON DELETE CASCADE,
            INDEX idx_status (status)
        )",
        
        "CREATE TABLE IF NOT EXISTS force_logouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            logged_out_by INT NOT NULL,
            logout_limit INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];
    
    try {
        // Create tables
        foreach ($tables as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                // Table might already exist, continue
                error_log("Table creation info: " . $e->getMessage());
            }
        }
        
        // Create default admin user if none exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $stmt->execute();
        $result = $stmt->fetch();
        $adminCount = $result['count'] ?? 0;
        
        if ($adminCount == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 99999.00]);
        }
        
        // Mark as initialized for SQLite
        if (!$isPostgres && !$isMysql) {
            @touch('/home/runner/workspace/data/.initialized');
        }
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
    }
}

// Auto-initialize database
initializeDatabase();
?>
