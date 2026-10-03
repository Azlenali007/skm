<?php
/**
 * Sikkim Gaming Platform - Place Colour & Number Game Bet API
 * Fully Server-Side Validated with Atomic MySQL Transactions.
 * Supports Colour (Red, Green, Violet) and Number (0-9) selections.
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
    echo json_encode(['success' => false, 'message' => 'Please sign in to place your entry.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$rawColour = strtolower(trim($_POST['colour'] ?? $_POST['choice'] ?? ''));
$rawNumber = isset($_POST['number']) && $_POST['number'] !== '' ? trim($_POST['number']) : null;
$amount = (float)($_POST['points'] ?? $_POST['amount'] ?? 0);

// Validate colour if provided
$selectedColour = null;
if (!empty($rawColour)) {
    if (in_array($rawColour, ['red', 'green', 'violet'], true)) {
        $selectedColour = $rawColour;
    } elseif (is_numeric($rawColour) && $rawNumber === null) {
        $rawNumber = $rawColour;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid colour selected. Choose Red, Green, or Violet.']);
        exit;
    }
}

// Validate number if provided
$selectedNumber = null;
if ($rawNumber !== null && $rawNumber !== '') {
    if (is_numeric($rawNumber) && (int)$rawNumber >= 0 && (int)$rawNumber <= 9) {
        $selectedNumber = (int)$rawNumber;
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid number. Must be between 0 and 9.']);
        exit;
    }
}

// Ensure at least one selection is made
if ($selectedColour === null && $selectedNumber === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please select a Colour (Red, Green, Violet) or Number (0–9).']);
    exit;
}

// Validate amount/points
if ($amount < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Minimum entry points is 10.']);
    exit;
}
if ($amount > 50000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Maximum entry points per round is 50,000.']);
    exit;
}

$pdo = getDB();
$gameSlug = 'colour-game';

// Compute multiplier display
$multiplier = 2.00;
if ($selectedNumber !== null && $selectedColour !== null) {
    $multiplier = 9.00 + ($selectedColour === 'violet' ? 4.50 : 2.00);
} elseif ($selectedNumber !== null) {
    $multiplier = 9.00;
} elseif ($selectedColour === 'violet') {
    $multiplier = 4.50;
}

// Primary choice label
$choiceLabel = '';
if ($selectedColour && $selectedNumber !== null) {
    $choiceLabel = strtoupper($selectedColour) . '-' . $selectedNumber;
} elseif ($selectedColour) {
    $choiceLabel = strtoupper($selectedColour);
} else {
    $choiceLabel = 'NUM-' . $selectedNumber;
}

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
            'message' => 'Insufficient points balance. You have ' . number_format($currentBalance, 2) . ' points, but need ' . number_format($amount, 2) . '.'
        ]);
        exit;
    }

    // 3. Deduct points from wallet
    $newBalance = $currentBalance - $amount;
    $upWallet = $pdo->prepare("UPDATE `wallets` SET `balance` = :new_bal WHERE `user_id` = :uid");
    $upWallet->execute([':new_bal' => $newBalance, ':uid' => $userId]);

    // 4. Record Bet in game_bets with status = 'pending'
    $betStmt = $pdo->prepare("
        INSERT INTO `game_bets` (`user_id`, `round_id`, `bet_choice`, `selected_colour`, `selected_number`, `amount`, `points`, `multiplier`, `status`)
        VALUES (:uid, :rid, :choice, :col, :num, :amount, :pts, :mult, 'pending')
    ");
    $betStmt->execute([
        ':uid' => $userId,
        ':rid' => $round['id'],
        ':choice' => $choiceLabel,
        ':col' => $selectedColour,
        ':num' => $selectedNumber,
        ':amount' => $amount,
        ':pts' => $amount,
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
        ':notes' => 'Colour Game Round #' . $round['round_number'] . ' Entry: ' . $choiceLabel
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Entry of ' . number_format($amount, 2) . ' points on [' . $choiceLabel . '] placed successfully!',
        'new_balance' => $newBalance,
        'entry' => [
            'id' => $betId,
            'round_number' => (int)$round['round_number'],
            'selected_colour' => $selectedColour ? strtoupper($selectedColour) : null,
            'selected_number' => $selectedNumber,
            'points' => $amount,
            'multiplier' => $multiplier,
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
