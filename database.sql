



-- -----------------------------------------------------------------------------
-- 1. Table structure for `users`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NOT NULL UNIQUE,
  `email` VARCHAR(100) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT '/assets/images/avatar-default.png',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_phone` (`phone`),
  INDEX `idx_users_username` (`username`),
  INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table structure for `wallets`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `wallets`;
CREATE TABLE `wallets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `bonus_balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_deposited` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_withdrawn` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_wallets_balance` (`balance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table structure for `transactions`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `type` ENUM('deposit', 'withdrawal', 'bet', 'win', 'refund', 'bonus') NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `balance_before` DECIMAL(15, 2) NOT NULL,
  `balance_after` DECIMAL(15, 2) NOT NULL,
  `reference_id` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'completed',
  `payment_method` VARCHAR(50) DEFAULT 'UPI',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_trans_user` (`user_id`),
  INDEX `idx_trans_type` (`type`),
  INDEX `idx_trans_status` (`status`),
  INDEX `idx_trans_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table structure for `categories`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `icon` VARCHAR(255) NOT NULL,
  `display_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cat_order` (`display_order`),
  INDEX `idx_cat_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table structure for `games`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `image` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `display_order` INT NOT NULL DEFAULT 0,
  `is_recommended` TINYINT(1) NOT NULL DEFAULT 0,
  `config_json` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
  INDEX `idx_games_slug` (`slug`),
  INDEX `idx_games_rec` (`is_recommended`),
  INDEX `idx_games_status` (`status`),
  INDEX `idx_games_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table structure for `banners`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `image` VARCHAR(255) NOT NULL,
  `link` VARCHAR(255) NOT NULL DEFAULT '#',
  `display_order` INT NOT NULL DEFAULT 0,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_banner_status` (`status`),
  INDEX `idx_banner_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table structure for `announcements`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `content` TEXT NOT NULL,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `priority` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ann_status` (`status`),
  INDEX `idx_ann_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table structure for `game_rounds`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `game_rounds`;
CREATE TABLE `game_rounds` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `round_number` BIGINT NOT NULL UNIQUE,
  `game_slug` VARCHAR(50) NOT NULL DEFAULT 'win-go-1m',
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `status` ENUM('active', 'calculating', 'completed') NOT NULL DEFAULT 'active',
  `result_mode` ENUM('auto', 'manual') NOT NULL DEFAULT 'auto',
  `manual_result` VARCHAR(20) DEFAULT NULL,
  `result_status` ENUM('pending', 'locked', 'published') NOT NULL DEFAULT 'pending',
  `manually_set_at` DATETIME DEFAULT NULL,
  `manually_set_by` INT DEFAULT NULL,
  `result_number` INT DEFAULT NULL,
  `result_color` VARCHAR(20) DEFAULT NULL,
  `result_size` VARCHAR(10) DEFAULT NULL,
  `result_published` TINYINT(1) NOT NULL DEFAULT 0,
  `total_bets_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_payout_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  INDEX `idx_rounds_number` (`round_number`),
  INDEX `idx_rounds_status` (`status`),
  INDEX `idx_rounds_published` (`result_published`),
  INDEX `idx_rounds_game` (`game_slug`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table structure for `game_bets`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `game_bets`;
CREATE TABLE `game_bets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `round_id` INT NOT NULL,
  `bet_choice` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `multiplier` DECIMAL(6, 2) NOT NULL DEFAULT 1.96,
  `win_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'won', 'lost', 'refunded') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`round_id`) REFERENCES `game_rounds`(`id`) ON DELETE CASCADE,
  INDEX `idx_bets_user` (`user_id`),
  INDEX `idx_bets_round` (`round_id`),
  INDEX `idx_bets_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. Table structure for `notifications`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `type` VARCHAR(50) NOT NULL DEFAULT 'system',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notif_user` (`user_id`),
  INDEX `idx_notif_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. Table structure for `support_tickets`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `support_tickets`;
CREATE TABLE `support_tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `ticket_number` VARCHAR(30) NOT NULL UNIQUE,
  `subject` VARCHAR(200) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'General',
  `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_ticket_user` (`user_id`),
  INDEX `idx_ticket_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 12. Table structure for `support_messages`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `support_messages`;
CREATE TABLE `support_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `sender_type` ENUM('user', 'admin') NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE,
  INDEX `idx_msg_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 13. Table structure for `settings`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `key_name` VARCHAR(50) PRIMARY KEY,
  `value_content` TEXT NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- INITIAL SEED DATA
-- =============================================================================

-- Seed Default Admin Account: admin / Admin@123456
INSERT INTO `users` (`id`, `username`, `phone`, `email`, `password_hash`, `role`, `status`)
VALUES (1, 'admin', '9999999999', 'admin@sikkim.game', '$2y$10$EBGUFLDzPZJwpE/OUTN9zOo.dFF1UFix4cscKB4cmmwx0FfbIXiDG', 'admin', 'active')
ON DUPLICATE KEY UPDATE `username`=`username`;

-- Seed Admin Wallet
INSERT INTO `wallets` (`id`, `user_id`, `balance`, `bonus_balance`, `total_deposited`, `total_withdrawn`)
VALUES (1, 1, 10000.00, 0.00, 0.00, 0.00)
ON DUPLICATE KEY UPDATE `user_id`=`user_id`;

-- Seed Game Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `display_order`, `status`) VALUES
(1, 'Hot Slots', 'hot-slots', 'hot-slots', 1, 'active'),
(2, 'Lottery', 'lottery', 'lottery', 2, 'active'),
(3, 'Original', 'original', 'original', 3, 'active'),
(4, 'Slots', 'slots', 'slots', 4, 'active'),
(5, 'Fishing', 'fishing', 'fishing', 5, 'active'),
(6, 'Sports', 'sports', 'sports', 6, 'active'),
(7, 'Casino', 'casino', 'casino', 7, 'active'),
(8, 'Rummy', 'rummy', 'rummy', 8, 'active')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed Recommended Games
INSERT INTO `games` (`id`, `category_id`, `name`, `slug`, `image`, `description`, `status`, `display_order`, `is_recommended`, `config_json`) VALUES
(1, 2, 'Colour Game', 'colour-game', '/assets/images/game-wingo.png', 'Real-time Sikkim Colour Prediction game with Red, Green and Violet outcomes, sequential rounds, and instant payout.', 'active', 1, 1, '{\"options\":[{\"key\":\"red\",\"name\":\"RED\",\"multiplier\":2.00,\"color\":\"#ef4444\"},{\"key\":\"green\",\"name\":\"GREEN\",\"multiplier\":2.00,\"color\":\"#10b981\"},{\"key\":\"violet\",\"name\":\"VIOLET\",\"multiplier\":4.50,\"color\":\"#8b5cf6\"}],\"round_duration\":45,\"lock_before_end\":5}'),
(2, 2, 'Win Go 1Min', 'win-go-1m', '/assets/images/game-wingo.png', 'Classic Sikkim 1-Minute Color and Number Prediction round with guaranteed payout.', 'active', 2, 1, NULL),
(3, 3, 'Aviator Blast', 'aviator-blast', '/assets/images/game-aviator.png', 'Real-time multiplier aircraft game with instant cash out.', 'active', 3, 1, NULL),
(4, 6, 'Cricket Premier', 'cricket-premier', '/assets/images/game-cricket.png', 'Predict match overs, wickets and boundary streaks with premier multipliers.', 'active', 4, 1, NULL),
(5, 1, 'Royal 777 Deluxe', 'royal-777', '/assets/images/game-slots777.png', 'Triple lucky reels, wild scatters, and high jackpot multiplier rounds.', 'active', 5, 1, NULL)
ON DUPLICATE KEY UPDATE `name`=`name`;

-- Seed Promotional Banners
INSERT INTO `banners` (`id`, `title`, `description`, `image`, `link`, `display_order`, `status`) VALUES
(1, 'PLAY WITH SKILL & WIN BIG REWARDS', 'Join the elite Sikkim gaming arena with instant UPI deposits and fast payouts.', '/assets/images/banner-pubg.png', '/games.php', 1, 'active'),
(2, 'DAILY REBATE & VIP CASHBACK', 'Earn up to 2.5% daily cashback on all active games with no turnover requirement.', '/assets/images/banner-vip.png', '/wallet.php', 2, 'active'),
(3, '100% FIRST DEPOSIT BONUS', 'Deposit Rs 500 or more today and get an extra Rs 500 bonus directly in your wallet.', '/assets/images/banner-bonus.png', '/wallet.php', 3, 'active')
ON DUPLICATE KEY UPDATE `title`=`title`;

-- Seed System Announcements
INSERT INTO `announcements` (`id`, `content`, `status`, `priority`) VALUES
(1, 'Welcome to SIKKIM game platform, we will serve you wholeheartedly! Instant automated deposits and fast round settlements.', 'active', 10),
(2, 'Notice: Daily maintenance window is strictly handled seamlessly with zero downtime. Enjoy non-stop gaming!', 'active', 5)
ON DUPLICATE KEY UPDATE `content`=`content`;

-- Seed Initial Active Rounds
INSERT INTO `game_rounds` (`id`, `round_number`, `game_slug`, `start_time`, `end_time`, `status`, `result_mode`, `result_published`)
VALUES 
(1, 321001, 'win-go-1m', NOW(), DATE_ADD(NOW(), INTERVAL 60 SECOND), 'active', 'auto', 0),
(2, 321, 'colour-game', NOW(), DATE_ADD(NOW(), INTERVAL 45 SECOND), 'active', 'auto', 0)
ON DUPLICATE KEY UPDATE `game_slug`=`game_slug`;
