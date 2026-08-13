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
$filterKu = $_GET['ku'] ?? '';
$search = trim($_GET['q'] ?? '');

$presetAtletId = (int)($_GET['atlet_id'] ?? 0);
if ($role === 'atlet') {
    $presetAtletId = $user['atlet_id'];
}

$bulanMap = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];

// Dynamic SQL Query with Filters
$where = ["a.status_keanggotaan = 'Aktif'"];
$params = [$bulan, $tahun];

if (!empty($filterKu)) {
    $where[] = "a.kelompok_usia = ?";
    $params[] = $filterKu;
}

if (!empty($search)) {
    $where[] = "(a.nama_lengkap LIKE ? OR a.nisn_nik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($presetAtletId > 0) {
    $where[] = "a.id = ?";
    $params[] = $presetAtletId;
}

$whereSql = "WHERE " . implode(" AND ", $where);

$sql = "
    SELECT a.id as atlet_id, a.nama_lengkap, a.kelompok_usia, a.posisi_utama, a.posisi_sekunder, a.foto_profil, o.no_whatsapp, o.nama_ayah, o.nama_ibu,
           i.id as iuran_id, i.jumlah, i.status_bayar, i.tanggal_bayar, i.keterangan
    FROM atlet a
    LEFT JOIN orang_tua o ON a.id = o.atlet_id
    LEFT JOIN iuran_spp i ON a.id = i.atlet_id AND i.bulan = ? AND i.tahun = ?
    $whereSql
    ORDER BY a.kelompok_usia ASC, a.nama_lengkap ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$sppRows = $stmt->fetchAll();

// Statistics Counter Calculation
$totalAtletCount = count($sppRows);
$totalSudah = 0;
$totalBelum = 0;
$totalNominal = 0;

foreach ($sppRows as $r) {
    if ($r['status_bayar'] == 'Lunas') {
        $totalSudah++;
        $totalNominal += ($r['jumlah'] ?: 150000);
    } else {
        $totalBelum++;
    }
}

$persenLunas = $totalAtletCount > 0 ? round(($totalSudah / $totalAtletCount) * 100) : 0;

// Pagination Logic (Max 10 records per page)
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = ceil($totalAtletCount / $perPage);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Slice 10 items for current page display
$displaySppRows = array_slice($sppRows, $offset, $perPage);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- ALERT NOTIFICATION -->
<?php if (isset($_GET['success']) && $_GET['success'] === 'spp_updated'): ?>
    <div style="background:rgba(16,185,129,0.15); border:1px solid var(--emerald); color:#34d399; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:0.85rem; display:flex; align-items:center; gap:8px;">
        <span>✓</span> Data iuran & status pembayaran SPP berhasil diperbarui!
    </div>
<?php endif; ?>

<!-- FILTER ACTIVE NOTIFICATION BADGE -->
<?php if (!empty($filterKu) || !empty($search)): ?>
    <div style="margin-bottom:0.75rem; font-size:0.8rem; color:#38bdf8; font-weight:600; display:flex; align-items:center; gap:6px;">
        <span style="background:rgba(56,189,248,0.15); border:1px solid rgba(56,189,248,0.3); padding:4px 12px; border-radius:10px;">
            ⚡ Data Rekapitulasi Terfilter: <?= !empty($filterKu) ? 'Kategori <strong>KU ' . htmlspecialchars($filterKu) . '</strong>' : '' ?> <?= (!empty($filterKu) && !empty($search)) ? '|' : '' ?> <?= !empty($search) ? 'Pencarian: "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>
        </span>
    </div>
<?php endif; ?>

<!-- REKAPITULASI SPP HEADER CARD -->
<div class="card" style="padding: 1.25rem; margin-bottom:1.5rem;">
    
    <!-- CARD HEADER & FILTER CONTROL BAR -->
    <div class="card-header" style="flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid var(--border-glass); padding-bottom:1rem; margin-bottom:1.25rem;">
        <div>
            <h2 class="card-title" style="font-size:1.2rem; display:flex; align-items:center; gap:8px;">
                <span>💳</span> <?= ($role === 'atlet') ? 'Status SPP Saya' : 'Rekapitulasi SPP Bulanan' ?> - Periode <span style="color:#38bdf8;"><?= $bulanMap[$bulan] ?> <?= $tahun ?></span>
            </h2>
            <p style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">Rekapitulasi iuran pembinaan bulanan atlet SSB Tamalanrea Makassar (Maksimal 10 Atlet per Halaman)</p>
        </div>

        <!-- FILTER FORM: BULAN, TAHUN, KELOMPOK USIA & SEARCH -->
        <form method="GET" style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <?php if ($presetAtletId > 0 && $role !== 'atlet'): ?>
                <input type="hidden" name="atlet_id" value="<?= $presetAtletId ?>">
            <?php endif; ?>

            <div>
                <select name="bulan" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; font-weight:700; padding:0.4rem 0.75rem;">
                    <?php foreach ($bulanMap as $mNum => $mName): ?>
                        <option value="<?= $mNum ?>" <?= $bulan == $mNum ? 'selected' : '' ?>><?= $mName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <select name="tahun" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; font-weight:700; padding:0.4rem 0.75rem;">
                    <?php for ($y = 2024; $y <= 2028; $y++): ?>
                        <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <?php if ($role !== 'atlet'): ?>
                <div>
                    <select name="ku" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; font-weight:700; color:#38bdf8; padding:0.4rem 0.75rem;">
                        <option value="">🏆 Semua Kategori (KU)</option>
                        <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $ku): ?>
                            <option value="<?= $ku ?>" <?= $filterKu == $ku ? 'selected' : '' ?>><?= $ku ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if (!empty($filterKu) || !empty($search)): ?>
                <div>
                    <a href="index.php?bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-secondary btn-sm" style="padding:0.45rem 0.8rem; font-size:0.78rem; border-color:rgba(244,63,94,0.4); color:#f87171;">
                        ✕ Reset Filter
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- SUMMARY MINI TILES GRID FOR ADMIN & COACHES -->
    <?php if ($role === 'admin' || $role === 'pelatih'): ?>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:1.5rem;">
        
        <!-- TILE 1: TOTAL TERKUMPUL -->
        <div style="background:rgba(15,23,42,0.65); border:1px solid rgba(52,211,153,0.3); padding:1rem 1.1rem; border-radius:14px; border-left:4px solid #34d399;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span style="font-size:0.72rem; color:#34d399; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">💰 Dana Terkumpul</span>
                <span style="font-size:0.9rem;">💵</span>
            </div>
            <div style="font-family:'Outfit', sans-serif; font-size:1.35rem; font-weight:800; color:#fff;">
                Rp <?= number_format($totalNominal, 0, ',', '.') ?>
            </div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">
                Dari total <?= $totalSudah ?> atlet terverifikasi lunas
            </div>
        </div>

        <!-- TILE 2: TOTAL LUNAS -->
        <div style="background:rgba(15,23,42,0.65); border:1px solid rgba(99,102,241,0.3); padding:1rem 1.1rem; border-radius:14px; border-left:4px solid #818cf8;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span style="font-size:0.72rem; color:#818cf8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">✓ Status Lunas</span>
                <span class="badge badge-emerald" style="font-size:0.68rem; padding:1px 6px;"><?= $persenLunas ?>%</span>
            </div>
            <div style="font-family:'Outfit', sans-serif; font-size:1.35rem; font-weight:800; color:#818cf8;">
                <?= $totalSudah ?> <span style="font-size:0.82rem; color:var(--text-muted); font-weight:600;">/ <?= $totalAtletCount ?> Atlet</span>
            </div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">
                Sudah menyelesaikan iuran bulanan
            </div>
        </div>

        <!-- TILE 3: BELUM BAYAR -->
        <div style="background:rgba(15,23,42,0.65); border:1px solid rgba(244,63,94,0.3); padding:1rem 1.1rem; border-radius:14px; border-left:4px solid #f87171;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span style="font-size:0.72rem; color:#f87171; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">⚠️ Belum Bayar</span>
                <span style="font-size:0.9rem;">⏳</span>
            </div>
            <div style="font-family:'Outfit', sans-serif; font-size:1.35rem; font-weight:800; color:#f87171;">
                <?= $totalBelum ?> <span style="font-size:0.82rem; color:var(--text-muted); font-weight:600;">Atlet</span>
            </div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">
                Menunggu konfirmasi pelunasan SPP
            </div>
        </div>

        <!-- TILE 4: PERSENTASE PROGRESS -->
        <div style="background:rgba(15,23,42,0.65); border:1px solid rgba(56,189,248,0.3); padding:1rem 1.1rem; border-radius:14px; border-left:4px solid #38bdf8;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                <span style="font-size:0.72rem; color:#38bdf8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">📊 Rasio Pelunasan</span>
                <span style="font-size:0.85rem; color:#38bdf8; font-weight:800;"><?= $persenLunas ?>%</span>
            </div>
            <!-- PROGRESS BAR -->
            <div style="width:100%; height:8px; background:rgba(255,255,255,0.1); border-radius:10px; overflow:hidden; margin-top:8px;">
                <div style="width:<?= $persenLunas ?>%; height:100%; background:linear-gradient(90deg, #38bdf8, #34d399); border-radius:10px; transition:width 0.5s ease;"></div>
            </div>
            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:6px;">
                Target pelunasan iuran bulanan SSB
            </div>
        </div>

    </div>
    <?php endif; ?>

    <!-- HIGH TECH DATA TABLE -->
    <div style="width: 100%; overflow: hidden; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.12);">
        <table class="data-table" style="table-layout: auto; width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.84rem;">
            <thead>
                <tr style="background: rgba(15, 23, 42, 0.95);">
                    <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; width: 35px;">No</th>
                    <th style="padding: 10px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1);">Nama Atlet & Posisi</th>
                    <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">KU</th>
                    <th style="padding: 10px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: right; white-space: nowrap; min-width: 125px;">Nominal SPP</th>
                    <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Status Pembayaran</th>
                    <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Tgl Bayar</th>
                    <th style="padding: 10px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1);">Keterangan & Catatan</th>
                    <?php if ($role === 'admin'): ?>
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); text-align: center; white-space: nowrap;">Aksi</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (count($displaySppRows) == 0): ?>
                    <tr>
                        <td colspan="<?= $role === 'admin' ? 8 : 7 ?>" style="text-align:center; padding:2rem; color:var(--text-muted);">
                            Belum ada data iuran SPP yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1; 
                    foreach ($displaySppRows as $row): 
                        $photoPath = __DIR__ . '/../assets/img/atlet/' . ($row['foto_profil'] ?? '');
                        $hasPhoto = !empty($row['foto_profil']) && $row['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
                        $isLunas = ($row['status_bayar'] === 'Lunas');

                        // Prepare WhatsApp reminder link if unpaid and parent WA exists
                        $waLink = '';
                        if (!$isLunas && !empty($row['no_whatsapp'])) {
                            $cleanWa = preg_replace('/[^0-9]/', '', $row['no_whatsapp']);
                            if (strpos($cleanWa, '0') === 0) $cleanWa = '62' . substr($cleanWa, 1);
                            elseif (strpos($cleanWa, '62') !== 0) $cleanWa = '62' . $cleanWa;

                            $msgText = urlencode("Halo Wali Atlet " . $row['nama_lengkap'] . " (SSB Tamalanrea), mengingatkan untuk pembayaran iuran SPP Periode " . $bulanMap[$bulan] . " " . $tahun . ". Terima kasih!");
                            $waLink = "https://wa.me/{$cleanWa}?text={$msgText}";
                        }

                        $modalData = [
                            'atlet_id'   => $row['atlet_id'],
                            'nama'       => $row['nama_lengkap'],
                            'ku'         => $row['kelompok_usia'],
                            'posisi'     => $row['posisi_utama'],
                            'nominal'    => $row['jumlah'] ?: 150000,
                            'bulan'      => $bulan,
                            'tahun'      => $tahun,
                            'bulan_nama' => $bulanMap[$bulan],
                            'tgl_bayar'  => $row['tanggal_bayar'] ?: date('Y-m-d'),
                            'keterangan' => $row['keterangan'] ?: ''
                        ];
                    ?>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); transition: background 0.2s;">
                            <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; color: var(--text-muted); font-weight: 600;">
                                <?= $no++ ?>
                            </td>

                            <td style="padding: 9px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08);">
                                <div style="display:flex; align-items:center; gap:9px;">
                                    <div style="background:#1e293b; display:flex; align-items:center; justify-content:center; font-weight:700; color:#818cf8; overflow:hidden; width:32px; height:32px; border-radius:50%; flex-shrink:0; font-size:0.8rem; border:1.5px solid rgba(99,102,241,0.3);">
                                        <?php if ($hasPhoto): ?>
                                            <img src="../assets/img/atlet/<?= htmlspecialchars($row['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($row['nama_lengkap'], 0, 2)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="../atlet/detail.php?id=<?= $row['atlet_id'] ?>" style="color:#fff; font-weight:700; text-decoration:none; font-size:0.86rem;" onmouseover="this.style.color='#38bdf8'" onmouseout="this.style.color='#fff'">
                                            <?= htmlspecialchars($row['nama_lengkap']) ?>
                                        </a>
                                        <div style="font-size:0.7rem; color:var(--text-muted); font-weight:600; margin-top:1px;">
                                            ⚽ <?= htmlspecialchars($row['posisi_utama'] ?: '-') ?>
                                            <?php if (!empty($row['posisi_sekunder']) && $row['posisi_sekunder'] !== '-'): ?>
                                                &bull; <span style="color:#7dd3fc;">🔄 <?= htmlspecialchars($row['posisi_sekunder']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <span class="badge badge-primary" style="font-size:0.68rem; font-weight:700; padding:2px 8px; border:1px solid rgba(99,102,241,0.3);"><?= htmlspecialchars($row['kelompok_usia']) ?></span>
                            </td>

                            <td style="padding: 9px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: right; font-family:'Courier New', monospace; font-weight:700; color:#e2e8f0; white-space: nowrap; min-width: 125px;">
                                Rp <?= number_format($row['jumlah'] ?: 150000, 0, ',', '.') ?>
                            </td>

                            <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <?php if ($isLunas): ?>
                                    <span class="badge badge-emerald" style="font-size:0.72rem; font-weight:800; padding:3px 10px; border:1px solid rgba(52,211,153,0.4);">
                                        ✓ LUNAS
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-rose" style="font-size:0.72rem; font-weight:800; padding:3px 10px; border:1px solid rgba(244,63,94,0.4);">
                                        ⚠️ BELUM BAYAR
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; font-size:0.78rem; color:#cbd5e1;">
                                <?= $row['tanggal_bayar'] ? date('d/m/Y', strtotime($row['tanggal_bayar'])) : '-' ?>
                            </td>

                            <td style="padding: 9px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); font-size:0.78rem; color:var(--text-muted);">
                                <?= htmlspecialchars($row['keterangan'] ?: '-') ?>
                            </td>

                            <?php if ($role === 'admin'): ?>
                            <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: center; white-space:nowrap;">
                                <div style="display:flex; gap:5px; justify-content:center; align-items:center;">
                                    <?php if ($isLunas): ?>
                                        <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:2px 8px; color:#38bdf8; border-color:rgba(56,189,248,0.3);" onclick='openPayModal(<?= json_encode($modalData) ?>)' title="Edit Detail & Catatan Keterangan">
                                            ✏️ Detail
                                        </button>
                                        <a href="bayar.php?atlet_id=<?= $row['atlet_id'] ?>&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>&action=unpay&ku=<?= urlencode($filterKu) ?>" class="btn btn-secondary btn-sm" style="font-size:0.72rem; padding:2px 6px; color:#f87171;" onclick="return confirm('Batalkan status lunas untuk <?= htmlspecialchars(addslashes($row['nama_lengkap'])) ?>?')">
                                            Batalkan
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-primary btn-sm" style="font-size:0.72rem; padding:3px 10px; font-weight:700; box-shadow:0 2px 8px rgba(99,102,241,0.4);" onclick='openPayModal(<?= json_encode($modalData) ?>)'>
                                            💳 Tandai Lunas
                                        </button>
                                        <?php if ($waLink): ?>
                                            <a href="<?= $waLink ?>" target="_blank" class="btn btn-sm" style="font-size:0.72rem; padding:3px 8px; background:#22c55e; color:#fff; font-weight:700; border-radius:6px; text-decoration:none;" title="Kirim Pengingat WA Wali">
                                                💬 WA
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION CONTROLS (10 DATA PER HALAMAN) -->
    <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.25rem; padding-top:0.75rem; border-top:1px solid var(--border-glass); flex-wrap:wrap; gap:0.5rem; font-size:0.82rem;">
            <div style="color:var(--text-muted);">
                Menampilkan <strong><?= count($displaySppRows) ?></strong> dari total <strong><?= $totalAtletCount ?></strong> data atlet (Halaman <?= $page ?> dari <?= $totalPages ?>)
            </div>
            <div style="display:flex; gap:4px; align-items:center;">
                <?php
                $queryParams = $_GET;
                ?>
                <?php if ($page > 1): ?>
                    <?php $queryParams['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-secondary btn-sm" style="padding:3px 8px; font-size:0.78rem;">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $queryParams['page'] = $i; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="padding:3px 8px; font-size:0.78rem;"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <?php $queryParams['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-secondary btn-sm" style="padding:3px 8px; font-size:0.78rem;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- INTERACTIVE PAYMENT MODAL POP-UP (OPSI 1) -->
<div id="sppModalBackdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); backdrop-filter:blur(8px); z-index:9999; align-items:center; justify-content:center; padding:1rem; transition:all 0.3s ease;">
    <div style="background:linear-gradient(135deg, rgba(30,27,75,0.98), rgba(15,23,42,0.98)); border:1px solid rgba(99,102,241,0.4); border-radius:20px; width:100%; max-width:540px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.7); overflow:hidden;">
        
        <!-- MODAL HEADER -->
        <div style="background:rgba(15,23,42,0.6); border-bottom:1px solid var(--border-glass); padding:1.1rem 1.4rem; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="width:36px; height:36px; border-radius:12px; background:linear-gradient(135deg, rgba(52,211,153,0.25), rgba(16,185,129,0.15)); color:#34d399; display:inline-flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:700; border:1px solid rgba(52,211,153,0.3);">
                    💳
                </span>
                <div>
                    <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0;">Form Pelunasan & Detail SPP</h3>
                    <p style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">Konfirmasi iuran, metode bayar & catatan keterangan</p>
                </div>
            </div>
            <button type="button" onclick="closePayModal()" style="background:none; border:none; color:var(--text-muted); font-size:1.4rem; cursor:pointer; padding:0; line-height:1;">✕</button>
        </div>

        <!-- MODAL FORM -->
        <form method="POST" action="bayar.php" style="padding:1.4rem; display:flex; flex-direction:column; gap:1.1rem;">
            <input type="hidden" name="atlet_id" id="modalAtletId">
            <input type="hidden" name="bulan" id="modalBulan">
            <input type="hidden" name="tahun" id="modalTahun">
            <input type="hidden" name="action" value="pay">
            <input type="hidden" name="ku" value="<?= htmlspecialchars($filterKu) ?>">

            <!-- ATHLETE SUMMARY CHIP INSIDE MODAL -->
            <div style="background:rgba(15,23,42,0.55); border:1px solid var(--border-glass); padding:0.85rem 1rem; border-radius:14px; display:flex; align-items:center; justify-content:space-between;">
                <div>
                    <div style="font-size:0.72rem; color:#38bdf8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">Atlet Terpilih</div>
                    <div id="modalNamaAtlet" style="font-size:0.95rem; font-weight:800; color:#fff; margin-top:2px;">-</div>
                    <div id="modalKuPosisi" style="font-size:0.75rem; color:var(--text-muted);">KU U-12 &bull; Gelandang</div>
                </div>
                <span id="modalPeriodeBadge" class="badge badge-primary" style="font-weight:700; font-size:0.75rem; padding:4px 10px;">-</span>
            </div>

            <!-- NOMINAL SPP -->
            <div class="form-group" style="margin:0;">
                <label style="font-size:0.8rem; font-weight:700; color:#cbd5e1; display:block; margin-bottom:4px;">
                    💰 Nominal Iuran SPP (Rp)
                </label>
                <div style="position:relative;">
                    <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); font-weight:800; color:#34d399; font-size:0.9rem;">Rp</span>
                    <input type="number" name="jumlah" id="modalNominalInput" class="form-control" style="padding-left:38px; font-family:'Courier New', monospace; font-weight:800; color:#34d399; font-size:1.05rem;" required>
                </div>
                <!-- PRESET NOMINAL CHIPS -->
                <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:2px 6px;" onclick="setNominal(150000)">150 Rb (Standar)</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:2px 6px;" onclick="setNominal(100000)">100 Rb (Diskon)</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:2px 6px;" onclick="setNominal(0)">0 (Beasiswa Full)</button>
                </div>
            </div>

            <!-- TANGGAL BAYAR & METODE BAYAR GRID -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.9rem;">
                <div class="form-group" style="margin:0;">
                    <label style="font-size:0.8rem; font-weight:700; color:#cbd5e1; display:block; margin-bottom:4px;">
                        📅 Tanggal Pembayaran
                    </label>
                    <input type="date" name="tanggal_bayar" id="modalTglBayar" class="form-control" style="font-weight:700; font-size:0.82rem;" required>
                </div>

                <div class="form-group" style="margin:0;">
                    <label style="font-size:0.8rem; font-weight:700; color:#cbd5e1; display:block; margin-bottom:4px;">
                        💳 Metode Pembayaran
                    </label>
                    <select name="metode_bayar" id="modalMetodeBayar" class="form-control" style="font-weight:700; font-size:0.82rem; color:#38bdf8;">
                        <option value="Tunai / Cash">💵 Tunai / Cash</option>
                        <option value="Transfer BCA">🏦 Transfer Bank BCA</option>
                        <option value="Transfer Mandiri / BRI">💳 Transfer Mandiri / BRI</option>
                        <option value="QRIS / E-Wallet">📲 QRIS / E-Wallet</option>
                        <option value="Beasiswa / Potongan">🎓 Beasiswa / Diskon</option>
                        <option value="Lainnya">📝 Lainnya</option>
                    </select>
                </div>
            </div>

            <!-- CATATAN KETERANGAN TAMBAHAN -->
            <div class="form-group" style="margin:0;">
                <label style="font-size:0.8rem; font-weight:700; color:#cbd5e1; display:block; margin-bottom:4px;">
                    📝 Catatan Keterangan Tambahan (Opsional)
                </label>
                <!-- PRESET CATATAN CHIPS -->
                <div style="display:flex; gap:5px; margin-bottom:6px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.68rem; padding:2px 6px; background:rgba(255,255,255,0.05);" onclick="addCatatan('Diberikan Kwitansi Resmi')">+ Kwitansi Resmi</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.68rem; padding:2px 6px; background:rgba(255,255,255,0.05);" onclick="addCatatan('Ditransfer via Orang Tua')">+ Via Orang Tua</button>
                    <button type="button" class="btn btn-secondary btn-sm" style="font-size:0.68rem; padding:2px 6px; background:rgba(255,255,255,0.05);" onclick="addCatatan('Diserahkan di Lapangan')">+ Di Lapangan</button>
                </div>
                <textarea name="catatan" id="modalCatatanInput" class="form-control" rows="2" placeholder="Contoh: Kwitansi #108, dikirim via WA oleh Ibu Maya..."></textarea>
            </div>

            <!-- MODAL ACTIONS -->
            <div style="display:flex; justify-content:flex-end; gap:0.75rem; margin-top:0.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closePayModal()" style="padding:0.55rem 1.1rem;">Batal</button>
                <button type="submit" class="btn btn-primary" style="padding:0.55rem 1.4rem; font-weight:700; box-shadow:0 4px 15px rgba(99,102,241,0.4);">
                    💾 Simpan Pelunasan & Keterangan
                </button>
            </div>
        </form>

    </div>
</div>

<script>
function openPayModal(data) {
    const backdrop = document.getElementById('sppModalBackdrop');
    if (!backdrop) return;

    document.getElementById('modalAtletId').value = data.atlet_id;
    document.getElementById('modalBulan').value = data.bulan;
    document.getElementById('modalTahun').value = data.tahun;
    document.getElementById('modalNamaAtlet').innerText = data.nama;
    document.getElementById('modalKuPosisi').innerHTML = `KU ${data.ku} &bull; ${data.posisi}`;
    document.getElementById('modalPeriodeBadge').innerText = `${data.bulan_nama} ${data.tahun}`;
    document.getElementById('modalNominalInput').value = data.nominal || 150000;
    
    // Set Date
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('modalTglBayar').value = data.tgl_bayar || today;

    // Parse existing keterangan
    const catInput = document.getElementById('modalCatatanInput');
    const metodeSelect = document.getElementById('modalMetodeBayar');

    if (data.keterangan) {
        let ket = data.keterangan;
        if (ket.startsWith('[')) {
            const closeBracket = ket.indexOf(']');
            if (closeBracket > -1) {
                const met = ket.substring(1, closeBracket);
                metodeSelect.value = met;
                catInput.value = ket.substring(closeBracket + 1).trim();
            } else {
                catInput.value = ket;
            }
        } else {
            catInput.value = ket;
        }
    } else {
        catInput.value = '';
        metodeSelect.value = 'Tunai / Cash';
    }

    backdrop.style.display = 'flex';
}

function closePayModal() {
    const backdrop = document.getElementById('sppModalBackdrop');
    if (backdrop) backdrop.style.display = 'none';
}

function setNominal(val) {
    const input = document.getElementById('modalNominalInput');
    if (input) input.value = val;
}

function addCatatan(text) {
    const input = document.getElementById('modalCatatanInput');
    if (!input) return;
    if (input.value.trim() === '') {
        input.value = text;
    } else {
        input.value = input.value.trim() + " - " + text;
    }
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
