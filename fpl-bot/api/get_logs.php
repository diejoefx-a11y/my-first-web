<?php
/**
 * API Endpoint: Ambil Riwayat Eksekusi (Logs)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getConnection();
    $stmt = $db->query("
        SELECT * FROM `execution_logs` 
        ORDER BY `id` DESC 
        LIMIT 20
    ");
    $logs = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'logs' => $logs
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
