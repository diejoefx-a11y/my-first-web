<?php
/**
 * API Endpoint: Simpan Skuad Pilihan Manajer (Aturan Resmi FPL: 2 GK, 5 DEF, 5 MID, 3 FWD = 15 Pemain)
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/FplService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || empty($input['picks'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Data skuad tidak valid']);
        exit;
    }

    $picks = $input['picks'];
    $pickCount = count($picks);

    if ($pickCount !== 15) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Jumlah skuad harus tepat 15 pemain (2 GK, 5 DEF, 5 MID, 3 FWD)'
        ]);
        exit;
    }

    $fpl = new FplService();
    $bootstrap = $fpl->getBootstrapStatic();
    $elements = $bootstrap['elements'] ?? [];
    $teams = $bootstrap['teams'] ?? [];

    $elemMap = [];
    foreach ($elements as $el) {
        $elemMap[$el['id']] = $el;
    }

    $teamMap = [];
    foreach ($teams as $t) {
        $teamMap[$t['id']] = $t['short_name'] ?? 'PL';
    }

    $totalCost = 0;
    $typeCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $teamCounts = [];

    foreach ($picks as $pick) {
        $elemId = (int)$pick['element_id'];
        if (!isset($elemMap[$elemId])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Pemain ID {$elemId} tidak ditemukan di database FPL."]);
            exit;
        }

        $el = $elemMap[$elemId];
        $totalCost += ($el['now_cost'] / 10.0);
        $typeCounts[$el['element_type']] = ($typeCounts[$el['element_type']] ?? 0) + 1;
        
        $teamId = $el['team'];
        $teamCounts[$teamId] = ($teamCounts[$teamId] ?? 0) + 1;

        if ($teamCounts[$teamId] > 3) {
            $teamName = $teamMap[$teamId] ?? "Tim #{$teamId}";
            http_response_code(400);
            echo json_encode([
                'status' => 'error', 
                'message' => "Aturan FPL: Maksimal 3 pemain dari klub yang sama! Anda memilih lebih dari 3 pemain dari {$teamName}."
            ]);
            exit;
        }
    }

    // Validasi komposisi posisi: 2 GK, 5 DEF, 5 MID, 3 FWD
    if ($typeCounts[1] !== 2 || $typeCounts[2] !== 5 || $typeCounts[3] !== 5 || $typeCounts[4] !== 3) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => "Komposisi posisi harus tepat: 2 Kiper (GK), 5 Bek (DEF), 5 Gelandang (MID), dan 3 Penyerang (FWD)."
        ]);
        exit;
    }

    // Cek batas total budget (£100.0m)
    $totalCostRounded = round($totalCost, 1);
    if ($totalCostRounded > 100.0) {
        $deficit = round($totalCostRounded - 100.0, 1);
        http_response_code(400);
        echo json_encode([
            'status' => 'error', 
            'message' => "Total biaya skuad (£{$totalCostRounded}m) melebihi batas budget (£100.0m)! Saldo kurang £{$deficit}m."
        ]);
        exit;
    }

    // Simpan ke Database MySQL
    $db = Database::getConnection();
    $db->beginTransaction();

    $db->exec("DELETE FROM `user_squad`");

    $stmt = $db->prepare("
        INSERT INTO `user_squad` (`element_id`, `position`, `is_captain`, `is_vice_captain`)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($picks as $idx => $pick) {
        $elemId = (int)$pick['element_id'];
        $pos = $idx + 1;
        $isCap = !empty($pick['is_captain']) ? 1 : 0;
        $isVc = !empty($pick['is_vice_captain']) ? 1 : 0;

        $stmt->execute([$elemId, $pos, $isCap, $isVc]);
    }

    $db->commit();

    $remainingBank = round(100.0 - $totalCostRounded, 1);

    echo json_encode([
        'status' => 'success',
        'message' => "15 Pemain berhasil disimpan (2 GK, 5 DEF, 5 MID, 3 FWD)! Total: £{$totalCostRounded}m | Sisa Bank: £{$remainingBank}m",
        'total_cost' => $totalCostRounded,
        'bank' => $remainingBank
    ]);

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
