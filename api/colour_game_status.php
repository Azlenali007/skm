<?php
/**
 * Sikkim Gaming Platform - Colour Game Live Status API
 * Real MySQL Authoritative.
 * Provides active round, server countdown, user's own history with PENDING -> WIN/LOSE states,
 * and verified round outcomes with both Color and Number (0-9).
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
$isLocked = ($remaining <= 5);

$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$userBalance = 0.00;
$myHistory = [];

if ($userId > 0) {
    // Get real-time balance
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid LIMIT 1");
    $wStmt->execute([':uid' => $userId]);
    $userBalance = (float)$wStmt->fetchColumn();

    // Fetch user's own game history (from real MySQL game_bets joined with game_rounds)
    $myStmt = $pdo->prepare("
        SELECT 
            b.id,
            b.round_id,
            r.round_number,
            b.selected_colour,
            b.selected_number,
            COALESCE(b.points, b.amount) as points,
            b.multiplier,
            b.win_amount,
            b.status,
            b.created_at,
            r.result_color,
            r.result_number,
            r.status as round_status,
            r.result_published
        FROM `game_bets` b
        JOIN `game_rounds` r ON b.round_id = r.id
        WHERE b.user_id = :uid AND r.game_slug = :slug
        ORDER BY b.id DESC
        LIMIT 25
    ");
    $myStmt->execute([':uid' => $userId, ':slug' => $gameSlug]);
    $userBets = $myStmt->fetchAll();

    foreach ($userBets as $ub) {
        $displayStatus = 'PENDING';
        if ($ub['status'] === 'won') {
            $displayStatus = 'WIN';
        } elseif ($ub['status'] === 'lost') {
            $displayStatus = 'LOSE';
        } else {
            $displayStatus = 'PENDING';
        }

        $myHistory[] = [
            'id' => (int)$ub['id'],
            'round_number' => (int)$ub['round_number'],
            'selected_colour' => !empty($ub['selected_colour']) ? strtoupper($ub['selected_colour']) : null,
            'selected_number' => ($ub['selected_number'] !== null && $ub['selected_number'] !== '') ? (int)$ub['selected_number'] : null,
            'points' => (float)$ub['points'],
            'multiplier' => (float)$ub['multiplier'],
            'win_amount' => (float)$ub['win_amount'],
            'status' => $ub['status'], // 'pending', 'won', 'lost'
            'result' => $displayStatus, // 'PENDING', 'WIN', 'LOSE'
            'published_color' => $ub['result_published'] ? $ub['result_color'] : null,
            'published_number' => $ub['result_published'] ? (int)$ub['result_number'] : null,
            'created_at' => $ub['created_at']
        ];
    }
}

// Fetch last completed round for win/lose announcement
$lastRoundStmt = $pdo->prepare("
    SELECT id, round_number, result_color, result_number, completed_at 
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
        SELECT id, selected_colour, selected_number, amount, win_amount, status 
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
            'result_number' => (int)$lastRound['result_number'],
            'selected_colour' => $lastBet['selected_colour'],
            'selected_number' => $lastBet['selected_number'],
            'status' => $lastBet['status'], // 'won' or 'lost'
            'points' => (float)$lastBet['amount'],
            'win_amount' => (float)$lastBet['win_amount']
        ];
    }
}

// Fetch recent completed game history for public board (15 records)
$histStmt = $pdo->prepare("
    SELECT id, round_number, result_color, result_number, completed_at
    FROM `game_rounds`
    WHERE `game_slug` = :slug AND `status` = 'completed' AND `result_published` = 1
    ORDER BY `round_number` DESC
    LIMIT 15
");
$histStmt->execute([':slug' => $gameSlug]);
$historyRounds = $histStmt->fetchAll();

$historyData = [];
foreach ($historyRounds as $hr) {
    $historyData[] = [
        'round_number' => (int)$hr['round_number'],
        'result_color' => $hr['result_color'],
        'result_number' => (int)$hr['result_number'],
        'completed_at' => $hr['completed_at']
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
        'balance' => $userBalance
    ],
    'my_history' => $myHistory,
    'last_round_result' => $lastRound ? [
        'round_number' => (int)$lastRound['round_number'],
        'result_color' => $lastRound['result_color'],
        'result_number' => (int)$lastRound['result_number']
    ] : null,
    'last_outcome' => $lastUserOutcome,
    'game_history' => $historyData,
    'server_time' => date('Y-m-d H:i:s')
]);
