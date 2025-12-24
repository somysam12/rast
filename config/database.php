<?php
// Support both PostgreSQL (Render/Production) and SQLite (Local Development)
// Automatically detect environment

$isProduction = !empty(getenv('DATABASE_URL'));

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo !== null) {
        return $pdo;
    }
    
    try {
        $databaseUrl = getenv('DATABASE_URL');
        
        if (!empty($databaseUrl)) {
            // PostgreSQL Connection (Render Production)
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
        } else {
            // SQLite Connection (Local Development)
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
    $isPostgres = !empty($databaseUrl);
    
    // Skip initialization check for PostgreSQL (tables won't exist on first run)
    // Only use file check for SQLite
    if (!$isPostgres && file_exists('/home/runner/workspace/data/.initialized')) {
        return;
    }
    
    $pdo = getDBConnection();
    
    // Check if tables already exist (for PostgreSQL)
    if ($isPostgres) {
        try {
            $stmt = $pdo->query("SELECT to_regclass('public.users')");
            if ($stmt->fetchColumn() !== null) {
                return; // Tables already exist
            }
        } catch (Exception $e) {
            // Tables don't exist, continue with creation
        }
    }
    
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0.00,
            role VARCHAR(50) DEFAULT 'user',
            referral_code VARCHAR(255) UNIQUE,
            referred_by INTEGER,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            session_id VARCHAR(255) UNIQUE NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS mods (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS license_keys (
            id SERIAL PRIMARY KEY,
            mod_id INTEGER NOT NULL,
            license_key VARCHAR(255) UNIQUE NOT NULL,
            duration INTEGER NOT NULL,
            duration_type VARCHAR(50) DEFAULT 'days',
            price DECIMAL(10,2) NOT NULL,
            status VARCHAR(50) DEFAULT 'available',
            sold_to INTEGER,
            sold_at TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS mod_apks (
            id SERIAL PRIMARY KEY,
            mod_id INTEGER NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path TEXT NOT NULL,
            file_size BIGINT NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            type VARCHAR(50) NOT NULL,
            reference VARCHAR(255),
            description TEXT,
            status VARCHAR(50) DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS referral_codes (
            id SERIAL PRIMARY KEY,
            code VARCHAR(255) UNIQUE NOT NULL,
            created_by INTEGER NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS key_requests (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            key_id INTEGER NOT NULL,
            request_type VARCHAR(50) NOT NULL,
            mod_name VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            reason TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (key_id) REFERENCES license_keys(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS key_confirmations (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            request_id INTEGER NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(50) DEFAULT 'unread',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES key_requests(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS force_logouts (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            logged_out_by INTEGER NOT NULL,
            logout_limit INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE
        )"
    ];
    
    // Create indexes
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_license_keys_status ON license_keys(status)",
        "CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON transactions(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_key_requests_user_id ON key_requests(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_key_requests_status ON key_requests(status)",
        "CREATE INDEX IF NOT EXISTS idx_key_confirmations_user_id ON key_confirmations(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_key_confirmations_status ON key_confirmations(status)"
    ];
    
    try {
        // Create tables
        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
        
        // Create indexes
        foreach ($indexes as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Exception $e) {
                // Index might already exist, ignore
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
        if (!$isPostgres) {
            @touch('/home/runner/workspace/data/.initialized');
        }
    } catch (Exception $e) {
        error_log("Database initialization error: " . $e->getMessage());
        // Don't die here, let the app continue
    }
}

// Auto-initialize database
initializeDatabase();
?>
