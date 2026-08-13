<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireRole('admin');

$pdo = getPdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $atletId = (int)($_POST['atlet_id'] ?? 0);
    $bulan = (int)($_POST['bulan'] ?? date('n'));
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $action = $_POST['action'] ?? 'pay';
    $jumlah = (int)($_POST['jumlah'] ?? 150000);
    $tanggal_bayar = !empty($_POST['tanggal_bayar']) ? $_POST['tanggal_bayar'] : date('Y-m-d');
    $metode_bayar = trim($_POST['metode_bayar'] ?? 'Tunai / Cash');
    $catatan = trim($_POST['catatan'] ?? '');
    $ku = urlencode($_POST['ku'] ?? '');

    if ($atletId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM iuran_spp WHERE atlet_id = ? AND bulan = ? AND tahun = ?");
        $stmt->execute([$atletId, $bulan, $tahun]);
        $existing = $stmt->fetch();

        if ($action === 'pay') {
            $keteranganFull = !empty($catatan) ? "[$metode_bayar] $catatan" : "[$metode_bayar]";

            if ($existing) {
                $update = $pdo->prepare("UPDATE iuran_spp SET status_bayar = 'Lunas', jumlah = ?, tanggal_bayar = ?, keterangan = ? WHERE id = ?");
                $update->execute([$jumlah, $tanggal_bayar, $keteranganFull, $existing['id']]);
            } else {
                $insert = $pdo->prepare("INSERT INTO iuran_spp (atlet_id, bulan, tahun, jumlah, status_bayar, tanggal_bayar, keterangan) VALUES (?, ?, ?, ?, 'Lunas', ?, ?)");
                $insert->execute([$atletId, $bulan, $tahun, $jumlah, $tanggal_bayar, $keteranganFull]);
            }
        } elseif ($action === 'unpay') {
            if ($existing) {
                $update = $pdo->prepare("UPDATE iuran_spp SET status_bayar = 'Belum Bayar', tanggal_bayar = NULL, keterangan = 'Tunggakan' WHERE id = ?");
                $update->execute([$existing['id']]);
            }
        }
    }

    $redirectUrl = "index.php?bulan={$bulan}&tahun={$tahun}";
    if (!empty($ku)) {
        $redirectUrl .= "&ku={$ku}";
    }
    header("Location: {$redirectUrl}&success=spp_updated");
    exit;
}

// GET Fallback for direct links / legacy actions
$atletId = (int)($_GET['atlet_id'] ?? 0);
$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$action = $_GET['action'] ?? 'pay';
$ku = urlencode($_GET['ku'] ?? '');

if ($atletId > 0) {
    $stmt = $pdo->prepare("SELECT id FROM iuran_spp WHERE atlet_id = ? AND bulan = ? AND tahun = ?");
    $stmt->execute([$atletId, $bulan, $tahun]);
    $existing = $stmt->fetch();

    if ($action === 'pay') {
        if ($existing) {
            $update = $pdo->prepare("UPDATE iuran_spp SET status_bayar = 'Lunas', tanggal_bayar = ?, keterangan = '[Tunai / Cash] Pelunasan via Admin' WHERE id = ?");
            $update->execute([date('Y-m-d'), $existing['id']]);
        } else {
            $insert = $pdo->prepare("INSERT INTO iuran_spp (atlet_id, bulan, tahun, jumlah, status_bayar, tanggal_bayar, keterangan) VALUES (?, ?, ?, 150000, 'Lunas', ?, '[Tunai / Cash] Pelunasan via Admin')");
            $insert->execute([$atletId, $bulan, $tahun, date('Y-m-d')]);
        }
    } else {
        if ($existing) {
            $update = $pdo->prepare("UPDATE iuran_spp SET status_bayar = 'Belum Bayar', tanggal_bayar = NULL, keterangan = 'Tunggakan' WHERE id = ?");
            $update->execute([$existing['id']]);
        }
    }
}

$redirectUrl = "index.php?bulan={$bulan}&tahun={$tahun}";
if (!empty($ku)) {
    $redirectUrl .= "&ku={$ku}";
}
header("Location: {$redirectUrl}&success=spp_updated");
exit;
