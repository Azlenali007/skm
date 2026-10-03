<?php
/**
 * Sikkim Gaming Platform - Real-Time Server Round Status API
 * Authoritative Server Timing & Published Results Only
 * No upcoming results exposed under any circumstance.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/round_engine.php';

$pdo = getDB();
$gameSlug = trim($_GET['game'] ?? 'win-go-1m');

// Get or auto-advance active round from MySQL
$activeRound = processAndGetActiveRound($pdo, $gameSlug);

$nowTime = time();
$roundEndTime = strtotime($activeRound['end_time']);
$remainingSeconds = max(0, $roundEndTime - $nowTime);

// 1. Fetch only PUBLISHED historical results (latest 15)
$historyStmt = $pdo->prepare("
    SELECT round_number, result_number, result_color, result_size, completed_at
    FROM `game_rounds`
    WHERE `game_slug` = :slug AND `result_published` = 1 AND `status` = 'completed'
    ORDER BY `round_number` DESC
    LIMIT 15
");
$historyStmt->execute([':slug' => $gameSlug]);
$history = $historyStmt->fetchAll();

// 2. If logged in, fetch user balance and current round bets
$userBets = [];
$userBalance = 0.00;
if (isLoggedIn()) {
    $uid = getCurrentUserId();
    $user = getCurrentUser();
    $userBalance = (float)($user['balance'] ?? 0.00);

    $betsStmt = $pdo->prepare("
        SELECT id, bet_choice, amount, multiplier, win_amount, status, created_at
        FROM `game_bets`
        WHERE `round_id` = :rid AND `user_id` = :uid
        ORDER BY `id` DESC
    ");
    $betsStmt->execute([':rid' => $activeRound['id'], ':uid' => $uid]);
    $userBets = $betsStmt->fetchAll();
}

echo json_encode([
    'success' => true,
    'server_time' => date('Y-m-d H:i:s'),
    'active_round' => [
        'id' => (int)$activeRound['id'],
        'round_number' => (int)$activeRound['round_number'],
        'game_slug' => $activeRound['game_slug'],
        'remaining_seconds' => $remainingSeconds,
        'betting_open' => ($remainingSeconds > 5)
    ],
    'history' => $history,
    'user' => [
        'is_logged_in' => isLoggedIn(),
        'balance' => $userBalance,
        'current_bets' => $userBets
    ]
], JSON_UNESCAPED_UNICODE);
