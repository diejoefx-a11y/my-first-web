<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireAuth();
$pdo = getPdo();
$user = getAuthUser();
$role = $user['role'];
$canEdit = ($role === 'admin' || $role === 'pelatih');

$pageTitle = "Raport & Evaluasi Performa Atlet";

// Filter Parameters
$filterKu = $_GET['ku'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = [];
$params = [];

if (!empty($filterKu)) {
    $where[] = "a.kelompok_usia = ?";
    $params[] = $filterKu;
}

if (!empty($search)) {
    $where[] = "(a.nama_lengkap LIKE ? OR a.nisn_nik LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = "";
if (count($where) > 0) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

// Fetch All Records For Statistics Calculation
$sqlAll = "
    SELECT e.*, a.nama_lengkap, a.kelompok_usia, a.posisi_utama, a.posisi_sekunder, a.foto_profil
    FROM evaluasi_atlet e 
    JOIN atlet a ON e.atlet_id = a.id 
    $whereSql
    ORDER BY e.tanggal_evaluasi DESC, e.id DESC
";
$stmtAll = $pdo->prepare($sqlAll);
$stmtAll->execute($params);
$evaluasiList = $stmtAll->fetchAll();

// Statistics Counter Cards
$currentMonth = date('n');
$currentYear = date('Y');

$totalRaport = count($evaluasiList);
$totalAtletDievaluasi = count(array_unique(array_column($evaluasiList, 'atlet_id')));
$avgVo2max = $totalRaport > 0 ? round(array_sum(array_column($evaluasiList, 'vo2max')) / $totalRaport, 1) : 0;
$evalBulanIni = 0;
foreach ($evaluasiList as $ev) {
    if (date('n', strtotime($ev['tanggal_evaluasi'])) == $currentMonth && date('Y', strtotime($ev['tanggal_evaluasi'])) == $currentYear) {
        $evalBulanIni++;
    }
}

// Position Breakdown Counter (Filter-Aware)
$atletWhere = [];
$atletParams = [];

if (!empty($filterKu)) {
    $atletWhere[] = "kelompok_usia = ?";
    $atletParams[] = $filterKu;
}

if (!empty($search)) {
    $atletWhere[] = "(nama_lengkap LIKE ? OR nisn_nik LIKE ?)";
    $atletParams[] = "%$search%";
    $atletParams[] = "%$search%";
}

$atletWhereSql = count($atletWhere) > 0 ? "WHERE " . implode(" AND ", $atletWhere) : "";
$stmtPos = $pdo->prepare("SELECT posisi_utama FROM atlet $atletWhereSql");
$stmtPos->execute($atletParams);
$filteredAtletPos = $stmtPos->fetchAll(PDO::FETCH_COLUMN);

$posisiCounts = ['Kiper' => 0, 'Bek' => 0, 'Gelandang' => 0, 'Penyerang' => 0];
foreach ($filteredAtletPos as $pos) {
    if (strpos($pos, 'Kiper') !== false) $posisiCounts['Kiper']++;
    elseif (strpos($pos, 'Bek') !== false) $posisiCounts['Bek']++;
    elseif (strpos($pos, 'Gelandang') !== false) $posisiCounts['Gelandang']++;
    elseif (strpos($pos, 'Penyerang') !== false) $posisiCounts['Penyerang']++;
}

// Pagination Logic (Max 10 records per page)
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages = ceil($totalRaport / $perPage);
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

// Slice 10 items for current page display
$displayList = array_slice($evaluasiList, $offset, $perPage);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- KARTU STATISTIK POSISI PEMAIN & RATA-RATA VO2MAX (DINAMIS SESUAI FILTER KU) -->
<?php if (!empty($filterKu) || !empty($search)): ?>
    <div style="margin-bottom:0.75rem; font-size:0.8rem; color:#818cf8; font-weight:600; display:flex; align-items:center; gap:6px;">
        <span style="background:rgba(99,102,241,0.2); border:1px solid rgba(99,102,241,0.4); padding:3px 10px; border-radius:8px;">
            ⚡ Statistik Disesuaikan dengan Filter: <?= !empty($filterKu) ? 'KU <strong>' . htmlspecialchars($filterKu) . '</strong>' : '' ?> <?= (!empty($filterKu) && !empty($search)) ? '|' : '' ?> <?= !empty($search) ? 'Pencarian: "<strong>' . htmlspecialchars($search) . '</strong>"' : '' ?>
        </span>
    </div>
<?php endif; ?>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:1rem; margin-bottom: 1.5rem;">
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

    <div class="stat-card">
        <div>
            <div class="stat-label">Rata-Rata VO2Max</div>
            <div class="stat-value" style="color:#38bdf8;"><?= $avgVo2max ?> <span style="font-size:0.85rem; font-weight:500;">mL/kg</span></div>
        </div>
        <div class="stat-icon" style="color:#38bdf8; background:rgba(6,182,212,0.18);">
            🫁
        </div>
    </div>
</div>





<div class="card" style="padding: 1.25rem;">
    <div class="card-header" style="flex-wrap:wrap; gap:1rem; margin-bottom: 1rem; padding-bottom: 0.75rem;">
        <div>
            <h2 class="card-title">Raport Perkembangan Fisik & Teknis Atlet</h2>
            <p style="font-size:0.82rem; color:var(--text-muted);">Riwayat Penilaian Berkala oleh Tim Pelatih SSB Tamalanrea (Maksimal 10 Data per Halaman)</p>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'eval_updated'): ?>
        <div style="background:rgba(16,185,129,0.15); border:1px solid var(--emerald); color:#34d399; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:0.85rem;">
            ✓ Perubahan data raport evaluasi berhasil disimpan.
        </div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'eval_added'): ?>
        <div style="background:rgba(16,185,129,0.15); border:1px solid var(--emerald); color:#34d399; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:0.85rem;">
            ✓ Raport evaluasi atlet baru berhasil ditambahkan.
        </div>
    <?php elseif (isset($_GET['success']) && $_GET['success'] === 'eval_deleted'): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1rem; font-size:0.85rem;">
            ✓ Data raport evaluasi berhasil dihapus.
        </div>
    <?php endif; ?>

    <!-- FILTER & SEARCH BAR -->
    <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem; margin-bottom:1rem; background:rgba(15,23,42,0.6); padding:0.75rem; border-radius:12px; border:1px solid var(--border-glass);">
        <div>
            <label style="font-size:0.72rem; color:var(--text-muted); font-weight:600; display:block; margin-bottom:2px;">CARI NAMA ATLET</label>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama atlet..." class="form-control" style="font-size:0.82rem; padding:0.4rem 0.75rem;">
        </div>

        <div>
            <label style="font-size:0.72rem; color:var(--text-muted); font-weight:600; display:block; margin-bottom:2px;">KELOMPOK USIA (KU)</label>
            <select name="ku" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; padding:0.4rem 0.75rem;">
                <option value="">Semua Kelompok Usia</option>
                <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $ku): ?>
                    <option value="<?= $ku ?>" <?= $filterKu == $ku ? 'selected' : '' ?>><?= $ku ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; align-items:flex-end;">
            <a href="index.php" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center; padding:0.45rem;">Reset Filter</a>
        </div>
    </form>

    <!-- DYNAMICALLY FITTING TABLE (NO SCROLLBAR) -->
    <div style="width: 100%; overflow: hidden; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.12);">
        <table class="data-table" style="table-layout: auto; width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.82rem;">
            <thead>
                <tr style="background: rgba(15, 23, 42, 0.95);">
                    <th style="padding: 9px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); width: 32px; text-align: center;">No</th>
                    <th style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1);">Nama Atlet</th>
                    <th style="padding: 9px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">KU/Posisi</th>
                    <th style="padding: 9px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">Tgl Eval</th>
                    <th style="padding: 9px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;" title="Passing & Control">Pass</th>
                    <th style="padding: 9px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;" title="Dribbling & Ball Handling">Drib</th>
                    <th style="padding: 9px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;" title="Shooting & Finishing">Shoot</th>
                    <th style="padding: 9px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;" title="Tackling & Defending">Tack</th>
                    <th style="padding: 9px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;" title="Stamina & Physical">Stam</th>
                    <th style="padding: 9px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center;" title="VO2Max Stamina">VO2</th>
                    <th style="padding: 9px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($displayList) == 0): ?>
                    <tr>
                        <td colspan="11" style="text-align:center; padding:2rem; color:var(--text-muted);">
                            Belum ada data raport evaluasi yang sesuai dengan filter.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php 
                    $no = $offset + 1; 
                    foreach ($displayList as $ev): 
                        $photoPath = __DIR__ . '/../assets/img/atlet/' . ($ev['foto_profil'] ?? '');
                        $hasPhoto = !empty($ev['foto_profil']) && $ev['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);

                        // Helper function for compact score badge with Red to Metallic Green gradient
                        $getScoreBadge = function($val) {
                            $st = getScoreStyle($val);
                            return "<span style='{$st['badge']}'>$val</span>";
                        };

                    ?>
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); transition: background 0.2s;">
                            <td style="padding: 8px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; color: var(--text-muted); font-weight: 600;">
                                <?= $no++ ?>
                            </td>

                            <td style="padding: 8px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08);">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="background:#1e293b; display:flex; align-items:center; justify-content:center; font-weight:700; color:#818cf8; overflow:hidden; width:28px; height:28px; border-radius:50%; flex-shrink:0; font-size:0.75rem;">
                                        <?php if ($hasPhoto): ?>
                                            <img src="../assets/img/atlet/<?= htmlspecialchars($ev['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($ev['nama_lengkap'], 0, 2)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <strong style="color:#fff; font-size:0.85rem; white-space:nowrap;"><?= htmlspecialchars($ev['nama_lengkap']) ?></strong>
                                </div>
                            </td>

                            <td style="padding: 8px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; white-space:nowrap;">
                                <span class="badge badge-primary" style="font-size:0.65rem; padding:1px 6px;"><?= htmlspecialchars($ev['kelompok_usia']) ?></span>
                                <div style="font-size:0.72rem; color:#38bdf8; font-weight:700; margin-top:2px;">
                                    ⚽ <?= htmlspecialchars($ev['posisi_utama'] ?: '-') ?>
                                </div>
                                <?php if (!empty($ev['posisi_sekunder']) && $ev['posisi_sekunder'] !== '-'): ?>
                                    <div style="font-size:0.68rem; color:#7dd3fc; font-weight:600; margin-top:1px;">
                                        🔄 <?= htmlspecialchars($ev['posisi_sekunder']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td style="padding: 8px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; font-size:0.8rem; color:#cbd5e1; white-space:nowrap;">
                                <?= date('d/m/y', strtotime($ev['tanggal_evaluasi'])) ?>
                            </td>

                            <td style="padding: 8px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <?= $getScoreBadge($ev['nilai_passing']) ?>
                            </td>

                            <td style="padding: 8px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <?= $getScoreBadge($ev['nilai_dribbling']) ?>
                            </td>

                            <td style="padding: 8px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <?= $getScoreBadge($ev['nilai_shooting']) ?>
                            </td>

                            <td style="padding: 8px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <?= $getScoreBadge($ev['nilai_tackling']) ?>
                            </td>

                            <td style="padding: 8px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <?= $getScoreBadge($ev['nilai_stamina']) ?>
                            </td>

                            <td style="padding: 8px 4px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                <span style="color:#22d3ee; font-weight:700; font-size:0.8rem;"><?= $ev['vo2max'] ?></span>
                            </td>

                            <td style="padding: 8px 6px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: center; white-space:nowrap;">
                                <div style="display:flex; gap:4px; justify-content:center;">
                                    <?php if ($canEdit): ?>
                                        <a href="edit.php?id=<?= $ev['id'] ?>" class="btn btn-secondary btn-sm" style="padding:2px 6px; font-size:0.75rem; color:#fbbf24;" title="Edit Evaluasi">Edit Raport</a>
                                    <?php endif; ?>
                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </div>

    <!-- PAGINATION CONTROLS (10 DATA PER HALAMAN) -->
    <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding-top:0.75rem; border-top:1px solid var(--border-glass); flex-wrap:wrap; gap:0.5rem; font-size:0.82rem;">
            <div style="color:var(--text-muted);">
                Menampilkan <strong><?= count($displayList) ?></strong> dari total <strong><?= $totalRaport ?></strong> data raport (Halaman <?= $page ?> dari <?= $totalPages ?>)
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

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
