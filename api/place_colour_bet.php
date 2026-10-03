<?php
/**
 * Sikkim Gaming Platform - Place Colour Game Bet API
 * Fully Server-Side Validated with Atomic MySQL Transactions.
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/round_engine.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to place your bet.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$choice = strtolower(trim($_POST['choice'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);

// Validate colour option
$validColors = ['red', 'green', 'violet'];
if (!in_array($choice, $validColors, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid colour selected. Choose Red, Green, or Violet.']);
    exit;
}

// Validate amount
if ($amount < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum bet amount is ₹10.00.']);
    exit;
}
if ($amount > 50000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Maximum bet amount per selection is ₹50,000.00.']);
    exit;
}

$pdo = getDB();
$gameSlug = 'colour-game';

// Multipliers
$multiplier = ($choice === 'violet') ? 4.50 : 2.00;

try {
    $pdo->beginTransaction();

    // 1. Authoritative active round check
    $round = processAndGetActiveRound($pdo, $gameSlug);
    if (!$round || $round['status'] !== 'active') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No active round available at this moment.']);
        exit;
    }

    // Lock check: last 5 seconds are locked
    $now = time();
    $endTime = strtotime($round['end_time']);
    $remaining = $endTime - $now;

    if ($remaining <= 5) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => 'Round #' . $round['round_number'] . ' is locked for settlement. Please wait for the next round.'
        ]);
        exit;
    }

    // 2. Lock user wallet and check balance
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid FOR UPDATE");
    $wStmt->execute([':uid' => $userId]);
    $currentBalance = (float)$wStmt->fetchColumn();

    if ($currentBalance < $amount) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Insufficient wallet balance. You have ₹' . number_format($currentBalance, 2) . ', but need ₹' . number_format($amount, 2) . '.'
        ]);
        exit;
    }

    // 3. Deduct amount from wallet
    $newBalance = $currentBalance - $amount;
    $upWallet = $pdo->prepare("UPDATE `wallets` SET `balance` = :new_bal WHERE `user_id` = :uid");
    $upWallet->execute([':new_bal' => $newBalance, ':uid' => $userId]);

    // 4. Record Bet in game_bets
    $betStmt = $pdo->prepare("
        INSERT INTO `game_bets` (`user_id`, `round_id`, `bet_choice`, `amount`, `multiplier`, `status`)
        VALUES (:uid, :rid, :choice, :amount, :mult, 'pending')
    ");
    $betStmt->execute([
        ':uid' => $userId,
        ':rid' => $round['id'],
        ':choice' => $choice,
        ':amount' => $amount,
        ':mult' => $multiplier
    ]);
    $betId = (int)$pdo->lastInsertId();

    // 5. Update round total bets volume
    $upRound = $pdo->prepare("UPDATE `game_rounds` SET `total_bets_amount` = `total_bets_amount` + :amt WHERE `id` = :rid");
    $upRound->execute([':amt' => $amount, ':rid' => $round['id']]);

    // 6. Record transaction in financial ledger
    $txStmt = $pdo->prepare("
        INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
        VALUES (:uid, 'bet', :amt, :before, :after, :ref, 'completed', :notes)
    ");
    $txStmt->execute([
        ':uid' => $userId,
        ':amt' => $amount,
        ':before' => $currentBalance,
        ':after' => $newBalance,
        ':ref' => 'BET-' . $round['round_number'] . '-' . $betId,
        ':notes' => 'Colour Game Round #' . $round['round_number'] . ' Bet: ' . strtoupper($choice)
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Bet of ₹' . number_format($amount, 2) . ' on [' . strtoupper($choice) . '] placed successfully!',
        'new_balance' => $newBalance,
        'bet' => [
            'id' => $betId,
            'choice' => $choice,
            'amount' => $amount,
            'multiplier' => $multiplier,
            'round_number' => (int)$round['round_number']
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
