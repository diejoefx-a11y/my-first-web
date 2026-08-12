<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$pdo = getPdo();
$user = getAuthUser();
$isAdmin = ($user && $user['role'] === 'admin');

$pageTitle = "Data Atlet SSB Tamalanrea";

// Filters
$filterKu = $_GET['ku'] ?? '';
$filterPosisi = $_GET['posisi'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM atlet WHERE 1=1";
$params = [];

if ($filterKu) {
    $sql .= " AND kelompok_usia = ?";
    $params[] = $filterKu;
}

if ($filterPosisi) {
    $sql .= " AND posisi_utama LIKE ?";
    $params[] = "%$filterPosisi%";
}

if ($search) {
    $sql .= " AND (nama_lengkap LIKE ? OR nisn_nik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY kelompok_usia ASC, nama_lengkap ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$atletList = $stmt->fetchAll();

// Statistics Breakdown
$totalAktif = 0;
$posisiCounts = ['Kiper' => 0, 'Bek' => 0, 'Gelandang' => 0, 'Penyerang' => 0];
foreach ($atletList as $at) {
    if (($at['status_keanggotaan'] ?? '') === 'Aktif') $totalAktif++;
    $pos = $at['posisi_utama'] ?? '';
    if (strpos($pos, 'Kiper') !== false) $posisiCounts['Kiper']++;
    elseif (strpos($pos, 'Bek') !== false) $posisiCounts['Bek']++;
    elseif (strpos($pos, 'Gelandang') !== false) $posisiCounts['Gelandang']++;
    elseif (strpos($pos, 'Penyerang') !== false) $posisiCounts['Penyerang']++;
}

// Kelompok Usia Breakdown for Stat Cards
$kuCountsAll = $pdo->query("SELECT kelompok_usia, COUNT(*) as total FROM atlet WHERE status_keanggotaan = 'Aktif' GROUP BY kelompok_usia ORDER BY kelompok_usia ASC")->fetchAll();

// Pagination Logic (10 items per page)
$perPage = 10;
$totalAtletCount = count($atletList);
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = ceil($totalAtletCount / $perPage);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Slice 10 items for current page display
$displayAtletList = array_slice($atletList, $offset, $perPage);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- CUSTOM STYLES FOR DYNAMIC ATHLETE INDEX -->
<style>
.atlet-card-item {
    background: rgba(15, 23, 42, 0.75);
    border: 1px solid var(--border-glass);
    border-radius: 16px;
    padding: 1.1rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}
.atlet-card-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px -8px rgba(99, 102, 241, 0.3);
    border-color: rgba(99, 102, 241, 0.5);
}

.atlet-row-hover:hover {
    background: rgba(30, 41, 59, 0.6) !important;
}

.view-btn-active {
    background: var(--primary) !important;
    color: #fff !important;
    border-color: var(--primary) !important;
}
</style>

<!-- KARTU STATISTIK KELOMPOK USIA (KU BREAKDOWN) -->
<div class="card" style="margin-bottom: 1.5rem; padding: 1.1rem 1.25rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem; flex-wrap:wrap; gap:0.5rem;">
        <div>
            <h3 style="font-size:1.1rem; font-weight:700; color:#fff; margin:0; display:flex; align-items:center; gap:8px;">
                📊 Kartu Statistik Kelompok Usia (KU)
            </h3>
            <span style="font-size:0.78rem; color:var(--text-muted); margin-top:2px; display:block;">Klik kartu kelompok usia di bawah untuk menyaring daftar atlet secara instan</span>
        </div>
        <?php if ($filterKu): ?>
            <a href="index.php" class="btn btn-secondary btn-sm" style="font-size:0.75rem;">Reset Filter KU (<?= htmlspecialchars($filterKu) ?>)</a>
        <?php endif; ?>
    </div>

    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(115px, 1fr)); gap:10px;">
        <a href="index.php" style="background:<?= empty($filterKu) ? 'rgba(99,102,241,0.25)' : 'rgba(15,23,42,0.65)' ?>; padding:0.75rem 0.5rem; border-radius:12px; border:1px solid <?= empty($filterKu) ? 'rgba(99,102,241,0.6)' : 'var(--border-glass)' ?>; text-decoration:none; text-align:center; transition:all 0.25s ease;">
            <span class="badge" style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.7rem;">SEMUA KU</span>
            <div style="font-family:'Outfit'; font-size:1.45rem; font-weight:800; color:#fff; margin:4px 0 1px 0;"><?= $totalAktif ?></div>
            <div style="font-size:0.68rem; color:var(--text-muted); font-weight:600;">Total Atlet</div>
        </a>
        <?php 
        $kuColorList = ['#818cf8', '#38bdf8', '#34d399', '#fbbf24', '#f472b6', '#a78bfa', '#fb7185'];
        $idx = 0;
        foreach ($kuCountsAll as $ku): 
            $accentColor = $kuColorList[$idx % count($kuColorList)];
            $isSelected = ($filterKu === $ku['kelompok_usia']);
            $idx++;
        ?>
            <a href="index.php?ku=<?= urlencode($ku['kelompok_usia']) ?>" style="background:<?= $isSelected ? 'rgba(99,102,241,0.25)' : 'rgba(15,23,42,0.65)' ?>; padding:0.75rem 0.5rem; border-radius:12px; border:1px solid <?= $isSelected ? 'rgba(99,102,241,0.6)' : 'var(--border-glass)' ?>; border-top:3.5px solid <?= $accentColor ?>; text-decoration:none; text-align:center; transition:all 0.25s ease;">
                <span class="badge" style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.7rem; font-weight:700;"><?= htmlspecialchars($ku['kelompok_usia']) ?></span>
                <div style="font-family:'Outfit'; font-size:1.45rem; font-weight:800; color:#fff; margin:4px 0 1px 0;"><?= $ku['total'] ?></div>
                <div style="font-size:0.68rem; color:var(--text-muted); font-weight:600;">Pemain</div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- STATISTIK RINGKASAN DATA ATLET BERDASARKAN POSISI -->
<div class="grid-4" style="margin-bottom: 1.5rem;">
    <div class="stat-card">
        <div>
            <div class="stat-label">Kiper (GK)</div>
            <div class="stat-value" style="color:#fbbf24;"><?= $posisiCounts['Kiper'] ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
        </div>
        <div class="stat-icon" style="color:#fbbf24; background:rgba(251,191,36,0.18);">
            🧤
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Bek (Defender)</div>
            <div class="stat-value" style="color:#a855f7;"><?= $posisiCounts['Bek'] ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
        </div>
        <div class="stat-icon" style="color:#a855f7; background:rgba(168,85,247,0.18);">
            🛡️
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Gelandang (Midfielder)</div>
            <div class="stat-value" style="color:#38bdf8;"><?= $posisiCounts['Gelandang'] ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
        </div>
        <div class="stat-icon" style="color:#38bdf8; background:rgba(6,182,212,0.18);">
            🎯
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Striker / Penyerang</div>
            <div class="stat-value" style="color:#34d399;"><?= $posisiCounts['Penyerang'] ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
        </div>
        <div class="stat-icon" style="color:#34d399; background:rgba(16,185,129,0.18);">
            ⚽
        </div>
    </div>
</div>


<div class="card" style="padding: 1.25rem;">
    <!-- HEADER BAR WITH VIEW TOGGLE & TAMBAH BUTTON -->
    <div class="card-header" style="flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem; padding-bottom:0.75rem; border-bottom:1px solid var(--border-glass);">
        <div>
            <h2 class="card-title" style="font-size:1.3rem;">🏃‍♂️ Daftar Atlet Sekolah Sepak Bola</h2>
            <p style="font-size:0.82rem; color:var(--text-muted); margin-top:2px;">Direktori resmi data pemain & anggota aktif SSB Tamalanrea Makassar (Maksimal 10 Atlet per Halaman)</p>
        </div>
        
        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <!-- View Mode Switcher -->
            <div style="display:flex; background:rgba(15,23,42,0.8); padding:3px; border-radius:10px; border:1px solid var(--border-glass);">
                <button type="button" id="btnTableView" onclick="switchViewMode('table')" class="btn btn-secondary btn-sm view-btn-active" style="padding:4px 10px; font-size:0.78rem;">📋 Tabel</button>
                <button type="button" id="btnCardView" onclick="switchViewMode('card')" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:0.78rem;">🎴 Kartu Pemain</button>
            </div>

            <?php if ($isAdmin): ?>
                <a href="tambah.php" class="btn btn-primary btn-sm" style="font-size:0.82rem; padding:0.5rem 0.9rem;">+ Tambah Atlet Baru</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1.25rem; font-size:0.85rem;">
            ✓ Data atlet beserta riwayat raport & SPP berhasil dihapus dari sistem.
        </div>
    <?php endif; ?>

    <!-- FILTER & SEARCH BAR WITH REALTIME JAVASCRIPT FILTERING -->
    <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem; margin-bottom:1.25rem; background:rgba(15,23,42,0.6); padding:0.85rem; border-radius:14px; border:1px solid var(--border-glass);">
        <div>
            <label style="font-size:0.72rem; color:var(--text-muted); font-weight:600; display:block; margin-bottom:3px;">CARI PEMAIN / NISN</label>
            <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama atau NISN..." class="form-control" style="font-size:0.82rem; padding:0.45rem 0.75rem;" oninput="applyLiveFilter()">
        </div>

        <div>
            <label style="font-size:0.72rem; color:var(--text-muted); font-weight:600; display:block; margin-bottom:3px;">KELOMPOK USIA (KU)</label>
            <select name="ku" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; padding:0.45rem 0.75rem;">
                <option value="">Semua Kelompok Usia</option>
                <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $ku): ?>
                    <option value="<?= $ku ?>" <?= $filterKu == $ku ? 'selected' : '' ?>><?= $ku ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:0.72rem; color:var(--text-muted); font-weight:600; display:block; margin-bottom:3px;">POSISI UTAMA</label>
            <select name="posisi" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; padding:0.45rem 0.75rem;">
                <option value="">Semua Posisi</option>
                <option value="Kiper" <?= $filterPosisi == 'Kiper' ? 'selected' : '' ?>>🧤 Kiper (GK)</option>
                <option value="Bek" <?= $filterPosisi == 'Bek' ? 'selected' : '' ?>>🛡️ Bek / Defender</option>
                <option value="Gelandang" <?= $filterPosisi == 'Gelandang' ? 'selected' : '' ?>>🎯 Gelandang / Midfielder</option>
                <option value="Penyerang" <?= $filterPosisi == 'Penyerang' ? 'selected' : '' ?>>⚽ Penyerang / Forward</option>
            </select>
        </div>

        <div style="display:flex; align-items:flex-end;">
            <a href="index.php" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center; padding:0.5rem; font-size:0.8rem;">Reset Filter</a>
        </div>
    </form>

    <!-- 1. VIEW MODE: TABEL SLEEK -->
    <div id="containerTableView" style="width:100%; overflow:hidden; border-radius:12px; border:1px solid rgba(255,255,255,0.12);">
        <table class="data-table" style="width:100%; border-collapse:separate; border-spacing:0; font-size:0.83rem;">
            <thead>
                <tr style="background:rgba(15,23,42,0.95);">
                    <th style="padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08);">Nama Atlet</th>
                    <th style="padding:10px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08);">NISN / NIK</th>
                    <th style="padding:10px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center;">KU</th>
                    <th style="padding:10px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center;">Posisi Utama</th>
                    <th style="padding:10px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center;">Tinggi / Berat</th>
                    <th style="padding:10px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center;">Kaki</th>
                    <th style="padding:10px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center;">Status</th>
                    <th style="padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.12); text-align:center;">Aksi Management</th>
                </tr>
            </thead>
            <tbody id="atletTableBody">
                <?php if (count($displayAtletList) == 0): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:2.5rem; color:var(--text-muted);">Tidak ada data atlet yang ditemukan.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($displayAtletList as $a): ?>
                        <?php
                            $photoPath = __DIR__ . '/../assets/img/atlet/' . ($a['foto_profil'] ?? '');
                            $hasPhoto = !empty($a['foto_profil']) && $a['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
                            
                            // Format Position Badge Icon
                            $posName = $a['posisi_utama'] ?? '';
                            $posIcon = '⚽';
                            if (strpos($posName, 'Kiper') !== false) $posIcon = '🧤';
                            elseif (strpos($posName, 'Bek') !== false) $posIcon = '🛡️';
                            elseif (strpos($posName, 'Gelandang') !== false) $posIcon = '🎯';
                        ?>
                        <tr class="atlet-row-hover atlet-data-row" data-name="<?= strtolower(htmlspecialchars($a['nama_lengkap'])) ?>" data-nisn="<?= strtolower(htmlspecialchars($a['nisn_nik'])) ?>" style="border-bottom:1px solid rgba(255,255,255,0.06); transition:background 0.2s;">
                            <td style="padding:9px 10px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06);">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="background:#1e293b; display:flex; align-items:center; justify-content:center; font-weight:700; color:#818cf8; overflow:hidden; width:34px; height:34px; border-radius:50%; flex-shrink:0; border:2px solid rgba(129,140,248,0.3);">
                                        <?php if ($hasPhoto): ?>
                                            <img src="../assets/img/atlet/<?= htmlspecialchars($a['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($a['nama_lengkap'], 0, 2)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong style="color:#fff; font-size:0.88rem; display:block;"><?= htmlspecialchars($a['nama_lengkap']) ?></strong>
                                        <span style="font-size:0.73rem; color:var(--text-muted);"><?= htmlspecialchars($a['tempat_lahir'] ?: '-') ?>, <?= !empty($a['tanggal_lahir']) ? date('d/m/Y', strtotime($a['tanggal_lahir'])) : '-' ?></span>
                                    </div>
                                </div>
                            </td>

                            <td style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); font-family:monospace; color:#a5b4fc; font-size:0.85rem;">
                                <?= htmlspecialchars($a['nisn_nik'] ?: '-') ?>
                            </td>

                            <td style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center;">
                                <span class="badge badge-primary" style="padding:2px 7px; font-size:0.7rem;"><?= htmlspecialchars($a['kelompok_usia']) ?></span>
                            </td>

                            <td style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center;">
                                <strong style="color:#e2e8f0; font-size:0.82rem;"><?= $posIcon ?> <?= htmlspecialchars($a['posisi_utama']) ?></strong>
                            </td>

                            <td style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center; font-size:0.8rem; color:#cbd5e1;">
                                <?= $a['tinggi_badan'] ?> cm / <?= $a['berat_badan'] ?> kg
                            </td>

                            <td style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center; color:var(--text-muted); font-size:0.8rem;">
                                <?= htmlspecialchars($a['kaki_dominan']) ?>
                            </td>

                            <td style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center;">
                                <span class="badge badge-emerald" style="padding:2px 8px; font-size:0.7rem;">✓ <?= htmlspecialchars($a['status_keanggotaan']) ?></span>
                            </td>

                            <td style="padding:9px 10px; border-bottom:1px solid rgba(255,255,255,0.06); text-align:center; white-space:nowrap;">
                                <div style="display:flex; gap:4px; justify-content:center;">
                                    <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:3px 8px; font-size:0.75rem;" title="Lihat Profil & Raport">Profil</a>
                                    <?php if ($isAdmin): ?>
                                        <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:3px 8px; font-size:0.75rem; color:#fbbf24;" title="Edit Data Atlet">Edit</a>
                                        <a href="hapus.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:3px 8px; font-size:0.75rem; color:#f87171;" title="Hapus Atlet" onclick="return confirm('Apakah Anda yakin ingin menghapus atlet <?= htmlspecialchars(addslashes($a['nama_lengkap'])) ?>? Seluruh riwayat raport & SPP atlet ini akan terhapus.');">Hapus</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. VIEW MODE: KARTU PEMAIN (SCOUTING GRID) -->
    <div id="containerCardView" style="display:none; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:1rem; margin-top:0.5rem;">
        <?php foreach ($displayAtletList as $a): ?>
            <?php
                $photoPath = __DIR__ . '/../assets/img/atlet/' . ($a['foto_profil'] ?? '');
                $hasPhoto = !empty($a['foto_profil']) && $a['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
                
                $posName = $a['posisi_utama'] ?? '';
                $posIcon = '⚽';
                if (strpos($posName, 'Kiper') !== false) $posIcon = '🧤';
                elseif (strpos($posName, 'Bek') !== false) $posIcon = '🛡️';
                elseif (strpos($posName, 'Gelandang') !== false) $posIcon = '🎯';
            ?>
            <div class="atlet-card-item atlet-data-card" data-name="<?= strtolower(htmlspecialchars($a['nama_lengkap'])) ?>" data-nisn="<?= strtolower(htmlspecialchars($a['nisn_nik'])) ?>">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.75rem;">
                    <span class="badge badge-primary" style="font-size:0.7rem; font-weight:700;"><?= htmlspecialchars($a['kelompok_usia']) ?></span>
                    <span class="badge badge-emerald" style="font-size:0.68rem;">✓ <?= htmlspecialchars($a['status_keanggotaan']) ?></span>
                </div>

                <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.85rem;">
                    <div style="background:#1e293b; width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.1rem; font-weight:800; color:#818cf8; overflow:hidden; flex-shrink:0; border:2px solid rgba(129,140,248,0.4);">
                        <?php if ($hasPhoto): ?>
                            <img src="../assets/img/atlet/<?= htmlspecialchars($a['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($a['nama_lengkap'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong style="color:#fff; font-size:0.95rem; display:block;"><?= htmlspecialchars($a['nama_lengkap']) ?></strong>
                        <div style="font-size:0.75rem; color:#a5b4fc; font-family:monospace; margin-top:1px;">NISN: <?= htmlspecialchars($a['nisn_nik'] ?: '-') ?></div>
                    </div>
                </div>

                <div style="background:rgba(15,23,42,0.6); padding:0.65rem 0.85rem; border-radius:10px; border:1px solid var(--border-glass); margin-bottom:0.85rem; font-size:0.78rem; display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                    <div><span style="color:var(--text-muted);">Posisi:</span> <strong style="color:#fff;"><?= $posIcon ?> <?= htmlspecialchars($a['posisi_utama']) ?></strong></div>
                    <div><span style="color:var(--text-muted);">Kaki:</span> <strong style="color:#fff;"><?= htmlspecialchars($a['kaki_dominan']) ?></strong></div>
                    <div><span style="color:var(--text-muted);">Tinggi:</span> <strong style="color:#fff;"><?= $a['tinggi_badan'] ?> cm</strong></div>
                    <div><span style="color:var(--text-muted);">Berat:</span> <strong style="color:#fff;"><?= $a['berat_badan'] ?> kg</strong></div>
                </div>

                <div style="display:flex; gap:6px;">
                    <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1; justify-content:center; font-size:0.75rem;">Profil & Raport</a>
                    <?php if ($isAdmin): ?>
                        <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="color:#fbbf24; padding:0 8px; font-size:0.75rem;">Edit</a>
                        <a href="hapus.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="color:#f87171; padding:0 8px; font-size:0.75rem;" onclick="return confirm('Yakin hapus atlet ini?');">Hapus</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINATION CONTROLS (10 ATLET PER HALAMAN) -->
    <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.25rem; padding-top:0.85rem; border-top:1px solid var(--border-glass); flex-wrap:wrap; gap:0.5rem; font-size:0.82rem;">
            <div style="color:var(--text-muted);">
                Menampilkan <strong><?= count($displayAtletList) ?></strong> dari total <strong><?= $totalAtletCount ?></strong> atlet (Halaman <?= $page ?> dari <?= $totalPages ?>)
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

<!-- JAVASCRIPT SWITCHER & LIVE FILTER -->
<script>
function switchViewMode(mode) {
    const tbl = document.getElementById('containerTableView');
    const crd = document.getElementById('containerCardView');
    const btnTbl = document.getElementById('btnTableView');
    const btnCrd = document.getElementById('btnCardView');

    if (mode === 'card') {
        tbl.style.display = 'none';
        crd.style.display = 'grid';
        btnTbl.classList.remove('view-btn-active');
        btnCrd.classList.add('view-btn-active');
    } else {
        tbl.style.display = 'block';
        crd.style.display = 'none';
        btnCrd.classList.remove('view-btn-active');
        btnTbl.classList.add('view-btn-active');
    }
}

function applyLiveFilter() {
    const input = document.getElementById('liveSearchInput').value.toLowerCase().trim();
    
    // Filter table rows
    const rows = document.querySelectorAll('.atlet-data-row');
    rows.forEach(r => {
        const name = r.getAttribute('data-name');
        const nisn = r.getAttribute('data-nisn');
        if (name.includes(input) || nisn.includes(input)) {
            r.style.display = '';
        } else {
            r.style.display = 'none';
        }
    });

    // Filter cards
    const cards = document.querySelectorAll('.atlet-data-card');
    cards.forEach(c => {
        const name = c.getAttribute('data-name');
        const nisn = c.getAttribute('data-nisn');
        if (name.includes(input) || nisn.includes(input)) {
            c.style.display = '';
        } else {
            c.style.display = 'none';
        }
    });
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
