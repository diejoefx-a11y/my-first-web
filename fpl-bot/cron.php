<?php
/**
 * FPL-BOT - Background Cron Runner (cPanel / Server Cron Entrypoint)
 *
 * Cara Menjalankan di cPanel Niagahoster:
 * Cron command (setiap 5 menit):
 * php -q /home/USER/public_html/fpl-bot/cron.php
 *
 * Atau via URL Webhook:
 * https://domain-anda.com/fpl-bot/cron.php?token=fpl_bot_secret_token_12345
 */

require_once __DIR__ . '/src/CronHandler.php';

$isCli = (php_sapi_name() === 'cli');

// Jika dijalankan via browser/HTTP, validasi token keamanan
if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
    $config = require __DIR__ . '/config/config.php';
    $secretToken = $config['cron']['secret_token'] ?? 'fpl_bot_secret_token_12345';
    $providedToken = $_GET['token'] ?? '';

    if ($providedToken !== $secretToken) {
        http_response_code(403);
        echo json_encode([
            'status' => 'FORBIDDEN',
            'message' => 'Akses ditolak: Token cron tidak valid.'
        ]);
        exit;
    }
}

try {
    $cron = new CronHandler();
    $result = $cron->run(false);

    if ($isCli) {
        echo "[" . date('Y-m-d H:i:s') . "] Status: " . ($result['status'] ?? 'UNKNOWN') . " | " . ($result['message'] ?? '') . PHP_EOL;
        if (!empty($result['details'])) {
            echo "Details: " . $result['details'] . PHP_EOL;
        }
    } else {
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
} catch (Throwable $e) {
    if ($isCli) {
        echo "[" . date('Y-m-d H:i:s') . "] CRON ERROR: " . $e->getMessage() . PHP_EOL;
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'ERROR', 'message' => $e->getMessage()]);
    }
}
