<?php
/**
 * Sikkim Gaming Platform - Authoritative Sequential Round Engine
 * Completely Server-Side & MySQL Authoritative.
 * Guarantees strictly sequential rounds (e.g. 321 -> 322 -> 323).
 * Never exposes unpublished results.
 * Supports Colours (Red, Green, Violet) and Numbers (0-9).
 * Supports Automatic and Manual Result Modes with Manual Priority.
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

    // Check if current round reached expiration
    if (strtotime($currentRound['end_time']) <= time()) {
        finalizeRoundAndCreateNext($pdo, (int)$currentRound['id'], $gameSlug);

        $stmt->execute([':slug' => $gameSlug]);
        $newActive = $stmt->fetch();
        return $newActive ?: $currentRound;
    }

    return $currentRound;
}

/**
 * Finalize an active round, audit all bets, credit winners, and spawn next sequential round.
 */
function finalizeRoundAndCreateNext(PDO $pdo, int $roundId, string $gameSlug, ?string $forcedResult = null, ?int $forcedNumber = null): bool {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM `game_rounds` WHERE `id` = :id FOR UPDATE");
        $stmt->execute([':id' => $roundId]);
        $round = $stmt->fetch();

        if (!$round || $round['status'] !== 'active') {
            $pdo->rollBack();
            return false;
        }

        $resultColor = '';
        $resultNumber = null;
        $resultSize = null;

        // Determine number & color based on manual priority or server automatic generator
        $hasManualNumber = ($forcedNumber !== null && $forcedNumber >= 0 && $forcedNumber <= 9) || 
                            ($round['result_mode'] === 'manual' && $round['manual_number'] !== null);
        $hasManualColor = (!empty($forcedResult) && in_array(strtolower($forcedResult), ['red', 'green', 'violet'], true)) ||
                          ($round['result_mode'] === 'manual' && !empty($round['manual_result']));

        if ($hasManualNumber && $hasManualColor) {
            $resultNumber = ($forcedNumber !== null) ? (int)$forcedNumber : (int)$round['manual_number'];
            $resultColor = !empty($forcedResult) ? strtolower($forcedResult) : strtolower(trim($round['manual_result']));
        } elseif ($hasManualNumber) {
            $resultNumber = ($forcedNumber !== null) ? (int)$forcedNumber : (int)$round['manual_number'];
            if ($resultNumber === 0 || $resultNumber === 5) {
                $resultColor = 'violet';
            } elseif (in_array($resultNumber, [1, 3, 7, 9], true)) {
                $resultColor = 'green';
            } else {
                $resultColor = 'red';
            }
        } elseif ($hasManualColor) {
            $resultColor = !empty($forcedResult) ? strtolower($forcedResult) : strtolower(trim($round['manual_result']));
            if ($resultColor === 'violet') {
                $resultNumber = (random_int(1, 10) > 5) ? 5 : 0;
            } elseif ($resultColor === 'green') {
                $greens = [1, 3, 7, 9, 5];
                $resultNumber = $greens[array_rand($greens)];
            } else {
                $reds = [2, 4, 6, 8, 0];
                $resultNumber = $reds[array_rand($reds)];
            }
        } else {
            // Automatic generation: Pick random number 0 to 9
            $resultNumber = random_int(0, 9);
            if ($resultNumber === 0 || $resultNumber === 5) {
                $resultColor = 'violet';
            } elseif (in_array($resultNumber, [1, 3, 7, 9], true)) {
                $resultColor = 'green';
            } else {
                $resultColor = 'red';
            }
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
            $isWin = false;
            $winMultiplier = 0.0;

            $choice = strtolower(trim($bet['bet_choice'] ?? ''));
            $userColor = !empty($bet['selected_colour']) ? strtolower(trim($bet['selected_colour'])) : null;
            $userNumber = ($bet['selected_number'] !== null && $bet['selected_number'] !== '') ? (int)$bet['selected_number'] : null;

            // Evaluate Number match (multiplier 9.0X)
            $numberMatched = false;
            if ($userNumber !== null && $userNumber === $resultNumber) {
                $numberMatched = true;
            } elseif ($userNumber === null && is_numeric($choice) && (int)$choice === $resultNumber) {
                $numberMatched = true;
            }

            // Evaluate Color match (multiplier 2.0X for red/green, 4.5X for violet, 1.5X for 0/5 split)
            $colorMatched = false;
            $colorMultiplier = 0.0;

            $checkColor = $userColor ?: ($choice === 'red' || $choice === 'green' || $choice === 'violet' ? $choice : null);
            if ($checkColor !== null) {
                if ($checkColor === 'violet' && ($resultColor === 'violet' || in_array($resultNumber, [0, 5], true))) {
                    $colorMatched = true;
                    $colorMultiplier = 4.50;
                } elseif ($checkColor === 'green' && ($resultColor === 'green' || in_array($resultNumber, [1, 3, 7, 9], true))) {
                    $colorMatched = true;
                    $colorMultiplier = 2.00;
                } elseif ($checkColor === 'green' && $resultNumber === 5) {
                    $colorMatched = true;
                    $colorMultiplier = 1.50;
                } elseif ($checkColor === 'red' && ($resultColor === 'red' || in_array($resultNumber, [2, 4, 6, 8], true))) {
                    $colorMatched = true;
                    $colorMultiplier = 2.00;
                } elseif ($checkColor === 'red' && $resultNumber === 0) {
                    $colorMatched = true;
                    $colorMultiplier = 1.50;
                }
            }

            // Compute total multiplier
            if ($numberMatched && $colorMatched) {
                $isWin = true;
                $winMultiplier = 9.00 + $colorMultiplier;
            } elseif ($numberMatched) {
                $isWin = true;
                $winMultiplier = 9.00;
            } elseif ($colorMatched) {
                $isWin = true;
                $winMultiplier = $colorMultiplier;
            }

            if ($isWin) {
                $winAmount = round($betAmount * $winMultiplier, 2);
                $totalPayout += $winAmount;

                // Update original bet record (NO DUPLICATE ROW)
                $upBet = $pdo->prepare("
                    UPDATE `game_bets` 
                    SET `status` = 'won', `win_amount` = :win, `multiplier` = :mult, `updated_at` = NOW() 
                    WHERE `id` = :bid
                ");
                $upBet->execute([
                    ':win' => $winAmount,
                    ':mult' => $winMultiplier,
                    ':bid' => $bet['id']
                ]);

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
                    ':notes' => 'Colour Game Round #' . $round['round_number'] . ' Won Result: ' . strtoupper($resultColor) . ' ' . $resultNumber
                ]);

            } else {
                // Update original bet record as lost (NO DUPLICATE ROW)
                $upBet = $pdo->prepare("
                    UPDATE `game_bets` 
                    SET `status` = 'lost', `win_amount` = 0.00, `updated_at` = NOW() 
                    WHERE `id` = :bid
                ");
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
 * Get live betting statistics for a round (colours & numbers 0-9).
 */
function getRoundLiveStats(PDO $pdo, int $roundId): array {
    $stats = [
        'total_players' => 0,
        'total_amount' => 0.00,
        'colors' => [
            'red' => ['players' => 0, 'points' => 0.00],
            'green' => ['players' => 0, 'points' => 0.00],
            'violet' => ['players' => 0, 'points' => 0.00]
        ],
        'numbers' => []
    ];

    for ($i = 0; $i <= 9; $i++) {
        $stats['numbers'][$i] = ['players' => 0, 'points' => 0.00];
    }

    // Fetch all bets on this round
    $stmt = $pdo->prepare("
        SELECT user_id, amount, bet_choice, selected_colour, selected_number
        FROM `game_bets`
        WHERE `round_id` = :rid
    ");
    $stmt->execute([':rid' => $roundId]);
    $bets = $stmt->fetchAll();

    $distinctUsers = [];
    $colorUsers = ['red' => [], 'green' => [], 'violet' => []];
    $numberUsers = array_fill(0, 10, []);

    foreach ($bets as $b) {
        $uid = (int)$b['user_id'];
        $amt = (float)$b['amount'];
        $distinctUsers[$uid] = true;
        $stats['total_amount'] += $amt;

        // Color attribution
        $col = !empty($b['selected_colour']) ? strtolower($b['selected_colour']) : null;
        if (!$col && in_array(strtolower($b['bet_choice']), ['red', 'green', 'violet'], true)) {
            $col = strtolower($b['bet_choice']);
        }

        if ($col && isset($stats['colors'][$col])) {
            $stats['colors'][$col]['points'] += $amt;
            $colorUsers[$col][$uid] = true;
        }

        // Number attribution
        $num = null;
        if ($b['selected_number'] !== null && $b['selected_number'] !== '') {
            $num = (int)$b['selected_number'];
        } elseif (is_numeric($b['bet_choice'])) {
            $num = (int)$b['bet_choice'];
        }

        if ($num !== null && $num >= 0 && $num <= 9) {
            $stats['numbers'][$num]['points'] += $amt;
            $numberUsers[$num][$uid] = true;
        }
    }

    $stats['total_players'] = count($distinctUsers);

    foreach (['red', 'green', 'violet'] as $c) {
        $stats['colors'][$c]['players'] = count($colorUsers[$c]);
    }

    for ($i = 0; $i <= 9; $i++) {
        $stats['numbers'][$i]['players'] = count($numberUsers[$i]);
    }

    return $stats;
}

/**
 * Set manual result for a round (Admin control).
 */
function setRoundManualResult(PDO $pdo, int $roundId, ?string $color, ?int $number, int $adminId): bool {
    $validColors = ['red', 'green', 'violet'];
    $cleanColor = (!empty($color) && in_array(strtolower($color), $validColors, true)) ? strtolower($color) : null;
    $cleanNumber = ($number !== null && $number >= 0 && $number <= 9) ? (int)$number : null;

    $stmt = $pdo->prepare("
        UPDATE `game_rounds`
        SET `result_mode` = 'manual',
            `manual_result` = :color,
            `manual_number` = :num,
            `result_status` = 'locked',
            `manually_set_at` = NOW(),
            `manually_set_by` = :admin
        WHERE `id` = :rid AND `status` = 'active'
    ");
    return $stmt->execute([
        ':color' => $cleanColor,
        ':num' => $cleanNumber,
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
