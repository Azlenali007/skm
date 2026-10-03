<?php
/**
 * Sikkim Gaming Platform - Colour Game Live Status API
 * Completely Server-Side Authoritative.
 * Provides current active round, server countdown, live bet status,
 * user win/lose notification, and recent game history with internal scrolling.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/round_engine.php';

$pdo = getDB();
$gameSlug = 'colour-game';

// Server-authoritative active round
$activeRound = processAndGetActiveRound($pdo, $gameSlug);

$now = time();
$endTime = strtotime($activeRound['end_time']);
$remaining = max(0, $endTime - $now);
$isLocked = ($remaining <= 5); // Last 5 seconds locked for calculation

$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$userBalance = 0.00;
$userActiveBet = null;

if ($userId > 0) {
    // Get real-time balance
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid LIMIT 1");
    $wStmt->execute([':uid' => $userId]);
    $userBalance = (float)$wStmt->fetchColumn();

    // Check if user has an active bet in this round
    $bStmt = $pdo->prepare("
        SELECT id, bet_choice, amount, multiplier, win_amount, status 
        FROM `game_bets` 
        WHERE `round_id` = :rid AND `user_id` = :uid 
        ORDER BY id DESC LIMIT 1
    ");
    $bStmt->execute([':rid' => $activeRound['id'], ':uid' => $userId]);
    $userActiveBet = $bStmt->fetch();
}

// Fetch last completed round for win/lose alert
$lastRoundStmt = $pdo->prepare("
    SELECT id, round_number, result_color, completed_at 
    FROM `game_rounds` 
    WHERE `game_slug` = :slug AND `status` = 'completed' AND `result_published` = 1
    ORDER BY `round_number` DESC 
    LIMIT 1
");
$lastRoundStmt->execute([':slug' => $gameSlug]);
$lastRound = $lastRoundStmt->fetch();

$lastUserOutcome = null;
if ($lastRound && $userId > 0) {
    $lastBetStmt = $pdo->prepare("
        SELECT id, bet_choice, amount, multiplier, win_amount, status 
        FROM `game_bets` 
        WHERE `round_id` = :rid AND `user_id` = :uid 
        ORDER BY id DESC LIMIT 1
    ");
    $lastBetStmt->execute([':rid' => $lastRound['id'], ':uid' => $userId]);
    $lastBet = $lastBetStmt->fetch();

    if ($lastBet) {
        $lastUserOutcome = [
            'round_number' => (int)$lastRound['round_number'],
            'result_color' => $lastRound['result_color'],
            'bet_choice' => $lastBet['bet_choice'],
            'status' => $lastBet['status'], // 'won' or 'lost'
            'amount' => (float)$lastBet['amount'],
            'win_amount' => (float)$lastBet['win_amount']
        ];
    }
}

// Fetch recent completed game history (15 records)
$histStmt = $pdo->prepare("
    SELECT id, round_number, result_color, completed_at
    FROM `game_rounds`
    WHERE `game_slug` = :slug AND `status` = 'completed' AND `result_published` = 1
    ORDER BY `round_number` DESC
    LIMIT 15
");
$histStmt->execute([':slug' => $gameSlug]);
$historyRounds = $histStmt->fetchAll();

$historyData = [];
foreach ($historyRounds as $hr) {
    $userBetInfo = null;
    if ($userId > 0) {
        $hBetStmt = $pdo->prepare("
            SELECT bet_choice, amount, win_amount, status 
            FROM `game_bets` 
            WHERE `round_id` = :rid AND `user_id` = :uid 
            LIMIT 1
        ");
        $hBetStmt->execute([':rid' => $hr['id'], ':uid' => $userId]);
        $userBetInfo = $hBetStmt->fetch() ?: null;
    }

    $historyData[] = [
        'round_number' => (int)$hr['round_number'],
        'result_color' => $hr['result_color'],
        'user_choice' => $userBetInfo ? $userBetInfo['bet_choice'] : null,
        'user_status' => $userBetInfo ? $userBetInfo['status'] : null,
        'amount' => $userBetInfo ? (float)$userBetInfo['amount'] : null,
        'win_amount' => $userBetInfo ? (float)$userBetInfo['win_amount'] : null
    ];
}

echo json_encode([
    'success' => true,
    'round' => [
        'id' => (int)$activeRound['id'],
        'round_number' => (int)$activeRound['round_number'],
        'remaining_seconds' => $remaining,
        'is_locked' => $isLocked,
        'status' => $activeRound['status']
    ],
    'user' => [
        'is_logged_in' => ($userId > 0),
        'balance' => $userBalance,
        'active_bet' => $userActiveBet
    ],
    'last_outcome' => $lastUserOutcome,
    'history' => $historyData,
    'server_time' => date('Y-m-d H:i:s')
]);
