-- SilentMultiPanel Tables Creation Script
-- Run this on your cPanel MySQL database

-- 1. Create Users table (Master Table)
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` decimal(12,2) DEFAULT '0.00',
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'user',
  `referral_code` varchar(50) COLLATE utf8mb4_unicode_ci UNIQUE,
  `referred_by` int DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_enabled` boolean DEFAULT FALSE,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `referred_by` (`referred_by`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Mods table
CREATE TABLE IF NOT EXISTS `mods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create Referral Codes table
CREATE TABLE IF NOT EXISTS `referral_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `created_by` int NOT NULL,
  `bonus_amount` decimal(10,2) DEFAULT '50.00',
  `usage_limit` int DEFAULT '1',
  `usage_count` int DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `referral_codes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Create Stock Alerts table
CREATE TABLE IF NOT EXISTS `stock_alerts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `mod_id` int NOT NULL,
  `mod_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `mod_id` (`mod_id`),
  CONSTRAINT `stock_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_alerts_ibfk_2` FOREIGN KEY (`mod_id`) REFERENCES `mods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Create User Keys table
CREATE TABLE IF NOT EXISTS `user_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `key_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `max_devices` int DEFAULT 1,
  `is_active` tinyint DEFAULT 1,
  `expiry` datetime DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Create Key Devices table
CREATE TABLE IF NOT EXISTS `key_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_id` int NOT NULL,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_type` enum('VPN','MOBILE','WIFI') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `is_active` tinyint DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `key_id` (`key_id`),
  CONSTRAINT `key_devices_ibfk_1` FOREIGN KEY (`key_id`) REFERENCES `user_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Create Key Resets table
CREATE TABLE IF NOT EXISTS `key_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_id` int NOT NULL,
  `reset_count` int DEFAULT 0,
  `last_reset` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `key_id` (`key_id`),
  CONSTRAINT `key_resets_ibfk_1` FOREIGN KEY (`key_id`) REFERENCES `user_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Create Device Activity table
CREATE TABLE IF NOT EXISTS `device_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_id` int NOT NULL,
  `device_fingerprint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isp` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_type` enum('VPN','MOBILE','WIFI') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `key_id` (`key_id`),
  CONSTRAINT `device_activity_ibfk_1` FOREIGN KEY (`key_id`) REFERENCES `user_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Create Admin Commands table
CREATE TABLE IF NOT EXISTS `admin_commands` (
  `id` int NOT NULL AUTO_INCREMENT,
  `command` enum('CLEAR_COOKIES','CLEAR_SESSION','FORCE_LOGOUT','DISABLE_APP') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` enum('ALL','KEY','USER') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `is_executed` tinyint DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;