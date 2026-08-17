<?php
/**
 * FPL-BOT - Smart Fallback Cron Handler (H-30m Deadline Guard)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/FplService.php';
require_once __DIR__ . '/ScoringEngine.php';

class CronHandler {
    private PDO $db;
    private FplService $fpl;
    private ScoringEngine $engine;
    private array $config;

    public function __construct() {
        $this->db = Database::getConnection();
        $this->fpl = new FplService();
        $this->engine = new ScoringEngine();
        $this->config = require __DIR__ . '/../config/config.php';
    }

    /**
     * Jalankan pengecekan Smart Fallback
     */
    public function run(bool $forceExecute = false): array {
        $gwInfo = $this->fpl->getCurrentAndNextGameweek();
        $nextGw = $gwInfo['next'] ?? null;

        if (!$nextGw || empty($nextGw['deadline_time'])) {
            return [
                'status' => 'SKIPPED',
                'message' => 'Tidak ada Gameweek mendatang yang aktif.'
            ];
        }

        $gwNumber = (int)$nextGw['id'];
        $deadlineTimestamp = strtotime($nextGw['deadline_time']);
        $now = time();
        $fallbackMinutes = $this->config['cron']['fallback_minutes'] ?? 30;
        $triggerTimestamp = $deadlineTimestamp - ($fallbackMinutes * 60);

        // Periksa apakah sudah pernah dieksekusi sebelumnya untuk GW ini
        $stmt = $this->db->prepare("
            SELECT * FROM `execution_logs` 
            WHERE `gameweek` = ? AND `status` = 'SUCCESS'
            LIMIT 1
        ");
        $stmt->execute([$gwNumber]);
        $alreadyExecuted = $stmt->fetch();

        if ($alreadyExecuted && !$forceExecute) {
            return [
                'status' => 'SKIPPED',
                'message' => "Gameweek {$gwNumber} sudah berhasil dieksekusi sebelumnya pada {$alreadyExecuted['executed_at']} ({$alreadyExecuted['execution_type']}).",
                'gameweek' => $gwNumber
            ];
        }

        // Cek apakah waktu saat ini sudah masuk window Smart Fallback (H-30m sampai deadline)
        $isInsideFallbackWindow = ($now >= $triggerTimestamp && $now <= $deadlineTimestamp);

        if (!$isInsideFallbackWindow && !$forceExecute) {
            $minutesLeft = round(($triggerTimestamp - $now) / 60);
            return [
                'status' => 'WAITING',
                'message' => ($minutesLeft > 0)
                    ? "Menunggu window Smart Fallback. Trigger akan aktif {$minutesLeft} menit lagi (30 menit sebelum deadline GW{$gwNumber})."
                    : "Deadline Gameweek {$gwNumber} telah lewat.",
                'gameweek' => $gwNumber,
                'deadline' => $nextGw['deadline_time'],
                'server_time' => date('Y-m-d H:i:s')
            ];
        }

        // --- MULAI AUTO-EXECUTION SMART FALLBACK ---
        $plan = $this->engine->generateDecisionPlan();
        if (($plan['status'] ?? '') === 'error') {
            $this->logExecution($gwNumber, 'AUTO_FALLBACK', 'FAILED', null, null, null, null, 'Gagal membuat rencana: ' . $plan['message']);
            return [
                'status' => 'FAILED',
                'message' => 'Gagal generate decision plan: ' . $plan['message']
            ];
        }

        $rec = $plan['recommendation'] ?? [];
        $transferOut = $rec['transfer_out'] ?? null;
        $transferIn = $rec['transfer_in'] ?? null;
        $captain = $rec['captain'] ?? null;
        $viceCaptain = $rec['vice_captain'] ?? null;

        $transferSuccess = false;
        $transferMsg = 'No transfer needed / budget balance.';

        // 1. Eksekusi Transfer jika ada rekomendasi (Hanya 1 Free Transfer / 0 point hit)
        if (!empty($rec['has_transfer']) && $transferOut && $transferIn) {
            $trRes = $this->fpl->executeTransfer($transferOut['id'], $transferIn['id']);
            $transferSuccess = ($trRes['status'] === 'success');
            $transferMsg = $trRes['message'] ?? 'Transfer result unknown';
        }

        // 2. Eksekusi Susunan Lineup & Kapten
        $picks = [];
        $squad = $plan['squad'] ?? [];
        foreach ($squad as $idx => $p) {
            $pId = $p['id'];
            // Jika ada transfer yang berhasil, ganti id yang keluar dengan yang masuk
            if ($transferSuccess && $pId == ($transferOut['id'] ?? 0)) {
                $pId = $transferIn['id'];
            }

            $isCap = ($captain && $captain['id'] == $pId);
            $isVc = ($viceCaptain && $viceCaptain['id'] == $pId);

            $picks[] = [
                'element' => $pId,
                'position' => $p['position'],
                'is_captain' => $isCap,
                'is_vice_captain' => $isVc
            ];
        }

        $lineupRes = $this->fpl->updateLineup($picks);

        // 3. Catat Riwayat Eksekusi
        $finalStatus = ($lineupRes['status'] === 'success' || $transferSuccess) ? 'SUCCESS' : 'FAILED';
        $logNotes = "Transfer: {$transferMsg} | Lineup: " . ($lineupRes['message'] ?? '');

        $this->logExecution(
            $gwNumber,
            $forceExecute ? 'MANUAL' : 'AUTO_FALLBACK',
            $finalStatus,
            $transferOut['id'] ?? null,
            $transferOut['web_name'] ?? null,
            $transferIn['id'] ?? null,
            $transferIn['web_name'] ?? null,
            $captain['id'] ?? null,
            $captain['web_name'] ?? null,
            $viceCaptain['id'] ?? null,
            $viceCaptain['web_name'] ?? null,
            $logNotes
        );

        return [
            'status' => $finalStatus,
            'execution_type' => $forceExecute ? 'MANUAL' : 'AUTO_FALLBACK',
            'gameweek' => $gwNumber,
            'transfer_out' => $transferOut['web_name'] ?? '-',
            'transfer_in' => $transferIn['web_name'] ?? '-',
            'captain' => $captain['web_name'] ?? '-',
            'vice_captain' => $viceCaptain['web_name'] ?? '-',
            'details' => $logNotes
        ];
    }

    private function logExecution(int $gw, string $type, string $status, ?int $outId, ?string $outName, ?int $inId, ?string $inName, ?int $capId, ?string $capName, ?int $vcId, ?string $vcName, ?string $msg): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `execution_logs` 
                (`gameweek`, `execution_type`, `status`, `transfer_out_id`, `transfer_out_name`, `transfer_in_id`, `transfer_in_name`, `captain_id`, `captain_name`, `vice_captain_id`, `vice_captain_name`, `response_message`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $gw, $type, $status,
                $outId, $outName,
                $inId, $inName,
                $capId, $capName,
                $vcId, $vcName,
                $msg
            ]);
        } catch (Exception $e) {}
    }
}
