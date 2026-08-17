<?php
/**
 * API Endpoint: Cari Daftar Pemain FPL untuk Squad Builder
 * Mendukung filter posisi, batas harga maksimal (budget), kuota tim (maks 3 per klub), dan sorting popularitas.
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../src/FplService.php';

try {
    $q = strtolower(trim($_GET['q'] ?? ''));
    $typeFilter = (int)($_GET['type'] ?? 0); // 1: GKP, 2: DEF, 3: MID, 4: FWD
    $maxCost = isset($_GET['max_cost']) ? (float)$_GET['max_cost'] : 0.0;
    
    // Daftar tim yang kuotanya sudah penuh (3 pemain) untuk di-exclude
    $excludeTeamsRaw = trim($_GET['exclude_teams'] ?? '');
    $excludeTeams = [];
    if (!empty($excludeTeamsRaw)) {
        $excludeTeams = array_map('strtolower', array_map('trim', explode(',', $excludeTeamsRaw)));
    }

    $fpl = new FplService();
    $bootstrap = $fpl->getBootstrapStatic();
    $elements = $bootstrap['elements'] ?? [];
    $teams = $bootstrap['teams'] ?? [];

    $teamMap = [];
    foreach ($teams as $t) {
        $teamMap[$t['id']] = $t['short_name'] ?? 'PL';
    }

    $matched = [];
    foreach ($elements as $el) {
        // 1. Filter Posisi Ketat
        if ($typeFilter > 0 && (int)$el['element_type'] !== $typeFilter) {
            continue;
        }

        $cost = $el['now_cost'] / 10.0;

        // 2. Filter Batasan Budget (Harga <= Max Cost)
        if ($maxCost > 0.0 && $cost > ($maxCost + 0.001)) {
            continue;
        }

        $teamShort = $teamMap[$el['team']] ?? 'PL';
        $teamShortLower = strtolower($teamShort);

        // 3. Filter Kuota Tim (Hanya tampilkan jika tim belum mencapai 3 pemain)
        if (!empty($excludeTeams) && in_array($teamShortLower, $excludeTeams)) {
            continue;
        }

        $fullName = strtolower($el['first_name'] . ' ' . $el['second_name']);
        $webName = strtolower($el['web_name']);

        // 4. Filter Pencarian Teks
        if (empty($q) || strpos($fullName, $q) !== false || strpos($webName, $q) !== false || strpos($teamShortLower, $q) !== false) {
            $matched[] = [
                'id' => $el['id'],
                'web_name' => $el['web_name'],
                'full_name' => $el['first_name'] . ' ' . $el['second_name'],
                'team' => $teamShort,
                'element_type' => (int)$el['element_type'],
                'now_cost' => $cost,
                'form' => (float)($el['form'] ?? 0),
                'selected_by_percent' => (float)($el['selected_by_percent'] ?? 0),
                'chance_of_playing' => $el['chance_of_playing_next_round'],
                'news' => $el['news'] ?? ''
            ];
        }
    }

    // Urutkan berdasarkan kepemilikan tertinggi (popularitas)
    usort($matched, function($a, $b) {
        return $b['selected_by_percent'] <=> $a['selected_by_percent'];
    });

    $results = array_slice($matched, 0, 80);

    echo json_encode([
        'status' => 'success',
        'count' => count($results),
        'max_cost_applied' => $maxCost,
        'excluded_teams' => $excludeTeams,
        'players' => $results
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
