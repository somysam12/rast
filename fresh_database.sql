-- ============================================
-- SILENTMULTIPANEL - FRESH DATABASE SCHEMA
-- Complete schema covering all features
-- ============================================
-- Note: Database should already be created in cPanel
-- Just paste this SQL directly in phpMyAdmin

-- ============================================
-- 1. USERS TABLE - User accounts & authentication
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    balance DECIMAL(12,2) DEFAULT 0.00 COMMENT 'User account balance in currency',
    role ENUM('admin', 'user') DEFAULT 'user' COMMENT 'User role - admin or regular user',
    referral_code VARCHAR(50) UNIQUE COMMENT 'Unique referral code for user',
    referred_by INT COMMENT 'User ID who referred this user',
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active' COMMENT 'Account status',
    last_login TIMESTAMP NULL COMMENT 'Last login time',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 2. USER SESSIONS TABLE - Session management
-- ============================================
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(255) UNIQUE NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    is_active BOOLEAN DEFAULT 1,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id)
);

-- ============================================
-- 3. MODS TABLE - Application/MOD management
-- ============================================
CREATE TABLE IF NOT EXISTS mods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(50) COMMENT 'MOD category (game, tool, etc)',
    version VARCHAR(20) COMMENT 'Current MOD version',
    status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    icon_url VARCHAR(500) COMMENT 'MOD icon/logo URL',
    download_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 4. LICENSE KEYS TABLE - License key management
-- ============================================
CREATE TABLE IF NOT EXISTS license_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mod_id INT NOT NULL,
    license_key VARCHAR(255) UNIQUE NOT NULL,
    duration INT NOT NULL COMMENT 'Duration value',
    duration_type ENUM('hours', 'days', 'months', 'years') DEFAULT 'days',
    price DECIMAL(10,2) NOT NULL,
    status ENUM('available', 'sold', 'blocked', 'expired') DEFAULT 'available',
    sold_to INT COMMENT 'User ID who bought this key',
    sold_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL COMMENT 'When license expires',
    last_used TIMESTAMP NULL COMMENT 'Last time key was used',
    activation_count INT DEFAULT 0 COMMENT 'How many times key was activated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
    FOREIGN KEY (sold_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_mod_id (mod_id),
    INDEX idx_sold_to (sold_to),
    INDEX idx_expires_at (expires_at)
);

-- ============================================
-- 5. MOD APK FILES TABLE - APK file uploads
-- ============================================
CREATE TABLE IF NOT EXISTS mod_apks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mod_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT NOT NULL COMMENT 'File size in bytes',
    file_hash VARCHAR(64) COMMENT 'SHA256 hash for integrity',
    download_count INT DEFAULT 0,
    version VARCHAR(20),
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mod_id) REFERENCES mods(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_mod_id (mod_id),
    INDEX idx_file_name (file_name)
);

-- ============================================
-- 6. TRANSACTIONS TABLE - Payment/balance history
-- ============================================
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    type ENUM('purchase', 'balance_add', 'refund', 'withdrawal', 'bonus') NOT NULL,
    reference VARCHAR(100) COMMENT 'Transaction reference ID',
    description TEXT COMMENT 'Transaction details',
    payment_method VARCHAR(50) COMMENT 'How user paid (stripe, paypal, etc)',
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 7. REFERRAL CODES TABLE - Referral system
-- ============================================
CREATE TABLE IF NOT EXISTS referral_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    created_by INT NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0 COMMENT 'Discount % for referee',
    bonus_amount DECIMAL(10,2) DEFAULT 0 COMMENT 'Bonus balance for referrer',
    max_uses INT DEFAULT -1 COMMENT '-1 for unlimited',
    uses_count INT DEFAULT 0,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_expires_at (expires_at)
);

-- ============================================
-- 8. KEY REQUESTS TABLE - Block/Reset requests
-- ============================================
CREATE TABLE IF NOT EXISTS key_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    key_id INT NOT NULL,
    request_type ENUM('block', 'reset', 'replace', 'extend') NOT NULL COMMENT 'Type of request',
    mod_name VARCHAR(150),
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT COMMENT 'Admin review notes',
    reviewed_by INT COMMENT 'Admin who reviewed this',
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (key_id) REFERENCES license_keys(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_key_id (key_id),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 9. KEY CONFIRMATIONS TABLE - Action confirmations
-- ============================================
CREATE TABLE IF NOT EXISTS key_confirmations (
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
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 10. FORCE LOGOUTS TABLE - Session termination
-- ============================================
CREATE TABLE IF NOT EXISTS force_logouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    logged_out_by INT NOT NULL COMMENT 'Admin who initiated logout',
    reason VARCHAR(255),
    logout_limit INT DEFAULT 0 COMMENT 'If > 0, limit future logins',
    is_global BOOLEAN DEFAULT 0 COMMENT 'Logout from all devices',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (logged_out_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 11. NOTIFICATIONS TABLE - User notifications
-- ============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    related_table VARCHAR(50) COMMENT 'Related table (license_keys, transactions, etc)',
    related_id INT COMMENT 'ID in related table',
    is_read BOOLEAN DEFAULT 0,
    action_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 12. APPLICATIONS TABLE - User applications
-- ============================================
CREATE TABLE IF NOT EXISTS applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_name VARCHAR(150) NOT NULL,
    app_package VARCHAR(255) COMMENT 'Android package name',
    description TEXT,
    status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
    api_key VARCHAR(255) UNIQUE COMMENT 'API key for app integration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_app_package (app_package),
    INDEX idx_api_key (api_key)
);

-- ============================================
-- 13. ACTIVITY LOG TABLE - Admin audit log
-- ============================================
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'What action was performed',
    entity_type VARCHAR(50) COMMENT 'What was modified (user, license, etc)',
    entity_id INT COMMENT 'ID of entity modified',
    old_value TEXT COMMENT 'Previous value',
    new_value TEXT COMMENT 'New value',
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_id (admin_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_created_at (created_at)
);

-- ============================================
-- 14. SETTINGS TABLE - System configuration
-- ============================================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- ============================================
-- DEFAULT DATA INSERTION
-- ============================================

-- Insert admin user (password: admin123)
INSERT IGNORE INTO users (username, email, password, role, balance) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 99999.00);

-- Insert sample mods
INSERT IGNORE INTO mods (name, description, category, version, status) VALUES
('ADMIN SERVER', 'Premium admin server mod with advanced features', 'game', '1.0.0', 'active'),
('KING MOD APK', '4.0 (KUMARES) - King of mods with unlimited features', 'game', '4.0', 'active'),
('TRX MOD', 'High-performance mod with TRX optimization', 'tool', '2.1.0', 'active'),
('ZERO KILL 120 FPS', '4.0 - Zero kill mod with 120 FPS support', 'game', '4.0', 'active'),
('VIP MOD PREMIUM', 'Premium VIP features unlocker', 'tool', '3.5.0', 'active');

-- Insert sample license keys (empty initially - admin adds these)
INSERT IGNORE INTO license_keys (mod_id, license_key, duration, duration_type, price, status) VALUES
(1, 'ADMIN-1D-FRESH001', 1, 'days', 90.00, 'available'),
(1, 'ADMIN-7D-FRESH002', 7, 'days', 350.00, 'available'),
(2, 'KING-1D-FRESH001', 1, 'days', 60.00, 'available'),
(2, 'KING-7D-FRESH002', 7, 'days', 300.00, 'available'),
(3, 'TRX-30D-FRESH001', 30, 'days', 500.00, 'available'),
(4, 'ZERO-1D-FRESH001', 1, 'days', 50.00, 'available'),
(5, 'VIP-30D-FRESH001', 30, 'days', 750.00, 'available');

-- Insert system settings
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('site_name', 'SilentMultiPanel', 'Website name'),
('site_description', 'Best Multipanel And Instant Support', 'Website description'),
('currency_symbol', '₹', 'Currency symbol for displaying prices'),
('timezone', 'Asia/Kolkata', 'Default timezone'),
('max_login_attempts', '5', 'Maximum failed login attempts before lockout'),
('session_timeout', '3600', 'Session timeout in seconds'),
('referral_bonus', '100', 'Bonus amount for successful referral'),
('maintenance_mode', '0', 'Is site in maintenance mode (1=yes, 0=no)');

-- ============================================
-- INDEX SUMMARY
-- ============================================
-- Key indexes for performance optimization:
-- - User lookups: username, email
-- - License status checks: status, expires_at, sold_to
-- - Transaction history: user_id, type, created_at
-- - Request tracking: status, user_id, created_at
-- - Notification filtering: user_id, is_read
-- - Audit trail: admin_id, entity_type, created_at

-- ============================================
-- END OF SCHEMA
-- ============================================
