<?php
/**
 * Sikkim Gaming Platform - Bet Placement Processing API
 * Authoritative Server Validation & MySQL Transaction
 * Strictly Real Balances & Deductions
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/round_engine.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please log in to place a bet.']);
    exit;
}

$user = getCurrentUser();
if (!$user || $user['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Account inactive or unauthorized.']);
    exit;
}

$pdo = getDB();
$gameSlug = trim($_POST['game_slug'] ?? 'win-go-1m');
$choice = strtolower(trim($_POST['choice'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);

// Validate Allowed Choices
$validColors = ['green', 'red', 'violet'];
$validSizes = ['big', 'small'];
$isValidNumber = is_numeric($choice) && (int)$choice >= 0 && (int)$choice <= 9;
$isValidColor = in_array($choice, $validColors, true);
$isValidSize = in_array($choice, $validSizes, true);

if (!$isValidNumber && !$isValidColor && !$isValidSize) {
    echo json_encode(['success' => false, 'message' => 'Invalid bet selection choice.']);
    exit;
}

// Validate Amount
if ($amount < 10) {
    echo json_encode(['success' => false, 'message' => 'Minimum bet amount is ₹10.']);
    exit;
}
if ($amount > 50000) {
    echo json_encode(['success' => false, 'message' => 'Maximum single bet limit is ₹50,000.']);
    exit;
}

// Fetch Active Round
$activeRound = processAndGetActiveRound($pdo, $gameSlug);
$remainingSeconds = strtotime($activeRound['end_time']) - time();

if ($remainingSeconds <= 5) {
    echo json_encode(['success' => false, 'message' => 'Betting closed for this round. Please wait for the next round.']);
    exit;
}

// Determine default multiplier
$multiplier = 1.96;
if ($isValidNumber) {
    $multiplier = 9.00;
} elseif ($choice === 'violet') {
    $multiplier = 4.50;
}

try {
    $pdo->beginTransaction();

    // Lock wallet row for update
    $wStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid FOR UPDATE");
    $wStmt->execute([':uid' => $user['id']]);
    $currentBalance = (float)$wStmt->fetchColumn();

    if ($currentBalance < $amount) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Insufficient wallet balance. Please deposit funds to continue.']);
        exit;
    }

    $newBalance = $currentBalance - $amount;

    // Deduct Balance
    $deductStmt = $pdo->prepare("UPDATE `wallets` SET `balance` = :newBal WHERE `user_id` = :uid");
    $deductStmt->execute([':newBal' => $newBalance, ':uid' => $user['id']]);

    // Record Bet
    $betStmt = $pdo->prepare("
        INSERT INTO `game_bets` (`user_id`, `round_id`, `bet_choice`, `amount`, `multiplier`, `status`)
        VALUES (:uid, :rid, :choice, :amt, :mul, 'pending')
    ");
    $betStmt->execute([
        ':uid' => $user['id'],
        ':rid' => $activeRound['id'],
        ':choice' => $choice,
        ':amt' => $amount,
        ':mul' => $multiplier
    ]);
    $betId = (int)$pdo->lastInsertId();

    // Record Transaction Ledger
    $txStmt = $pdo->prepare("
        INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
        VALUES (:uid, 'bet', :amt, :before, :after, :ref, 'completed', :notes)
    ");
    $txStmt->execute([
        ':uid' => $user['id'],
        ':amt' => $amount,
        ':before' => $currentBalance,
        ':after' => $newBalance,
        ':ref' => 'BET-' . $activeRound['round_number'] . '-' . $betId,
        ':notes' => 'Win Go Round #' . $activeRound['round_number'] . ' Bet: ' . strtoupper($choice)
    ]);

    // Update Round Total Bets
    $upRound = $pdo->prepare("UPDATE `game_rounds` SET `total_bets_amount` = `total_bets_amount` + :amt WHERE `id` = :rid");
    $upRound->execute([':amt' => $amount, ':rid' => $activeRound['id']]);

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
            'round_number' => $activeRound['round_number']
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Bet placement error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to process bet due to a server error.']);
}
