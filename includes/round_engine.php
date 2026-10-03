<?php
/**
 * Sikkim Gaming Platform - Authoritative Sequential Round Engine
 * Completely Server-Side & MySQL Authoritative.
 * Guarantees strictly sequential rounds (e.g. 321 -> 322 -> 323).
 * Never exposes unpublished results.
 * Supports both Automatic and Manual Result Modes with Manual Priority.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Get or advance the current active round for any game slug.
 */
function processAndGetActiveRound(PDO $pdo, string $gameSlug = 'colour-game'): array {
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

    // If no active round exists at all, initialize the first sequential round
    if (!$currentRound) {
        $lastRoundStmt = $pdo->prepare("SELECT MAX(round_number) as max_rn FROM `game_rounds` WHERE `game_slug` = :slug");
        $lastRoundStmt->execute([':slug' => $gameSlug]);
        $maxRn = $lastRoundStmt->fetchColumn();
        
        // Use 321 for colour-game as requested, or sequential from existing
        $defaultStart = ($gameSlug === 'colour-game') ? 321 : 321001;
        $nextRn = $maxRn ? ((int)$maxRn + 1) : $defaultStart;

        $duration = ($gameSlug === 'colour-game') ? 45 : 60;
        $endTime = date('Y-m-d H:i:s', time() + $duration);

        $insertStmt = $pdo->prepare("
            INSERT INTO `game_rounds` (`round_number`, `game_slug`, `start_time`, `end_time`, `status`, `result_mode`, `result_published`)
            VALUES (:rn, :slug, :start, :end, 'active', 'auto', 0)
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

    // Check if the current round has reached expiration (end_time <= now)
    if (strtotime($currentRound['end_time']) <= time()) {
        // Authoritatively finalize expired round in atomic MySQL transaction
        finalizeRoundAndCreateNext($pdo, (int)$currentRound['id'], $gameSlug);

        // Fetch newly created active round
        $stmt->execute([':slug' => $gameSlug]);
        $newActive = $stmt->fetch();
        return $newActive ?: $currentRound;
    }

    return $currentRound;
}

/**
 * Finalize an active round, audit all bets, credit winners, and spawn next sequential round.
 */
function finalizeRoundAndCreateNext(PDO $pdo, int $roundId, string $gameSlug, ?string $forcedResult = null): bool {
    try {
        $pdo->beginTransaction();

        // Lock the round record for update to prevent race conditions
        $stmt = $pdo->prepare("SELECT * FROM `game_rounds` WHERE `id` = :id FOR UPDATE");
        $stmt->execute([':id' => $roundId]);
        $round = $stmt->fetch();

        if (!$round || $round['status'] !== 'active') {
            $pdo->rollBack();
            return false;
        }

        // Determine final result color
        $resultColor = '';
        $resultNumber = null;
        $resultSize = null;

        // Priority 1: Forced result parameter (admin instant publish)
        if (!empty($forcedResult) && in_array(strtolower($forcedResult), ['red', 'green', 'violet'], true)) {
            $resultColor = strtolower($forcedResult);
        }
        // Priority 2: Pre-configured manual result in round record
        elseif ($round['result_mode'] === 'manual' && !empty($round['manual_result'])) {
            $resultColor = strtolower(trim($round['manual_result']));
        }
        // Priority 3: Automatic Server-Side Generation
        else {
            if ($gameSlug === 'colour-game') {
                // Weighted distribution: 45% Red, 45% Green, 10% Violet
                $rand = random_int(1, 100);
                if ($rand <= 45) {
                    $resultColor = 'red';
                } elseif ($rand <= 90) {
                    $resultColor = 'green';
                } else {
                    $resultColor = 'violet';
                }
            } else {
                // Win-Go style with number & color
                $resultNumber = random_int(0, 9);
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
            }
        }

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

            if ($gameSlug === 'colour-game') {
                if ($choice === $resultColor) {
                    $isWin = true;
                    if ($choice === 'violet') {
                        $winMultiplier = 4.50;
                    } else {
                        $winMultiplier = 2.00;
                    }
                }
            } else {
                // Win-go rules
                if (is_numeric($choice) && (int)$choice === $resultNumber) {
                    $isWin = true;
                    $winMultiplier = 9.00;
                } elseif ($choice === 'green' && ($resultColor === 'green' || in_array($resultNumber, [1, 3, 7, 9], true))) {
                    $isWin = true;
                    $winMultiplier = 2.00;
                } elseif ($choice === 'green' && $resultNumber === 5) {
                    $isWin = true;
                    $winMultiplier = 1.50;
                } elseif ($choice === 'red' && ($resultColor === 'red' || in_array($resultNumber, [2, 4, 6, 8], true))) {
                    $isWin = true;
                    $winMultiplier = 2.00;
                } elseif ($choice === 'red' && $resultNumber === 0) {
                    $isWin = true;
                    $winMultiplier = 1.50;
                } elseif ($choice === 'violet' && ($resultColor === 'violet' || in_array($resultNumber, [0, 5], true))) {
                    $isWin = true;
                    $winMultiplier = 4.50;
                }
            }

            if ($isWin) {
                $winAmount = round($betAmount * $winMultiplier, 2);
                $totalPayout += $winAmount;

                // Update Bet status to won
                $upBet = $pdo->prepare("UPDATE `game_bets` SET `status` = 'won', `win_amount` = :win WHERE `id` = :bid");
                $upBet->execute([':win' => $winAmount, ':bid' => $bet['id']]);

                // Update User Wallet Atomically
                $upWallet = $pdo->prepare("UPDATE `wallets` SET `balance` = `balance` + :win WHERE `user_id` = :uid");
                $upWallet->execute([':win' => $winAmount, ':uid' => $bet['user_id']]);

                // Fetch new balance for ledger audit
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
                    ':notes' => 'Colour Game Round #' . $round['round_number'] . ' Won Choice: ' . strtoupper($choice)
                ]);

                // Add Notification
                $notifStmt = $pdo->prepare("
                    INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`)
                    VALUES (:uid, 'Round Won!', :msg, 'win')
                ");
                $notifStmt->execute([
                    ':uid' => $bet['user_id'],
                    ':msg' => 'Round #' . $round['round_number'] . ' Result: ' . strtoupper($resultColor) . '. You won ₹' . number_format($winAmount, 2) . '!'
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
                `result_status` = 'published',
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
        $duration = ($gameSlug === 'colour-game') ? 45 : 60;
        $nextEndTime = date('Y-m-d H:i:s', time() + $duration);

        $newRoundStmt = $pdo->prepare("
            INSERT INTO `game_rounds` (`round_number`, `game_slug`, `start_time`, `end_time`, `status`, `result_mode`, `result_published`)
            VALUES (:rn, :slug, :start, :end, 'active', 'auto', 0)
        ");
        $newRoundStmt->execute([
            ':rn' => $nextRoundNumber,
            ':slug' => $gameSlug,
            ':start' => $nextStartTime,
            ':end' => $nextEndTime
        ]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Round finalization error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get live betting statistics for a round (used by Admin monitor).
 */
function getRoundLiveStats(PDO $pdo, int $roundId): array {
    $stats = [
        'total_players' => 0,
        'total_amount' => 0.00,
        'colors' => [
            'red' => ['players' => 0, 'amount' => 0.00],
            'green' => ['players' => 0, 'amount' => 0.00],
            'violet' => ['players' => 0, 'amount' => 0.00]
        ]
    ];

    $stmt = $pdo->prepare("
        SELECT 
            LOWER(bet_choice) as choice,
            COUNT(DISTINCT user_id) as player_count,
            COALESCE(SUM(amount), 0) as total_amount
        FROM `game_bets`
        WHERE `round_id` = :rid
        GROUP BY LOWER(bet_choice)
    ");
    $stmt->execute([':rid' => $roundId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $c = $r['choice'];
        $pCount = (int)$r['player_count'];
        $amt = (float)$r['total_amount'];

        if (isset($stats['colors'][$c])) {
            $stats['colors'][$c]['players'] = $pCount;
            $stats['colors'][$c]['amount'] = $amt;
        }

        $stats['total_amount'] += $amt;
    }

    // Total distinct players across all colors
    $pStmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM `game_bets` WHERE `round_id` = :rid");
    $pStmt->execute([':rid' => $roundId]);
    $stats['total_players'] = (int)$pStmt->fetchColumn();

    return $stats;
}

/**
 * Set manual result for a round (Admin control).
 */
function setRoundManualResult(PDO $pdo, int $roundId, string $color, int $adminId): bool {
    $validColors = ['red', 'green', 'violet'];
    if (!in_array(strtolower($color), $validColors, true)) {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE `game_rounds`
        SET `result_mode` = 'manual',
            `manual_result` = :color,
            `result_status` = 'locked',
            `manually_set_at` = NOW(),
            `manually_set_by` = :admin
        WHERE `id` = :rid AND `status` = 'active'
    ");
    return $stmt->execute([
        ':color' => strtolower($color),
        ':admin' => $adminId,
        ':rid' => $roundId
    ]);
}

/**
 * Set round result mode (auto vs manual).
 */
function setRoundMode(PDO $pdo, int $roundId, string $mode): bool {
    if (!in_array($mode, ['auto', 'manual'], true)) {
        return false;
    }

    $stmt = $pdo->prepare("
        UPDATE `game_rounds`
        SET `result_mode` = :mode
        WHERE `id` = :rid AND `status` = 'active'
    ");
    return $stmt->execute([
        ':mode' => $mode,
        ':rid' => $roundId
    ]);
}
