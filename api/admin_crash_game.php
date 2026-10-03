<?php
/**
 * Sikkim Gaming Platform - Admin Crash Game API
 * Real-time monitoring, manual multiplier override, mode switcher, and instant controls.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crash_engine.php';

// Require Admin authorization
requireAdmin();

$pdo = getDB();
$round = processAndGetActiveCrashRound($pdo);
$now = time();
$flightStartTs = strtotime($round['flight_start_time']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $roundId = (int)($_POST['round_id'] ?? $round['id']);

    if ($action === 'set_mode') {
        $mode = ($_POST['mode'] === 'manual') ? 'manual' : 'auto';
        $up = $pdo->prepare("UPDATE `crash_rounds` SET `result_mode` = :mode WHERE `id` = :id");
        $up->execute([':mode' => $mode, ':id' => $roundId]);

        echo json_encode([
            'success' => true,
            'message' => 'Crash Game mode switched to ' . strtoupper($mode) . '.',
            'result_mode' => $mode
        ]);
        exit;
    }

    if ($action === 'set_manual_multiplier') {
        $mult = (float)($_POST['multiplier'] ?? 0);
        if ($mult < 1.01) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Multiplier must be at least 1.01x.']);
            exit;
        }

        $adminUser = getCurrentUser();
        $ok = setCrashManualMultiplier($pdo, $roundId, $mult, (int)$adminUser['id']);
        if ($ok) {
            echo json_encode([
                'success' => true,
                'message' => 'Manual crash point set to ' . number_format($mult, 2) . 'x for Round #' . $round['round_number'] . '.',
                'multiplier' => $mult
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Round is already crashed or completed.']);
        }
        exit;
    }

    if ($action === 'crash_now') {
        // Immediately force crash active round
        $nowFlightSeconds = max(0.1, $now - $flightStartTs);
        $currentMult = getCrashMultiplierAtSeconds((float)$nowFlightSeconds);

        $up = $pdo->prepare("
            UPDATE `crash_rounds`
            SET `status` = 'crashed',
                \`crash_multiplier\` = :cmult,
                \`crash_time\` = NOW(),
                \`result_published\` = 1
            WHERE \`id\` = :id AND \`status\` IN ('waiting', 'running')
        ");
        $up->execute([':cmult' => $currentMult, ':id' => $roundId]);

        // Fail remaining pending bets
        $upBets = $pdo->prepare("UPDATE `crash_bets` SET `status` = 'crashed' WHERE `round_id` = :rid AND `status` = 'pending'");
        $upBets->execute([':rid' => $roundId]);

        echo json_encode([
            'success' => true,
            'message' => 'Round #' . $round['round_number'] . ' crashed immediately at ' . number_format($currentMult, 2) . 'x.'
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// GET: Live Monitoring State
$liveStats = getCrashLiveStats($pdo, (int)$round['id']);

$flightElapsed = max(0, $now - $flightStartTs);
$currentMultiplier = 1.00;
if ($round['status'] === 'running') {
    $currentMultiplier = getCrashMultiplierAtSeconds((float)$flightElapsed);
} elseif ($round['status'] === 'crashed') {
    $currentMultiplier = (float)$round['crash_multiplier'];
}

// Fetch recent 10 completed rounds with details
$hStmt = $pdo->query("
    SELECT 
        id, round_number, status, crash_multiplier, result_mode, manual_multiplier,
        total_bets_amount, total_payout_amount, created_at, completed_at
    FROM `crash_rounds`
    WHERE `result_published` = 1
    ORDER BY `round_number` DESC
    LIMIT 10
");
$recentRounds = $hStmt->fetchAll();

echo json_encode([
    'success' => true,
    'round' => [
        'id' => (int)$round['id'],
        'round_number' => (int)$round['round_number'],
        'status' => strtoupper($round['status']),
        'raw_status' => $round['status'],
        'current_multiplier' => $currentMultiplier,
        'crash_multiplier' => (float)$round['crash_multiplier'],
        'result_mode' => $round['result_mode'],
        'manual_multiplier' => $round['manual_multiplier'] ? (float)$round['manual_multiplier'] : null,
        'start_time' => $round['start_time'],
        'flight_start_time' => $round['flight_start_time'],
        'crash_time' => $round['crash_time']
    ],
    'live_stats' => $liveStats,
    'recent_rounds' => $recentRounds,
    'server_time' => date('Y-m-d H:i:s')
]);
