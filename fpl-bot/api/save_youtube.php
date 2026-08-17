<?php
/**
 * API Endpoint: Simpan & Ekstraksi Link Video YouTube Analis FPL
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/YoutubeService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['video_url']) || empty($input['gameweek'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Gameweek dan Video URL wajib diisi']);
        exit;
    }

    $gw = (int)$input['gameweek'];
    $url = trim($input['video_url']);
    $buys = is_array($input['buys'] ?? null) ? $input['buys'] : [];
    $sells = is_array($input['sells'] ?? null) ? $input['sells'] : [];
    $captains = is_array($input['captains'] ?? null) ? $input['captains'] : [];
    $notes = trim($input['notes'] ?? '');

    $ytService = new YoutubeService();
    $res = $ytService->saveVideoInsight($gw, $url, $buys, $sells, $captains, $notes);

    echo json_encode($res);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
