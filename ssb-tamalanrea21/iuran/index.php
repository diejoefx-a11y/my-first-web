<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

$pdo = getPdo();
$user = getAuthUser();
$role = $user['role'];

$pageTitle = "Manajemen Iuran & SPP Bulanan";

$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$presetAtletId = (int)($_GET['atlet_id'] ?? 0);
if ($role === 'atlet') {
    $presetAtletId = $user['atlet_id'];
}

$bulanMap = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];

// Query SPP
$sql = "
    SELECT a.id as atlet_id, a.nama_lengkap, a.kelompok_usia, a.posisi_utama, o.no_whatsapp,
           i.id as iuran_id, i.jumlah, i.status_bayar, i.tanggal_bayar, i.keterangan
    FROM atlet a
    LEFT JOIN orang_tua o ON a.id = o.atlet_id
    LEFT JOIN iuran_spp i ON a.id = i.atlet_id AND i.bulan = ? AND i.tahun = ?
    WHERE a.status_keanggotaan = 'Aktif'
";
$params = [$bulan, $tahun];

if ($presetAtletId > 0) {
    $sql .= " AND a.id = ?";
    $params[] = $presetAtletId;
}

$sql .= " ORDER BY a.kelompok_usia ASC, a.nama_lengkap ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sppRows = $stmt->fetchAll();

// Stats
$totalSudah = 0;
$totalBelum = 0;
$totalNominal = 0;

foreach ($sppRows as $r) {
    if ($r['status_bayar'] == 'Lunas') {
        $totalSudah++;
        $totalNominal += $r['jumlah'];
    } else {
        $totalBelum++;
    }
}

include_once __DIR__ . '/../includes/header.php';
?>

<!-- Filter & Summary -->
<div class="card">
    <div class="card-header" style="flex-wrap:wrap; gap:1rem;">
        <div>
            <h2 class="card-title"><?= ($role === 'atlet') ? 'Status SPP Saya' : 'Rekapitulasi SPP' ?> - Periode <?= $bulanMap[$bulan] ?> <?= $tahun ?></h2>
            <p style="font-size:0.85rem; color:var(--text-muted);">Status Iuran Bulanan Atlet SSB Tamalanrea</p>
        </div>

        <form method="GET" style="display:flex; gap:10px; align-items:center;">
            <select name="bulan" class="form-control" onchange="this.form.submit()">
                <?php foreach ($bulanMap as $mNum => $mName): ?>
                    <option value="<?= $mNum ?>" <?= $bulan == $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                <?php endforeach; ?>
            </select>

            <select name="tahun" class="form-control" onchange="this.form.submit()">
                <?php for ($y = 2024; $y <= 2028; $y++): ?>
                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <?php if ($role === 'admin'): ?>
    <div class="grid-4" style="margin-bottom:1.5rem;">
        <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">TERKUMPUL</div>
            <div style="font-size:1.4rem; font-weight:800; color:#34d399;">Rp <?= number_format($totalNominal, 0, ',', '.') ?></div>
        </div>

        <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">LUNAS</div>
            <div style="font-size:1.4rem; font-weight:800; color:#818cf8;"><?= $totalSudah ?> Atlet</div>
        </div>

        <div style="background:rgba(15,23,42,0.6); padding:1rem; border-radius:12px; border:1px solid var(--border-glass);">
            <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">BELUM BAYAR</div>
            <div style="font-size:1.4rem; font-weight:800; color:#f87171;"><?= $totalBelum ?> Atlet</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama Atlet</th>
                    <th>KU</th>
                    <th>Nominal SPP</th>
                    <th>Status Pembayaran</th>
                    <th>Tgl Bayar</th>
                    <th>Keterangan</th>
                    <?php if ($role === 'admin'): ?><th>Aksi</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sppRows as $row): ?>
                    <tr>
                        <td>
                            <strong style="color:#fff;"><?= htmlspecialchars($row['nama_lengkap']) ?></strong>
                        </td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($row['kelompok_usia']) ?></span></td>
                        <td>Rp <?= number_format($row['jumlah'] ?: 150000, 0, ',', '.') ?></td>
                        <td>
                            <?php if ($row['status_bayar'] == 'Lunas'): ?>
                                <span class="badge badge-emerald">✓ Lunas</span>
                            <?php else: ?>
                                <span class="badge badge-rose">✗ Belum Bayar</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['tanggal_bayar'] ? date('d/m/Y', strtotime($row['tanggal_bayar'])) : '-' ?></td>
                        <td style="font-size:0.85rem; color:var(--text-muted);"><?= htmlspecialchars($row['keterangan'] ?: '-') ?></td>
                        <?php if ($role === 'admin'): ?>
                        <td>
                            <?php if ($row['status_bayar'] == 'Lunas'): ?>
                                <a href="bayar.php?atlet_id=<?= $row['atlet_id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&action=unpay" class="btn btn-secondary btn-sm" onclick="return confirm('Batalkan status lunas?')">Batalkan</a>
                            <?php else: ?>
                                <a href="bayar.php?atlet_id=<?= $row['atlet_id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&action=pay" class="btn btn-primary btn-sm">Tandai Lunas</a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


<?php include_once __DIR__ . '/../includes/footer.php'; ?>
