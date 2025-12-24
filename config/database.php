<?php
define('DB_PATH', '/home/runner/workspace/data/database.db');
define('DB_INITIALIZED', '/home/runner/workspace/data/.initialized');

if (!is_dir('/home/runner/workspace/data')) {
    mkdir('/home/runner/workspace/data', 0777, true);
}

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO("sqlite:" . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("PRAGMA foreign_keys = ON");
            $pdo->exec("PRAGMA journal_mode = WAL");
            $pdo->exec("PRAGMA synchronous = NORMAL");
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

function initializeDatabase() {
    if (file_exists(DB_INITIALIZED)) {
        return;
    }
    
    $pdo = getDBConnection();
    
    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            balance REAL DEFAULT 0.00,
            role TEXT DEFAULT 'user',
            referral_code TEXT UNIQUE,
            referred_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS user_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            session_id TEXT UNIQUE NOT NULL,
            ip_address TEXT,
            user_agent TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS mods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS license_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mod_id INTEGER NOT NULL,
            license_key TEXT UNIQUE NOT NULL,
            duration INTEGER NOT NULL,
            duration_type TEXT DEFAULT 'days',
            price REAL NOT NULL,
            status TEXT DEFAULT 'available',
            sold_to INTEGER,
            sold_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
            FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS mod_apks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            mod_id INTEGER NOT NULL,
            file_name TEXT NOT NULL,
            file_path TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount REAL NOT NULL,
            type TEXT NOT NULL,
            reference TEXT,
            description TEXT,
            status TEXT DEFAULT 'completed',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS referral_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT UNIQUE NOT NULL,
            created_by INTEGER NOT NULL,
            expires_at DATETIME NOT NULL,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS key_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            key_id INTEGER NOT NULL,
            request_type TEXT NOT NULL,
            mod_name TEXT NOT NULL,
            status TEXT DEFAULT 'pending',
            reason TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (key_id) REFERENCES license_keys(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS key_confirmations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            request_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT DEFAULT 'unread',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (request_id) REFERENCES key_requests(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS force_logouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            logged_out_by INTEGER NOT NULL,
            logout_limit INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
        "CREATE INDEX IF NOT EXISTS idx_users_email ON users(email)",
        "CREATE INDEX IF NOT EXISTS idx_user_sessions_user_id ON user_sessions(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_license_keys_status ON license_keys(status)",
        "CREATE INDEX IF NOT EXISTS idx_transactions_user_id ON transactions(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_key_requests_user_id ON key_requests(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_key_requests_status ON key_requests(status)",
        "CREATE INDEX IF NOT EXISTS idx_key_confirmations_user_id ON key_confirmations(user_id)",
        "CREATE INDEX IF NOT EXISTS idx_key_confirmations_status ON key_confirmations(status)",
    ];
    
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
    $stmt->execute(['admin']);
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, balance) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@example.com', $adminPassword, 'admin', 99999.00]);
    }
    
    touch(DB_INITIALIZED);
}

initializeDatabase();
?>
