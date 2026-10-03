<?php
/**
 * Sikkim Gaming Platform - Cash Out Crash Bet API
 * Fully Server-Side Authoritative.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crash_engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to cash out.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$betId = (int)($_POST['bet_id'] ?? 0);

if ($betId <= 0) {
    // If not provided, find active pending bet
    $pdo = getDB();
    $bStmt = $pdo->prepare("
        SELECT b.id 
        FROM `crash_bets` b
        JOIN `crash_rounds` r ON b.round_id = r.id
        WHERE b.user_id = :uid AND b.status = 'pending' AND r.status = 'running'
        ORDER BY b.id DESC LIMIT 1
    ");
    $bStmt->execute([':uid' => $userId]);
    $betId = (int)$bStmt->fetchColumn();
}

if ($betId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No active in-flight entry found to cash out.']);
    exit;
}

$pdo = getDB();
$result = cashOutUserBet($pdo, $betId, $userId);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
