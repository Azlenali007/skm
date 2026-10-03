<?php
/**
 * Sikkim Gaming Platform - Database Schema & Initializer
 * Uses real MySQL schema with PDO
 */

require_once __DIR__ . '/database.php';

function initDatabase(): void {
    $pdo = getDB();

    // 1. Users Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `phone` VARCHAR(20) NOT NULL UNIQUE,
            `email` VARCHAR(100) NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('user', 'admin') NOT NULL DEFAULT 'user',
            `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
            `avatar` VARCHAR(255) DEFAULT '/assets/images/avatar-default.png',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_users_phone (`phone`),
            INDEX idx_users_username (`username`),
            INDEX idx_users_role (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Wallets Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `wallets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL UNIQUE,
            `balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `bonus_balance` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `total_deposited` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `total_withdrawn` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_wallets_balance (`balance`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 3. Transactions Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `transactions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `type` ENUM('deposit', 'withdrawal', 'bet', 'win', 'refund', 'bonus') NOT NULL,
            `amount` DECIMAL(15, 2) NOT NULL,
            `balance_before` DECIMAL(15, 2) NOT NULL,
            `balance_after` DECIMAL(15, 2) NOT NULL,
            `reference_id` VARCHAR(100) NULL,
            `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'completed',
            `payment_method` VARCHAR(50) DEFAULT 'UPI',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_trans_user (`user_id`),
            INDEX idx_trans_type (`type`),
            INDEX idx_trans_status (`status`),
            INDEX idx_trans_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 4. Categories Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(50) NOT NULL,
            `slug` VARCHAR(50) NOT NULL UNIQUE,
            `icon` VARCHAR(255) NOT NULL,
            `display_order` INT NOT NULL DEFAULT 0,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cat_order (`display_order`),
            INDEX idx_cat_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 5. Games Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `games` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `category_id` INT NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `image` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `display_order` INT NOT NULL DEFAULT 0,
            `is_recommended` TINYINT(1) NOT NULL DEFAULT 0,
            `config_json` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE,
            INDEX idx_games_slug (`slug`),
            INDEX idx_games_rec (`is_recommended`),
            INDEX idx_games_status (`status`),
            INDEX idx_games_order (`display_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 6. Banners Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `banners` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(150) NOT NULL,
            `description` VARCHAR(255) NULL,
            `image` VARCHAR(255) NOT NULL,
            `link` VARCHAR(255) NOT NULL DEFAULT '#',
            `display_order` INT NOT NULL DEFAULT 0,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_banner_status (`status`),
            INDEX idx_banner_order (`display_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 7. Announcements Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `announcements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `content` TEXT NOT NULL,
            `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
            `priority` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ann_status (`status`),
            INDEX idx_ann_priority (`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 8. Game Rounds Table (Authoritative Sequential Server-Side Rounds)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `game_rounds` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `round_number` BIGINT NOT NULL UNIQUE,
            `game_slug` VARCHAR(50) NOT NULL DEFAULT 'win-go-1m',
            `start_time` DATETIME NOT NULL,
            `end_time` DATETIME NOT NULL,
            `status` ENUM('active', 'calculating', 'completed') NOT NULL DEFAULT 'active',
            `result_number` INT NULL,
            `result_color` VARCHAR(20) NULL,
            `result_size` VARCHAR(10) NULL,
            `result_published` TINYINT(1) NOT NULL DEFAULT 0,
            `total_bets_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `total_payout_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `completed_at` DATETIME NULL,
            INDEX idx_rounds_number (`round_number`),
            INDEX idx_rounds_status (`status`),
            INDEX idx_rounds_published (`result_published`),
            INDEX idx_rounds_game (`game_slug`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 9. Game Bets Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `game_bets` (
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
            INDEX idx_bets_user (`user_id`),
            INDEX idx_bets_round (`round_id`),
            INDEX idx_bets_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 10. Notifications Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NULL,
            `title` VARCHAR(150) NOT NULL,
            `message` TEXT NOT NULL,
            `is_read` TINYINT(1) NOT NULL DEFAULT 0,
            `type` VARCHAR(50) NOT NULL DEFAULT 'system',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_notif_user (`user_id`),
            INDEX idx_notif_read (`is_read`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 11. Support Tickets Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `support_tickets` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `ticket_number` VARCHAR(30) NOT NULL UNIQUE,
            `subject` VARCHAR(200) NOT NULL,
            `category` VARCHAR(50) NOT NULL DEFAULT 'General',
            `status` ENUM('open', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'open',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_ticket_user (`user_id`),
            INDEX idx_ticket_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 12. Support Messages Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `support_messages` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `ticket_id` INT NOT NULL,
            `sender_type` ENUM('user', 'admin') NOT NULL,
            `user_id` INT NOT NULL,
            `message` TEXT NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets`(`id`) ON DELETE CASCADE,
            INDEX idx_msg_ticket (`ticket_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 13. System Settings Table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `key_name` VARCHAR(50) PRIMARY KEY,
            `value_content` TEXT NOT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Seed Administrator if not exists
    $adminCheck = $pdo->prepare("SELECT id FROM `users` WHERE `role` = 'admin' LIMIT 1");
    $adminCheck->execute();
    if (!$adminCheck->fetch()) {
        $adminHash = password_hash('Admin@123456', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("
            INSERT INTO `users` (`username`, `phone`, `email`, `password_hash`, `role`, `status`)
            VALUES ('admin', '9999999999', 'admin@sikkim.game', :hash, 'admin', 'active')
        ");
        $stmt->execute([':hash' => $adminHash]);
        $adminId = $pdo->lastInsertId();

        // Admin Wallet
        $stmtW = $pdo->prepare("INSERT INTO `wallets` (`user_id`, `balance`) VALUES (:uid, 10000.00)");
        $stmtW->execute([':uid' => $adminId]);
    }

    // Seed Categories if empty
    $catCheck = $pdo->query("SELECT COUNT(*) FROM `categories`")->fetchColumn();
    if ($catCheck == 0) {
        $categories = [
            ['name' => 'Hot Slots', 'slug' => 'hot-slots', 'icon' => 'casino-chips', 'order' => 1],
            ['name' => 'Lottery', 'slug' => 'lottery', 'icon' => 'lottery-ball', 'order' => 2],
            ['name' => 'Original', 'slug' => 'original', 'icon' => 'rocket-flame', 'order' => 3],
            ['name' => 'Slots', 'slug' => 'slots', 'icon' => 'slot-machine', 'order' => 4],
            ['name' => 'Fishing', 'slug' => 'fishing', 'icon' => 'fish', 'order' => 5],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'trophy', 'order' => 6],
            ['name' => 'Casino', 'slug' => 'casino', 'icon' => 'spade', 'order' => 7],
            ['name' => 'Rummy', 'slug' => 'rummy', 'icon' => 'cards', 'order' => 8],
        ];
        $catStmt = $pdo->prepare("
            INSERT INTO `categories` (`name`, `slug`, `icon`, `display_order`, `status`)
            VALUES (:name, :slug, :icon, :order, 'active')
        ");
        foreach ($categories as $cat) {
            $catStmt->execute([
                ':name' => $cat['name'],
                ':slug' => $cat['slug'],
                ':icon' => $cat['icon'],
                ':order' => $cat['order']
            ]);
        }
    }

    // Seed Banners if empty
    $bannerCheck = $pdo->query("SELECT COUNT(*) FROM `banners`")->fetchColumn();
    if ($bannerCheck == 0) {
        $banners = [
            [
                'title' => 'PLAY WITH SKILL & WIN BIG REWARDS',
                'description' => 'Join the elite Sikkim gaming arena with instant UPI deposits and fast payouts.',
                'image' => '/assets/images/banner-pubg.png',
                'link' => '/games.php',
                'order' => 1
            ],
            [
                'title' => 'DAILY REBATE & VIP CASHBACK',
                'description' => 'Earn up to 2.5% daily cashback on all active games with no turnover requirement.',
                'image' => '/assets/images/banner-vip.png',
                'link' => '/wallet.php',
                'order' => 2
            ],
            [
                'title' => '100% FIRST DEPOSIT BONUS',
                'description' => 'Deposit Rs 500 or more today and get an extra Rs 500 bonus directly in your wallet.',
                'image' => '/assets/images/banner-bonus.png',
                'link' => '/wallet.php',
                'order' => 3
            ]
        ];
        $bannerStmt = $pdo->prepare("
            INSERT INTO `banners` (`title`, `description`, `image`, `link`, `display_order`, `status`)
            VALUES (:title, :description, :image, :link, :order, 'active')
        ");
        foreach ($banners as $b) {
            $bannerStmt->execute([
                ':title' => $b['title'],
                ':description' => $b['description'],
                ':image' => $b['image'],
                ':link' => $b['link'],
                ':order' => $b['order']
            ]);
        }
    }

    // Seed Announcements if empty
    $annCheck = $pdo->query("SELECT COUNT(*) FROM `announcements`")->fetchColumn();
    if ($annCheck == 0) {
        $pdo->exec("
            INSERT INTO `announcements` (`content`, `status`, `priority`)
            VALUES ('Welcome to SIKKIM game platform, we will serve you wholeheartedly! Instant automated deposits and fast round settlements.', 'active', 10),
                   ('Notice: Daily maintenance window is strictly handled seamlessly with zero downtime. Enjoy non-stop gaming!', 'active', 5)
        ");
    }

    // Seed Games if empty
    $gameCheck = $pdo->query("SELECT COUNT(*) FROM `games`")->fetchColumn();
    if ($gameCheck == 0) {
        // Fetch lottery category
        $lotteryCat = $pdo->query("SELECT id FROM `categories` WHERE `slug` = 'lottery' LIMIT 1")->fetchColumn();
        $originalCat = $pdo->query("SELECT id FROM `categories` WHERE `slug` = 'original' LIMIT 1")->fetchColumn();
        $slotsCat = $pdo->query("SELECT id FROM `categories` WHERE `slug` = 'hot-slots' LIMIT 1")->fetchColumn();
        $sportsCat = $pdo->query("SELECT id FROM `categories` WHERE `slug` = 'sports' LIMIT 1")->fetchColumn();

        $defaultGames = [
            [
                'cat_id' => $lotteryCat ?: 1,
                'name' => 'Win Go 1Min',
                'slug' => 'win-go-1m',
                'image' => '/assets/images/game-wingo.png',
                'description' => 'Classic Sikkim 1-Minute Color and Number Prediction round with guaranteed payout.',
                'order' => 1,
                'rec' => 1
            ],
            [
                'cat_id' => $originalCat ?: 1,
                'name' => 'Aviator Blast',
                'slug' => 'aviator-blast',
                'image' => '/assets/images/game-aviator.png',
                'description' => 'Real-time multiplier aircraft game with instant cash out.',
                'order' => 2,
                'rec' => 1
            ],
            [
                'cat_id' => $sportsCat ?: 1,
                'name' => 'Cricket Premier',
                'slug' => 'cricket-premier',
                'image' => '/assets/images/game-cricket.png',
                'description' => 'Predict match overs, wickets and boundary streaks with premier multipliers.',
                'order' => 3,
                'rec' => 1
            ],
            [
                'cat_id' => $slotsCat ?: 1,
                'name' => 'Royal 777 Deluxe',
                'slug' => 'royal-777',
                'image' => '/assets/images/game-slots777.png',
                'description' => 'Triple lucky reels, wild scatters, and high jackpot multiplier rounds.',
                'order' => 4,
                'rec' => 1
            ]
        ];

        $gameStmt = $pdo->prepare("
            INSERT INTO `games` (`category_id`, `name`, `slug`, `image`, `description`, `status`, `display_order`, `is_recommended`)
            VALUES (:cid, :name, :slug, :image, :desc, 'active', :order, :rec)
        ");
        foreach ($defaultGames as $g) {
            $gameStmt->execute([
                ':cid' => $g['cat_id'],
                ':name' => $g['name'],
                ':slug' => $g['slug'],
                ':image' => $g['image'],
                ':desc' => $g['description'],
                ':order' => $g['order'],
                ':rec' => $g['rec']
            ]);
        }
    }

    // Ensure Initial Active Game Round Exists for 'win-go-1m'
    $activeRound = $pdo->query("SELECT id FROM `game_rounds` WHERE `game_slug` = 'win-go-1m' AND `status` = 'active' LIMIT 1")->fetch();
    if (!$activeRound) {
        $lastRound = $pdo->query("SELECT MAX(round_number) as max_rn FROM `game_rounds` WHERE `game_slug` = 'win-go-1m'")->fetch();
        $nextRn = ($lastRound && $lastRound['max_rn']) ? ($lastRound['max_rn'] + 1) : 321001;

        $now = date('Y-m-d H:i:s');
        $endTime = date('Y-m-d H:i:s', time() + 60);

        $stmtR = $pdo->prepare("
            INSERT INTO `game_rounds` (`round_number`, `game_slug`, `start_time`, `end_time`, `status`, `result_published`)
            VALUES (:rn, 'win-go-1m', :start, :end, 'active', 0)
        ");
        $stmtR->execute([
            ':rn' => $nextRn,
            ':start' => $now,
            ':end' => $endTime
        ]);
    }
}
