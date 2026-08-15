<?php
// api/action_meja.php
// Endpoint untuk manipulasi data meja catur (Tambah, Edit, Reset, Set Pemenang, Hapus)

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method Not Allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$action = $input['action'] ?? '';
$db = get_db();

try {
    switch ($action) {
        case 'tambah':
            $nomor_meja = clean_input($input['nomor_meja'] ?? 'Meja 1');
            $kategori_babak = clean_input($input['kategori_babak'] ?? 'Babak 1');
            $nama_putih = clean_input($input['nama_putih'] ?? 'Pemain Putih');
            $nama_hitam = clean_input($input['nama_hitam'] ?? 'Pemain Hitam');
            
            $time_base_minutes = max(1, (int)($input['time_base_minutes'] ?? 5));
            $time_increment_seconds = max(0, (int)($input['time_increment_seconds'] ?? 0));
            $time_mode = in_array($input['time_mode'] ?? '', ['fischer', 'delay', 'sudden_death']) ? $input['time_mode'] : 'fischer';
            
            $waktu_ms = $time_base_minutes * 60 * 1000;
            
            $stmt = $db->prepare("INSERT INTO `meja_catur` 
                (`nomor_meja`, `kategori_babak`, `nama_putih`, `nama_hitam`, `time_base_minutes`, `time_increment_seconds`, `time_mode`, `sisa_waktu_putih_ms`, `sisa_waktu_hitam_ms`, `status`, `giliran`, `jumlah_langkah`, `pemenang`, `last_sync_timestamp`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'standby', 'putih', 0, 'belum', ?)");
                
            $nowMs = (int)(microtime(true) * 1000);
            $stmt->execute([
                $nomor_meja,
                $kategori_babak,
                $nama_putih,
                $nama_hitam,
                $time_base_minutes,
                $time_increment_seconds,
                $time_mode,
                $waktu_ms,
                $waktu_ms,
                $nowMs
            ]);
            
            $newId = $db->lastInsertId();
            json_response([
                'success' => true,
                'message' => 'Meja berhasil ditambahkan',
                'id' => $newId
            ]);
            break;

        case 'edit':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'message' => 'ID meja tidak valid'], 400);
            }
            
            $nomor_meja = clean_input($input['nomor_meja'] ?? 'Meja 1');
            $kategori_babak = clean_input($input['kategori_babak'] ?? 'Babak 1');
            $nama_putih = clean_input($input['nama_putih'] ?? 'Pemain Putih');
            $nama_hitam = clean_input($input['nama_hitam'] ?? 'Pemain Hitam');
            $time_base_minutes = max(1, (int)($input['time_base_minutes'] ?? 5));
            $time_increment_seconds = max(0, (int)($input['time_increment_seconds'] ?? 0));
            $time_mode = in_array($input['time_mode'] ?? '', ['fischer', 'delay', 'sudden_death']) ? $input['time_mode'] : 'fischer';
            
            $reset_time = !empty($input['reset_time']);
            
            if ($reset_time) {
                $waktu_ms = $time_base_minutes * 60 * 1000;
                $stmt = $db->prepare("UPDATE `meja_catur` SET 
                    `nomor_meja` = ?, `kategori_babak` = ?, `nama_putih` = ?, `nama_hitam` = ?,
                    `time_base_minutes` = ?, `time_increment_seconds` = ?, `time_mode` = ?,
                    `sisa_waktu_putih_ms` = ?, `sisa_waktu_hitam_ms` = ?,
                    `status` = 'standby', `giliran` = 'putih', `jumlah_langkah` = 0, `pemenang` = 'belum', `keterangan_selesai` = NULL
                    WHERE `id` = ?");
                $stmt->execute([
                    $nomor_meja, $kategori_babak, $nama_putih, $nama_hitam,
                    $time_base_minutes, $time_increment_seconds, $time_mode,
                    $waktu_ms, $waktu_ms, $id
                ]);
            } else {
                $stmt = $db->prepare("UPDATE `meja_catur` SET 
                    `nomor_meja` = ?, `kategori_babak` = ?, `nama_putih` = ?, `nama_hitam` = ?,
                    `time_base_minutes` = ?, `time_increment_seconds` = ?, `time_mode` = ?
                    WHERE `id` = ?");
                $stmt->execute([
                    $nomor_meja, $kategori_babak, $nama_putih, $nama_hitam,
                    $time_base_minutes, $time_increment_seconds, $time_mode, $id
                ]);
            }
            
            json_response(['success' => true, 'message' => 'Data meja berhasil diperbarui']);
            break;

        case 'reset':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'message' => 'ID meja tidak valid'], 400);
            }
            
            $stmtGet = $db->prepare("SELECT `time_base_minutes` FROM `meja_catur` WHERE `id` = ?");
            $stmtGet->execute([$id]);
            $meja = $stmtGet->fetch();
            if (!$meja) {
                json_response(['success' => false, 'message' => 'Meja tidak ditemukan'], 404);
            }
            
            $waktu_ms = (int)$meja['time_base_minutes'] * 60 * 1000;
            $nowMs = (int)(microtime(true) * 1000);
            
            $stmt = $db->prepare("UPDATE `meja_catur` SET 
                `sisa_waktu_putih_ms` = ?, `sisa_waktu_hitam_ms` = ?,
                `status` = 'standby', `giliran` = 'putih', `jumlah_langkah` = 0,
                `pemenang` = 'belum', `keterangan_selesai` = NULL, `last_sync_timestamp` = ?
                WHERE `id` = ?");
            $stmt->execute([$waktu_ms, $waktu_ms, $nowMs, $id]);
            
            json_response(['success' => true, 'message' => 'Jam catur meja berhasil direset']);
            break;

        case 'set_pemenang':
            $id = (int)($input['id'] ?? 0);
            $pemenang = in_array($input['pemenang'] ?? '', ['putih', 'hitam', 'remis']) ? $input['pemenang'] : 'remis';
            $keterangan = clean_input($input['keterangan_selesai'] ?? 'Pertandingan Selesai');
            
            $stmt = $db->prepare("UPDATE `meja_catur` SET 
                `status` = 'finished', `pemenang` = ?, `keterangan_selesai` = ?
                WHERE `id` = ?");
            $stmt->execute([$pemenang, $keterangan, $id]);
            
            json_response(['success' => true, 'message' => 'Hasil pertandingan berhasil disimpan']);
            break;

        case 'hapus':
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) {
                json_response(['success' => false, 'message' => 'ID meja tidak valid'], 400);
            }
            
            $stmt = $db->prepare("DELETE FROM `meja_catur` WHERE `id` = ?");
            $stmt->execute([$id]);
            
            json_response(['success' => true, 'message' => 'Meja berhasil dihapus']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Aksi tidak dikenali'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()], 500);
}
