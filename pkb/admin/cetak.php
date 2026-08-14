<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    header("Location: keluarga.php");
    exit;
}

$db = get_db();
$stmt = $db->prepare("
    SELECT f.*, g.nama_kelompok, g.nama_ketua, g.wilayah_cakupan
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    WHERE f.id = ? 
    LIMIT 1
");
$stmt->execute([$id]);
$family = $stmt->fetch();

if (!$family) {
    die("Data keluarga tidak ditemukan.");
}

$stmtMembers = $db->prepare("SELECT * FROM family_members WHERE family_id = ? ORDER BY id ASC");
$stmtMembers->execute([$id]);
$members = $stmtMembers->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Data Keluarga - <?= htmlspecialchars($family['no_kk']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #111;
            padding: 20px;
            font-size: 13px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h2 {
            margin: 0;
            font-size: 18px;
            text-transform: uppercase;
        }
        .header p {
            margin: 4px 0 0 0;
            font-size: 12px;
        }
        table.info-table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        table.info-table td {
            padding: 4px 6px;
            vertical-align: top;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #333;
            padding: 6px 8px;
            text-align: left;
        }
        table.data-table th {
            background-color: #eee;
        }
        .footer-sign {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .sign-box {
            text-align: center;
            width: 200px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="margin-bottom: 20px; background: #ede9fe; padding: 10px 16px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: bold; color: #5b21b6;">Pratinjau Cetak Lembar Data Keluarga</span>
        <button onclick="window.print()" style="padding: 6px 16px; background: #7c3aed; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">🖨️ Cetak / Print Sekarang</button>
    </div>

    <div class="header">
        <h2>LEMBAR DATA KELUARGA & PEMETAAN WILAYAH</h2>
        <p>Sistem Informasi Pendataan Keluarga Terpadu (PKB)</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="20%"><strong>Nomor Kartu Keluarga</strong></td>
            <td width="30%">: <strong><?= htmlspecialchars($family['no_kk']) ?></strong></td>
            <td width="20%"><strong>Kelompok Domisili</strong></td>
            <td width="30%">: <strong><?= htmlspecialchars($family['nama_kelompok']) ?: '-' ?></strong> <?= !empty($family['nama_ketua']) ? '(Ketua: ' . htmlspecialchars($family['nama_ketua']) . ')' : '' ?></td>
        </tr>
        <tr>
            <td><strong>Kepala Keluarga</strong></td>
            <td>: <?= htmlspecialchars($family['nama_kepala']) ?></td>
            <td><strong>Wilayah Domisili</strong></td>
            <td>: RT <?= htmlspecialchars($family['rt']) ?> / RW <?= htmlspecialchars($family['rw']) ?></td>
        </tr>
        <tr>
            <td><strong>NIK Kepala Keluarga</strong></td>
            <td>: <?= htmlspecialchars($family['nik_kepala']) ?></td>
            <td><strong>Kelurahan / Desa</strong></td>
            <td>: <?= htmlspecialchars($family['kelurahan']) ?></td>
        </tr>
        <tr>
            <td><strong>No. WhatsApp / HP</strong></td>
            <td>: <?= htmlspecialchars($family['no_hp']) ?></td>
            <td><strong>Kecamatan</strong></td>
            <td>: <?= htmlspecialchars($family['kecamatan']) ?></td>
        </tr>
        <tr>
            <td><strong>Alamat Lengkap</strong></td>
            <td>: <?= htmlspecialchars($family['alamat_lengkap']) ?></td>
            <td><strong>Titik Koordinat (Peta)</strong></td>
            <td>: <?= $family['latitude'] ?>, <?= $family['longitude'] ?></td>
        </tr>
        <tr>
            <td><strong>Jumlah Tanggungan</strong></td>
            <td>: <?= $family['jumlah_tanggungan'] ?> Orang</td>
            <td><strong>Status Verifikasi</strong></td>
            <td>: <?= strtoupper($family['status_verifikasi']) ?></td>
        </tr>
        <tr>
            <td><strong>Tanggal Terdaftar</strong></td>
            <td>: <?= date('d F Y, H:i', strtotime($family['created_at'])) ?> WITA</td>
            <td></td>
            <td></td>
        </tr>
    </table>

    <h3 style="font-size: 14px; margin-bottom: 5px;">DAFTAR ANGGOTA KELUARGA</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th width="4%">No</th>
                <th>Nama Lengkap</th>
                <th>NIK</th>
                <th>Hubungan</th>
                <th>L/P</th>
                <th>Tempat, Tgl Lahir</th>
                <th>Pendidikan</th>
                <th>Pekerjaan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $idx => $m): ?>
                <tr>
                    <td align="center"><?= $idx + 1 ?></td>
                    <td><strong><?= htmlspecialchars($m['nama_lengkap']) ?></strong></td>
                    <td><?= htmlspecialchars($m['nik']) ?></td>
                    <td><?= htmlspecialchars($m['hubungan_keluarga']) ?></td>
                    <td align="center"><?= htmlspecialchars($m['jenis_kelamin']) ?></td>
                    <td><?= htmlspecialchars($m['tempat_lahir']) ?><?= !empty($m['tanggal_lahir']) ? ', ' . date('d/m/Y', strtotime($m['tanggal_lahir'])) : '' ?></td>
                    <td><?= htmlspecialchars($m['pendidikan_terakhir']) ?: '-' ?></td>
                    <td><?= htmlspecialchars($m['pekerjaan']) ?: '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-sign">
        <div class="sign-box">
            <p>Kepala Keluarga,</p>
            <br><br><br>
            <p><strong>( <?= htmlspecialchars($family['nama_kepala']) ?> )</strong></p>
        </div>
        <div class="sign-box">
            <p>Petugas / Ketua Kelompok,</p>
            <br><br><br>
            <p><strong>( ..................................... )</strong></p>
        </div>
    </div>

</body>
</html>
