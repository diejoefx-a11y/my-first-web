<?php
/**
 * API Endpoint: Simpan Status Toggle & Bobot Parameter
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['settings'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid payload format']);
        exit;
    }

    $db = Database::getConnection();
    $stmt = $db->prepare("
        UPDATE `settings` 
        SET `is_active` = ?, `weight` = ? 
        WHERE `param_key` = ?
    ");

    foreach ($input['settings'] as $key => $val) {
        $isActive = !empty($val['is_active']) ? 1 : 0;
        $weight = max(0, min(100, (int)($val['weight'] ?? 0)));
        $stmt->execute([$isActive, $weight, $key]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Pengaturan parameter dan bobot berhasil disimpan!'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
