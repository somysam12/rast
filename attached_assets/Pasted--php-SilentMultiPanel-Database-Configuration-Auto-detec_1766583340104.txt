<?php
// SilentMultiPanel Database Configuration
// Auto-detects and connects to MySQL (cPanel), PostgreSQL (Render), or SQLite (Replit)

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
        else if (!empty(getenv('DB_HOST')) || !empty(getenv('DB_NAME'))) {
            // Use environment variables from cPanel config
            $host = getenv('DB_HOST') ?: 'localhost';
            $database = getenv('DB_NAME') ?: 'silentmu_silentdb';
            $username = getenv('DB_USER') ?: 'silentmu_silentdb';
            $password = getenv('DB_PASS') ?: '844121@luvkush';
            
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
        else {
            $dataDir = '/home/runner/workspace/data';
            
            if (!is_dir($dataDir)) {
                @mkdir($dataDir, 0777, true);
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
    $isMysql = !empty(getenv('DB_HOST')) || !empty(getenv('DB_NAME'));
    
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
    
    // MySQL compatible table creation
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(12,2) DEFAULT 0.00,
            role ENUM('admin', 'user') DEFAULT 'user',
            referral_code VARCHAR(50) UNIQUE,
            referred_by INT,
            status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL,
            KEY idx_username (username),
            KEY idx_email (email),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            is_active BOOLEAN DEFAULT 1,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id)
        )",
        
        "CREATE TABLE IF NOT EXISTS mods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            description TEXT,
            category VARCHAR(50),
            version VARCHAR(20),
            status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
            icon_url VARCHAR(500),
            download_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_status (status),
            KEY idx_category (category),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS license_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mod_id INT NOT NULL,
            license_key VARCHAR(255) UNIQUE NOT NULL,
            duration INT NOT NULL,
            duration_type ENUM('hours', 'days', 'months', 'years') DEFAULT 'days',
            price DECIMAL(10,2) NOT NULL,
            status ENUM('available', 'sold', 'blocked', 'expired') DEFAULT 'available',
            sold_to INT,
            sold_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            last_used TIMESTAMP NULL,
            activation_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL,
            KEY idx_license_key (license_key),
            KEY idx_status (status),
            KEY idx_mod_id (mod_id),
            KEY idx_sold_to (sold_to),
            KEY idx_expires_at (expires_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS mod_apks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mod_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT NOT NULL,
            file_hash VARCHAR(64),
            download_count INT DEFAULT 0,
            version VARCHAR(20),
            uploaded_by INT,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
            KEY idx_mod_id (mod_id),
            KEY idx_file_name (file_name)
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            type ENUM('purchase', 'balance_add', 'refund', 'withdrawal', 'bonus') NOT NULL,
            reference VARCHAR(100),
            description TEXT,
            payment_method VARCHAR(50),
            status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_user_id (user_id),
            KEY idx_type (type),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS referral_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) UNIQUE NOT NULL,
            created_by INT NOT NULL,
            discount_percent DECIMAL(5,2) DEFAULT 0,
            bonus_amount DECIMAL(10,2) DEFAULT 0,
            max_uses INT DEFAULT -1,
            uses_count INT DEFAULT 0,
            expires_at TIMESTAMP NULL,
            status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_code (code),
            KEY idx_status (status),
            KEY idx_expires_at (expires_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS key_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            key_id INT NOT NULL,
            request_type ENUM('block', 'reset', 'replace', 'extend') NOT NULL,
            mod_name VARCHAR(150),
            reason TEXT,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            admin_notes TEXT,
            reviewed_by INT,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (key_id) REFERENCES license_keys(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            KEY idx_status (status),
            KEY idx_user_id (user_id),
            KEY idx_key_id (key_id),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS key_confirmations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            request_id INT,
            action_type ENUM('block', 'reset', 'approve', 'reject', 'activate', 'expire') NOT NULL,
            message TEXT,
            status ENUM('unread', 'read', 'archived') DEFAULT 'unread',
            action_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES key_requests(id) ON DELETE SET NULL,
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS force_logouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            logged_out_by INT NOT NULL,
            reason VARCHAR(255),
            logout_limit INT DEFAULT 0,
            is_global BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT,
            type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
            related_table VARCHAR(50),
            related_id INT,
            is_read BOOLEAN DEFAULT 0,
            action_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_user_id (user_id),
            KEY idx_is_read (is_read),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            app_name VARCHAR(150) NOT NULL,
            app_package VARCHAR(255),
            description TEXT,
            status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
            api_key VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_user_id (user_id),
            KEY idx_app_package (app_package),
            KEY idx_api_key (api_key)
        )",
        
        "CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INT,
            old_value TEXT,
            new_value TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
            KEY idx_admin_id (admin_id),
            KEY idx_entity_type (entity_type),
            KEY idx_created_at (created_at)
        )",
        
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value LONGTEXT,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_setting_key (setting_key)
        )"
    ];
    
    try {
        // Create tables
        foreach ($tables as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
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
