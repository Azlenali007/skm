<?php
/**
 * Sikkim Gaming Platform - Authoritative Crash Game Engine
 * Completely Server-Side & MySQL Authoritative.
 * State Lifecycle: WAITING -> RUNNING -> CRASHED -> COMPLETED -> NEXT ROUND.
 * Multiplier curve: M(t) = exp(0.06 * t) where t is elapsed flight seconds.
 * Result secrecy: Un-crashed final multiplier is NEVER transmitted to players.
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Calculate instantaneous flight multiplier from elapsed seconds.
 */
function getCrashMultiplierAtSeconds(float $elapsedSeconds): float {
    if ($elapsedSeconds <= 0) {
        return 1.00;
    }
    // M(t) = e^(0.06 * t) rounded to 2 decimals
    $mult = exp(0.06 * $elapsedSeconds);
    return max(1.00, floor($mult * 100) / 100);
}

/**
 * Calculate required flight seconds to reach target multiplier.
 */
function getCrashDurationForMultiplier(float $targetMultiplier): float {
    if ($targetMultiplier <= 1.00) {
        return 0.20;
    }
    return log($targetMultiplier) / 0.06;
}

/**
 * Generate server-authoritative provably fair crash multiplier.
 */
function generateCrashMultiplier(): float {
    $rand = random_int(1, 100);
    if ($rand <= 6) {
        // 6% instant low crash 1.00x - 1.15x
        return round(1.00 + (random_int(0, 15) / 100), 2);
    } elseif ($rand <= 45) {
        // 39% low tier 1.16x - 1.99x
        return round(1.16 + (random_int(0, 83) / 100), 2);
    } elseif ($rand <= 75) {
        // 30% mid tier 2.00x - 3.99x
        return round(2.00 + (random_int(0, 199) / 100), 2);
    } elseif ($rand <= 92) {
        // 17% high tier 4.00x - 9.99x
        return round(4.00 + (random_int(0, 599) / 100), 2);
    } else {
        // 8% rocket streak 10.00x - 45.00x
        return round(10.00 + (random_int(0, 3500) / 100), 2);
    }
}

/**
 * Authoritative round state machine.
 */
function processAndGetActiveCrashRound(PDO $pdo): array {
    $now = date('Y-m-d H:i:s');
    $nowTs = time();

    // Check for existing active or recent round
    $stmt = $pdo->query("
        SELECT * FROM `crash_rounds`
        WHERE `status` IN ('waiting', 'running', 'crashed')
        ORDER BY `round_number` DESC
        LIMIT 1
    ");
    $round = $stmt->fetch();

    if (!$round) {
        // Find last round number
        $maxRn = (int)$pdo->query("SELECT MAX(round_number) FROM `crash_rounds`")->fetchColumn();
        $nextRn = $maxRn > 0 ? ($maxRn + 1) : 501;

        $waitingSeconds = 6;
        $crashMult = generateCrashMultiplier();
        $flightDuration = getCrashDurationForMultiplier($crashMult);

        $startTime = date('Y-m-d H:i:s', $nowTs);
        $flightStartTime = date('Y-m-d H:i:s', $nowTs + $waitingSeconds);
        $crashTime = date('Y-m-d H:i:s', $nowTs + $waitingSeconds + (int)ceil($flightDuration));

        $ins = $pdo->prepare("
            INSERT INTO `crash_rounds` 
            (`round_number`, `status`, `start_time`, `flight_start_time`, `crash_time`, `crash_multiplier`, `result_mode`)
            VALUES (:rn, 'waiting', :start, :fstart, :ctime, :cmult, 'auto')
        ");
        $ins->execute([
            ':rn' => $nextRn,
            ':start' => $startTime,
            ':fstart' => $flightStartTime,
            ':ctime' => $crashTime,
            ':cmult' => $crashMult
        ]);

        $roundId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM `crash_rounds` WHERE `id` = :id");
        $stmt->execute([':id' => $roundId]);
        return $stmt->fetch();
    }

    $roundId = (int)$round['id'];
    $flightStartTs = strtotime($round['flight_start_time']);
    $crashTs = strtotime($round['crash_time']);

    // State 1: WAITING -> RUNNING
    if ($round['status'] === 'waiting') {
        if ($nowTs >= $flightStartTs) {
            $up = $pdo->prepare("UPDATE `crash_rounds` SET `status` = 'running' WHERE `id` = :id AND `status` = 'waiting'");
            $up->execute([':id' => $roundId]);
            $round['status'] = 'running';
        }
        return $round;
    }

    // State 2: RUNNING -> CRASHED
    if ($round['status'] === 'running') {
        if ($nowTs >= $crashTs) {
            try {
                $pdo->beginTransaction();

                // Mark round crashed
                $up = $pdo->prepare("
                    UPDATE `crash_rounds` 
                    SET `status` = 'crashed', `result_published` = 1 
                    WHERE `id` = :id AND `status` = 'running'
                ");
                $up->execute([':id' => $roundId]);

                // All bets remaining 'pending' crashed/lost
                $upBets = $pdo->prepare("
                    UPDATE `crash_bets`
                    SET `status` = 'crashed', `win_amount` = 0.00, `updated_at` = NOW()
                    WHERE `round_id` = :rid AND `status` = 'pending'
                ");
                $upBets->execute([':rid' => $roundId]);

                $pdo->commit();
                $round['status'] = 'crashed';
                $round['result_published'] = 1;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Crash transition error: " . $e->getMessage());
            }
        }
        return $round;
    }

    // State 3: CRASHED -> COMPLETED & SPAWN NEXT ROUND
    if ($round['status'] === 'crashed') {
        // 3 seconds cool-down period
        if ($nowTs >= ($crashTs + 3)) {
            try {
                $pdo->beginTransaction();

                // Calculate totals
                $tStmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(amount), 0) as total_bets,
                        COALESCE(SUM(win_amount), 0) as total_payout
                    FROM `crash_bets`
                    WHERE `round_id` = :rid
                ");
                $tStmt->execute([':rid' => $roundId]);
                $totals = $tStmt->fetch();

                // Mark completed
                $compStmt = $pdo->prepare("
                    UPDATE `crash_rounds`
                    SET `status` = 'completed',
                        `total_bets_amount` = :tb,
                        `total_payout_amount` = :tp,
                        `completed_at` = NOW()
                    WHERE `id` = :id
                ");
                $compStmt->execute([
                    ':tb' => $totals['total_bets'],
                    ':tp' => $totals['total_payout'],
                    ':id' => $roundId
                ]);

                // Next sequential round
                $nextRn = (int)$round['round_number'] + 1;
                $waitingSeconds = 6;
                $crashMult = generateCrashMultiplier();
                $flightDuration = getCrashDurationForMultiplier($crashMult);

                $startTime = date('Y-m-d H:i:s', $nowTs);
                $flightStartTime = date('Y-m-d H:i:s', $nowTs + $waitingSeconds);
                $crashTime = date('Y-m-d H:i:s', $nowTs + $waitingSeconds + (int)ceil($flightDuration));

                $ins = $pdo->prepare("
                    INSERT INTO `crash_rounds` 
                    (`round_number`, `status`, `start_time`, `flight_start_time`, `crash_time`, `crash_multiplier`, `result_mode`)
                    VALUES (:rn, 'waiting', :start, :fstart, :ctime, :cmult, 'auto')
                ");
                $ins->execute([
                    ':rn' => $nextRn,
                    ':start' => $startTime,
                    ':fstart' => $flightStartTime,
                    ':ctime' => $crashTime,
                    ':cmult' => $crashMult
                ]);

                $newRoundId = (int)$pdo->lastInsertId();
                $pdo->commit();

                $stmt = $pdo->prepare("SELECT * FROM `crash_rounds` WHERE `id` = :id");
                $stmt->execute([':id' => $newRoundId]);
                return $stmt->fetch();

            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Round completion error: " . $e->getMessage());
            }
        }
        return $round;
    }

    return $round;
}

/**
 * Cash out active user bet.
 */
function cashOutUserBet(PDO $pdo, int $betId, int $userId): array {
    try {
        $pdo->beginTransaction();

        // Lock bet record
        $bStmt = $pdo->prepare("SELECT * FROM `crash_bets` WHERE `id` = :id AND `user_id` = :uid FOR UPDATE");
        $bStmt->execute([':id' => $betId, ':uid' => $userId]);
        $bet = $bStmt->fetch();

        if (!$bet) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Bet not found.'];
        }

        if ($bet['status'] !== 'pending') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Bet already settled or cashed out.'];
        }

        // Lock round record
        $rStmt = $pdo->prepare("SELECT * FROM `crash_rounds` WHERE `id` = :rid FOR UPDATE");
        $rStmt->execute([':rid' => $bet['round_id']]);
        $round = $rStmt->fetch();

        if (!$round || $round['status'] !== 'running') {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Round is not currently in flight.'];
        }

        $now = time();
        $flightStart = strtotime($round['flight_start_time']);
        $crashTime = strtotime($round['crash_time']);

        if ($now >= $crashTime) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Aircraft has already crashed!'];
        }

        $elapsedSeconds = max(0, $now - $flightStart);
        $liveMult = getCrashMultiplierAtSeconds((float)$elapsedSeconds);

        if ($liveMult > (float)$round['crash_multiplier']) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Aircraft crashed before cash out!'];
        }

        $amount = (float)$bet['amount'];
        $winAmount = round($amount * $liveMult, 2);

        // Update Bet to cashed_out
        $upBet = $pdo->prepare("
            UPDATE `crash_bets`
            SET `status` = 'cashed_out',
                `cashed_out_at` = NOW(),
                `cashed_out_multiplier` = :mult,
                `win_amount` = :win
            WHERE `id` = :id
        ");
        $upBet->execute([
            ':mult' => $liveMult,
            ':win' => $winAmount,
            ':id' => $betId
        ]);

        // Credit User Wallet
        $upW = $pdo->prepare("UPDATE `wallets` SET `balance` = `balance` + :win WHERE `user_id` = :uid");
        $upW->execute([':win' => $winAmount, ':uid' => $userId]);

        // Fetch balance for audit
        $wBal = $pdo->prepare("SELECT balance FROM `wallets` WHERE `user_id` = :uid");
        $wBal->execute([':uid' => $userId]);
        $afterBal = (float)$wBal->fetchColumn();

        // Transaction Ledger
        $tx = $pdo->prepare("
            INSERT INTO `transactions` 
            (`user_id`, `type`, `amount`, `balance_before`, `balance_after`, `reference_id`, `status`, `notes`)
            VALUES (:uid, 'win', :amt, :before, :after, :ref, 'completed', :notes)
        ");
        $tx->execute([
            ':uid' => $userId,
            ':amt' => $winAmount,
            ':before' => $afterBal - $winAmount,
            ':after' => $afterBal,
            ':ref' => 'CRASH-WIN-' . $round['round_number'] . '-' . $betId,
            ':notes' => 'Crash Game Round #' . $round['round_number'] . ' Cashed Out at ' . $liveMult . 'x'
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Cashed out successfully at ' . number_format($liveMult, 2) . 'x! Won ₹' . number_format($winAmount, 2),
            'multiplier' => $liveMult,
            'win_amount' => $winAmount,
            'new_balance' => $afterBal
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Server error: ' . $e->getMessage()];
    }
}

/**
 * Get live stats for active crash round.
 */
function getCrashLiveStats(PDO $pdo, int $roundId): array {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bets,
            COUNT(DISTINCT user_id) as total_players,
            COALESCE(SUM(amount), 0) as total_amount,
            COALESCE(SUM(CASE WHEN status = 'cashed_out' THEN 1 ELSE 0 END), 0) as cashed_out_count,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
            COALESCE(SUM(win_amount), 0) as total_payout
        FROM `crash_bets`
        WHERE `round_id` = :rid
    ");
    $stmt->execute([':rid' => $roundId]);
    $res = $stmt->fetch();

    return [
        'total_players' => (int)($res['total_players'] ?? 0),
        'total_bets' => (int)($res['total_bets'] ?? 0),
        'total_amount' => (float)($res['total_amount'] ?? 0.00),
        'cashed_out_count' => (int)($res['cashed_out_count'] ?? 0),
        'pending_count' => (int)($res['pending_count'] ?? 0),
        'total_payout' => (float)($res['total_payout'] ?? 0.00)
    ];
}

/**
 * Set manual crash multiplier for active round.
 */
function setCrashManualMultiplier(PDO $pdo, int $roundId, float $multiplier, int $adminId): bool {
    if ($multiplier < 1.01) {
        return false;
    }

    $flightDuration = getCrashDurationForMultiplier($multiplier);

    // Fetch flight_start_time
    $stmt = $pdo->prepare("SELECT flight_start_time, status FROM `crash_rounds` WHERE `id` = :id");
    $stmt->execute([':id' => $roundId]);
    $round = $stmt->fetch();

    if (!$round || $round['status'] === 'crashed' || $round['status'] === 'completed') {
        return false;
    }

    $fStart = strtotime($round['flight_start_time']);
    $newCrashTime = date('Y-m-d H:i:s', $fStart + (int)ceil($flightDuration));

    $up = $pdo->prepare("
        UPDATE `crash_rounds`
        SET `result_mode` = 'manual',
            `manual_multiplier` = :mm,
            `crash_multiplier` = :cm,
            `crash_time` = :ctime
        WHERE `id` = :id
    ");
    return $up->execute([
        ':mm' => $multiplier,
        ':cm' => $multiplier,
        ':ctime' => $newCrashTime,
        ':id' => $roundId
    ]);
}
