<?php
/**
 * Sikkim Gaming Platform - Crash Game Background Processor
 * Advances round states: WAITING -> RUNNING -> CRASHED -> COMPLETED.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crash_engine.php';

$pdo = getDB();
$round = processAndGetActiveCrashRound($pdo);

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'round' => [
        'id' => (int)$round['id'],
        'round_number' => (int)$round['round_number'],
        'status' => $round['status']
    ]
];

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] CRASH GAME CRON: Round #" . $round['round_number'] . " Status: " . $round['status'] . "\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_PRETTY_PRINT);
}
