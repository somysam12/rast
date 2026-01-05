-- SQL Update Script for SilentMultiPanel
-- Run this in your phpMyAdmin or MySQL console

-- 1. Create the missing stock_alerts table
CREATE TABLE IF NOT EXISTS stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mod_id INT NOT NULL,
    mod_name VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (mod_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Verify referral_codes table structure
-- This table was already present but ensure indices for performance
ALTER TABLE referral_codes ADD INDEX IF NOT EXISTS (created_by);

-- Note: The syntax fixes (SQLite to MySQL) were applied in the PHP code 
-- by using NOW() instead of datetime('now'). 
-- No direct SQL schema changes are required for that fix beyond the table creation above.
