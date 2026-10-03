<?php
/**
 * Sikkim Gaming Platform - Real Cron Processing Script
 * CLI or HTTP compatible. Updates real MySQL database.
 * No fake JavaScript simulation.
 */

// If accessed via CLI or HTTP
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/round_engine.php';

$pdo = getDB();
$now = date('Y-m-d H:i:s');

// 1. Process active rounds whose end_time has passed
$stmt = $pdo->prepare("
    SELECT id, round_number, game_slug, end_time
    FROM `game_rounds`
    WHERE `status` = 'active' AND `end_time` <= :now
");
$stmt->execute([':now' => $now]);
$expiredRounds = $stmt->fetchAll();

$processed = [];
foreach ($expiredRounds as $r) {
    finalizeRoundAndCreateNext($pdo, (int)$r['id'], $r['game_slug']);
    $processed[] = [
        'id' => $r['id'],
        'round_number' => $r['round_number'],
        'game' => $r['game_slug'],
        'status' => 'finalized'
    ];
}

// 2. Ensure every active game has an active round
$games = $pdo->query("SELECT DISTINCT game_slug FROM `game_rounds`")->fetchAll(PDO::FETCH_COLUMN);
if (empty($games)) {
    $games = ['win-go-1m'];
}

foreach ($games as $slug) {
    processAndGetActiveRound($pdo, $slug);
}

$response = [
    'success' => true,
    'timestamp' => $now,
    'expired_rounds_finalized' => count($processed),
    'details' => $processed
];

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] CRON RUN: Processed " . count($processed) . " rounds.\n";
} else {
    echo json_encode($response, JSON_PRETTY_PRINT);
}
