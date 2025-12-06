-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 19, 2025 at 08:21 PM
-- Server version: 10.11.11-MariaDB-cll-lve
-- PHP Version: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `princeaalyan_gmn`
--

-- --------------------------------------------------------

--
-- Table structure for table `license_keys`
--

CREATE TABLE `license_keys` (
  `id` int(11) NOT NULL,
  `mod_id` int(11) NOT NULL,
  `license_key` varchar(100) NOT NULL,
  `duration` int(11) NOT NULL,
  `duration_type` enum('hours','days','months') DEFAULT 'days',
  `price` decimal(10,2) NOT NULL,
  `status` enum('available','sold') DEFAULT 'available',
  `sold_to` int(11) DEFAULT NULL,
  `sold_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `license_keys`
--

INSERT INTO `license_keys` (`id`, `mod_id`, `license_key`, `duration`, `duration_type`, `price`, `status`, `sold_to`, `sold_at`, `created_at`) VALUES
(1, 1, 'ADMINSER-1D-A1B2C3D4E5', 1, 'days', 90.00, 'sold', 5, '2025-09-19 04:54:19', '2025-09-19 04:22:23'),
(2, 1, 'ADMINSER-7D-F6G7H8I9J0', 7, 'days', 350.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(3, 1, 'ADMINSER-30D-K1L2M3N4O5', 30, 'days', 850.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(4, 2, 'KINGMOD-5H-P6Q7R8S9T0', 5, 'hours', 30.00, 'sold', 5, '2025-09-19 07:39:34', '2025-09-19 04:22:23'),
(5, 2, 'KINGMOD-1D-U1V2W3X4Y5', 1, 'days', 60.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(6, 2, 'KINGMOD-3D-Z6A7B8C9D0', 3, 'days', 150.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(7, 2, 'KINGMOD-7D-E1F2G3H4I5', 7, 'days', 300.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(8, 2, 'KINGMOD-30D-J6K7L8M9N0', 30, 'days', 600.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(9, 2, 'KINGMOD-60D-O1P2Q3R4S5', 60, 'days', 1200.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(10, 3, 'TRXMOD-1D-T6U7V8W9X0', 1, 'days', 75.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(11, 3, 'TRXMOD-7D-Y1Z2A3B4C5', 7, 'days', 400.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(12, 4, 'ZEROKIL-1D-D6E7F8G9H0', 1, 'days', 50.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(13, 5, 'ZEROKIL-1D-I1J2K3L4M5', 1, 'days', 45.00, 'available', NULL, NULL, '2025-09-19 04:22:23'),
(14, 2, 'ADMINSER-1D-qwfrwe', 1, 'days', 10.00, 'sold', 5, '2025-09-19 04:54:12', '2025-09-19 04:32:28'),
(15, 12, 'PRINCEXL-1D-0BQFSU4HAZ', 1, 'days', 111.00, 'available', NULL, NULL, '2025-09-19 07:06:32'),
(16, 12, 'PRINCEXL-1D-0BQFSU4HAZPRINCEXL-1D-0BQFSU4HAZPRINCEXL-1D-0BQFSU4HAZPRINCEXL-1D-0BQFSU4HAZPRINCEXL-1D-', 1, 'days', 111.00, 'sold', 5, '2025-09-19 07:15:42', '2025-09-19 07:06:32'),
(17, 1, 'ADMINS-1D-1639-UDA4CMGY', 1, 'days', 10.00, 'available', 6, '2025-09-19 14:25:01', '2025-09-19 14:08:25');

-- --------------------------------------------------------

--
-- Table structure for table `mods`
--

CREATE TABLE `mods` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `mods`
--

INSERT INTO `mods` (`id`, `name`, `description`, `status`, `created_at`) VALUES
(1, 'ADMIN SERVER', 'Premium admin server mod with advanced features', 'active', '2025-09-19 04:20:39'),
(2, 'KING MOD APK', '4.0 (KUMARES) - King of mods with unlimited features', 'active', '2025-09-19 04:20:39'),
(3, 'TRX MOD', 'High-performance mod with TRX optimization', 'active', '2025-09-19 04:20:39'),
(4, 'ZERO KILL 120 FPS', '4.0 - Zero kill mod with 120 FPS support', 'inactive', '2025-09-19 04:20:39'),
(5, 'Zero kill mod key', '4.0 - Zero kill mod key for premium access', 'active', '2025-09-19 04:20:39'),
(6, 'ADMIN SERVER', 'Premium admin server mod with advanced features', 'inactive', '2025-09-19 04:22:23'),
(8, 'TRX MOD', 'High-performance mod with TRX optimization', 'active', '2025-09-19 04:22:23'),
(9, 'ZERO KILL 120 FPS', '4.0 - Zero kill mod with 120 FPS support', 'inactive', '2025-09-19 04:22:23'),
(10, 'Zero kill mod key', '4.0 - Zero kill mod key for premium access', 'active', '2025-09-19 04:22:23'),
(11, 'Prince test mod', 'hack', 'active', '2025-09-19 04:31:34'),
(12, 'PRINCE X LOADER', 'PRINCE X LOADER', 'active', '2025-09-19 07:05:05');

-- --------------------------------------------------------

--
-- Table structure for table `mod_apks`
--

CREATE TABLE `mod_apks` (
  `id` int(11) NOT NULL,
  `mod_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_codes`
--

CREATE TABLE `referral_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `referral_codes`
--

INSERT INTO `referral_codes` (`id`, `code`, `created_by`, `expires_at`, `status`, `created_at`) VALUES
(1, 'REF12345', 1, '2025-10-19 04:22:23', 'active', '2025-09-19 04:22:23'),
(2, 'REF67890', 1, '2025-10-19 04:22:23', 'active', '2025-09-19 04:22:23'),
(3, 'REF13579', 1, '2025-10-19 04:22:23', 'active', '2025-09-19 04:22:23');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('purchase','balance_add','refund') NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `amount`, `type`, `reference`, `status`, `created_at`) VALUES
(1, 2, -90.00, 'purchase', 'License purchase #1', 'completed', '2025-09-19 04:22:23'),
(2, 2, -350.00, 'purchase', 'License purchase #2', 'completed', '2025-09-19 04:22:23'),
(3, 3, -60.00, 'purchase', 'License purchase #3', 'completed', '2025-09-19 04:22:23'),
(4, 3, -150.00, 'purchase', 'License purchase #4', 'completed', '2025-09-19 04:22:23'),
(5, 4, -75.00, 'purchase', 'License purchase #5', 'completed', '2025-09-19 04:22:23'),
(6, 2, 100.00, 'balance_add', 'Welcome bonus', 'completed', '2025-09-19 04:22:23'),
(7, 3, 100.00, 'balance_add', 'Welcome bonus', 'completed', '2025-09-19 04:22:23'),
(8, 4, 100.00, 'balance_add', 'Welcome bonus', 'completed', '2025-09-19 04:22:23'),
(9, 2, 59.00, 'balance_add', '1', 'completed', '2025-09-19 04:29:35'),
(10, 5, -10.00, 'purchase', 'License purchase #14', 'completed', '2025-09-19 04:54:12'),
(11, 5, -90.00, 'purchase', 'License purchase #1', 'completed', '2025-09-19 04:54:19'),
(12, 5, 1000.00, 'balance_add', '', 'completed', '2025-09-19 07:15:11'),
(13, 5, -111.00, 'purchase', 'License purchase #16', 'completed', '2025-09-19 07:15:42'),
(14, 5, -30.00, 'purchase', 'License purchase #4', 'completed', '2025-09-19 07:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `role` enum('admin','user') DEFAULT 'user',
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `balance`, `role`, `referral_code`, `referred_by`, `created_at`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$qOwheruqUz32uJN4qVoyvectZb/XtaXGXscJFk.MSeBcfm5Fs0hRi', 99999.00, 'admin', NULL, NULL, '2025-09-19 04:22:23'),
(2, 'testuser1', 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1059.00, 'user', 'ABC12345', NULL, '2025-09-19 04:22:23'),
(3, 'testuser2', 'user2@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 500.00, 'user', 'DEF67890', NULL, '2025-09-19 04:22:23'),
(4, 'testuser3', 'user3@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2500.00, 'user', 'GHI13579', NULL, '2025-09-19 04:22:23'),
(5, 'princeaalyan', 'princeaalyan2006@gmail.com', '$2y$10$5GK8huwUjrkn9Tx1cVqeiuKZXUIjNPe9mrOWslo4vzo6wkz701bCS', 859.00, 'user', 'CE9AE470', '', '2025-09-19 04:33:24'),
(6, 'Aditya268', 'rajbharadit165@gmail.com', '$2y$10$e1BTHyamtbX1mv3e1j8fUOk1MKzqPSzWJF89qBJj8FKRpy1q523oK', 90.00, 'user', 'AF0F340A', NULL, '2025-09-19 14:23:11');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `license_keys`
--
ALTER TABLE `license_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`),
  ADD KEY `idx_license_keys_mod_id` (`mod_id`),
  ADD KEY `idx_license_keys_status` (`status`),
  ADD KEY `idx_license_keys_sold_to` (`sold_to`);

--
-- Indexes for table `mods`
--
ALTER TABLE `mods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mod_apks`
--
ALTER TABLE `mod_apks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mod_id` (`mod_id`);

--
-- Indexes for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_referral_codes_code` (`code`),
  ADD KEY `idx_referral_codes_status` (`status`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transactions_user_id` (`user_id`),
  ADD KEY `idx_transactions_type` (`type`),
  ADD KEY `idx_transactions_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_users_username` (`username`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_referral_code` (`referral_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `license_keys`
--
ALTER TABLE `license_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `mods`
--
ALTER TABLE `mods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `mod_apks`
--
ALTER TABLE `mod_apks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_codes`
--
ALTER TABLE `referral_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `license_keys`
--
ALTER TABLE `license_keys`
  ADD CONSTRAINT `license_keys_ibfk_1` FOREIGN KEY (`mod_id`) REFERENCES `mods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `license_keys_ibfk_2` FOREIGN KEY (`sold_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mod_apks`
--
ALTER TABLE `mod_apks`
  ADD CONSTRAINT `mod_apks_ibfk_1` FOREIGN KEY (`mod_id`) REFERENCES `mods` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referral_codes`
--
ALTER TABLE `referral_codes`
  ADD CONSTRAINT `referral_codes_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
