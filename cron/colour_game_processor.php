<?php
/**
 * Sikkim Gaming Platform - Colour Game Round Processor Cron
 * CLI and Web compatible.
 * Handles automatic expiration, outcome settlement, payouts, and sequential round creation.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/round_engine.php';

$pdo = getDB();
$games = ['colour-game', 'win-go-1m'];
$processedCount = 0;

foreach ($games as $slug) {
    // 1. Fetch expired active rounds
    $stmt = $pdo->prepare("
        SELECT id, round_number, end_time, result_mode, manual_result 
        FROM `game_rounds` 
        WHERE `game_slug` = :slug AND `status` = 'active' AND `end_time` <= NOW()
        ORDER BY `id` ASC
    ");
    $stmt->execute([':slug' => $slug]);
    $expiredRounds = $stmt->fetchAll();

    foreach ($expiredRounds as $r) {
        $forced = ($r['result_mode'] === 'manual' && !empty($r['manual_result'])) ? $r['manual_result'] : null;
        $done = finalizeRoundAndCreateNext($pdo, (int)$r['id'], $slug, $forced);
        if ($done) {
            $processedCount++;
        }
    }

    // 2. Ensure an active round exists
    processAndGetActiveRound($pdo, $slug);
}

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'rounds_processed' => $processedCount,
    'message' => "Successfully audited and processed {$processedCount} game rounds."
];

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] COLOUR GAME CRON: Processed {$processedCount} rounds.\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
