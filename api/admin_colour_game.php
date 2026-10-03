<?php
/**
 * Sikkim Gaming Platform - Admin Colour Game API
 * Provides live monitoring with Colour & Numbers 0-9 totals,
 * Manual vs Automatic Mode Switcher, and Instant Result Publishing.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/round_engine.php';

// Require Admin authorization
requireAdmin();

$pdo = getDB();
$gameSlug = 'colour-game';
$adminUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $roundId = (int)($_POST['round_id'] ?? 0);

    // Verify round
    $rStmt = $pdo->prepare("SELECT * FROM `game_rounds` WHERE `id` = :id AND `game_slug` = :slug LIMIT 1");
    $rStmt->execute([':id' => $roundId, ':slug' => $gameSlug]);
    $round = $rStmt->fetch();

    if (!$round) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Round not found.']);
        exit;
    }

    if ($round['status'] !== 'active') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Round #' . $round['round_number'] . ' is already completed or locked.']);
        exit;
    }

    if ($action === 'set_mode') {
        $mode = ($_POST['mode'] === 'manual') ? 'manual' : 'auto';
        $up = $pdo->prepare("UPDATE `game_rounds` SET `result_mode` = :mode WHERE `id` = :id");
        $up->execute([':mode' => $mode, ':id' => $roundId]);

        echo json_encode([
            'success' => true,
            'message' => 'Round #' . $round['round_number'] . ' mode switched to ' . strtoupper($mode) . '.',
            'result_mode' => $mode
        ]);
        exit;
    }

    if ($action === 'set_manual_result') {
        $color = !empty($_POST['result_color']) ? strtolower(trim($_POST['result_color'])) : null;
        $number = (isset($_POST['result_number']) && $_POST['result_number'] !== '') ? (int)$_POST['result_number'] : null;

        if ($color !== null && !in_array($color, ['red', 'green', 'violet'], true)) {
            $color = null;
        }

        if ($number !== null && ($number < 0 || $number > 9)) {
            $number = null;
        }

        if ($color === null && $number === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select at least a valid Colour or Number (0–9).']);
            exit;
        }

        $success = setRoundManualResult($pdo, $roundId, $color, $number, (int)$adminUser['id']);
        if ($success) {
            $desc = [];
            if ($color) $desc[] = strtoupper($color);
            if ($number !== null) $desc[] = 'Number: ' . $number;
            echo json_encode([
                'success' => true,
                'message' => 'Manual outcome [' . implode(', ', $desc) . '] locked for Round #' . $round['round_number'] . '.',
                'manual_result' => $color,
                'manual_number' => $number,
                'result_mode' => 'manual',
                'result_status' => 'locked'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save manual result.']);
        }
        exit;
    }

    if ($action === 'publish_now') {
        $color = !empty($_POST['result_color']) ? strtolower(trim($_POST['result_color'])) : ($round['manual_result'] ?? null);
        $number = isset($_POST['result_number']) && $_POST['result_number'] !== '' ? (int)$_POST['result_number'] : ($round['manual_number'] !== null ? (int)$round['manual_number'] : null);

        // Finalize immediately
        $success = finalizeRoundAndCreateNext($pdo, $roundId, $gameSlug, $color, $number);

        if ($success) {
            $newActive = processAndGetActiveRound($pdo, $gameSlug);
            echo json_encode([
                'success' => true,
                'message' => 'Round #' . $round['round_number'] . ' settled! Next round #' . $newActive['round_number'] . ' started.',
                'new_round' => (int)$newActive['round_number']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to finalize round.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// GET: Fetch live monitoring state
$activeRound = processAndGetActiveRound($pdo, $gameSlug);
$remaining = max(0, strtotime($activeRound['end_time']) - time());

$stats = getRoundLiveStats($pdo, (int)$activeRound['id']);

// Fetch recent 10 completed rounds with detailed audit data
$hStmt = $pdo->prepare("
    SELECT 
        r.id, r.round_number, r.status, r.result_mode, r.manual_result, r.manual_number,
        r.result_color, r.result_number,
        r.result_published, r.total_bets_amount, r.total_payout_amount, r.created_at, r.completed_at,
        r.manually_set_at,
        u.username as set_by_admin
    FROM `game_rounds` r
    LEFT JOIN `users` u ON r.manually_set_by = u.id
    WHERE r.game_slug = :slug
    ORDER BY r.round_number DESC
    LIMIT 10
");
$hStmt->execute([':slug' => $gameSlug]);
$roundsHistory = $hStmt->fetchAll();

echo json_encode([
    'success' => true,
    'round' => [
        'id' => (int)$activeRound['id'],
        'round_number' => (int)$activeRound['round_number'],
        'status' => $activeRound['status'],
        'result_mode' => $activeRound['result_mode'] ?? 'auto',
        'manual_result' => $activeRound['manual_result'] ?? null,
        'manual_number' => $activeRound['manual_number'] !== null ? (int)$activeRound['manual_number'] : null,
        'result_status' => $activeRound['result_status'] ?? 'pending',
        'remaining_seconds' => $remaining,
        'start_time' => $activeRound['start_time'],
        'end_time' => $activeRound['end_time']
    ],
    'live_stats' => $stats,
    'recent_rounds' => $roundsHistory,
    'server_time' => date('Y-m-d H:i:s')
]);
