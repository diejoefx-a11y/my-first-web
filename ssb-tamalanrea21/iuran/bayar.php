<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pdo = getPdo();


$atletId = (int)($_GET['atlet_id'] ?? 0);
$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$action = $_GET['action'] ?? 'pay';

if ($atletId > 0) {
    // Check if record exists
    $stmt = $pdo->prepare("SELECT id FROM iuran_spp WHERE atlet_id = ? AND bulan = ? AND tahun = ?");
    $stmt->execute([$atletId, $bulan, $tahun]);
    $existing = $stmt->fetch();

    if ($action === 'pay') {
        if ($existing) {
            $update = $pdo->prepare("UPDATE iuran_spp SET status_bayar = 'Lunas', tanggal_bayar = ?, keterangan = 'Lunas via Admin' WHERE id = ?");
            $update->execute([date('Y-m-d'), $existing['id']]);
        } else {
            $insert = $pdo->prepare("INSERT INTO iuran_spp (atlet_id, bulan, tahun, jumlah, status_bayar, tanggal_bayar, keterangan) VALUES (?, ?, ?, 150000, 'Lunas', ?, 'Lunas via Admin')");
            $insert->execute([$atletId, $bulan, $tahun, date('Y-m-d')]);
        }
    } else {
        if ($existing) {
            $update = $pdo->prepare("UPDATE iuran_spp SET status_bayar = 'Belum Bayar', tanggal_bayar = NULL, keterangan = 'Tunggakan' WHERE id = ?");
            $update->execute([$existing['id']]);
        }
    }
}

header("Location: index.php?bulan=$bulan&tahun=$tahun");
exit;
