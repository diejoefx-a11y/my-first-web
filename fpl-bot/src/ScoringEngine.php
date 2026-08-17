<?php
/**
 * FPL-BOT - Modular Multi-Parameter Decision & Scoring Engine
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/FplService.php';
require_once __DIR__ . '/YoutubeService.php';

class ScoringEngine {
    private PDO $db;
    private FplService $fpl;
    private YoutubeService $youtube;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->fpl = new FplService();
        $this->youtube = new YoutubeService();
    }

    public function getSettings(): array {
        $stmt = $this->db->query("SELECT * FROM `settings`");
        $rows = $stmt->fetchAll();
        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['param_key']] = [
                'id' => $r['id'],
                'name' => $r['param_name'],
                'is_active' => (bool)$r['is_active'],
                'weight' => (int)$r['weight'],
                'description' => $r['description']
            ];
        }
        return $settings;
    }

    public function calculatePlayerScores(int $targetGw): array {
        $bootstrap = $this->fpl->getBootstrapStatic();
        $fixtures = $this->fpl->getFixtures();
        $elements = $bootstrap['elements'] ?? [];
        $teams = $bootstrap['teams'] ?? [];

        $teamMap = [];
        foreach ($teams as $t) {
            $teamMap[$t['id']] = $t;
        }

        $teamFdrMap = $this->calculateTeamFdr($fixtures, $targetGw, 3);

        $ytConsensus = $this->youtube->getConsensusForGameweek($targetGw);
        $ytBuys = array_map('strtolower', $ytConsensus['recommended_buys'] ?? []);
        $ytSells = array_map('strtolower', $ytConsensus['recommended_sells'] ?? []);
        $ytCaps = array_map('strtolower', $ytConsensus['recommended_captains'] ?? []);

        $settings = $this->getSettings();
        $wStats = ($settings['xg_xa_form']['is_active'] ?? true) ? ($settings['xg_xa_form']['weight'] ?? 30) : 0;
        $wFdr = ($settings['fdr_schedule']['is_active'] ?? true) ? ($settings['fdr_schedule']['weight'] ?? 25) : 0;
        $wYt = ($settings['youtube_consensus']['is_active'] ?? true) ? ($settings['youtube_consensus']['weight'] ?? 20) : 0;
        $wEo = ($settings['effective_ownership']['is_active'] ?? true) ? ($settings['effective_ownership']['weight'] ?? 15) : 0;
        $wIct = ($settings['ict_index']['is_active'] ?? true) ? ($settings['ict_index']['weight'] ?? 10) : 0;

        $totalWeight = max(1, ($wStats + $wFdr + $wYt + $wEo + $wIct));

        $scoredPlayers = [];

        foreach ($elements as $el) {
            $chance = $el['chance_of_playing_next_round'];
            $isInjured = ($chance !== null && $chance < 75);

            // 1. Stats Score
            $form = (float)($el['form'] ?? 0);
            $expectedGoals = (float)($el['expected_goals'] ?? 0);
            $expectedAssists = (float)($el['expected_assists'] ?? 0);
            $statsScore = min(100, ($form * 10) + ($expectedGoals * 6) + ($expectedAssists * 5));

            // 2. FDR Score
            $teamId = $el['team'];
            $avgFdr = $teamFdrMap[$teamId] ?? 3.0;
            $fdrScore = max(0, min(100, (5.5 - $avgFdr) * 25));

            // 3. YouTube Consensus Score
            $webNameLower = strtolower($el['web_name']);
            $fullNameLower = strtolower($el['first_name'] . ' ' . $el['second_name']);
            $ytScore = 50;
            foreach ($ytBuys as $buy) {
                if (strpos($webNameLower, $buy) !== false || strpos($fullNameLower, $buy) !== false || strpos($buy, $webNameLower) !== false) {
                    $ytScore += 35;
                }
            }
            foreach ($ytCaps as $cap) {
                if (strpos($webNameLower, $cap) !== false || strpos($fullNameLower, $cap) !== false || strpos($cap, $webNameLower) !== false) {
                    $ytScore += 25;
                }
            }
            foreach ($ytSells as $sell) {
                if (strpos($webNameLower, $sell) !== false || strpos($fullNameLower, $sell) !== false || strpos($sell, $webNameLower) !== false) {
                    $ytScore -= 40;
                }
            }
            $ytScore = max(0, min(100, $ytScore));

            // 4. EO Score
            $selectedBy = (float)($el['selected_by_percent'] ?? 0);
            $eoScore = min(100, $selectedBy * 2);

            // 5. ICT Score
            $ictRank = (float)($el['ict_index'] ?? 0);
            $ictScore = min(100, $ictRank / 2);

            $finalScore = (
                ($statsScore * $wStats) +
                ($fdrScore * $wFdr) +
                ($ytScore * $wYt) +
                ($eoScore * $wEo) +
                ($ictScore * $wIct)
            ) / $totalWeight;

            if ($isInjured) {
                $finalScore = $finalScore * 0.1;
            }

            $scoredPlayers[$el['id']] = [
                'id' => $el['id'],
                'web_name' => $el['web_name'],
                'full_name' => $el['first_name'] . ' ' . $el['second_name'],
                'team_id' => $teamId,
                'team_short' => $teamMap[$teamId]['short_name'] ?? 'PL',
                'element_type' => $el['element_type'],
                'now_cost' => $el['now_cost'] / 10.0,
                'raw_cost' => $el['now_cost'],
                'form' => $form,
                'selected_by_percent' => $selectedBy,
                'chance_of_playing' => $chance,
                'is_injured' => $isInjured,
                'news' => $el['news'] ?? '',
                'avg_fdr' => round($avgFdr, 1),
                'total_score' => round($finalScore, 1),
                'stats_score' => round($statsScore, 1),
                'fdr_score' => round($fdrScore, 1),
                'yt_score' => round($ytScore, 1),
                'eo_score' => round($eoScore, 1)
            ];
        }

        return $scoredPlayers;
    }

    public function generateDecisionPlan(string $customTeamId = ''): array {
        $gwInfo = $this->fpl->getCurrentAndNextGameweek();
        $targetGw = $gwInfo['next']['id'] ?? ($gwInfo['current']['id'] ?? 1);

        $teamData = $this->fpl->getMyTeam($customTeamId);
        if (($teamData['status'] ?? '') === 'error') {
            return $teamData;
        }

        $allScoredPlayers = $this->calculatePlayerScores($targetGw);
        $myPicks = $teamData['data']['picks'] ?? [];

        // Hitung total biaya aktual skuad saat ini
        $squad = [];
        $squadIds = [];
        $teamCounts = [];
        $totalSquadCost = 0;

        foreach ($myPicks as $pick) {
            $pId = $pick['element'];
            $squadIds[] = $pId;
            if (isset($allScoredPlayers[$pId])) {
                $p = $allScoredPlayers[$pId];
                $p['position'] = $pick['position'];
                $p['is_captain'] = (bool)($pick['is_captain'] ?? false);
                $p['is_vice_captain'] = (bool)($pick['is_vice_captain'] ?? false);
                $squad[] = $p;
                
                $totalSquadCost += $p['now_cost'];
                $tId = $p['team_id'];
                $teamCounts[$tId] = ($teamCounts[$tId] ?? 0) + 1;
            }
        }

        $totalSquadCost = round($totalSquadCost, 1);
        
        // Sisa Saldo di Bank
        $rawBank = ($teamData['data']['transfers']['bank'] ?? 0) / 10.0;
        $bank = ($rawBank > 0) ? $rawBank : max(0.0, round(100.0 - $totalSquadCost, 1));
        $freeTransfers = max(1, ($teamData['data']['transfers']['limit'] ?? 1) - ($teamData['data']['transfers']['made'] ?? 0));

        // 1. Cari Calon Transfer Out (Paling diprioritaskan: Cedera -> Skor Terendah)
        usort($squad, function($a, $b) {
            if ($a['is_injured'] !== $b['is_injured']) {
                return $a['is_injured'] ? -1 : 1;
            }
            return $a['total_score'] <=> $b['total_score'];
        });

        $transferOut = $squad[0] ?? null;
        $transferIn = null;

        if ($transferOut) {
            // BATAS ANGGARAN KETAT: Harga pemain baru TIDAK BOLEH melebihi (harga jual + saldo bank)
            $maxAffordableBudget = round($transferOut['now_cost'] + $bank, 1);
            $reqType = $transferOut['element_type'];
            $outTeamId = $transferOut['team_id'];

            // Filter calon transfer in:
            // 1. Posisi sama
            // 2. Belum ada di skuad
            // 3. Harga <= maxAffordableBudget (WAJIB SESUAI SALDO BANK)
            // 4. Tidak melanggar aturan maks 3 pemain per klub
            // 5. Tidak cedera
            $candidates = [];
            foreach ($allScoredPlayers as $cand) {
                if ($cand['element_type'] === $reqType && !in_array($cand['id'], $squadIds)) {
                    if ($cand['now_cost'] <= $maxAffordableBudget && !$cand['is_injured']) {
                        // Cek kuota klub
                        $candTeamId = $cand['team_id'];
                        $currentTeamCount = $teamCounts[$candTeamId] ?? 0;
                        if ($candTeamId == $outTeamId) {
                            $currentTeamCount -= 1; // Pemain tim ini keluar 1
                        }

                        if ($currentTeamCount < 3) {
                            $candidates[] = $cand;
                        }
                    }
                }
            }

            usort($candidates, function($a, $b) {
                return $b['total_score'] <=> $a['total_score'];
            });

            // Hanya rekomendasikan transfer jika skor pemain baru lebih tinggi dari pemain keluar
            if (!empty($candidates) && $candidates[0]['total_score'] > $transferOut['total_score']) {
                $transferIn = $candidates[0];
            }
        }

        // 2. Tentukan Rekomendasi Kapten & Wakil Kapten Terbaik
        $starters = array_filter($squad, fn($p) => $p['position'] <= 11 && !$p['is_injured']);
        usort($starters, function($a, $b) {
            return $b['total_score'] <=> $a['total_score'];
        });

        $recommendedCaptain = $starters[0] ?? ($squad[0] ?? null);
        $recommendedViceCaptain = $starters[1] ?? ($squad[1] ?? null);

        $ytConsensus = $this->youtube->getConsensusForGameweek($targetGw);

        return [
            'status' => 'success',
            'gameweek' => $targetGw,
            'deadline_time' => $gwInfo['next']['deadline_time'] ?? '',
            'squad_value' => $totalSquadCost,
            'bank' => $bank,
            'max_budget' => 100.0,
            'is_over_budget' => ($totalSquadCost > 100.0),
            'free_transfers' => $freeTransfers,
            'squad' => $squad,
            'recommendation' => [
                'has_transfer' => ($transferIn !== null && $transferOut !== null),
                'transfer_out' => $transferOut,
                'transfer_in' => $transferIn,
                'gain_score' => ($transferIn && $transferOut) ? round($transferIn['total_score'] - $transferOut['total_score'], 1) : 0,
                'cost_diff' => ($transferIn && $transferOut) ? round($transferIn['now_cost'] - $transferOut['now_cost'], 1) : 0,
                'remaining_bank_after' => ($transferIn && $transferOut) ? round($bank - ($transferIn['now_cost'] - $transferOut['now_cost']), 1) : $bank,
                'captain' => $recommendedCaptain,
                'vice_captain' => $recommendedViceCaptain,
            ],
            'youtube_insight' => $ytConsensus,
            'settings' => $this->getSettings()
        ];
    }

    private function calculateTeamFdr(array $fixtures, int $startGw, int $lookahead = 3): array {
        $teamFdrSum = [];
        $teamFdrCount = [];

        foreach ($fixtures as $fix) {
            $event = $fix['event'] ?? 0;
            if ($event >= $startGw && $event < ($startGw + $lookahead)) {
                $homeTeam = $fix['team_h'];
                $awayTeam = $fix['team_a'];
                $homeDiff = $fix['team_h_difficulty'] ?? 3;
                $awayDiff = $fix['team_a_difficulty'] ?? 3;

                $homeDiffAdjusted = max(1, $homeDiff - 0.25);
                $awayDiffAdjusted = min(5, $awayDiff + 0.25);

                $teamFdrSum[$homeTeam] = ($teamFdrSum[$homeTeam] ?? 0) + $homeDiffAdjusted;
                $teamFdrCount[$homeTeam] = ($teamFdrCount[$homeTeam] ?? 0) + 1;

                $teamFdrSum[$awayTeam] = ($teamFdrSum[$awayTeam] ?? 0) + $awayDiffAdjusted;
                $teamFdrCount[$awayTeam] = ($teamFdrCount[$awayTeam] ?? 0) + 1;
            }
        }

        $avgMap = [];
        foreach ($teamFdrSum as $teamId => $sum) {
            $cnt = max(1, $teamFdrCount[$teamId] ?? 1);
            $avgMap[$teamId] = $sum / $cnt;
        }

        return $avgMap;
    }
}
