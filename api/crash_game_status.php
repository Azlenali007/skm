<?php
/**
 * Sikkim Gaming Platform - Crash Game Status API
 * Completely Server-Side Authoritative.
 * Zero client leakage: Future crash point is strictly hidden until crashed.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crash_engine.php';

$pdo = getDB();

// Authoritative active round
$round = processAndGetActiveCrashRound($pdo);
$now = time();
$flightStartTs = strtotime($round['flight_start_time']);
$crashTs = strtotime($round['crash_time']);

$status = $round['status'];
$waitingRemaining = max(0, $flightStartTs - $now);
$flightElapsed = max(0, $now - $flightStartTs);

$currentMultiplier = 1.00;
if ($status === 'running') {
    $currentMultiplier = getCrashMultiplierAtSeconds((float)$flightElapsed);
} elseif ($status === 'crashed') {
    $currentMultiplier = (float)$round['crash_multiplier'];
}

$userId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;
$userBalance = 0.00;
$userActiveBet = null;
$myHistory = [];

if ($userId > 0) {
    // Live balance
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid LIMIT 1");
    $wStmt->execute([':uid' => $userId]);
    $userBalance = (float)$wStmt->fetchColumn();

    // Active bet for this round
    $bStmt = $pdo->prepare("
        SELECT id, amount, auto_cashout, cashed_out_multiplier, win_amount, status 
        FROM `crash_bets` 
        WHERE `round_id` = :rid AND `user_id` = :uid 
        ORDER BY id DESC LIMIT 1
    ");
    $bStmt->execute([':rid' => $round['id'], ':uid' => $userId]);
    $userActiveBet = $bStmt->fetch() ?: null;

    // Fetch user's own history
    $hStmt = $pdo->prepare("
        SELECT 
            b.id,
            b.round_id,
            r.round_number,
            b.amount,
            b.cashed_out_multiplier,
            b.win_amount,
            b.status,
            b.created_at,
            r.status as round_status,
            r.crash_multiplier,
            r.result_published
        FROM `crash_bets` b
        JOIN `crash_rounds` r ON b.round_id = r.id
        WHERE b.user_id = :uid
        ORDER BY b.id DESC
        LIMIT 20
    ");
    $hStmt->execute([':uid' => $userId]);
    $userBets = $hStmt->fetchAll();

    foreach ($userBets as $ub) {
        $displayResult = 'PENDING';
        if ($ub['status'] === 'cashed_out') {
            $displayResult = 'WIN';
        } elseif ($ub['status'] === 'crashed' || ($ub['round_status'] === 'crashed' && $ub['status'] === 'pending')) {
            $displayResult = 'LOSE';
        }

        $myHistory[] = [
            'id' => (int)$ub['id'],
            'round_number' => (int)$ub['round_number'],
            'amount' => (float)$ub['amount'],
            'cashed_out_multiplier' => $ub['cashed_out_multiplier'] ? (float)$ub['cashed_out_multiplier'] : null,
            'win_amount' => (float)$ub['win_amount'],
            'status' => $ub['status'],
            'result' => $displayResult,
            'crash_multiplier' => $ub['result_published'] ? (float)$ub['crash_multiplier'] : null,
            'created_at' => $ub['created_at']
        ];
    }
}

// Fetch recent completed crashes for public board
$recentStmt = $pdo->query("
    SELECT round_number, crash_multiplier, completed_at
    FROM `crash_rounds`
    WHERE `result_published` = 1
    ORDER BY `round_number` DESC
    LIMIT 15
");
$recentCrashes = $recentStmt->fetchAll();

$recentList = [];
foreach ($recentCrashes as $rc) {
    $recentList[] = [
        'round_number' => (int)$rc['round_number'],
        'crash_multiplier' => (float)$rc['crash_multiplier']
    ];
}

// Construct response: STRICT PRIVACY - never reveal un-crashed multiplier
$responseRound = [
    'id' => (int)$round['id'],
    'round_number' => (int)$round['round_number'],
    'status' => $status,
    'waiting_remaining' => $waitingRemaining,
    'flight_elapsed' => $flightElapsed,
    'current_multiplier' => $currentMultiplier,
    'flight_start_time' => $round['flight_start_time'],
    'flight_start_ts' => $flightStartTs,
];

if ($status === 'crashed' || (int)$round['result_published'] === 1) {
    $responseRound['crash_multiplier'] = (float)$round['crash_multiplier'];
}

echo json_encode([
    'success' => true,
    'round' => $responseRound,
    'user' => [
        'is_logged_in' => ($userId > 0),
        'balance' => $userBalance,
        'active_bet' => $userActiveBet
    ],
    'my_history' => $myHistory,
    'recent_crashes' => $recentList,
    'server_time' => date('Y-m-d H:i:s')
]);
