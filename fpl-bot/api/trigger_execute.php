<?php
/**
 * API Endpoint: Pemicu Eksekusi Manual 1-Klik (Apply to FPL)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/CronHandler.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $cron = new CronHandler();
    // Force execute manual
    $result = $cron->run(true);

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
