<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

requireAuth();
$pdo = getPdo();
$user = getAuthUser();
$role = $user['role'] ?? 'admin';

// Check tournament ID parameter
$tourneyId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tourneyId) {
    header("Location: index.php");
    exit;
}

// Fetch Tournament Details
$stmtT = $pdo->prepare("SELECT * FROM turnamen WHERE id = ?");
$stmtT->execute([$tourneyId]);
$tournament = $stmtT->fetch();

if (!$tournament) {
    header("Location: index.php");
    exit;
}

$pageTitle = "Kelola Atlet: " . $tournament['nama_turnamen'];
$successMsg = '';
$errorMsg = '';

// ==========================================
// BACKEND POST ACTIONS (CRUD PER TURNAMEN)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role === 'admin' || $role === 'pelatih')) {

    // 1. SAVE (INSERT OR UPDATE) ATLET STATS IN THIS TOURNAMENT
    if (isset($_POST['action']) && $_POST['action'] === 'save_player_stat') {
        $stat_id = isset($_POST['stat_id']) && $_POST['stat_id'] !== '' ? (int)$_POST['stat_id'] : null;
        $atlet_id = (int)$_POST['atlet_id'];
        $main = (int)($_POST['main'] ?? 0);
        $gol = (int)($_POST['gol'] ?? 0);
        $assist = (int)($_POST['assist'] ?? 0);
        $kartu_kuning = (int)($_POST['kartu_kuning'] ?? 0);
        $kartu_merah = (int)($_POST['kartu_merah'] ?? 0);

        if ($atlet_id) {
            if ($stat_id) {
                // Update existing stat entry
                $stmt = $pdo->prepare("UPDATE statistik_pertandingan SET atlet_id = ?, main = ?, gol = ?, assist = ?, kartu_kuning = ?, kartu_merah = ? WHERE id = ? AND turnamen_id = ?");
                $stmt->execute([$atlet_id, $main, $gol, $assist, $kartu_kuning, $kartu_merah, $stat_id, $tourneyId]);
                $successMsg = "Statistik atlet berhasil diperbarui!";
            } else {
                // Check if entry already exists for this athlete in this tournament
                $check = $pdo->prepare("SELECT id FROM statistik_pertandingan WHERE atlet_id = ? AND turnamen_id = ?");
                $check->execute([$atlet_id, $tourneyId]);
                $existing = $check->fetchColumn();

                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE statistik_pertandingan SET main = main + ?, gol = gol + ?, assist = assist + ?, kartu_kuning = kartu_kuning + ?, kartu_merah = kartu_merah + ? WHERE id = ?");
                    $stmt->execute([$main, $gol, $assist, $kartu_kuning, $kartu_merah, $existing]);
                    $successMsg = "Statistik atlet yang sudah ada berhasil ditambahkan!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO statistik_pertandingan (atlet_id, turnamen_id, main, gol, assist, kartu_kuning, kartu_merah) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$atlet_id, $tourneyId, $main, $gol, $assist, $kartu_kuning, $kartu_merah]);
                    $successMsg = "Atlet berhasil ditambahkan ke turnamen ini!";
                }
            }
        }
    }

    // 2. DELETE ATLET STATS FROM THIS TOURNAMENT
    if (isset($_POST['action']) && $_POST['action'] === 'delete_player_stat') {
        $stat_id = (int)$_POST['stat_id'];
        if ($stat_id) {
            $stmt = $pdo->prepare("DELETE FROM statistik_pertandingan WHERE id = ? AND turnamen_id = ?");
            $stmt->execute([$stat_id, $tourneyId]);
            $successMsg = "Atlet berhasil dihapus dari turnamen ini!";
        }
    }
}

// ==========================================
// FETCH DATA FOR THIS TOURNAMENT
// ==========================================
$playerStats = $pdo->prepare("
    SELECT s.*, a.nama_lengkap, a.kelompok_usia, a.posisi_utama 
    FROM statistik_pertandingan s 
    JOIN atlet a ON s.atlet_id = a.id 
    WHERE s.turnamen_id = ?
    ORDER BY s.gol DESC, s.assist DESC, a.nama_lengkap ASC
");
$playerStats->execute([$tourneyId]);
$atletInTourney = $playerStats->fetchAll();

// List of all active athletes for dropdown select
$atletList = $pdo->query("SELECT id, nama_lengkap, kelompok_usia, posisi_utama FROM atlet WHERE status_keanggotaan = 'Aktif' ORDER BY nama_lengkap ASC")->fetchAll();

$totalGoalsInTourney = array_sum(array_column($atletInTourney, 'gol'));
$totalAssistsInTourney = array_sum(array_column($atletInTourney, 'assist'));

include_once __DIR__ . '/../includes/header.php';
?>

<!-- BREADCRUMB & BACK LINK -->
<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
    <a href="index.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        Kembali ke Daftar Turnamen
    </a>

    <a href="pemain.php?turnamen_id=<?= $tourneyId ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8; border-color: rgba(56, 189, 248, 0.4);">
        🎴 Tampilan Kartu Atlet Turnamen Ini
    </a>
</div>

<!-- ALERT NOTIFICATION -->
<?php if ($successMsg): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 1rem 1.25rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.2rem;">✨</span>
            <span style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#34d399; cursor:pointer; font-size:1.2rem;">&times;</button>
    </div>
<?php endif; ?>

<!-- TOURNAMENT HEADER SUMMARY -->
<div class="card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%); border: 1px solid var(--border-glass); margin-bottom: 2rem; padding: 2rem; position: relative; overflow: hidden;">
    <div style="position: absolute; right: -30px; top: -30px; width: 260px; height: 260px; background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem; position: relative; z-index: 2;">
        <div>
            <?php
            $pencapaian = htmlspecialchars($tournament['pencapaian'] ?: 'Peserta');
            $badgeClass = 'badge-primary';
            if (strripos($pencapaian, 'juara 1') !== false || strripos($pencapaian, 'juara i') !== false) $badgeClass = 'badge-amber';
            elseif (strripos($pencapaian, 'juara 2') !== false || strripos($pencapaian, 'runner') !== false) $badgeClass = 'badge-cyan';
            elseif (strripos($pencapaian, 'juara 3') !== false || strripos($pencapaian, 'semifinal') !== false) $badgeClass = 'badge-rose';
            ?>
            <span class="badge <?= $badgeClass ?>" style="font-size: 0.8rem; padding: 4px 12px; margin-bottom: 0.75rem; display: inline-block;">
                🏆 <?= $pencapaian ?>
            </span>
            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 0.5rem;">
                ⚙️ Kelola Pemain: <?= htmlspecialchars($tournament['nama_turnamen']) ?>
            </h1>
            <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem;">
                <span>📍 <strong><?= htmlspecialchars($tournament['lokasi'] ?: 'Makassar') ?></strong></span>
                <span>🗓️ <?= date('d M Y', strtotime($tournament['tanggal_mulai'])) ?></span>
                <span>👥 <strong><?= count($atletInTourney) ?> Pemain Terdaftar</strong></span>
                <span>⚽ <strong><?= $totalGoalsInTourney ?> Gol Total</strong></span>
            </div>
        </div>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 1.5fr; align-items: start;">

    <!-- FORM INPUT / EDIT ATLET KE TURNAMEN INI -->
    <?php if ($role === 'admin' || $role === 'pelatih'): ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title" id="formTitle">➕ Tambah Atlet ke Turnamen Ini</h2>
            </div>

            <form method="POST" id="formAtletStat">
                <input type="hidden" name="action" value="save_player_stat">
                <input type="hidden" name="stat_id" id="form_stat_id" value="">

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label>Pilih Atlet / Siswa *</label>
                    <select name="atlet_id" id="form_atlet_id" class="form-control" required>
                        <option value="">-- Pilih Atlet --</option>
                        <?php foreach ($atletList as $a): ?>
                            <option value="<?= $a['id'] ?>">
                                <?= htmlspecialchars($a['nama_lengkap']) ?> (<?= htmlspecialchars($a['kelompok_usia']) ?> - <?= htmlspecialchars($a['posisi_utama']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grid" style="margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label>Jumlah Main (Match) *</label>
                        <input type="number" name="main" id="form_main" class="form-control" min="0" value="1" required>
                    </div>
                    <div class="form-group">
                        <label>Jumlah Gol ⚽ *</label>
                        <input type="number" name="gol" id="form_gol" class="form-control" min="0" value="0" required>
                    </div>
                    <div class="form-group">
                        <label>Jumlah Assist 🎯 *</label>
                        <input type="number" name="assist" id="form_assist" class="form-control" min="0" value="0" required>
                    </div>
                </div>

                <div class="form-grid" style="margin-bottom: 1.5rem;">
                    <div class="form-group">
                        <label>Kartu Kuning 🟨</label>
                        <input type="number" name="kartu_kuning" id="form_kartu_kuning" class="form-control" min="0" value="0">
                    </div>
                    <div class="form-group">
                        <label>Kartu Merah 🟥</label>
                        <input type="number" name="kartu_merah" id="form_kartu_merah" class="form-control" min="0" value="0">
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="resetForm()" id="btnReset" class="btn btn-secondary" style="display: none;">
                        Batal Edit
                    </button>
                    <button type="submit" id="btnSubmit" class="btn btn-primary">
                        Simpan Statistik Atlet
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- TABEL ATLET TERDAFTAR DALAM TURNAMEN INI -->
    <div class="card" style="<?= ($role !== 'admin' && $role !== 'pelatih') ? 'grid-column: 1 / -1;' : '' ?>">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h2 class="card-title">👥 Daftar Pemain di Turnamen Ini</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);"><?= count($atletInTourney) ?> Atlet Tercatat</span>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>Nama Atlet</th>
                        <th style="text-align: center;">Main</th>
                        <th style="text-align: center;">Gol</th>
                        <th style="text-align: center;">Assist</th>
                        <th style="text-align: center;">Kartu</th>
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                            <th style="text-align: right;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($atletInTourney)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                Belum ada atlet terdaftar di turnamen ini. Gunakan form di sebelah untuk menambahkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($atletInTourney as $ps): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--text-muted); font-size: 0.85rem;"><?= $no++ ?></td>
                                <td>
                                    <strong style="color: #fff; font-size: 0.95rem;"><?= htmlspecialchars($ps['nama_lengkap']) ?></strong><br>
                                    <div style="display: flex; gap: 4px; margin-top: 2px;">
                                        <span class="badge badge-primary" style="font-size: 0.65rem;"><?= htmlspecialchars($ps['kelompok_usia']) ?></span>
                                        <span style="font-size: 0.7rem; color: var(--text-muted);"><?= htmlspecialchars($ps['posisi_utama']) ?></span>
                                    </div>
                                </td>
                                <td style="text-align: center; font-weight: 600;"><?= $ps['main'] ?></td>
                                <td style="text-align: center;">
                                    <strong style="color: #22c55e; font-size: 1rem;"><?= $ps['gol'] ?> ⚽</strong>
                                </td>
                                <td style="text-align: center;">
                                    <strong style="color: #38bdf8; font-size: 1rem;"><?= $ps['assist'] ?> 🎯</strong>
                                </td>
                                <td style="text-align: center; font-size: 0.78rem;">
                                    <?php if ($ps['kartu_kuning'] > 0): ?>🟨<?= $ps['kartu_kuning'] ?><?php endif; ?>
                                    <?php if ($ps['kartu_merah'] > 0): ?>🟥<?= $ps['kartu_merah'] ?><?php endif; ?>
                                    <?php if ($ps['kartu_kuning'] == 0 && $ps['kartu_merah'] == 0): ?>-<span style="color: var(--text-muted);"></span><?php endif; ?>
                                </td>
                                <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <button onclick="editAtletStat(<?= htmlspecialchars(json_encode($ps)) ?>)" class="btn btn-secondary btn-sm" style="padding: 4px 8px;" title="Edit Stat Atlet Ini">
                                            ✏️ Edit
                                        </button>
                                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('Hapus atlet ini dari turnamen?')">
                                            <input type="hidden" name="action" value="delete_player_stat">
                                            <input type="hidden" name="stat_id" value="<?= $ps['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" style="padding: 4px 8px; color: #f87171;" title="Hapus Atlet dari Turnamen">
                                                🗑️ Hapus
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- JAVASCRIPT FOR FORM AUTO-FILL -->
<script>
function editAtletStat(data) {
    document.getElementById('formTitle').innerText = "✏️ Edit Statistik: " + data.nama_lengkap;
    document.getElementById('form_stat_id').value = data.id;
    document.getElementById('form_atlet_id').value = data.atlet_id;
    document.getElementById('form_main').value = data.main;
    document.getElementById('form_gol').value = data.gol;
    document.getElementById('form_assist').value = data.assist;
    document.getElementById('form_kartu_kuning').value = data.kartu_kuning || 0;
    document.getElementById('form_kartu_merah').value = data.kartu_merah || 0;

    document.getElementById('btnReset').style.display = 'inline-block';
    document.getElementById('btnSubmit').innerText = 'Update Stat Atlet';

    // Scroll to form smoothly
    document.getElementById('formAtletStat').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formTitle').innerText = "➕ Tambah Atlet ke Turnamen Ini";
    document.getElementById('form_stat_id').value = "";
    document.getElementById('form_atlet_id').value = "";
    document.getElementById('form_main').value = 1;
    document.getElementById('form_gol').value = 0;
    document.getElementById('form_assist').value = 0;
    document.getElementById('form_kartu_kuning').value = 0;
    document.getElementById('form_kartu_merah').value = 0;

    document.getElementById('btnReset').style.display = 'none';
    document.getElementById('btnSubmit').innerText = 'Simpan Statistik Atlet';
}
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
