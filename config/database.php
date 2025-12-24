<?php
// SilentMultiPanel Database Configuration
// Auto-detects: SQLite (Replit), MySQL (cPanel), PostgreSQL (Render)

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $isReplit = file_exists('/home/runner/workspace');
        $databaseUrl = getenv('DATABASE_URL');
        
        // PRIORITY 1: Replit Local Development - Always use SQLite
        if ($isReplit) {
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
            return $pdo;
        }
        
        // PRIORITY 2: cPanel/Production - Check for PostgreSQL first (Render)
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
            return $pdo;
        }
        
        // PRIORITY 3: cPanel/Production - Use MySQL (default for non-Replit)
        // Use environment variables if available, otherwise use hardcoded cPanel credentials
        $host = getenv('DB_HOST') ?: 'localhost';
        $database = getenv('DB_NAME') ?: 'silentmu_silentdb';
        $username = getenv('DB_USER') ?: 'silentmu_isam';
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
        
    } catch(PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        // Show detailed error for debugging
        $errorMsg = "Database connection failed.\n\n";
        $errorMsg .= "Error: " . $e->getMessage() . "\n\n";
        $errorMsg .= "To debug:\n";
        $errorMsg .= "1. Check test-db-connection.php in browser\n";
        $errorMsg .= "2. Verify credentials: silentmu_isam / 844121@luvkush\n";
        $errorMsg .= "3. Verify database: silentmu_silentdb\n";
        die($errorMsg);
    }
}

function initializeDatabase() {
    $isReplit = file_exists('/home/runner/workspace');
    
    // Only auto-create tables for Replit (SQLite)
    // For cPanel/Production, tables are created via fresh_database.sql import
    if (!$isReplit) {
        return;
    }
    
    if (file_exists('/home/runner/workspace/data/.initialized')) {
        return;
    }
    
    $pdo = getDBConnection();
    
    // SQLite table creation
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(150) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(12,2) DEFAULT 0.00,
            role VARCHAR(50) DEFAULT 'user',
            referral_code VARCHAR(50) UNIQUE,
            referred_by INTEGER,
            status VARCHAR(50) DEFAULT 'active',
            last_login TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            is_active BOOLEAN DEFAULT 1,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS mods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(150) NOT NULL,
            description TEXT,
            category VARCHAR(50),
            version VARCHAR(20),
            status VARCHAR(50) DEFAULT 'active',
            icon_url VARCHAR(500),
            download_count INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS license_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mod_id INTEGER NOT NULL,
            license_key VARCHAR(255) UNIQUE NOT NULL,
            duration INTEGER NOT NULL,
            duration_type VARCHAR(50) DEFAULT 'days',
            price DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) DEFAULT 'available',
            sold_to INTEGER,
            sold_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            last_used TIMESTAMP NULL,
            activation_count INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS mod_apks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mod_id INTEGER NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_size BIGINT NOT NULL,
            file_hash VARCHAR(64),
            download_count INTEGER DEFAULT 0,
            version VARCHAR(20),
            uploaded_by INTEGER,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference VARCHAR(100),
            description TEXT,
            payment_method VARCHAR(50),
            status VARCHAR(50) DEFAULT 'pending',
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS referral_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(50) UNIQUE NOT NULL,
            created_by INTEGER NOT NULL,
            discount_percent DECIMAL(5,2) DEFAULT 0,
            bonus_amount DECIMAL(10,2) DEFAULT 0,
            max_uses INTEGER DEFAULT -1,
            uses_count INTEGER DEFAULT 0,
            expires_at TIMESTAMP NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS key_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            key_id INTEGER NOT NULL,
            request_type VARCHAR(50) NOT NULL,
            mod_name VARCHAR(150),
            reason TEXT,
            status VARCHAR(50) DEFAULT 'pending',
            admin_notes TEXT,
            reviewed_by INTEGER,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (key_id) REFERENCES license_keys(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS key_confirmations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            request_id INTEGER,
            action_type VARCHAR(50) NOT NULL,
            message TEXT,
            status VARCHAR(50) DEFAULT 'unread',
            action_date TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES key_requests(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS force_logouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            logged_out_by INTEGER NOT NULL,
            reason VARCHAR(255),
            logout_limit INTEGER DEFAULT 0,
            is_global BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title VARCHAR(200) NOT NULL,
            message TEXT,
            type VARCHAR(50) DEFAULT 'info',
            related_table VARCHAR(50),
            related_id INTEGER,
            is_read BOOLEAN DEFAULT 0,
            action_url VARCHAR(500),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            read_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            app_name VARCHAR(150) NOT NULL,
            app_package VARCHAR(255),
            description TEXT,
            status VARCHAR(50) DEFAULT 'active',
            api_key VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS activity_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER NOT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(50),
            entity_id INTEGER,
            old_value TEXT,
            new_value TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value LONGTEXT,
            description VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ];
    
    try {
        foreach ($tables as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                error_log("Table creation info: " . $e->getMessage());
            }
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
        $stmt->execute();
        $result = $stmt->fetch();
        $adminCount = $result['count'] ?? 0;
        
        if ($adminCount == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 99999.00]);
        }
        
        @touch('/home/runner/workspace/data/.initialized');
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
    }
}

// Auto-initialize database (only for Replit)
initializeDatabase();
?>
