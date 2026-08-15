<?php
// api/get_meja.php
// Endpoint untuk mengambil data meja atau daftar semua meja beserta status live-nya

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$baseUrlMobile = get_base_url(true);
$baseUrlLocal = get_base_url(false);

try {
    $nowMs = (int)(microtime(true) * 1000);

    if ($id > 0) {
        $stmt = $db->prepare("SELECT * FROM `meja_catur` WHERE `id` = ?");
        $stmt->execute([$id]);
        $meja = $stmt->fetch();
        
        if (!$meja) {
            json_response(['success' => false, 'message' => 'Meja tidak ditemukan'], 404);
        }
        
        // Perhitungkan sisa waktu aktual jika status saat ini 'running'
        if ($meja['status'] === 'running' && !empty($meja['last_sync_timestamp'])) {
            $elapsedMs = max(0, $nowMs - (int)$meja['last_sync_timestamp']);
            if ($meja['giliran'] === 'putih') {
                $meja['sisa_waktu_putih_ms'] = max(0, (int)$meja['sisa_waktu_putih_ms'] - $elapsedMs);
            } else {
                $meja['sisa_waktu_hitam_ms'] = max(0, (int)$meja['sisa_waktu_hitam_ms'] - $elapsedMs);
            }
        }
        
        $meja['qr_url'] = $baseUrlMobile . '/timer.php?id=' . $meja['id'];
        $meja['local_url'] = $baseUrlLocal . '/timer.php?id=' . $meja['id'];
        
        json_response([
            'success' => true,
            'server_time_ms' => $nowMs,
            'data' => $meja
        ]);
    } else {
        $stmt = $db->query("SELECT * FROM `meja_catur` ORDER BY `id` ASC");
        $daftarMeja = $stmt->fetchAll();
        
        $stats = [
            'total' => count($daftarMeja),
            'running' => 0,
            'standby' => 0,
            'paused' => 0,
            'finished' => 0
        ];
        
        foreach ($daftarMeja as &$m) {
            $status = $m['status'];
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
            
            // Hitung estimasi sisa waktu live
            if ($m['status'] === 'running' && !empty($m['last_sync_timestamp'])) {
                $elapsedMs = max(0, $nowMs - (int)$m['last_sync_timestamp']);
                if ($m['giliran'] === 'putih') {
                    $m['sisa_waktu_putih_ms'] = max(0, (int)$m['sisa_waktu_putih_ms'] - $elapsedMs);
                } else {
                    $m['sisa_waktu_hitam_ms'] = max(0, (int)$m['sisa_waktu_hitam_ms'] - $elapsedMs);
                }
            }
            
            $m['qr_url'] = $baseUrlMobile . '/timer.php?id=' . $m['id'];
            $m['local_url'] = $baseUrlLocal . '/timer.php?id=' . $m['id'];
        }
        unset($m);
        
        json_response([
            'success' => true,
            'server_time_ms' => $nowMs,
            'stats' => $stats,
            'data' => $daftarMeja
        ]);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
