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
        $kebobolan = (int)($_POST['kebobolan'] ?? 0);

        if ($atlet_id) {
            if ($stat_id) {
                // Update existing stat entry
                $stmt = $pdo->prepare("UPDATE statistik_pertandingan SET atlet_id = ?, main = ?, gol = ?, assist = ?, kartu_kuning = ?, kartu_merah = ?, kebobolan = ? WHERE id = ? AND turnamen_id = ?");
                $stmt->execute([$atlet_id, $main, $gol, $assist, $kartu_kuning, $kartu_merah, $kebobolan, $stat_id, $tourneyId]);
                $successMsg = "Statistik atlet berhasil diperbarui!";
            } else {
                // Check if entry already exists for this athlete in this tournament
                $check = $pdo->prepare("SELECT id FROM statistik_pertandingan WHERE atlet_id = ? AND turnamen_id = ?");
                $check->execute([$atlet_id, $tourneyId]);
                $existing = $check->fetchColumn();

                if ($existing) {
                    $stmt = $pdo->prepare("UPDATE statistik_pertandingan SET main = main + ?, gol = gol + ?, assist = assist + ?, kartu_kuning = kartu_kuning + ?, kartu_merah = kartu_merah + ?, kebobolan = kebobolan + ? WHERE id = ?");
                    $stmt->execute([$main, $gol, $assist, $kartu_kuning, $kartu_merah, $kebobolan, $existing]);
                    $successMsg = "Statistik atlet yang sudah ada berhasil ditambahkan!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO statistik_pertandingan (atlet_id, turnamen_id, main, gol, assist, kartu_kuning, kartu_merah, kebobolan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$atlet_id, $tourneyId, $main, $gol, $assist, $kartu_kuning, $kartu_merah, $kebobolan]);
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
// AUTO-MIGRATE: TABEL RIWAYAT LAGA & GALERI FOTO
// ==========================================
try { $pdo->exec("CREATE TABLE IF NOT EXISTS `turnamen_match` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `turnamen_id` int(11) NOT NULL,
    `tanggal` date DEFAULT NULL,
    `lawan` varchar(100) NOT NULL,
    `skor_kita` int(11) NOT NULL DEFAULT 0,
    `skor_lawan` int(11) NOT NULL DEFAULT 0,
    `keterangan` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `turnamen_id` (`turnamen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (PDOException $e) {}

try { $pdo->exec("CREATE TABLE IF NOT EXISTS `turnamen_foto` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `turnamen_id` int(11) NOT NULL,
    `nama_file` varchar(255) NOT NULL,
    `caption` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `turnamen_id` (`turnamen_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (PDOException $e) {}

try { $pdo->exec("CREATE TABLE IF NOT EXISTS `turnamen_match_event` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `match_id` int(11) NOT NULL,
    `turnamen_id` int(11) NOT NULL,
    `atlet_id` int(11) NOT NULL,
    `type` enum('gol','kebobolan') NOT NULL DEFAULT 'gol',
    `created_at` datetime DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `match_id` (`match_id`),
    KEY `atlet_id` (`atlet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (PDOException $e) {}

// ==========================================
// BACKEND POST: MATCH HISTORY
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role === 'admin' || $role === 'pelatih')) {

    if (isset($_POST['action']) && $_POST['action'] === 'save_match') {
        $match_id   = isset($_POST['match_id']) && $_POST['match_id'] !== '' ? (int)$_POST['match_id'] : null;
        $lawan      = trim($_POST['lawan'] ?? '');
        $tanggal    = $_POST['tanggal_match'] ?? null;
        $skor_kita  = (int)($_POST['skor_kita'] ?? 0);
        $skor_lawan = (int)($_POST['skor_lawan'] ?? 0);
        $ket        = trim($_POST['keterangan_match'] ?? '');
        // Hanya ambil atlet_id yang ada di POST (bisa ada nilai 0/kosong = tidak dipilih)
        $golAtlet = array_values(array_filter(array_map('intval', $_POST['pencetak_gol'] ?? []), fn($v) => $v > 0));
        $kbAtlet  = array_values(array_filter(array_map('intval', $_POST['kebobolan_atlet'] ?? []), fn($v) => $v > 0));

        // Ambil set atlet yang SUDAH terdaftar di turnamen ini (whitelist)
        $stmtReg = $pdo->prepare("SELECT atlet_id FROM statistik_pertandingan WHERE turnamen_id = ?");
        $stmtReg->execute([$tourneyId]);
        $registeredIds = array_column($stmtReg->fetchAll(), 'atlet_id');
        // Filter: hanya atlet yang sudah terdaftar yang boleh dicatat event-nya
        $golAtlet = array_values(array_filter($golAtlet, fn($id) => in_array($id, $registeredIds)));
        $kbAtlet  = array_values(array_filter($kbAtlet,  fn($id) => in_array($id, $registeredIds)));

        // ---- VALIDASI: wajib pilih atlet jika ada skor ----
        $validasiOk = true;
        if ($skor_kita > 0 && count($golAtlet) < $skor_kita) {
            $errorMsg   = "Wajib memilih pencetak gol untuk semua $skor_kita gol yang dicetak!";
            $validasiOk = false;
        }
        if ($skor_lawan > 0 && count($kbAtlet) < $skor_lawan) {
            $errorMsg   = isset($errorMsg) ? $errorMsg . " Dan wajib memilih atlet untuk semua $skor_lawan gol kebobolan!" : "Wajib memilih atlet yang kebobolan untuk semua $skor_lawan gol yang masuk!";
            $validasiOk = false;
        }
        if (!$validasiOk) { /* skip, errorMsg sudah di-set */ }

        if ($lawan !== '') {
            if (!$validasiOk) {
                // Validasi gagal — skip proses simpan, pesan error sudah di-set
            } elseif ($match_id) {
                // Hapus events lama untuk match ini
                $pdo->prepare("DELETE FROM turnamen_match_event WHERE match_id=? AND turnamen_id=?")->execute([$match_id, $tourneyId]);
                // Update match record
                $pdo->prepare("UPDATE turnamen_match SET lawan=?, tanggal=?, skor_kita=?, skor_lawan=?, keterangan=? WHERE id=? AND turnamen_id=?")
                    ->execute([$lawan, $tanggal ?: null, $skor_kita, $skor_lawan, $ket, $match_id, $tourneyId]);
                $newMatchId = $match_id;
                $successMsg = "Riwayat laga berhasil diperbarui!";
            } else {
                $pdo->prepare("INSERT INTO turnamen_match (turnamen_id, tanggal, lawan, skor_kita, skor_lawan, keterangan) VALUES (?,?,?,?,?,?)")
                    ->execute([$tourneyId, $tanggal ?: null, $lawan, $skor_kita, $skor_lawan, $ket]);
                $newMatchId = $pdo->lastInsertId();
                $successMsg = "Riwayat laga berhasil ditambahkan!";
            }
            // Simpan events baru (hanya atlet terdaftar)
            $evStmt = $pdo->prepare("INSERT INTO turnamen_match_event (match_id, turnamen_id, atlet_id, type) VALUES (?,?,?,?)");
            foreach (array_slice($golAtlet, 0, $skor_kita) as $aid) {
                $evStmt->execute([$newMatchId, $tourneyId, $aid, 'gol']);
            }
            foreach (array_slice($kbAtlet, 0, $skor_lawan) as $aid) {
                $evStmt->execute([$newMatchId, $tourneyId, $aid, 'kebobolan']);
            }
            // Hitung ulang gol & kebobolan dari SEMUA events (bukan ±1, mencegah double)
            $affectedIds = array_unique(array_merge($golAtlet, $kbAtlet));
            $recalcStmt  = $pdo->prepare("
                UPDATE statistik_pertandingan SET
                    gol       = (SELECT COUNT(*) FROM turnamen_match_event WHERE atlet_id=? AND turnamen_id=? AND type='gol'),
                    kebobolan = (SELECT COUNT(*) FROM turnamen_match_event WHERE atlet_id=? AND turnamen_id=? AND type='kebobolan')
                WHERE atlet_id=? AND turnamen_id=?
            ");
            foreach ($affectedIds as $aid) {
                $recalcStmt->execute([$aid, $tourneyId, $aid, $tourneyId, $aid, $tourneyId]);
            }
        } // end if lawan
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_match') {
        $mid = (int)$_POST['match_id'];
        if ($mid) {
            // Kumpulkan atlet terdampak sebelum events dihapus
            $evRows = $pdo->prepare("SELECT DISTINCT atlet_id FROM turnamen_match_event WHERE match_id=? AND turnamen_id=?");
            $evRows->execute([$mid, $tourneyId]);
            $affectedIds = array_column($evRows->fetchAll(), 'atlet_id');
            // Hapus events & match
            $pdo->prepare("DELETE FROM turnamen_match_event WHERE match_id=? AND turnamen_id=?")->execute([$mid, $tourneyId]);
            $pdo->prepare("DELETE FROM turnamen_match WHERE id=? AND turnamen_id=?")->execute([$mid, $tourneyId]);
            // Hitung ulang dari events yang tersisa (bukan decrement)
            $recalcStmt = $pdo->prepare("
                UPDATE statistik_pertandingan SET
                    gol       = (SELECT COUNT(*) FROM turnamen_match_event WHERE atlet_id=? AND turnamen_id=? AND type='gol'),
                    kebobolan = (SELECT COUNT(*) FROM turnamen_match_event WHERE atlet_id=? AND turnamen_id=? AND type='kebobolan')
                WHERE atlet_id=? AND turnamen_id=?
            ");
            foreach ($affectedIds as $aid) {
                $recalcStmt->execute([$aid, $tourneyId, $aid, $tourneyId, $aid, $tourneyId]);
            }
            $successMsg = "Riwayat laga berhasil dihapus!";
        }
    }


    // ==========================================
    // BACKEND POST: GALERI FOTO
    // ==========================================
    if (isset($_POST['action']) && $_POST['action'] === 'upload_foto') {
        $uploadDir = __DIR__ . '/../assets/img/turnamen/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $captions = $_POST['captions'] ?? [];
        $uploaded = 0;
        if (!empty($_FILES['foto_files']['name'][0])) {
            foreach ($_FILES['foto_files']['name'] as $k => $fname) {
                if ($_FILES['foto_files']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
                $newName = 'turnamen_' . $tourneyId . '_' . time() . '_' . $k . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_files']['tmp_name'][$k], $uploadDir . $newName)) {
                    $cap = htmlspecialchars(trim($captions[$k] ?? ''));
                    $pdo->prepare("INSERT INTO turnamen_foto (turnamen_id, nama_file, caption) VALUES (?,?,?)")
                        ->execute([$tourneyId, $newName, $cap]);
                    $uploaded++;
                }
            }
        }
        $successMsg = $uploaded > 0 ? "$uploaded foto berhasil diupload!" : "Tidak ada foto yang berhasil diupload.";
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_foto') {
        $fid = (int)$_POST['foto_id'];
        if ($fid) {
            $fRow = $pdo->prepare("SELECT nama_file FROM turnamen_foto WHERE id=? AND turnamen_id=?");
            $fRow->execute([$fid, $tourneyId]);
            $fData = $fRow->fetch();
            if ($fData) {
                $fpath = __DIR__ . '/../assets/img/turnamen/' . $fData['nama_file'];
                if (file_exists($fpath)) @unlink($fpath);
                $pdo->prepare("DELETE FROM turnamen_foto WHERE id=? AND turnamen_id=?")->execute([$fid, $tourneyId]);
            }
            $successMsg = "Foto berhasil dihapus!";
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
// Auto-add kebobolan column if not exists
try { $pdo->exec("ALTER TABLE `statistik_pertandingan` ADD COLUMN `kebobolan` INT(11) NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
$playerStats->execute([$tourneyId]);
$atletInTourney = $playerStats->fetchAll();

// Fetch match history + events per match
$stmtMatch = $pdo->prepare("SELECT * FROM turnamen_match WHERE turnamen_id = ? ORDER BY tanggal ASC, id ASC");
$stmtMatch->execute([$tourneyId]);
$matchHistory = $stmtMatch->fetchAll();

// Ambil semua events sekaligus, kelompokkan per match_id
$stmtEv = $pdo->prepare("
    SELECT e.match_id, e.type, e.atlet_id, a.nama_lengkap, a.posisi_utama
    FROM turnamen_match_event e
    JOIN atlet a ON e.atlet_id = a.id
    WHERE e.turnamen_id = ?
    ORDER BY e.id ASC
");
$stmtEv->execute([$tourneyId]);
$allEvents = [];
foreach ($stmtEv->fetchAll() as $ev) {
    $allEvents[$ev['match_id']][$ev['type']][] = $ev;
}
$totalSkorKita  = array_sum(array_column($matchHistory, 'skor_kita'));
$totalSkorLawan = array_sum(array_column($matchHistory, 'skor_lawan'));
$totalMenang = 0; $totalSeri = 0; $totalKalah = 0;
foreach ($matchHistory as $m) {
    if ($m['skor_kita'] > $m['skor_lawan']) $totalMenang++;
    elseif ($m['skor_kita'] === $m['skor_lawan']) $totalSeri++;
    else $totalKalah++;
}

// Fetch galeri foto
$stmtFoto = $pdo->prepare("SELECT * FROM turnamen_foto WHERE turnamen_id = ? ORDER BY created_at DESC");
$stmtFoto->execute([$tourneyId]);
$galeri = $stmtFoto->fetchAll();

// List of all active athletes for dropdown select
$atletList = $pdo->query("SELECT id, nama_lengkap, kelompok_usia, posisi_utama, posisi_sekunder FROM atlet WHERE status_keanggotaan = 'Aktif' ORDER BY nama_lengkap ASC")->fetchAll();

$totalGoalsInTourney    = array_sum(array_column($atletInTourney, 'gol'));
$totalAssistsInTourney  = array_sum(array_column($atletInTourney, 'assist'));
$totalYellowCards       = array_sum(array_column($atletInTourney, 'kartu_kuning'));
$totalRedCards          = array_sum(array_column($atletInTourney, 'kartu_merah'));
// Kebobolan diambil dari SUM statistik atlet (single source of truth)
$totalKebobolan         = array_sum(array_column($atletInTourney, 'kebobolan'));
// Skor agregat dari history laga (untuk perbandingan relasi)
$selisihGol      = $totalGoalsInTourney - $totalSkorKita;   // positif = ada gol atlet belum dicatat di laga
$selisihKebobolan= $totalKebobolan - $totalSkorLawan;

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
        <div style="font-size: 0.68rem; color: #34d399; margin-top: 4px; display:flex; align-items:center; gap:4px;">
            <span style="background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.3); padding:1px 6px; border-radius:10px;">📊 Dari statistik atlet</span>
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
        <div style="font-size: 0.68rem; color: #fb923c; margin-top: 4px; display:flex; align-items:center; gap:4px;">
            <span style="background:rgba(251,146,60,0.15); border:1px solid rgba(251,146,60,0.3); padding:1px 6px; border-radius:10px;">📊 Dari statistik atlet</span>
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
                    <div class="form-group" style="margin:0;">
                        <label style="font-size: 0.78rem; font-weight: 700; color: #fb923c; display: block; margin-bottom: 4px;">
                            🥅 Kebobolan
                        </label>
                        <input type="number" name="kebobolan" id="form_kebobolan" class="form-control" min="0" value="0" style="font-weight: 800; font-size: 0.95rem; text-align: center; color: #fb923c;">
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
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap; color: #fb923c;">🥅 Kebobolan</th>
                        <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); border-right: 1px solid rgba(255, 255, 255, 0.1); text-align: center; white-space: nowrap;">Kartu</th>
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                            <th style="padding: 10px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.12); text-align: center; white-space: nowrap;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody id="playerTableBody">
                    <?php if (empty($atletInTourney)): ?>
                        <tr>
                            <td colspan="<?= ($role === 'admin' || $role === 'pelatih') ? 8 : 7 ?>" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">
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

                                <td style="padding: 9px 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); border-right: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">
                                    <?php $kebobolan_val = (int)($ps['kebobolan'] ?? 0); ?>
                                    <?php if ($kebobolan_val > 0): ?>
                                        <span style="background: rgba(251,146,60,0.15); color: #fb923c; border: 1px solid rgba(251,146,60,0.35); padding: 2px 8px; border-radius: 6px; font-weight: 800; font-size: 0.78rem;">
                                            🥅 <?= $kebobolan_val ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.82rem;">-</span>
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

<!-- ============================================================ -->
<!--  KOMPONEN: HISTORY & FOTO TURNAMEN                          -->
<!-- ============================================================ -->
<div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 1.5rem;">

    <!-- ---- HISTORY SKOR LAGA ---- -->
    <div class="card" style="padding: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-glass); padding-bottom: 0.85rem; margin-bottom: 1.25rem;">
            <div>
                <h2 class="card-title" style="font-size: 1.15rem; display: flex; align-items: center; gap: 8px;">
                    📋 History Skor Laga
                </h2>
                <p style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">Riwayat hasil pertandingan — skor tim bersinergi otomatis dengan statistik gol &amp; kebobolan</p>
            </div>
            <!-- Summary Badge -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <span style="background: rgba(52,211,153,0.15); border: 1px solid rgba(52,211,153,0.35); color: #34d399; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">W <?= $totalMenang ?></span>
                <span style="background: rgba(148,163,184,0.15); border: 1px solid rgba(148,163,184,0.3); color: #94a3b8; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">D <?= $totalSeri ?></span>
                <span style="background: rgba(248,113,113,0.15); border: 1px solid rgba(248,113,113,0.35); color: #f87171; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">L <?= $totalKalah ?></span>
                <span style="background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.35); color: #818cf8; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 800;">
                    ⚽ <?= $totalSkorKita ?> : <?= $totalSkorLawan ?> 🥅
                </span>
            </div>
        </div>

        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
        <!-- Form Input Laga -->
        <form method="POST" id="formMatch" style="background: rgba(15,23,42,0.5); border: 1px solid var(--border-glass); border-radius: 14px; padding: 1.1rem; margin-bottom: 1.25rem;"
              onsubmit="return validateMatchForm(event)">
            <input type="hidden" name="action" value="save_match">
            <input type="hidden" name="match_id" id="fm_match_id" value="">
            <div style="font-size: 0.75rem; font-weight: 800; color: #fbbf24; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.85rem;">➕ Tambah / Edit Riwayat Laga</div>
            <!-- Pesan error validasi backend -->
            <?php if (!empty($errorMsg)): ?>
            <div id="matchErrorBox" style="background:rgba(248,113,113,0.12); border:1px solid rgba(248,113,113,0.4); border-radius:10px; padding:0.65rem 1rem; margin-bottom:0.85rem; display:flex; align-items:center; gap:8px; font-size:0.8rem; color:#fca5a5;">
                <span style="font-size:1.1rem;">🚫</span>
                <span><?= htmlspecialchars($errorMsg) ?></span>
            </div>
            <?php endif; ?>

            <!-- Baris 1: Info Laga -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(155px, 1fr)); gap: 10px; margin-bottom: 0.85rem;">
                <div class="form-group" style="margin:0;">
                    <label style="font-size: 0.74rem; font-weight: 700; color: #cbd5e1; display:block; margin-bottom:4px;">🗓️ Tanggal Laga</label>
                    <input type="date" name="tanggal_match" id="fm_tanggal" class="form-control" style="font-size:0.85rem;">
                </div>
                <div class="form-group" style="margin:0; grid-column: span 2;">
                    <label style="font-size: 0.74rem; font-weight: 700; color: #cbd5e1; display:block; margin-bottom:4px;">🆚 Tim Lawan *</label>
                    <input type="text" name="lawan" id="fm_lawan" class="form-control" placeholder="Nama tim lawan..." style="font-size:0.85rem; font-weight:700;" required>
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size: 0.74rem; font-weight: 700; color: #34d399; display:block; margin-bottom:4px;">⚽ Gol Kita</label>
                    <input type="number" name="skor_kita" id="fm_skor_kita" class="form-control" min="0" value="0"
                        style="font-weight:800; font-size:1.1rem; text-align:center; color:#34d399;"
                        oninput="buildScorerDropdowns()">
                </div>
                <div class="form-group" style="margin:0;">
                    <label style="font-size: 0.74rem; font-weight: 700; color: #fb923c; display:block; margin-bottom:4px;">🥅 Kebobolan</label>
                    <input type="number" name="skor_lawan" id="fm_skor_lawan" class="form-control" min="0" value="0"
                        style="font-weight:800; font-size:1.1rem; text-align:center; color:#fb923c;"
                        oninput="buildScorerDropdowns()">
                </div>
                <div class="form-group" style="margin:0; grid-column: span 2;">
                    <label style="font-size: 0.74rem; font-weight: 700; color: #cbd5e1; display:block; margin-bottom:4px;">📝 Keterangan</label>
                    <input type="text" name="keterangan_match" id="fm_keterangan" class="form-control" placeholder="Contoh: Babak Grup, Final, dll..." style="font-size:0.82rem;">
                </div>
            </div>

            <!-- Area Dinamis: Pencetak Gol & Kebobolan -->
            <div id="scorerArea" style="display:none; border-top: 1px solid var(--border-glass); padding-top: 0.85rem; margin-bottom: 0.85rem;">
                <!-- Pencetak Gol -->
                <div id="golScorerArea"></div>
                <!-- Kebobolan Atlet -->
                <div id="kbScorerArea"></div>
            </div>

            <div style="display:flex; gap:8px; justify-content:flex-end; align-items:center;">
                <!-- Pesan error validasi JS inline -->
                <div id="matchErrorInline" style="display:none; font-size:0.75rem; color:#fca5a5; flex:1; display:none; align-items:center; gap:5px;">
                    <span>🚫</span><span id="matchErrorText"></span>
                </div>
                <button type="button" onclick="resetMatchForm()" id="btnMatchReset" class="btn btn-secondary" style="display:none; padding:0.5rem 0.9rem; font-size:0.82rem;">Batal Edit</button>
                <button type="submit" id="btnSimpanLaga" class="btn btn-primary" style="padding:0.5rem 1.2rem; font-size:0.82rem; font-weight:700; box-shadow:0 4px 12px rgba(99,102,241,0.4);">💾 Simpan Laga</button>
            </div>
        </form>
        <?php endif; ?>

        <!-- BLOK RELASI DATA: Atlet vs History Laga -->
        <div style="background: linear-gradient(135deg, rgba(15,23,42,0.7), rgba(30,27,75,0.5)); border: 1px solid rgba(99,102,241,0.3); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
            <div style="font-size: 0.73rem; font-weight: 800; color: #a5b4fc; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.75rem; display:flex; align-items:center; gap:6px;">
                🔗 Relasi Data: Statistik Atlet ↔ History Laga
            </div>
            <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center;">
                <!-- Kolom Kiri: Data Atlet -->
                <div style="background: rgba(15,23,42,0.6); border: 1px solid rgba(99,102,241,0.2); border-radius: 10px; padding: 0.75rem;">
                    <div style="font-size: 0.68rem; font-weight: 700; color: #818cf8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px;">📊 Dari Statistik Atlet</div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <div style="text-align:center;">
                            <div style="font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:900; color:#34d399;"><?= $totalGoalsInTourney ?></div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:-2px;">Total Gol</div>
                        </div>
                        <div style="width:1px; background:rgba(255,255,255,0.08); margin:0 4px;"></div>
                        <div style="text-align:center;">
                            <div style="font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:900; color:#fb923c;"><?= $totalKebobolan ?></div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:-2px;">Kebobolan</div>
                        </div>
                        <div style="width:1px; background:rgba(255,255,255,0.08); margin:0 4px;"></div>
                        <div style="text-align:center;">
                            <div style="font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:900; color:#38bdf8;"><?= $totalAssistsInTourney ?></div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:-2px;">Assist</div>
                        </div>
                    </div>
                    <div style="font-size:0.63rem; color:var(--text-muted); margin-top:6px;"><?= count($atletInTourney) ?> atlet terdaftar</div>
                </div>

                <!-- Panah Tengah -->
                <div style="text-align:center; padding:0 4px;">
                    <?php
                    $golMatch    = $totalGoalsInTourney === $totalSkorKita;
                    $kbMatch     = $totalKebobolan === $totalSkorLawan;
                    $bothMatch   = $golMatch && $kbMatch;
                    $statusColor = $bothMatch ? '#34d399' : '#fbbf24';
                    $statusIcon  = $bothMatch ? '✅' : '⚠️';
                    ?>
                    <div style="font-size:1.3rem;"><?= $statusIcon ?></div>
                    <div style="font-size:0.6rem; color:<?= $statusColor ?>; font-weight:700; white-space:nowrap; margin-top:2px;">
                        <?= $bothMatch ? 'Selaras' : 'Ada Selisih' ?>
                    </div>
                    <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                    <button type="button" onclick="autoSyncMatchFromAtlet()" title="Isi form laga otomatis dari data atlet" style="margin-top:6px; background:rgba(99,102,241,0.2); border:1px solid rgba(99,102,241,0.4); color:#a5b4fc; font-size:0.62rem; padding:3px 7px; border-radius:8px; cursor:pointer; white-space:nowrap; transition:all 0.2s;" onmouseover="this.style.background='rgba(99,102,241,0.4)'" onmouseout="this.style.background='rgba(99,102,241,0.2)'">
                        ⚡ Auto-isi Form
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Kolom Kanan: Data History Laga -->
                <div style="background: rgba(15,23,42,0.6); border: 1px solid rgba(251,191,36,0.2); border-radius: 10px; padding: 0.75rem;">
                    <div style="font-size: 0.68rem; font-weight: 700; color: #fbbf24; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px;">📋 Dari History Laga</div>
                    <?php if (empty($matchHistory)): ?>
                    <div style="color:var(--text-muted); font-size:0.73rem; padding:0.5rem 0;">Belum ada riwayat laga</div>
                    <?php else: ?>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <div style="text-align:center;">
                            <div style="font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:900; color:<?= $golMatch ? '#34d399' : '#fbbf24' ?>;"><?= $totalSkorKita ?></div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:-2px;">Total Gol</div>
                        </div>
                        <div style="width:1px; background:rgba(255,255,255,0.08); margin:0 4px;"></div>
                        <div style="text-align:center;">
                            <div style="font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:900; color:<?= $kbMatch ? '#fb923c' : '#fbbf24' ?>;"><?= $totalSkorLawan ?></div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:-2px;">Kebobolan</div>
                        </div>
                        <div style="width:1px; background:rgba(255,255,255,0.08); margin:0 4px;"></div>
                        <div style="text-align:center;">
                            <div style="font-family:'Outfit',sans-serif; font-size:1.5rem; font-weight:900; color:#94a3b8;"><?= count($matchHistory) ?></div>
                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:-2px;">Laga</div>
                        </div>
                    </div>
                    <?php if (!$bothMatch): ?>
                    <div style="margin-top:6px; font-size:0.63rem; color:#fbbf24;">
                        <?= !$golMatch ? "⚠ Gol selisih ".abs($selisihGol).' ' : '' ?>
                        <?= !$kbMatch  ? "⚠ Kebobolan selisih ".abs($selisihKebobolan) : '' ?>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                    <div style="font-size:0.63rem; color:var(--text-muted); margin-top:6px;"><?= $totalMenang ?>M · <?= $totalSeri ?>S · <?= $totalKalah ?>K</div>
                </div>
            </div>
        </div>

        <!-- Tabel Riwayat -->
        <div style="width:100%; overflow:hidden; border-radius:12px; border:1px solid rgba(255,255,255,0.12);">
            <table class="data-table" style="width:100%; border-collapse:separate; border-spacing:0; font-size:0.83rem;">
                <thead>
                    <tr style="background:rgba(15,23,42,0.95);">
                        <th style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.1); text-align:center; width:30px;">#</th>
                        <th style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.1);">Tanggal</th>
                        <th style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.1);">Lawan</th>
                        <th style="padding:9px 10px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.1); text-align:center; white-space:nowrap;">Hasil Skor</th>
                        <th style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.1); text-align:center;">Hasil</th>
                        <th style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.1);">Keterangan</th>
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                        <th style="padding:9px 8px; border-bottom:1px solid rgba(255,255,255,0.12); text-align:center;">Aksi</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($matchHistory)): ?>
                    <tr><td colspan="<?= ($role==='admin'||$role==='pelatih') ? 7 : 6 ?>" style="text-align:center; color:var(--text-muted); padding:2rem;">Belum ada riwayat laga. Tambahkan di form di atas.</td></tr>
                <?php else: ?>
                    <?php $mno=1; foreach ($matchHistory as $m):
                        $hasil = $m['skor_kita'] > $m['skor_lawan'] ? 'Menang' : ($m['skor_kita'] === $m['skor_lawan'] ? 'Seri' : 'Kalah');
                        $hasilColor = $hasil === 'Menang' ? '#34d399' : ($hasil === 'Seri' ? '#94a3b8' : '#f87171');
                        $hasilBg = $hasil === 'Menang' ? 'rgba(52,211,153,0.12)' : ($hasil === 'Seri' ? 'rgba(148,163,184,0.1)' : 'rgba(248,113,113,0.12)');
                        $mEvents = $allEvents[$m['id']] ?? [];
                        $mGolAtlet = $mEvents['gol'] ?? [];
                        $mKbAtlet  = $mEvents['kebobolan'] ?? [];
                    ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.07); transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.03)'" onmouseout="this.style.background='transparent'">
                        <td style="padding:9px 8px; border-right:1px solid rgba(255,255,255,0.07); text-align:center; color:var(--text-muted); font-weight:600;"><?= $mno++ ?></td>
                        <td style="padding:9px 8px; border-right:1px solid rgba(255,255,255,0.07); color:#94a3b8; white-space:nowrap; font-size:0.78rem;"><?= $m['tanggal'] ? date('d M Y', strtotime($m['tanggal'])) : '<span style="color:var(--text-muted)">—</span>' ?></td>
                        <td style="padding:9px 10px; border-right:1px solid rgba(255,255,255,0.07); font-weight:700; color:#e2e8f0;"><?= htmlspecialchars($m['lawan']) ?></td>
                        <td style="padding:9px 10px; border-right:1px solid rgba(255,255,255,0.07); text-align:center; font-family:'Outfit',sans-serif; font-weight:900; font-size:1.05rem; letter-spacing:1px; white-space:nowrap;">
                            <span style="color:#34d399;"><?= $m['skor_kita'] ?></span>
                            <span style="color:rgba(255,255,255,0.3); margin:0 4px;">:</span>
                            <span style="color:#fb923c;"><?= $m['skor_lawan'] ?></span>
                        </td>
                        <td style="padding:9px 8px; border-right:1px solid rgba(255,255,255,0.07); text-align:center;">
                            <span style="background:<?= $hasilBg ?>; color:<?= $hasilColor ?>; border:1px solid <?= $hasilColor ?>55; padding:2px 10px; border-radius:20px; font-size:0.72rem; font-weight:800;"><?= $hasil ?></span>
                        </td>
                        <!-- Kolom Pencetak Gol -->
                        <td style="padding:9px 10px; border-right:1px solid rgba(255,255,255,0.07); font-size:0.75rem; min-width:140px;">
                            <?php if (!empty($mGolAtlet)): ?>
                                <?php foreach ($mGolAtlet as $ga): ?>
                                    <span style="display:inline-flex; align-items:center; gap:4px; background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.3); color:#34d399; padding:1px 7px; border-radius:20px; font-weight:700; margin:1px 2px 1px 0; font-size:0.7rem;">
                                        ⚽ <?= htmlspecialchars($ga['nama_lengkap']) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php if ($m['skor_kita'] > 0): ?>
                                    <span style="color:var(--text-muted); font-size:0.72rem; font-style:italic;">Pencetak belum dipilih</span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);">—</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if (!empty($mKbAtlet)): ?>
                                <div style="margin-top:3px;">
                                <?php foreach ($mKbAtlet as $kb): ?>
                                    <span style="display:inline-flex; align-items:center; gap:4px; background:rgba(251,146,60,0.12); border:1px solid rgba(251,146,60,0.3); color:#fb923c; padding:1px 7px; border-radius:20px; font-weight:700; margin:1px 2px 1px 0; font-size:0.7rem;">
                                        🥅 <?= htmlspecialchars($kb['nama_lengkap']) ?>
                                    </span>
                                <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:9px 8px; border-right:1px solid rgba(255,255,255,0.07); color:var(--text-muted); font-size:0.78rem;"><?= htmlspecialchars($m['keterangan'] ?: '—') ?></td>
                        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                        <td style="padding:9px 8px; text-align:center; white-space:nowrap;">
                            <div style="display:flex; gap:4px; justify-content:center;">
                                <button onclick="editMatchRow(<?= htmlspecialchars(json_encode($m)) ?>, <?= htmlspecialchars(json_encode($mEvents)) ?>)" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:2px 7px; color:#38bdf8;">✏️ Edit</button>
                                <form method="POST" style="display:inline-block;" onsubmit="return confirm('Hapus laga vs <?= htmlspecialchars(addslashes($m['lawan'])) ?> dan rollback statistik atlet?')">
                                    <input type="hidden" name="action" value="delete_match">
                                    <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="btn btn-secondary btn-sm" style="font-size:0.7rem; padding:2px 7px; color:#f87171;">🗑️ Hapus</button>
                                </form>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($matchHistory)): ?>
                <tfoot>
                    <tr style="background:rgba(15,23,42,0.8);">
                        <td colspan="3" style="padding:9px 10px; border-top:2px solid rgba(255,255,255,0.15); color:#cbd5e1; font-weight:700; font-size:0.78rem;">TOTAL <?= count($matchHistory) ?> Laga</td>
                        <td style="padding:9px 10px; border-top:2px solid rgba(255,255,255,0.15); text-align:center; font-family:'Outfit',sans-serif; font-weight:900; font-size:1.05rem;">
                            <span style="color:#34d399;"><?= $totalSkorKita ?></span><span style="color:rgba(255,255,255,0.3); margin:0 4px;">:</span><span style="color:#fb923c;"><?= $totalSkorLawan ?></span>
                        </td>
                        <td colspan="<?= ($role==='admin'||$role==='pelatih') ? 3 : 2 ?>" style="padding:9px 10px; border-top:2px solid rgba(255,255,255,0.15); font-size:0.78rem; color:var(--text-muted);">
                            <span style="color:#34d399; font-weight:700;"><?= $totalMenang ?>M</span> &bull;
                            <span style="color:#94a3b8; font-weight:700;"><?= $totalSeri ?>S</span> &bull;
                            <span style="color:#f87171; font-weight:700;"><?= $totalKalah ?>K</span>
                        </td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- ---- GALERI FOTO TURNAMEN ---- -->
    <div class="card" style="padding: 1.5rem;">
        <div class="card-header" style="border-bottom:1px solid var(--border-glass); padding-bottom:0.85rem; margin-bottom:1.25rem;">
            <h2 class="card-title" style="font-size:1.15rem; display:flex; align-items:center; gap:8px;">📸 Galeri Foto Turnamen</h2>
            <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Dokumentasi foto selama turnamen berlangsung</p>
        </div>

        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
        <!-- Upload Form -->
        <div style="background:rgba(15,23,42,0.5); border:1px solid var(--border-glass); border-radius:14px; padding:1.1rem; margin-bottom:1.25rem;">
            <div style="font-size:0.75rem; font-weight:800; color:#fbbf24; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.75rem;">📤 Upload Foto Baru</div>
            <form method="POST" enctype="multipart/form-data" id="formFoto">
                <input type="hidden" name="action" value="upload_foto">
                <!-- Drop Zone -->
                <div id="dropZone" onclick="document.getElementById('fotoInput').click()" style="border:2px dashed rgba(99,102,241,0.4); border-radius:12px; padding:1.5rem; text-align:center; cursor:pointer; transition:all 0.2s; margin-bottom:0.85rem;" onmouseover="this.style.borderColor='rgba(99,102,241,0.8)'; this.style.background='rgba(99,102,241,0.05)';" onmouseout="this.style.borderColor='rgba(99,102,241,0.4)'; this.style.background='transparent';">
                    <div style="font-size:2rem; margin-bottom:6px;">🖼️</div>
                    <div style="color:#a5b4fc; font-weight:700; font-size:0.88rem;">Klik atau drag foto ke sini</div>
                    <div style="color:var(--text-muted); font-size:0.73rem; margin-top:4px;">JPG, PNG, WEBP — Maks. 5MB per file — bisa pilih banyak sekaligus</div>
                    <input type="file" id="fotoInput" name="foto_files[]" multiple accept="image/*" style="display:none;" onchange="previewFoto(this)">
                </div>
                <!-- Preview Grid -->
                <div id="fotoPreviewGrid" style="display:none; display:grid; grid-template-columns:repeat(auto-fill, minmax(120px,1fr)); gap:10px; margin-bottom:0.85rem;"></div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" onclick="clearFotoPreview()" id="btnClearFoto" class="btn btn-secondary" style="display:none; font-size:0.82rem; padding:0.45rem 0.9rem;">Batal</button>
                    <button type="submit" class="btn btn-primary" style="font-size:0.82rem; padding:0.45rem 1.1rem; font-weight:700; box-shadow:0 4px 12px rgba(99,102,241,0.4);">📤 Upload Foto</button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Galeri Grid -->
        <?php if (empty($galeri)): ?>
        <div style="text-align:center; padding:3rem; color:var(--text-muted);">
            <div style="font-size:3rem; margin-bottom:0.5rem;">📷</div>
            <div style="font-weight:600; font-size:0.88rem;">Belum ada foto yang diupload</div>
            <div style="font-size:0.76rem; margin-top:4px;">Upload foto dokumentasi turnamen di atas</div>
        </div>
        <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:10px;">
            <?php foreach ($galeri as $fi): ?>
            <?php $imgUrl = '../assets/img/turnamen/' . htmlspecialchars($fi['nama_file']); ?>
            <div class="foto-card" style="position:relative; border-radius:12px; overflow:hidden; aspect-ratio:1; background:#0f172a; border:1px solid rgba(255,255,255,0.1); cursor:pointer; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='scale(1.03)'; this.style.boxShadow='0 8px 20px rgba(0,0,0,0.5)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';" onclick="openLightbox('<?= $imgUrl ?>', '<?= htmlspecialchars(addslashes($fi['caption'] ?: $fi['nama_file'])) ?>')">
                <img src="<?= $imgUrl ?>" alt="<?= htmlspecialchars($fi['caption'] ?: 'Foto Turnamen') ?>" style="width:100%; height:100%; object-fit:cover; display:block;" loading="lazy">
                <?php if (!empty($fi['caption'])): ?>
                <div style="position:absolute; bottom:0; left:0; right:0; background:linear-gradient(transparent, rgba(0,0,0,0.75)); padding:0.4rem 0.5rem;">
                    <div style="color:#fff; font-size:0.68rem; font-weight:600; line-height:1.3;"><?= htmlspecialchars($fi['caption']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($role === 'admin' || $role === 'pelatih'): ?>
                <form method="POST" style="position:absolute; top:4px; right:4px;" onsubmit="return confirm('Hapus foto ini?')">
                    <input type="hidden" name="action" value="delete_foto">
                    <input type="hidden" name="foto_id" value="<?= $fi['id'] ?>">
                    <button type="submit" style="background:rgba(0,0,0,0.65); border:none; color:#f87171; width:24px; height:24px; border-radius:50%; cursor:pointer; font-size:0.75rem; display:flex; align-items:center; justify-content:center; line-height:1; backdrop-filter:blur(4px);" title="Hapus foto">✕</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:right; margin-top:0.75rem; font-size:0.73rem; color:var(--text-muted);"><?= count($galeri) ?> foto tersimpan</div>
        <?php endif; ?>
    </div>

</div>

<!-- LIGHTBOX OVERLAY -->
<div id="lightboxOverlay" onclick="closeLightbox()" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.92); backdrop-filter:blur(8px); align-items:center; justify-content:center; flex-direction:column; gap:12px; cursor:zoom-out;">
    <img id="lightboxImg" src="" alt="" style="max-width:90vw; max-height:80vh; border-radius:10px; object-fit:contain; box-shadow:0 20px 60px rgba(0,0,0,0.8);">
    <div id="lightboxCaption" style="color:#e2e8f0; font-size:0.9rem; font-weight:600; text-align:center; max-width:80vw;"></div>
    <button onclick="closeLightbox()" style="position:absolute; top:16px; right:20px; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); color:#fff; width:38px; height:38px; border-radius:50%; cursor:pointer; font-size:1.1rem;">✕</button>
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
    document.getElementById('form_kebobolan').value = data.kebobolan || 0;

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
    document.getElementById('form_kebobolan').value = 0;

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

// ---- Match History Helpers ----
// Data atlet di turnamen ini untuk dropdown (dari PHP)
const atletTurnamen = <?= json_encode(array_map(fn($a) => [
    'id'           => $a['atlet_id'],
    'nama'         => $a['nama_lengkap'],
    'posisi'       => $a['posisi_utama'],
    'kelompok_usia'=> $a['kelompok_usia'],
], $atletInTourney)) ?>;

function buildScorerDropdowns(presetGol = [], presetKb = []) {
    const skor_kita  = parseInt(document.getElementById('fm_skor_kita').value)  || 0;
    const skor_lawan = parseInt(document.getElementById('fm_skor_lawan').value) || 0;
    const area       = document.getElementById('scorerArea');
    const golArea    = document.getElementById('golScorerArea');
    const kbArea     = document.getElementById('kbScorerArea');

    golArea.innerHTML = '';
    kbArea.innerHTML  = '';
    area.style.display = (skor_kita > 0 || skor_lawan > 0) ? 'block' : 'none';

    // -- Opsi atlet (reusable) --
    function makeOptions(selectedId = 0) {
        let opts = '<option value="">-- Pilih Atlet --</option>';
        atletTurnamen.forEach(a => {
            const sel = a.id == selectedId ? ' selected' : '';
            opts += `<option value="${a.id}"${sel}>${a.nama} (${a.posisi})</option>`;
        });
        return opts;
    }

    // -- Pencetak Gol --
    if (skor_kita > 0) {
        let html = `<div style="font-size:0.73rem; font-weight:800; color:#34d399; margin-bottom:6px; display:flex; align-items:center; gap:6px;">⚽ Pencetak Gol <span style="background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.3); padding:1px 8px; border-radius:10px;">${skor_kita} gol</span></div>`;
        html += `<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:8px; margin-bottom:0.85rem;">`;
        for (let i = 0; i < skor_kita; i++) {
            html += `<div>
                <label style="font-size:0.68rem; color:#94a3b8; display:block; margin-bottom:3px;">Gol ke-${i+1}</label>
                <select name="pencetak_gol[]" class="form-control" style="font-size:0.8rem; color:#34d399; font-weight:700;">
                    ${makeOptions(presetGol[i] || 0)}
                </select>
            </div>`;
        }
        html += '</div>';
        golArea.innerHTML = html;
    }

    // -- Atlet Kebobolan --
    if (skor_lawan > 0) {
        let html = `<div style="font-size:0.73rem; font-weight:800; color:#fb923c; margin-bottom:6px; display:flex; align-items:center; gap:6px;">🥅 Siapa yang Kebobolan? <span style="background:rgba(251,146,60,0.15); border:1px solid rgba(251,146,60,0.3); padding:1px 8px; border-radius:10px;">${skor_lawan} gol masuk</span></div>`;
        html += `<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:8px; margin-bottom:0.6rem;">`;
        for (let i = 0; i < skor_lawan; i++) {
            html += `<div>
                <label style="font-size:0.68rem; color:#94a3b8; display:block; margin-bottom:3px;">Gol ke-${i+1} masuk oleh</label>
                <select name="kebobolan_atlet[]" class="form-control" style="font-size:0.8rem; color:#fb923c; font-weight:700;">
                    ${makeOptions(presetKb[i] || 0)}
                </select>
            </div>`;
        }
        html += '</div>';
        kbArea.innerHTML = html;
    }
}

function editMatchRow(data, events) {
    document.getElementById('fm_match_id').value = data.id;
    document.getElementById('fm_tanggal').value = data.tanggal || '';
    document.getElementById('fm_lawan').value = data.lawan || '';
    document.getElementById('fm_skor_kita').value = data.skor_kita || 0;
    document.getElementById('fm_skor_lawan').value = data.skor_lawan || 0;
    document.getElementById('fm_keterangan').value = data.keterangan || '';
    document.getElementById('btnMatchReset').style.display = 'inline-block';
    // Build dropdowns dengan preset events yang sudah ada
    const presetGol = events && events.gol ? events.gol.map(e => e.atlet_id) : [];
    const presetKb  = events && events.kebobolan ? events.kebobolan.map(e => e.atlet_id) : [];
    buildScorerDropdowns(presetGol, presetKb);
    document.getElementById('formMatch').scrollIntoView({ behavior: 'smooth' });
}
function resetMatchForm() {
    document.getElementById('fm_match_id').value = '';
    document.getElementById('fm_tanggal').value = '';
    document.getElementById('fm_lawan').value = '';
    document.getElementById('fm_skor_kita').value = 0;
    document.getElementById('fm_skor_lawan').value = 0;
    document.getElementById('fm_keterangan').value = '';
    document.getElementById('btnMatchReset').style.display = 'none';
    // Reset scorer area
    document.getElementById('golScorerArea').innerHTML = '';
    document.getElementById('kbScorerArea').innerHTML  = '';
    document.getElementById('scorerArea').style.display = 'none';
    // Reset error state
    clearMatchError();
}

function clearMatchError() {
    const box = document.getElementById('matchErrorInline');
    if (box) { box.style.display = 'none'; }
    document.querySelectorAll('#scorerArea select.select-error').forEach(s => {
        s.classList.remove('select-error');
        s.style.borderColor = '';
        s.style.boxShadow   = '';
    });
}

function validateMatchForm(e) {
    clearMatchError();
    const skor_kita  = parseInt(document.getElementById('fm_skor_kita').value)  || 0;
    const skor_lawan = parseInt(document.getElementById('fm_skor_lawan').value) || 0;

    const errMessages = [];
    let   firstEmpty  = null;

    // Validasi pencetak gol
    if (skor_kita > 0) {
        const golSelects = document.querySelectorAll('#golScorerArea select[name="pencetak_gol[]"]');
        let emptyGol = 0;
        golSelects.forEach((sel, i) => {
            if (!sel.value) {
                emptyGol++;
                markSelectError(sel);
                if (!firstEmpty) firstEmpty = sel;
            }
        });
        if (emptyGol > 0) {
            errMessages.push(`Pilih atlet pencetak gol untuk semua ${skor_kita} gol (${emptyGol} belum dipilih)`);
        }
    }

    // Validasi kebobolan atlet
    if (skor_lawan > 0) {
        const kbSelects = document.querySelectorAll('#kbScorerArea select[name="kebobolan_atlet[]"]');
        let emptyKb = 0;
        kbSelects.forEach((sel, i) => {
            if (!sel.value) {
                emptyKb++;
                markSelectError(sel);
                if (!firstEmpty) firstEmpty = sel;
            }
        });
        if (emptyKb > 0) {
            errMessages.push(`Pilih atlet yang kebobolan untuk semua ${skor_lawan} gol masuk (${emptyKb} belum dipilih)`);
        }
    }

    if (errMessages.length > 0) {
        // Tampilkan pesan error inline
        const errBox  = document.getElementById('matchErrorInline');
        const errText = document.getElementById('matchErrorText');
        if (errBox && errText) {
            errText.textContent = errMessages.join(' · ');
            errBox.style.display     = 'flex';
            errBox.style.flexDirection = 'row';
        }
        // Shake tombol Simpan
        const btn = document.getElementById('btnSimpanLaga');
        if (btn) {
            btn.style.animation = 'none';
            btn.offsetHeight; // reflow
            btn.style.animation = 'matchShake 0.4s ease';
            setTimeout(() => btn.style.animation = '', 500);
        }
        // Scroll ke dropdown pertama yang error
        if (firstEmpty) firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false; // Blok submit
    }
    return true; // Lanjut submit
}

function markSelectError(el) {
    el.classList.add('select-error');
    el.style.borderColor = '#f87171';
    el.style.boxShadow   = '0 0 0 3px rgba(248,113,113,0.3)';
    el.style.animation   = 'matchShake 0.4s ease';
    // Hapus error state saat user memilih
    el.addEventListener('change', function once() {
        el.style.borderColor = '';
        el.style.boxShadow   = '';
        el.style.animation   = '';
        el.classList.remove('select-error');
        el.removeEventListener('change', once);
        // Cek apakah masih ada error lain
        const remaining = document.querySelectorAll('#scorerArea select.select-error');
        if (remaining.length === 0) clearMatchError();
    });
}

// ---- Auto Sinkronisasi: isi form laga dari total statistik atlet ----
function autoSyncMatchFromAtlet() {
    const totalGolAtlet     = <?= (int)$totalGoalsInTourney ?>;
    const totalKebobolanAtlet = <?= (int)$totalKebobolan ?>;
    document.getElementById('fm_skor_kita').value  = totalGolAtlet;
    document.getElementById('fm_skor_lawan').value = totalKebobolanAtlet;
    document.getElementById('fm_keterangan').value = 'Agregat semua laga (dari statistik atlet)';
    // Highlight
    ['fm_skor_kita','fm_skor_lawan'].forEach(id => {
        const el = document.getElementById(id);
        el.style.transition = 'box-shadow 0.3s';
        el.style.boxShadow = '0 0 0 3px rgba(99,102,241,0.6)';
        setTimeout(() => el.style.boxShadow = '', 1500);
    });
    document.getElementById('formMatch')?.scrollIntoView({ behavior: 'smooth' });
}

// ---- Foto Upload Preview ----
function previewFoto(input) {
    const grid = document.getElementById('fotoPreviewGrid');
    const btnClear = document.getElementById('btnClearFoto');
    grid.innerHTML = '';
    grid.style.display = 'grid';
    btnClear.style.display = 'inline-block';
    Array.from(input.files).forEach((file, i) => {
        const reader = new FileReader();
        reader.onload = e => {
            const div = document.createElement('div');
            div.style.cssText = 'position:relative; border-radius:10px; overflow:hidden; aspect-ratio:1; background:#0f172a; border:1px solid rgba(255,255,255,0.1);';
            div.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover;"><div style="position:absolute;bottom:0;left:0;right:0;padding:4px;"><input type="text" name="captions[${i}]" placeholder="Caption..." style="width:100%; font-size:0.65rem; background:rgba(0,0,0,0.6); border:none; color:#fff; border-radius:4px; padding:2px 5px;"></div>`;
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}
function clearFotoPreview() {
    document.getElementById('fotoInput').value = '';
    document.getElementById('fotoPreviewGrid').innerHTML = '';
    document.getElementById('fotoPreviewGrid').style.display = 'none';
    document.getElementById('btnClearFoto').style.display = 'none';
}

// ---- Lightbox ----
function openLightbox(src, caption) {
    const ov = document.getElementById('lightboxOverlay');
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxCaption').textContent = caption || '';
    ov.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightboxOverlay').style.display = 'none';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
/* ---- Validasi Match Form ---- */
const _matchValidationStyle = document.createElement('style');
_matchValidationStyle.textContent = `
    @keyframes matchShake {
        0%,100% { transform: translateX(0); }
        15%      { transform: translateX(-6px); }
        30%      { transform: translateX(6px); }
        45%      { transform: translateX(-5px); }
        60%      { transform: translateX(5px); }
        75%      { transform: translateX(-3px); }
        90%      { transform: translateX(3px); }
    }
    #scorerArea select.select-error {
        border-color: #f87171 !important;
        box-shadow: 0 0 0 3px rgba(248,113,113,0.28) !important;
        animation: matchShake 0.4s ease !important;
    }
    #matchErrorInline {
        background: rgba(248,113,113,0.1);
        border: 1px solid rgba(248,113,113,0.35);
        border-radius: 8px;
        padding: 5px 10px;
    }
`;
document.head.appendChild(_matchValidationStyle);
</script>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
