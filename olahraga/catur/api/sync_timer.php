<?php
// api/sync_timer.php
// Endpoint sinkronisasi real-time jam catur dari perangkat meja ke MySQL

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method Not Allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    json_response(['success' => false, 'message' => 'ID meja tidak valid'], 400);
}

$sisa_putih_ms = max(0, (int)($input['sisa_waktu_putih_ms'] ?? 0));
$sisa_hitam_ms = max(0, (int)($input['sisa_waktu_hitam_ms'] ?? 0));
$status = in_array($input['status'] ?? '', ['standby', 'running', 'paused', 'finished']) ? $input['status'] : 'standby';
$giliran = in_array($input['giliran'] ?? '', ['putih', 'hitam']) ? $input['giliran'] : 'putih';
$jumlah_langkah = max(0, (int)($input['jumlah_langkah'] ?? 0));
$pemenang = in_array($input['pemenang'] ?? '', ['belum', 'putih', 'hitam', 'remis']) ? $input['pemenang'] : 'belum';
$keterangan_selesai = isset($input['keterangan_selesai']) ? clean_input($input['keterangan_selesai']) : null;

$nowMs = (int)(microtime(true) * 1000);
$db = get_db();

try {
    $stmt = $db->prepare("UPDATE `meja_catur` SET 
        `sisa_waktu_putih_ms` = ?,
        `sisa_waktu_hitam_ms` = ?,
        `status` = ?,
        `giliran` = ?,
        `jumlah_langkah` = ?,
        `pemenang` = ?,
        `keterangan_selesai` = ?,
        `last_sync_timestamp` = ?
        WHERE `id` = ?");
        
    $stmt->execute([
        $sisa_putih_ms,
        $sisa_hitam_ms,
        $status,
        $giliran,
        $jumlah_langkah,
        $pemenang,
        $keterangan_selesai,
        $nowMs,
        $id
    ]);

    json_response([
        'success' => true,
        'server_time_ms' => $nowMs,
        'message' => 'Timer berhasil disinkronkan'
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Gagal sinkron: ' . $e->getMessage()], 500);
}
