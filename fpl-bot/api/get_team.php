<?php
/**
 * API Endpoint: Ambil Data Tim, Visual Skuad & Rekomendasi Bot
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/ScoringEngine.php';

try {
    $teamId = $_GET['team_id'] ?? '';
    $engine = new ScoringEngine();
    $result = $engine->generateDecisionPlan($teamId);

    echo json_encode($result);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
