<?php
/**
 * Sikkim Gaming Platform - Place Crash Game Bet API
 * Fully Server-Side Validated with Atomic MySQL Transactions.
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
    echo json_encode(['success' => false, 'message' => 'Please sign in to place your entry.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$amount = (float)($_POST['amount'] ?? 0);
$autoCashout = isset($_POST['auto_cashout']) && (float)$_POST['auto_cashout'] > 1.01 ? (float)$_POST['auto_cashout'] : null;

if ($amount < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum entry amount is ₹10.00.']);
    exit;
}
if ($amount > 20000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Maximum entry amount is ₹20,000.00.']);
    exit;
}

$pdo = getDB();

try {
    $pdo->beginTransaction();

    $round = processAndGetActiveCrashRound($pdo);

    if (!$round || $round['status'] !== 'waiting') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Entry window is closed. Aircraft is currently in flight or preparing next round.'
        ]);
        exit;
    }

    // Check if user already entered this round
    $dupCheck = $pdo->prepare("SELECT id FROM `crash_bets` WHERE `round_id` = :rid AND `user_id` = :uid LIMIT 1");
    $dupCheck->execute([':rid' => $round['id'], ':uid' => $userId]);
    if ($dupCheck->fetch()) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'You already have an active entry for Round #' . $round['round_number'] . '.'
        ]);
        exit;
    }

    // Check wallet balance
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid FOR UPDATE");
    $wStmt->execute([':uid' => $userId]);
    $currentBalance = (float)$wStmt->fetchColumn();

    if ($currentBalance < $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient balance. Available: ₹' . number_format($currentBalance, 2) . ', Required: ₹' . number_format($amount, 2) . '.'
        ]);
        exit;
    }

    // Deduct from wallet
    $newBalance = $currentBalance - $amount;
    $upW = $pdo->prepare("UPDATE `wallets` SET `balance` = :nb WHERE `user_id` = :uid");
    $upW->execute([':nb' => $newBalance, ':uid' => $userId]);

    // Insert Bet
    $insB = $pdo->prepare("
        INSERT INTO `crash_bets` 
        (`user_id`, `round_id`, `amount`, `auto_cashout`, `status`)
        VALUES (:uid, :rid, :amt, :ac, 'pending')
    ");
    $insB->execute([
        ':uid' => $userId,
        ':rid' => $round['id'],
        ':amt' => $amount,
        ':ac' => $autoCashout
    ]);
    $betId = (int)$pdo->lastInsertId();

    // Update round total volume
    $upR = $pdo->prepare("UPDATE `crash_rounds` SET `total_bets_amount` = `total_bets_amount` + :amt WHERE `id` = :rid");
    $upR->execute([':amt' => $amount, ':rid' => $round['id']]);

    // Transaction Ledger
    $tx = $pdo->prepare("
        INSERT INTO `transactions` 
        (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
        VALUES (:uid, 'bet', :amt, :before, :after, :ref, 'completed', :notes)
    ");
    $tx->execute([
        ':uid' => $userId,
        ':amt' => $amount,
        ':before' => $currentBalance,
        ':after' => $newBalance,
        ':ref' => 'CRASH-BET-' . $round['round_number'] . '-' . $betId,
        ':notes' => 'Crash Game Round #' . $round['round_number'] . ' Entry'
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Entry of ₹' . number_format($amount, 2) . ' placed for Round #' . $round['round_number'] . '!',
        'new_balance' => $newBalance,
        'bet' => [
            'id' => $betId,
            'round_number' => (int)$round['round_number'],
            'amount' => $amount,
            'auto_cashout' => $autoCashout,
            'status' => 'pending'
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
