-- SilentMultiPanel Tables Creation Script
-- Run this on your cPanel MySQL database

-- Create Referral Codes table
CREATE TABLE IF NOT EXISTS `referral_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `created_by` int NOT NULL,
  `bonus_amount` decimal(10,2) DEFAULT '50.00',
  `usage_limit` int DEFAULT '1',
  `usage_count` int DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `two_factor_secret` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_enabled` boolean DEFAULT FALSE,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `referral_codes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create Stock Alerts table
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
