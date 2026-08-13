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

$pageTitle = "Kelola Pemain & Statistik: " . $tournament['nama_turnamen'];
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
    SELECT s.*, a.nama_lengkap, a.kelompok_usia, a.posisi_utama, a.posisi_sekunder, a.foto_profil 
    FROM statistik_pertandingan s 
    JOIN atlet a ON s.atlet_id = a.id 
    WHERE s.turnamen_id = ?
    ORDER BY s.gol DESC, s.assist DESC, a.nama_lengkap ASC
");
$playerStats->execute([$tourneyId]);
$atletInTourney = $playerStats->fetchAll();

// List of all active athletes for dropdown select
$atletList = $pdo->query("SELECT id, nama_lengkap, kelompok_usia, posisi_utama, posisi_sekunder FROM atlet WHERE status_keanggotaan = 'Aktif' ORDER BY nama_lengkap ASC")->fetchAll();

$totalGoalsInTourney = array_sum(array_column($atletInTourney, 'gol'));
$totalAssistsInTourney = array_sum(array_column($atletInTourney, 'assist'));
$totalYellowCards = array_sum(array_column($atletInTourney, 'kartu_kuning'));
$totalRedCards = array_sum(array_column($atletInTourney, 'kartu_merah'));
$totalKebobolan = (int)($tournament['kebobolan'] ?? 0);

include_once __DIR__ . '/../includes/header.php';
?>

<!-- BREADCRUMB & BACK ACTION BAR -->
<div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
    <a href="index.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
        &larr; Kembali ke Daftar Turnamen
    </a>

    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <a href="edit.php?id=<?= $tourneyId ?>" class="btn btn-secondary btn-sm">
            ✏️ Edit Turnamen
        </a>
        <a href="pemain.php?turnamen_id=<?= $tourneyId ?>" class="btn btn-secondary btn-sm" style="color: #38bdf8; border-color: rgba(56, 189, 248, 0.4);">
            🎴 Tampilan Kartu Atlet Turnamen
        </a>
    </div>
</div>

<!-- ALERT NOTIFICATION -->
<?php if ($successMsg): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399; padding: 1rem 1.25rem; border-radius: 14px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.2rem;">✨</span>
            <span style="font-weight: 600; font-size: 0.95rem;"><?= htmlspecialchars($successMsg) ?></span>
        </div>
        <button onclick="this.parentElement.remove()" style="background:none; border:none; color:#34d399; cursor:pointer; font-size:1.2rem;">&times;</button>
    </div>
<?php endif; ?>

<!-- FUT-STYLE TOURNAMENT SUMMARY HEADER CARD -->
<div class="card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.95) 0%, rgba(15, 23, 42, 0.98) 50%, rgba(17, 24, 39, 0.95) 100%); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.7); margin-bottom: 1.5rem; padding: 1.75rem; position: relative; overflow: hidden;">
    <!-- Glowing Ambient Orbs -->
    <div style="position: absolute; right: -40px; top: -40px; width: 280px; height: 280px; background: radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>
    <div style="position: absolute; left: 30%; bottom: -50px; width: 220px; height: 220px; background: radial-gradient(circle, rgba(56, 189, 248, 0.15) 0%, transparent 70%); border-radius: 50%; pointer-events: none;"></div>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; position: relative; z-index: 2;">
        <div>
            <?php
            $pencapaian = htmlspecialchars($tournament['pencapaian'] ?: 'Peserta');
            $badgeStyle = "background:rgba(99,102,241,0.15); border:1px solid rgba(99,102,241,0.3); color:#818cf8;";
            if (strripos($pencapaian, 'juara 1') !== false || strripos($pencapaian, 'juara i') !== false) {
                $badgeStyle = "background:rgba(251,191,36,0.2); border:1px solid #fbbf24; color:#fbbf24;";
            } elseif (strripos($pencapaian, 'juara 2') !== false || strripos($pencapaian, 'runner') !== false) {
                $badgeStyle = "background:rgba(203,213,225,0.2); border:1px solid #cbd5e1; color:#cbd5e1;";
            } elseif (strripos($pencapaian, 'juara 3') !== false || strripos($pencapaian, 'semifinal') !== false) {
                $badgeStyle = "background:rgba(249,115,22,0.2); border:1px solid #f97316; color:#f97316;";
            }
            ?>
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 0.75rem; flex-wrap: wrap;">
                <span style="<?= $badgeStyle ?> font-size: 0.75rem; padding: 4px 12px; border-radius: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                    🏆 <?= $pencapaian ?>
                </span>
                <span class="badge badge-primary" style="font-weight: 700; border: 1px solid rgba(99,102,241,0.4);">
                    KATEGORI <?= htmlspecialchars($tournament['kelompok_usia'] ?: 'Semua KU') ?>
                </span>
            </div>

            <h1 style="font-family: 'Outfit', sans-serif; font-size: 2.1rem; font-weight: 800; color: #fff; line-height: 1.2; margin-bottom: 0.5rem; letter-spacing: -0.5px;">
                ⚙️ Kelola Atlet: <?= htmlspecialchars($tournament['nama_turnamen']) ?>
            </h1>

            <div style="display: flex; gap: 14px; flex-wrap: wrap; font-size: 0.88rem; color: #cbd5e1; margin-top: 0.5rem;">
                <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.06); padding: 4px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                    📍 <strong><?= htmlspecialchars($tournament['lokasi'] ?: 'Makassar') ?></strong>
                </span>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: rgba(255,255,255,0.06); padding: 4px 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1);">
                    🗓️ <?= date('d M Y', strtotime($tournament['tanggal_mulai'])) ?>
                    <?php if ($tournament['tanggal_selesai'] && $tournament['tanggal_selesai'] !== $tournament['tanggal_mulai']): ?>
                        s/d <?= date('d M Y', strtotime($tournament['tanggal_selesai'])) ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- METRICS SUMMARY TILES GRID -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
    <!-- TILE 1: TOTAL PEMAIN -->
    <div style="background: rgba(15,23,42,0.65); border: 1px solid rgba(99,102,241,0.3); padding: 1rem 1.1rem; border-radius: 14px; border-left: 4px solid #818cf8;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 0.72rem; color: #818cf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">👥 Skuad Terdaftar</span>
            <span style="font-size: 0.9rem;">⚽</span>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: #fff;">
            <?= count($atletInTourney) ?> <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Atlet</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
            Tercatat dalam turnamen ini
        </div>
    </div>

    <!-- TILE 2: TOTAL GOL -->
    <div style="background: rgba(15,23,42,0.65); border: 1px solid rgba(52,211,153,0.3); padding: 1rem 1.1rem; border-radius: 14px; border-left: 4px solid #34d399;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 0.72rem; color: #34d399; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">⚽ Total Gol Tim</span>
            <span style="font-size: 0.9rem;">🔥</span>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: #34d399;">
            <?= $totalGoalsInTourney ?> <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Gol</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
            Diciptakan sepanjang turnamen
        </div>
    </div>

    <!-- TILE 2B: TOTAL KEBOBOLAN -->
    <div style="background: rgba(15,23,42,0.65); border: 1px solid rgba(251,146,60,0.3); padding: 1rem 1.1rem; border-radius: 14px; border-left: 4px solid #fb923c;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 0.72rem; color: #fb923c; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">🥅 Total Kebobolan</span>
            <span style="font-size: 0.9rem;">🛡️</span>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: #fb923c;">
            <?= $totalKebobolan ?> <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Gol</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
            Gol yang diterima tim
        </div>
    </div>

    <!-- TILE 3: TOTAL ASSIST -->
    <div style="background: rgba(15,23,42,0.65); border: 1px solid rgba(56,189,248,0.3); padding: 1rem 1.1rem; border-radius: 14px; border-left: 4px solid #38bdf8;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 0.72rem; color: #38bdf8; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">🎯 Total Assist</span>
            <span style="font-size: 0.9rem;">👟</span>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: #38bdf8;">
            <?= $totalAssistsInTourney ?> <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Assist</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
            Umpan kunci berbuah gol
        </div>
    </div>

    <!-- TILE 4: PELANGGARAN KARTU -->
    <div style="background: rgba(15,23,42,0.65); border: 1px solid rgba(244,63,94,0.3); padding: 1rem 1.1rem; border-radius: 14px; border-left: 4px solid #f87171;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <span style="font-size: 0.72rem; color: #f87171; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">🟨🟥 Catatan Kartu</span>
            <span style="font-size: 0.9rem;">⚠️</span>
        </div>
        <div style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: #fff;">
            <span style="color: #fbbf24;"><?= $totalYellowCards ?> 🟨</span> &bull; <span style="color: #f87171;"><?= $totalRedCards ?> 🟥</span>
        </div>
        <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 2px;">
            Total kedisiplinan pertandingan
        </div>
    </div>
</div>

<div style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- FORM INPUT / EDIT ATLET KE TURNAMEN INI -->
    <?php if ($role === 'admin' || $role === 'pelatih'): ?>
        <div class="card" style="padding: 1.5rem;">
            <div class="card-header" style="border-bottom: 1px solid var(--border-glass); padding-bottom: 0.85rem; margin-bottom: 1.25rem;">
                <h2 class="card-title" id="formTitle" style="font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    ➕ Tambah / Edit Statistik Atlet
                </h2>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Input data partisipasi, gol, assist & kartu atlet di turnamen ini</p>
            </div>

            <form method="POST" id="formAtletStat">
                <input type="hidden" name="action" value="save_player_stat">
                <input type="hidden" name="stat_id" id="form_stat_id" value="">

                <!-- ATHLETE SELECTOR -->
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.82rem; font-weight: 700; color: #cbd5e1; display: block; margin-bottom: 4px;">
                        Pilih Atlet / Siswa *
                    </label>
                    <select name="atlet_id" id="form_atlet_id" class="form-control" style="font-size: 0.85rem; font-weight: 700;" required>
                        <option value="">-- Pilih Atlet --</option>
                        <?php foreach ($atletList as $a): ?>
                            <option value="<?= $a['id'] ?>">
                                <?= htmlspecialchars($a['nama_lengkap']) ?> (KU <?= htmlspecialchars($a['kelompok_usia']) ?> &bull; <?= htmlspecialchars($a['posisi_utama']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- MAIN, GOL, ASSIST STEPPER INPUTS -->
                <div class="form-grid" style="margin-bottom: 1.25rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #cbd5e1; display: block; margin-bottom: 4px;">
                            🏃 Main (Match) *
                        </label>
                        <input type="number" name="main" id="form_main" class="form-control" min="0" value="1" style="font-weight: 800; font-size: 1rem; text-align: center;" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #34d399; display: block; margin-bottom: 4px;">
                            ⚽ Gol *
                        </label>
                        <input type="number" name="gol" id="form_gol" class="form-control" min="0" value="0" style="font-weight: 800; font-size: 1rem; text-align: center; color: #34d399;" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: #38bdf8; display: block; margin-bottom: 4px;">
                            🎯 Assist *
                        </label>
                        <input type="number" name="assist" id="form_assist" class="form-control" min="0" value="0" style="font-weight: 800; font-size: 1rem; text-align: center; color: #38bdf8;" required>
                    </div>
                </div>

                <!-- QUICK PRESET ADD CHIPS -->
                <div style="background: rgba(15,23,42,0.5); padding: 0.75rem; border-radius: 12px; border: 1px solid var(--border-glass); margin-bottom: 1.25rem;">
                    <div style="font-size: 0.7rem; font-weight: 700; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.4rem;">
                        ⚡ Tombol Cepat Tambah Stat:
                    </div>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 2px 8px;" onclick="adjustVal('form_gol', 1)">+1 Gol ⚽</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 2px 8px;" onclick="adjustVal('form_assist', 1)">+1 Assist 🎯</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 2px 8px;" onclick="adjustVal('form_main', 1)">+1 Main 🏃</button>
                        <button type="button" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 2px 8px;" onclick="adjustVal('form_kartu_kuning', 1)">+1 Kuning 🟨</button>
                    </div>
                </div>

                <!-- KARTU KUNING & KARTU MERAH -->
                <div class="form-grid" style="margin-bottom: 1.5rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div class="form-group" style="margin:0;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: #fbbf24; display: block; margin-bottom: 4px;">
                            🟨 Kartu Kuning
                        </label>
                        <input type="number" name="kartu_kuning" id="form_kartu_kuning" class="form-control" min="0" value="0" style="font-weight: 800; font-size: 0.95rem; text-align: center; color: #fbbf24;">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: #f87171; display: block; margin-bottom: 4px;">
                            🟥 Kartu Merah
                        </label>
                        <input type="number" name="kartu_merah" id="form_kartu_merah" class="form-control" min="0" value="0" style="font-weight: 800; font-size: 0.95rem; text-align: center; color: #f87171;">
                    </div>
                </div>

                <!-- FORM BUTTONS -->
                <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center;">
                    <button type="button" onclick="resetForm()" id="btnReset" class="btn btn-secondary" style="display: none; padding: 0.55rem 1rem;">
                        Batal Edit
                    </button>
                    <button type="submit" id="btnSubmit" class="btn btn-primary" style="padding: 0.55rem 1.4rem; font-weight: 700; box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);">
                        💾 Simpan Statistik Atlet
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- TABEL ATLET TERDAFTAR DALAM TURNAMEN INI -->
    <div class="card" style="padding: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.85rem; margin-bottom: 1.25rem;">
            <div>
                <h2 class="card-title" style="font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    👥 Skuad Pemain & Statistik Laga
                </h2>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Daftar partisipasi atlet SSB Tamalanrea di turnamen ini</p>
            </div>

            <!-- LIVE SEARCH INSIDE TABLE -->
            <div style="position: relative; width: 210px;">
                <input type="text" id="playerTableSearch" class="form-control" placeholder="Cari nama atlet..." style="padding-left: 32px; font-size: 0.8rem; height: 36px; border-radius: 10px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="position: absolute; left: 10px; top: 11px; color: var(--text-muted);"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
            </div>
        </div>

        <div style="width: 100%; overflow: hidden; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.12);">
            <table class="data-table" style="table-layout: auto; width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.84rem;">
                <thead>
                    <tr style="background: rgba(15, 23, 42, 0.95);">
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; width: 35px;">#</th>
                        <th style="padding: 10px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1);">Nama Atlet & Posisi</th>
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Main</th>
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Gol</th>
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Assist</th>
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Kartu</th>
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                            <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); text-align: center; white-space: nowrap;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="playerTableBody">
                    <?php if (empty($atletInTourney)): ?>
                        <tr>
                            <td colspan="<?= ($role === 'admin' || $role === 'pelatih') ? 7 : 6 ?>" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
                                Belum ada atlet terdaftar di turnamen ini. Gunakan form di sebelah untuk menambahkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $no = 1; 
                        foreach ($atletInTourney as $ps): 
                            $photoPath = __DIR__ . '/../assets/img/atlet/' . ($ps['foto_profil'] ?? '');
                            $hasPhoto = !empty($ps['foto_profil']) && $ps['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);
                        ?>
                            <tr class="player-row" style="border-bottom: 1px solid rgba(255, 255, 255, 0.08); transition: background 0.2s;">
                                <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; color: var(--text-muted); font-weight: 600;">
                                    <?= $no++ ?>
                                </td>

                                <td style="padding: 9px 10px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08);">
                                    <div style="display: flex; align-items: center; gap: 9px;">
                                        <div style="background: #1e293b; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #818cf8; overflow: hidden; width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; font-size: 0.8rem; border: 1.5px solid rgba(99,102,241,0.3);">
                                            <?php if ($hasPhoto): ?>
                                                <img src="../assets/img/atlet/<?= htmlspecialchars($ps['foto_profil']) ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                            <?php else: ?>
                                                <?= strtoupper(substr($ps['nama_lengkap'], 0, 2)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="../atlet/detail.php?id=<?= $ps['atlet_id'] ?>" style="color: #fff; font-weight: 700; text-decoration: none; font-size: 0.86rem;" onmouseover="this.style.color='#38bdf8'" onmouseout="this.style.color='#fff'">
                                                <?= htmlspecialchars($ps['nama_lengkap']) ?>
                                            </a>
                                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 600; margin-top: 1px;">
                                                <span class="badge badge-primary" style="font-size: 0.65rem; padding: 1px 6px; border: 1px solid rgba(99,102,241,0.3);"><?= htmlspecialchars($ps['kelompok_usia']) ?></span>
                                                &bull; ⚽ <?= htmlspecialchars($ps['posisi_utama'] ?: '-') ?>
                                                <?php if (!empty($ps['posisi_sekunder']) && $ps['posisi_sekunder'] !== '-'): ?>
                                                    &bull; <span style="color: #7dd3fc;">🔄 <?= htmlspecialchars($ps['posisi_sekunder']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; font-weight: 700; color: #cbd5e1;">
                                    <?= $ps['main'] ?> Laga
                                </td>

                                <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                    <?php if ($ps['gol'] > 0): ?>
                                        <span class="badge badge-emerald" style="font-size: 0.78rem; font-weight: 800; padding: 2px 8px; border: 1px solid rgba(52,211,153,0.4);">
                                            <?= $ps['gol'] ?> ⚽
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.82rem;">0</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                    <?php if ($ps['assist'] > 0): ?>
                                        <span class="badge badge-cyan" style="font-size: 0.78rem; font-weight: 800; padding: 2px 8px; border: 1px solid rgba(56,189,248,0.4);">
                                            <?= $ps['assist'] ?> 🎯
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.82rem;">0</span>
                                    <?php endif; ?>
                                </td>

                                <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center; font-size: 0.78rem;">
                                    <?php if ($ps['kartu_kuning'] > 0): ?>
                                        <span style="background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); padding: 1px 6px; border-radius: 6px; font-weight: 800; margin-right: 2px;">
                                            🟨 <?= $ps['kartu_kuning'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($ps['kartu_merah'] > 0): ?>
                                        <span style="background: rgba(244,63,94,0.15); color: #f87171; border: 1px solid rgba(244,63,94,0.3); padding: 1px 6px; border-radius: 6px; font-weight: 800;">
                                            🟥 <?= $ps['kartu_merah'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($ps['kartu_kuning'] == 0 && $ps['kartu_merah'] == 0): ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>

                                <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                                    <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: center; white-space: nowrap;">
                                        <div style="display: flex; gap: 4px; justify-content: center; align-items: center;">
                                            <button onclick="editAtletStat(<?= htmlspecialchars(json_encode($ps)) ?>)" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 2px 7px; color: #38bdf8;" title="Edit Stat Atlet Ini">
                                                ✏️ Edit
                                            </button>
                                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('Hapus <?= htmlspecialchars(addslashes($ps['nama_lengkap'])) ?> dari turnamen ini?')">
                                                <input type="hidden" name="action" value="delete_player_stat">
                                                <input type="hidden" name="stat_id" value="<?= $ps['id'] ?>">
                                                <button type="submit" class="btn btn-secondary btn-sm" style="font-size: 0.72rem; padding: 2px 7px; color: #f87171;" title="Hapus Atlet dari Turnamen">
                                                    🗑️ Hapus
                                                </button>
                                            </form>
                                        </div>
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

<!-- JAVASCRIPT FOR FORM AUTO-FILL & INTERACTIVE HELPERS -->
<script>
function editAtletStat(data) {
    document.getElementById('formTitle').innerHTML = "✏️ Edit Statistik: <span style='color:#38bdf8;'>" + data.nama_lengkap + "</span>";
    document.getElementById('form_stat_id').value = data.id;
    document.getElementById('form_atlet_id').value = data.atlet_id;
    document.getElementById('form_main').value = data.main;
    document.getElementById('form_gol').value = data.gol;
    document.getElementById('form_assist').value = data.assist;
    document.getElementById('form_kartu_kuning').value = data.kartu_kuning || 0;
    document.getElementById('form_kartu_merah').value = data.kartu_merah || 0;

    document.getElementById('btnReset').style.display = 'inline-block';
    document.getElementById('btnSubmit').innerText = '💾 Update Stat Atlet';

    // Scroll smoothly to form
    document.getElementById('formAtletStat').scrollIntoView({ behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('formTitle').innerText = "➕ Tambah / Edit Statistik Atlet";
    document.getElementById('form_stat_id').value = "";
    document.getElementById('form_atlet_id').value = "";
    document.getElementById('form_main').value = 1;
    document.getElementById('form_gol').value = 0;
    document.getElementById('form_assist').value = 0;
    document.getElementById('form_kartu_kuning').value = 0;
    document.getElementById('form_kartu_merah').value = 0;

    document.getElementById('btnReset').style.display = 'none';
    document.getElementById('btnSubmit').innerText = '💾 Simpan Statistik Atlet';
}

function adjustVal(fieldId, delta) {
    const el = document.getElementById(fieldId);
    if (el) {
        let cur = parseInt(el.value) || 0;
        el.value = Math.max(0, cur + delta);
    }
}

// Live search inside table
document.getElementById('playerTableSearch')?.addEventListener('keyup', function() {
    let filter = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll('.player-row');
    rows.forEach(r => {
        let text = r.textContent.toLowerCase();
        r.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
