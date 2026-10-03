<?php
/**
 * Sikkim Gaming Platform - Authoritative Sequential Round Engine
 * Completely Server-Side & MySQL Authoritative.
 * Guarantees strictly sequential rounds (e.g. 321 -> 322 -> 323).
 * Never exposes unpublished results.
 */

require_once __DIR__ . '/../config/database.php';

function processAndGetActiveRound(PDO $pdo, string $gameSlug = 'win-go-1m'): array {
    $now = date('Y-m-d H:i:s');

    // 1. Check for current active round
    $stmt = $pdo->prepare("
        SELECT * FROM `game_rounds`
        WHERE `game_slug` = :slug AND `status` = 'active'
        ORDER BY `round_number` DESC
        LIMIT 1
    ");
    $stmt->execute([':slug' => $gameSlug]);
    $currentRound = $stmt->fetch();

    // If no active round exists at all, initialize the first round
    if (!$currentRound) {
        $lastRoundStmt = $pdo->prepare("SELECT MAX(round_number) as max_rn FROM `game_rounds` WHERE `game_slug` = :slug");
        $lastRoundStmt->execute([':slug' => $gameSlug]);
        $maxRn = $lastRoundStmt->fetchColumn();
        $nextRn = $maxRn ? ((int)$maxRn + 1) : 321001;

        $endTime = date('Y-m-d H:i:s', time() + 60);
        $insertStmt = $pdo->prepare("
            INSERT INTO `game_rounds` (`round_number`, `game_slug`, `start_time`, `end_time`, `status`, `result_published`)
            VALUES (:rn, :slug, :start, :end, 'active', 0)
        ");
        $insertStmt->execute([
            ':rn' => $nextRn,
            ':slug' => $gameSlug,
            ':start' => $now,
            ':end' => $endTime
        ]);

        $stmt->execute([':slug' => $gameSlug]);
        return $stmt->fetch();
    }

    // Check if the current round has expired (end_time <= now)
    if (strtotime($currentRound['end_time']) <= time()) {
        // Authoritatively finalize expired round in atomic MySQL transaction
        finalizeRoundAndCreateNext($pdo, (int)$currentRound['id'], $gameSlug);

        // Fetch newly created active round
        $stmt->execute([':slug' => $gameSlug]);
        return $stmt->fetch();
    }

    return $currentRound;
}

function finalizeRoundAndCreateNext(PDO $pdo, int $roundId, string $gameSlug): void {
    try {
        $pdo->beginTransaction();

        // Lock the round record for update to prevent race conditions
        $stmt = $pdo->prepare("SELECT * FROM `game_rounds` WHERE `id` = :id FOR UPDATE");
        $stmt->execute([':id' => $roundId]);
        $round = $stmt->fetch();

        if (!$round || $round['status'] !== 'active') {
            $pdo->rollBack();
            return;
        }

        // 1. Generate Authoritative Provably Fair Result
        $resultNumber = random_int(0, 9);
        $resultColor = '';
        if ($resultNumber === 0) {
            $resultColor = 'red-violet';
        } elseif ($resultNumber === 5) {
            $resultColor = 'green-violet';
        } elseif (in_array($resultNumber, [1, 3, 7, 9], true)) {
            $resultColor = 'green';
        } else {
            $resultColor = 'red';
        }

        $resultSize = ($resultNumber >= 5) ? 'big' : 'small';

        // 2. Fetch and evaluate all bets on this round
        $betsStmt = $pdo->prepare("SELECT * FROM `game_bets` WHERE `round_id` = :rid AND `status` = 'pending' FOR UPDATE");
        $betsStmt->execute([':rid' => $roundId]);
        $bets = $betsStmt->fetchAll();

        $totalPayout = 0.00;
        $totalBets = 0.00;

        foreach ($bets as $bet) {
            $betAmount = (float)$bet['amount'];
            $totalBets += $betAmount;
            $choice = strtolower(trim($bet['bet_choice']));
            $isWin = false;
            $winMultiplier = (float)$bet['multiplier'];

            // Match choice
            if (is_numeric($choice) && (int)$choice === $resultNumber) {
                $isWin = true;
                $winMultiplier = 9.00;
            } elseif ($choice === 'green' && in_array($resultNumber, [1, 3, 7, 9], true)) {
                $isWin = true;
                $winMultiplier = 2.00;
            } elseif ($choice === 'green' && $resultNumber === 5) {
                $isWin = true;
                $winMultiplier = 1.50; // Half win on green-violet
            } elseif ($choice === 'red' && in_array($resultNumber, [2, 4, 6, 8], true)) {
                $isWin = true;
                $winMultiplier = 2.00;
            } elseif ($choice === 'red' && $resultNumber === 0) {
                $isWin = true;
                $winMultiplier = 1.50; // Half win on red-violet
            } elseif ($choice === 'violet' && in_array($resultNumber, [0, 5], true)) {
                $isWin = true;
                $winMultiplier = 4.50;
            } elseif ($choice === 'big' && $resultSize === 'big') {
                $isWin = true;
                $winMultiplier = 2.00;
            } elseif ($choice === 'small' && $resultSize === 'small') {
                $isWin = true;
                $winMultiplier = 2.00;
            }

            if ($isWin) {
                $winAmount = round($betAmount * $winMultiplier, 2);
                $totalPayout += $winAmount;

                // Update Bet
                $upBet = $pdo->prepare("UPDATE `game_bets` SET `status` = 'won', `win_amount` = :win WHERE `id` = :bid");
                $upBet->execute([':win' => $winAmount, ':bid' => $bet['id']]);

                // Update User Wallet Atomically
                $upWallet = $pdo->prepare("UPDATE `wallets` SET `balance` = `balance` + :win WHERE `user_id` = :uid");
                $upWallet->execute([':win' => $winAmount, ':uid' => $bet['user_id']]);

                // Fetch new balance for ledger
                $wBalStmt = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid");
                $wBalStmt->execute([':uid' => $bet['user_id']]);
                $afterBalance = (float)$wBalStmt->fetchColumn();

                // Add Transaction Ledger Entry
                $txStmt = $pdo->prepare("
                    INSERT INTO `transactions` (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
                    VALUES (:uid, 'win', :amt, :before, :after, :ref, 'completed', :notes)
                ");
                $txStmt->execute([
                    ':uid' => $bet['user_id'],
                    ':amt' => $winAmount,
                    ':before' => $afterBalance - $winAmount,
                    ':after' => $afterBalance,
                    ':ref' => 'WIN-' . $round['round_number'] . '-' . $bet['id'],
                    ':notes' => 'Win Go Round #' . $round['round_number'] . ' Won Choice: ' . strtoupper($choice)
                ]);

                // Add User Notification
                $notifStmt = $pdo->prepare("
                    INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`)
                    VALUES (:uid, 'Round Won!', :msg, 'win')
                ");
                $notifStmt->execute([
                    ':uid' => $bet['user_id'],
                    ':msg' => 'Congratulations! You won ₹' . number_format($winAmount, 2) . ' on Round #' . $round['round_number'] . ' (Result: ' . $resultNumber . ' ' . ucfirst($resultColor) . ').'
                ]);

            } else {
                // Update Bet as lost
                $upBet = $pdo->prepare("UPDATE `game_bets` SET `status` = 'lost', `win_amount` = 0.00 WHERE `id` = :bid");
                $upBet->execute([':bid' => $bet['id']]);
            }
        }

        // 3. Mark Round Completed and Publish Result
        $upRound = $pdo->prepare("
            UPDATE `game_rounds`
            SET `status` = 'completed',
                `result_number` = :num,
                `result_color` = :color,
                `result_size` = :size,
                `result_published` = 1,
                `total_bets_amount` = :total_bets,
                `total_payout_amount` = :total_payout,
                `completed_at` = NOW()
            WHERE `id` = :id
        ");
        $upRound->execute([
            ':num' => $resultNumber,
            ':color' => $resultColor,
            ':size' => $resultSize,
            ':total_bets' => $totalBets,
            ':total_payout' => $totalPayout,
            ':id' => $roundId
        ]);

        // 4. Create Strictly Sequential NEXT Round: (round_number + 1)
        $nextRoundNumber = (int)$round['round_number'] + 1;
        $nextStartTime = date('Y-m-d H:i:s');
        $nextEndTime = date('Y-m-d H:i:s', time() + 60);

        $newRoundStmt = $pdo->prepare("
            INSERT INTO `game_rounds` (`round_number`, `game_slug`, `start_time`, `end_time`, `status`, `result_published`)
            VALUES (:rn, :slug, :start, :end, 'active', 0)
        ");
        $newRoundStmt->execute([
            ':rn' => $nextRoundNumber,
            ':slug' => $gameSlug,
            ':start' => $nextStartTime,
            ':end' => $nextEndTime
        ]);

        $pdo->commit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Round finalization error: " . $e->getMessage());
    }
}
