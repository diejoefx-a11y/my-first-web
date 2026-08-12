<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Require user authentication
requireAuth();

$pdo = getPdo();
$user = getAuthUser();
$role = $user['role'];

$pageTitle = ($role === 'atlet') ? "Portal Atlet - SSB Tamalanrea" : "Dashboard Analytics";

// Determine greeting based on current local hour
$hour = (int)date('H');
if ($hour >= 5 && $hour < 12) {
    $greeting = "Selamat Pagi 🌅";
} elseif ($hour >= 12 && $hour < 15) {
    $greeting = "Selamat Siang ☀️";
} elseif ($hour >= 15 && $hour < 18) {
    $greeting = "Selamat Sore 🌆";
} else {
    $greeting = "Selamat Malam 🌙";
}

$currentMonth = date('n');
$currentYear = date('Y');
$bulanIndo = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

if ($role === 'atlet') {
    // DATA SPECIFIC TO ATLET / SISWA LOGGED IN
    $atletId = $user['atlet_id'];

    // Fetch Atlet details
    $stmt = $pdo->prepare("SELECT * FROM atlet WHERE id = :id");
    $stmt->execute([':id' => $atletId]);
    $atletInfo = $stmt->fetch();

    // Fetch Parents info
    $stmtOrtu = $pdo->prepare("SELECT * FROM orang_tua WHERE atlet_id = :id");
    $stmtOrtu->execute([':id' => $atletId]);
    $ortuInfo = $stmtOrtu->fetch();

    // Fetch Latest Evaluasi
    $stmtEval = $pdo->prepare("SELECT * FROM evaluasi_atlet WHERE atlet_id = :id ORDER BY tanggal_evaluasi DESC LIMIT 1");
    $stmtEval->execute([':id' => $atletId]);
    $latestEval = $stmtEval->fetch();

    // Fetch SPP Bulan Ini
    $stmtSpp = $pdo->prepare("SELECT * FROM iuran_spp WHERE atlet_id = :id AND bulan = :b AND tahun = :t");
    $stmtSpp->execute([':id' => $atletId, ':b' => $currentMonth, ':t' => $currentYear]);
    $sppBulanIni = $stmtSpp->fetch();

    // Fetch All SPP History
    $stmtSppHistory = $pdo->prepare("SELECT * FROM iuran_spp WHERE atlet_id = :id ORDER BY tahun DESC, bulan DESC LIMIT 5");
    $stmtSppHistory->execute([':id' => $atletId]);
    $sppHistory = $stmtSppHistory->fetchAll();

    // Fetch Turnamen Stats
    $stmtTurnamen = $pdo->prepare("SELECT s.*, t.nama_turnamen, t.lokasi, t.pencapaian FROM statistik_pertandingan s JOIN turnamen t ON s.turnamen_id = t.id WHERE s.atlet_id = :id");
    $stmtTurnamen->execute([':id' => $atletId]);
    $turnamenStats = $stmtTurnamen->fetchAll();

    $stmtMatchTotals = $pdo->prepare("SELECT SUM(main) as total_main, SUM(gol) as total_gol, SUM(assist) as total_assist, SUM(kartu_kuning) as total_kk, SUM(kartu_merah) as total_km FROM statistik_pertandingan WHERE atlet_id = :id");
    $stmtMatchTotals->execute([':id' => $atletId]);
    $matchTotals = $stmtMatchTotals->fetch();

    // Kelompok Usia Breakdown & Peer Stats
    $kuCounts = $pdo->query("SELECT kelompok_usia, COUNT(*) as total FROM atlet WHERE status_keanggotaan = 'Aktif' GROUP BY kelompok_usia ORDER BY kelompok_usia ASC")->fetchAll();
    $totalAtletAll = $pdo->query("SELECT COUNT(*) FROM atlet WHERE status_keanggotaan = 'Aktif'")->fetchColumn() ?: 1;
    
    $userKu = $atletInfo['kelompok_usia'] ?? 'U-12';
    $stmtKuTeammates = $pdo->prepare("SELECT COUNT(*) FROM atlet WHERE kelompok_usia = :ku AND status_keanggotaan = 'Aktif'");
    $stmtKuTeammates->execute([':ku' => $userKu]);
    $kuTeammatesCount = $stmtKuTeammates->fetchColumn() ?: 0;

    // Average skill scores for the user's Kelompok Usia
    $stmtKuAvg = $pdo->prepare("SELECT 
        AVG(e.nilai_passing) as avg_passing,
        AVG(e.nilai_dribbling) as avg_dribbling,
        AVG(e.nilai_shooting) as avg_shooting,
        AVG(e.nilai_tackling) as avg_tackling,
        AVG(e.nilai_stamina) as avg_stamina
        FROM evaluasi_atlet e 
        JOIN atlet a ON e.atlet_id = a.id 
        WHERE a.kelompok_usia = :ku");
    $stmtKuAvg->execute([':ku' => $userKu]);
    $kuAvg = $stmtKuAvg->fetch();

    // Overall Rating (OVR)
    $passingVal   = (int)($latestEval['nilai_passing'] ?? 70);
    $dribblingVal = (int)($latestEval['nilai_dribbling'] ?? 70);
    $shootingVal  = (int)($latestEval['nilai_shooting'] ?? 70);
    $tacklingVal  = (int)($latestEval['nilai_tackling'] ?? 70);
    $staminaVal   = (int)($latestEval['nilai_stamina'] ?? 70);
    $ovr = (int)round(($passingVal + $dribblingVal + $shootingVal + $tacklingVal + $staminaVal) / 5);

    if ($ovr >= 90) {
        $ovrGrade = "ELITE 👑";
        $ovrColor = "#38bdf8";
        $ovrBadgeBg = "rgba(56, 189, 248, 0.2)";
    } elseif ($ovr >= 80) {
        $ovrGrade = "GOLD 🏆";
        $ovrColor = "#fbbf24";
        $ovrBadgeBg = "rgba(251, 191, 36, 0.2)";
    } elseif ($ovr >= 70) {
        $ovrGrade = "SILVER 🥈";
        $ovrColor = "#cbd5e1";
        $ovrBadgeBg = "rgba(203, 213, 225, 0.2)";
    } else {
        $ovrGrade = "BRONZE 🥉";
        $ovrColor = "#f97316";
        $ovrBadgeBg = "rgba(249, 115, 22, 0.2)";
    }
} else {
    // DATA FOR ADMIN / PELATIH LOGGED IN
    $totalAtlet = $pdo->query("SELECT COUNT(*) FROM atlet WHERE status_keanggotaan = 'Aktif'")->fetchColumn() ?: 0;
    $totalTurnamen = $pdo->query("SELECT COUNT(*) FROM turnamen")->fetchColumn() ?: 0;

    $totalIuranLunas = $pdo->query("SELECT SUM(jumlah) FROM iuran_spp WHERE bulan = $currentMonth AND tahun = $currentYear AND status_bayar = 'Lunas'")->fetchColumn() ?: 0;
    $totalLunasCount = $pdo->query("SELECT COUNT(*) FROM iuran_spp WHERE bulan = $currentMonth AND tahun = $currentYear AND status_bayar = 'Lunas'")->fetchColumn() ?: 0;
    $totalTunggakan = $pdo->query("SELECT COUNT(*) FROM iuran_spp WHERE bulan = $currentMonth AND tahun = $currentYear AND status_bayar = 'Belum Bayar'")->fetchColumn() ?: 0;
    $totalEvaluasiThisMonth = $pdo->query("SELECT COUNT(*) FROM evaluasi_atlet WHERE MONTH(tanggal_evaluasi) = $currentMonth AND YEAR(tanggal_evaluasi) = $currentYear")->fetchColumn() ?: 0;

    // KU Breakdown
    $kuCounts = $pdo->query("SELECT kelompok_usia, COUNT(*) as total FROM atlet GROUP BY kelompok_usia ORDER BY kelompok_usia ASC")->fetchAll();

    // Posisi Breakdown
    $allAtletPos = $pdo->query("SELECT posisi_utama FROM atlet")->fetchAll(PDO::FETCH_COLUMN);
    $posisiCounts = ['Kiper' => 0, 'Bek' => 0, 'Gelandang' => 0, 'Penyerang' => 0];
    foreach ($allAtletPos as $pos) {
        if (strpos($pos, 'Kiper') !== false) $posisiCounts['Kiper']++;
        elseif (strpos($pos, 'Bek') !== false) $posisiCounts['Bek']++;
        elseif (strpos($pos, 'Gelandang') !== false) $posisiCounts['Gelandang']++;
        elseif (strpos($pos, 'Penyerang') !== false) $posisiCounts['Penyerang']++;
    }

    // Recent Athletes
    $recentAtlet = $pdo->query("SELECT * FROM atlet ORDER BY id DESC LIMIT 5")->fetchAll();


    // Recent Payments
    $recentPayments = $pdo->query("SELECT i.*, a.nama_lengkap, a.kelompok_usia FROM iuran_spp i JOIN atlet a ON i.atlet_id = a.id ORDER BY i.id DESC LIMIT 5")->fetchAll();

    // Upcoming / Recent Tournaments
    $tournaments = $pdo->query("SELECT * FROM turnamen ORDER BY id DESC LIMIT 4")->fetchAll();
}

include_once __DIR__ . '/includes/header.php';
?>

<!-- CUSTOM DASHBOARD MICRO-ANIMATIONS & INTERACTIVE STYLES -->
<style>
@keyframes pulseGlow {
    0%, 100% { transform: scale(1); opacity: 0.8; }
    50% { transform: scale(1.15); opacity: 1; box-shadow: 0 0 15px rgba(34, 197, 94, 0.8); }
}

@keyframes floatSmooth {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}

.dash-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.dash-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px -10px rgba(99, 102, 241, 0.25);
    border-color: rgba(99, 102, 241, 0.4) !important;
}

.action-tile {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    background: rgba(15, 23, 42, 0.65);
    border: 1px solid var(--border-glass);
    border-radius: 14px;
    text-decoration: none;
    color: #fff;
    transition: all 0.25s ease;
}
.action-tile:hover {
    background: rgba(30, 41, 59, 0.9);
    transform: translateX(6px);
    border-color: var(--primary-light);
    box-shadow: 0 8px 20px -6px rgba(99, 102, 241, 0.3);
}
.action-tile .action-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    transition: transform 0.25s ease;
}
.action-tile:hover .action-icon {
    transform: scale(1.1) rotate(4deg);
}

.ku-stat-item {
    transition: all 0.25s ease;
}
.ku-stat-item:hover {
    transform: translateY(-3px);
    background: rgba(30, 41, 59, 0.8) !important;
    border-color: rgba(129, 140, 248, 0.4) !important;
}

.fut-card {
    background: linear-gradient(145deg, rgba(30, 27, 75, 0.9), rgba(15, 23, 42, 0.95));
    border: 1px solid rgba(251, 191, 36, 0.4);
    border-radius: 20px;
    padding: 1.35rem;
    position: relative;
    overflow: hidden;
    box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.6), 0 0 20px rgba(251, 191, 36, 0.12);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
}
.fut-card:hover {
    transform: translateY(-5px) scale(1.01);
    box-shadow: 0 20px 40px -10px rgba(99, 102, 241, 0.35), 0 0 25px rgba(251, 191, 36, 0.25);
}

.ku-atlet-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.ku-atlet-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 26px -8px rgba(99, 102, 241, 0.35);
    border-color: rgba(129, 140, 248, 0.6) !important;
}

.atlet-ovr-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-family: 'Outfit', sans-serif;
    font-weight: 800;
    line-height: 1;
    border: 2px solid currentColor;
    box-shadow: 0 0 15px rgba(255, 255, 255, 0.15);
}
</style>

<!-- HERO WELCOME BANNER WITH DYNAMIC GLOW -->
<div class="card" style="background: linear-gradient(135deg, rgba(30, 27, 75, 0.9) 0%, rgba(15, 23, 42, 0.95) 50%, rgba(17, 24, 39, 0.9) 100%); border: 1px solid rgba(99, 102, 241, 0.3); margin-bottom: 1.75rem; position:relative; overflow:hidden; border-radius:18px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.6);">
    <!-- Glowing Accent Orbs -->
    <div style="position:absolute; right:-40px; top:-40px; width:260px; height:260px; background:radial-gradient(circle, rgba(99, 102, 241, 0.35) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
    <div style="position:absolute; left:20%; bottom:-60px; width:200px; height:200px; background:radial-gradient(circle, rgba(56, 189, 248, 0.2) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.5rem; position:relative; z-index:2; padding:0.5rem 0.25rem;">
        <div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                <span style="display:inline-flex; align-items:center; gap:6px; background:rgba(34, 197, 94, 0.15); border:1px solid rgba(34, 197, 94, 0.3); color:#4ade80; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:700; letter-spacing:0.5px; text-transform:uppercase;">
                    <span style="width:7px; height:7px; background:#22c55e; border-radius:50%; display:inline-block; animation: pulseGlow 2s infinite;"></span>
                    Sistem Aktif
                </span>
                <span style="background:rgba(99, 102, 241, 0.18); border:1px solid rgba(99, 102, 241, 0.3); color:#a5b4fc; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:700; text-transform:uppercase;">
                    Role: <?= strtoupper($role) ?>
                </span>
            </div>

            <h1 style="font-family:'Outfit', sans-serif; font-size:1.85rem; font-weight:800; color:#fff; margin-bottom:6px; letter-spacing:-0.5px;">
                <?= $greeting ?>, <span style="background:linear-gradient(90deg, #818cf8, #38bdf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent;"><?= htmlspecialchars($user['nama_lengkap']) ?></span>!
            </h1>
            <p style="color:var(--text-muted); font-size:0.9rem; max-width:620px; line-height:1.5;">
                <?= ($role === 'atlet') 
                    ? "Pantau grafik atribut teknis, riwayat evaluasi raport, status SPP bulanan, dan statistik turnamen Anda secara gratis & real-time." 
                    : "Pusat kendali operasional SSB Tamalanrea Makassar. Pantau perkembangan pemain, raport fisik & teknis, pembayaran iuran, serta statistik turnamen." ?>
            </p>
        </div>

        <div style="display:flex; align-items:center; gap:14px; background:rgba(255,255,255,0.05); padding:0.9rem 1.35rem; border-radius:16px; border:1px solid rgba(255,255,255,0.1); backdrop-filter:blur(10px);">
            <div style="text-align:right;">
                <div style="font-size:0.72rem; color:var(--text-muted); font-weight:600; text-transform:uppercase; letter-spacing:0.5px;">Tanggal Hari Ini</div>
                <div style="font-family:'Outfit'; font-weight:700; color:#fff; font-size:1.05rem;"><?= date('d') ?> <?= $bulanIndo[(int)date('n')] ?> <?= date('Y') ?></div>
            </div>
            <div style="font-size:2rem; animation: floatSmooth 3s ease-in-out infinite;">⚽</div>
        </div>
    </div>
</div>

<?php if ($role === 'atlet'): ?>
    <!-- ========================================== -->
    <!-- DASHBOARD VIEW DINAMIS UNTUK ATLET         -->
    <!-- ========================================== -->

    <!-- TOP SECTION: FUT PLAYER BADGE & QUICK STAT CARDS -->
    <div style="display:grid; grid-template-columns: minmax(290px, 340px) 1fr; gap:1.25rem; margin-bottom:1.5rem;">
        
        <!-- FUT KARTU ATLET PLAYER BADGE -->
        <div class="fut-card">
            <!-- Background Glow -->
            <div style="position:absolute; top:-30px; right:-30px; width:180px; height:180px; background:radial-gradient(circle, rgba(251, 191, 36, 0.22) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem; position:relative; z-index:2;">
                <div>
                    <span style="background:<?= $ovrBadgeBg ?>; border:1px solid <?= $ovrColor ?>; color:<?= $ovrColor ?>; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;">
                        <?= $ovrGrade ?>
                    </span>
                    <h3 style="font-family:'Outfit', sans-serif; font-size:1.35rem; font-weight:800; color:#fff; margin-top:8px; margin-bottom:2px;">
                        <?= htmlspecialchars($atletInfo['nama_lengkap']) ?>
                    </h3>
                    <div style="font-size:0.8rem; color:var(--primary-light); font-weight:600;">
                        NISN/NIK: <?= htmlspecialchars($atletInfo['nisn_nik'] ?: '-') ?>
                    </div>
                </div>

                <!-- OVR CIRCLE BADGE -->
                <div class="atlet-ovr-circle" style="color:<?= $ovrColor ?>; background:rgba(15, 23, 42, 0.85);">
                    <span style="font-size:1.45rem;"><?= $ovr ?></span>
                    <span style="font-size:0.6rem; opacity:0.8; letter-spacing:0.5px;">OVR</span>
                </div>
            </div>

            <!-- PLAYER PHOTO & SPECS -->
            <div style="display:flex; align-items:center; gap:0.9rem; margin-bottom:1.1rem; background:rgba(15,23,42,0.6); padding:0.8rem; border-radius:14px; border:1px solid rgba(255,255,255,0.08);">
                <div style="position:relative; flex-shrink:0;">
                    <?php 
                    $fotoPath = !empty($atletInfo['foto_profil']) && $atletInfo['foto_profil'] !== 'default_avatar.png' 
                        ? 'assets/img/atlet/' . htmlspecialchars($atletInfo['foto_profil']) 
                        : 'https://ui-avatars.com/api/?name=' . urlencode($atletInfo['nama_lengkap']) . '&background=6366f1&color=fff&bold=true';
                    ?>
                    <img src="<?= $fotoPath ?>" alt="Foto Atlet" style="width:64px; height:64px; border-radius:14px; object-fit:cover; border:2px solid <?= $ovrColor ?>; box-shadow:0 6px 15px rgba(0,0,0,0.4);">
                    <span style="position:absolute; bottom:-3px; right:-3px; background:#22c55e; width:12px; height:12px; border-radius:50%; border:2px solid #0f172a;" title="Status: Aktif"></span>
                </div>

                <div style="display:flex; flex-direction:column; gap:3px; font-size:0.78rem; color:#cbd5e1;">
                    <div>🎯 Posisi: <strong style="color:#fff;"><?= htmlspecialchars($atletInfo['posisi_utama'] ?: '-') ?></strong></div>
                    <div>⚡ Kaki: <strong style="color:#fff;"><?= htmlspecialchars($atletInfo['kaki_dominan'] ?: 'Kanan') ?></strong></div>
                    <div>📏 Fisik: <strong style="color:#fff;"><?= $atletInfo['tinggi_badan'] ?> cm / <?= $atletInfo['berat_badan'] ?> kg</strong></div>
                </div>
            </div>

            <!-- BUTTONS -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                <a href="idcard.php?id=<?= $atletId ?>" class="btn btn-primary btn-sm" style="justify-content:center; padding:0.55rem; font-size:0.78rem;">
                    💳 Kartu ID
                </a>
                <a href="atlet/detail.php?id=<?= $atletId ?>" class="btn btn-secondary btn-sm" style="justify-content:center; padding:0.55rem; font-size:0.78rem;">
                    👤 Profil Lengkap
                </a>
            </div>
        </div>

        <!-- 3 STAT METRIC CARDS -->
        <div style="display:flex; flex-direction:column; gap:0.9rem;">
            <!-- KARTU STATISTIK KELOMPOK USIA ANDA -->
            <div class="stat-card dash-card" style="border-left:4px solid #818cf8;">
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <div class="stat-label">Kelompok Usia Anda</div>
                        <span class="badge" style="background:rgba(99,102,241,0.2); color:#818cf8; border:1px solid rgba(99,102,241,0.4); font-size:0.7rem;">KATEGORI <?= $userKu ?></span>
                    </div>
                    <div class="stat-value" style="font-size:1.35rem; color:#fff;">
                        <?= $kuTeammatesCount ?> <span style="font-size:0.85rem; color:var(--text-muted); font-weight:500;">Rekan Tim di <?= $userKu ?></span>
                    </div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">
                        Kontribusi <?= round(($kuTeammatesCount / $totalAtletAll) * 100) ?>% dari seluruh total atlet SSB Tamalanrea
                    </div>
                </div>
                <div class="stat-icon" style="color:#818cf8; background:rgba(99,102,241,0.18);">
                    👥
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.9rem;">
                <!-- STAMINA & VO2MAX -->
                <div class="stat-card dash-card" style="border-left:4px solid #38bdf8;">
                    <div>
                        <div class="stat-label">Stamina VO2Max</div>
                        <div class="stat-value" style="color:#38bdf8; font-size:1.3rem; margin-top:2px;">
                            <?= $latestEval ? $latestEval['vo2max'] : '-' ?> <span style="font-size:0.78rem; font-weight:500;">mL/kg</span>
                        </div>
                        <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">
                            Score: <?= $latestEval ? $latestEval['nilai_stamina'] : '-' ?>/100
                        </div>
                    </div>
                    <div class="stat-icon" style="color:#38bdf8; background:rgba(56,189,248,0.18);">
                        ⚡
                    </div>
                </div>

                <!-- STATUS SPP -->
                <div class="stat-card dash-card" style="border-left:4px solid <?= ($sppBulanIni && $sppBulanIni['status_bayar'] === 'Lunas') ? '#34d399' : '#f87171' ?>;">
                    <div>
                        <div class="stat-label">SPP <?= $bulanIndo[$currentMonth] ?></div>
                        <?php if ($sppBulanIni && $sppBulanIni['status_bayar'] === 'Lunas'): ?>
                            <div class="stat-value" style="font-size:1.25rem; color:#34d399; margin-top:2px;">✓ LUNAS</div>
                        <?php else: ?>
                            <div class="stat-value" style="font-size:1.25rem; color:#f87171; margin-top:2px;">BELUM BAYAR</div>
                        <?php endif; ?>
                        <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">
                            Rp <?= number_format($sppBulanIni ? $sppBulanIni['jumlah'] : 150000, 0, ',', '.') ?>
                        </div>
                    </div>
                    <div class="stat-icon" style="color:<?= ($sppBulanIni && $sppBulanIni['status_bayar'] === 'Lunas') ? '#34d399' : '#f87171' ?>; background:<?= ($sppBulanIni && $sppBulanIni['status_bayar'] === 'Lunas') ? 'rgba(52,211,153,0.18)' : 'rgba(248,113,113,0.18)' ?>;">
                        💳
                    </div>
                </div>
            </div>
        </div>

    </div>


    <!-- ========================================== -->
    <!-- KARTU STATISTIK KELOMPOK USIA (KU BREAKDOWN WIDGET) -->
    <!-- ========================================== -->
    <div class="card" style="margin-bottom:1.5rem;">
        <div class="card-header" style="padding-bottom:0.75rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass); flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h2 class="card-title" style="font-size:1.15rem;">📊 Kartu Statistik Kelompok Usia SSB Tamalanrea</h2>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Distribusi jumlah atlet aktif di setiap kategori umur. Klik kartu untuk melihat daftar rekan tim.</p>
            </div>
            <a href="atlet/index.php" class="btn btn-secondary btn-sm">Lihat Semua Atlet</a>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(125px, 1fr)); gap:12px;">
            <?php 
            $kuColorList = ['#818cf8', '#38bdf8', '#34d399', '#fbbf24', '#f472b6', '#a78bfa', '#fb7185'];
            $idx = 0;
            foreach ($kuCounts as $ku): 
                $accentColor = $kuColorList[$idx % count($kuColorList)];
                $isUserKu = ($ku['kelompok_usia'] === $userKu);
                $pct = $totalAtletAll > 0 ? round(($ku['total'] / $totalAtletAll) * 100) : 0;
                $idx++;
            ?>
                <a href="atlet/index.php?ku=<?= urlencode($ku['kelompok_usia']) ?>" class="ku-atlet-card" style="background:<?= $isUserKu ? 'rgba(99, 102, 241, 0.18)' : 'rgba(15, 23, 42, 0.65)' ?>; padding:0.85rem 0.6rem; border-radius:14px; border:1px solid <?= $isUserKu ? 'rgba(99, 102, 241, 0.6)' : 'var(--border-glass)' ?>; border-top:4px solid <?= $accentColor ?>; text-decoration:none; display:block; text-align:center; position:relative;">
                    
                    <?php if ($isUserKu): ?>
                        <span style="position:absolute; top:-9px; right:50%; transform:translateX(50%); background:#818cf8; color:#fff; font-size:0.58rem; font-weight:800; padding:2px 7px; border-radius:10px; white-space:nowrap; box-shadow:0 2px 8px rgba(99,102,241,0.5);">
                            KU ANDA ⭐
                        </span>
                    <?php endif; ?>

                    <span class="badge" style="background:rgba(255,255,255,0.1); color:#fff; font-size:0.75rem; font-weight:700; padding:2px 7px; border-radius:6px; margin-top:<?= $isUserKu ? '4px' : '0' ?>;">
                        <?= htmlspecialchars($ku['kelompok_usia']) ?>
                    </span>

                    <div style="font-family:'Outfit', sans-serif; font-size:1.5rem; font-weight:800; color:#fff; margin:5px 0 2px 0;">
                        <?= $ku['total'] ?>
                    </div>
                    <div style="font-size:0.7rem; color:var(--text-muted); font-weight:600;">
                        <?= $pct ?>% Atlet
                    </div>

                    <!-- Progress Mini Bar -->
                    <div style="width:100%; height:4px; background:rgba(255,255,255,0.1); border-radius:3px; margin-top:7px; overflow:hidden;">
                        <div style="width:<?= $pct ?>%; height:100%; background:<?= $accentColor ?>; border-radius:3px;"></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>


    <!-- RADAR CHART SKILL & BREAKDOWN RAPORT -->
    <div class="grid-2" style="margin-bottom:1.5rem;">
        
        <!-- HEXAGONAL RADAR CHART ATTRIBUTE ATLET -->
        <div class="card">
            <div class="card-header" style="padding-bottom:0.75rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass);">
                <div>
                    <h2 class="card-title" style="font-size:1.15rem;">🎯 Radar Atribut Skill Teknis</h2>
                    <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Perbandingan atribut Anda vs Rata-rata Kelompok Usia <?= $userKu ?></p>
                </div>
            </div>

            <div style="position:relative; height:260px; width:100%; display:flex; justify-content:center; align-items:center;">
                <canvas id="atletSkillRadarChart"></canvas>
            </div>
        </div>

        <!-- DETAIL BREAKDOWN RAPORT TEKNIS & CATATAN PELATIH -->
        <div class="card">
            <div class="card-header" style="padding-bottom:0.75rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass);">
                <div>
                    <h2 class="card-title" style="font-size:1.15rem;">📝 Raport Evaluasi Terakhir</h2>
                    <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                        Tanggal Evaluasi: <strong><?= $latestEval ? date('d M Y', strtotime($latestEval['tanggal_evaluasi'])) : '-' ?></strong>
                    </p>
                </div>
                <a href="atlet/detail.php?id=<?= $atletId ?>" class="btn btn-secondary btn-sm">Lihat Detail Raport</a>
            </div>

            <?php if ($latestEval): ?>
                <?php
                    $stPassing  = getScoreStyle($latestEval['nilai_passing']);
                    $stDribbling= getScoreStyle($latestEval['nilai_dribbling']);
                    $stShooting = getScoreStyle($latestEval['nilai_shooting']);
                    $stTackling = getScoreStyle($latestEval['nilai_tackling']);
                    $stStamina  = getScoreStyle($latestEval['nilai_stamina']);
                ?>
                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                            <span>Passing & Control</span>
                            <span style="color:<?= $stPassing['color'] ?>; font-weight:700;"><?= $latestEval['nilai_passing'] ?>/100</span>
                        </div>
                        <div class="skill-bar"><div class="skill-fill" style="<?= $stPassing['barFill'] ?>"></div></div>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                            <span>Dribbling & Agility</span>
                            <span style="color:<?= $stDribbling['color'] ?>; font-weight:700;"><?= $latestEval['nilai_dribbling'] ?>/100</span>
                        </div>
                        <div class="skill-bar"><div class="skill-fill" style="<?= $stDribbling['barFill'] ?>"></div></div>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                            <span>Shooting & Akurasi Tendangan</span>
                            <span style="color:<?= $stShooting['color'] ?>; font-weight:700;"><?= $latestEval['nilai_shooting'] ?>/100</span>
                        </div>
                        <div class="skill-bar"><div class="skill-fill" style="<?= $stShooting['barFill'] ?>"></div></div>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                            <span>Tackling & Defending</span>
                            <span style="color:<?= $stTackling['color'] ?>; font-weight:700;"><?= $latestEval['nilai_tackling'] ?>/100</span>
                        </div>
                        <div class="skill-bar"><div class="skill-fill" style="<?= $stTackling['barFill'] ?>"></div></div>
                    </div>

                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                            <span>Stamina & Fisik (VO2Max: <?= $latestEval['vo2max'] ?>)</span>
                            <span style="color:<?= $stStamina['color'] ?>; font-weight:700;"><?= $latestEval['nilai_stamina'] ?>/100</span>
                        </div>
                        <div class="skill-bar"><div class="skill-fill" style="<?= $stStamina['barFill'] ?>"></div></div>
                    </div>
                </div>

                <?php if (!empty($latestEval['catatan_pelatih'])): ?>
                    <div style="margin-top:1rem; background:rgba(15,23,42,0.6); padding:0.85rem; border-radius:12px; border:1px solid var(--border-glass);">
                        <div style="font-size:0.75rem; font-weight:700; color:var(--primary-light); text-transform:uppercase; margin-bottom:3px;">Catatan Pelatih:</div>
                        <p style="font-size:0.85rem; color:var(--text-body); font-style:italic; margin:0;">"<?= htmlspecialchars($latestEval['catatan_pelatih']) ?>"</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p style="color:var(--text-muted); font-style:italic;">Belum ada raport evaluasi yang diinput pelatih.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- STATISTIK PERTANDINGAN & RIWAYAT TURNAMEN ATLET -->
    <div class="grid-2" style="margin-bottom:1.5rem;">
        
        <!-- COUNTER STATS TURNAMEN -->
        <div class="card">
            <div class="card-header" style="padding-bottom:0.75rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass);">
                <h2 class="card-title" style="font-size:1.15rem;">🏆 Rekapitulasi Statistik Pertandingan</h2>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:1rem;">
                <div style="background:rgba(99,102,241,0.12); border:1px solid rgba(99,102,241,0.3); padding:0.85rem; border-radius:12px; text-align:center;">
                    <div style="font-size:0.72rem; color:#818cf8; font-weight:700; text-transform:uppercase;">Main (Caps)</div>
                    <div style="font-family:'Outfit'; font-size:1.6rem; font-weight:800; color:#fff; margin-top:2px;"><?= (int)($matchTotals['total_main'] ?? 0) ?></div>
                </div>

                <div style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.3); padding:0.85rem; border-radius:12px; text-align:center;">
                    <div style="font-size:0.72rem; color:#34d399; font-weight:700; text-transform:uppercase;">Gol Dicetak</div>
                    <div style="font-family:'Outfit'; font-size:1.6rem; font-weight:800; color:#fff; margin-top:2px;"><?= (int)($matchTotals['total_gol'] ?? 0) ?></div>
                </div>

                <div style="background:rgba(56,189,248,0.12); border:1px solid rgba(56,189,248,0.3); padding:0.85rem; border-radius:12px; text-align:center;">
                    <div style="font-size:0.72rem; color:#38bdf8; font-weight:700; text-transform:uppercase;">Assist</div>
                    <div style="font-family:'Outfit'; font-size:1.6rem; font-weight:800; color:#fff; margin-top:2px;"><?= (int)($matchTotals['total_assist'] ?? 0) ?></div>
                </div>

                <div style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); padding:0.85rem; border-radius:12px; text-align:center;">
                    <div style="font-size:0.72rem; color:#fbbf24; font-weight:700; text-transform:uppercase;">Kartu Kuning / Merah</div>
                    <div style="font-family:'Outfit'; font-size:1.4rem; font-weight:800; color:#fff; margin-top:2px;"><?= (int)($matchTotals['total_kk'] ?? 0) ?> 🟨 / <?= (int)($matchTotals['total_km'] ?? 0) ?> 🟥</div>
                </div>
            </div>
        </div>

        <!-- RIWAYAT TURNAMEN DIIKUTI & SPP -->
        <div class="card">
            <div class="card-header" style="padding-bottom:0.75rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass);">
                <h2 class="card-title" style="font-size:1.15rem;">🥇 Turnamen & Event Yang Diikuti</h2>
                <a href="turnamen/index.php" class="btn btn-secondary btn-sm">Jadwal Turnamen</a>
            </div>

            <?php if (!empty($turnamenStats)): ?>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($turnamenStats as $ts): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; background:rgba(15,23,42,0.6); padding:0.85rem; border-radius:12px; border:1px solid var(--border-glass);">
                            <div>
                                <strong style="font-size:0.88rem; color:#fff;"><?= htmlspecialchars($ts['nama_turnamen']) ?></strong>
                                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;">📍 <?= htmlspecialchars($ts['lokasi']) ?></div>
                            </div>
                            <span class="badge badge-amber" style="font-weight:700; font-size:0.75rem;"><?= htmlspecialchars($ts['pencapaian'] ?: 'Peserta') ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color:var(--text-muted); font-style:italic; font-size:0.88rem;">Belum ada statistik turnamen tercatat.</p>
            <?php endif; ?>
        </div>

    </div>

<?php else: ?>
    <!-- ========================================== -->
    <!-- DASHBOARD VIEW UNTUK ADMIN / PELATIH       -->
    <!-- ========================================== -->
    
    <?php if ($role === 'pelatih'): ?>
        <!-- 4 KARTU STATISTIK POSISI PEMAIN UNTUK PELATIH -->
        <div class="grid-4" style="margin-bottom:1.5rem;">
            <div class="stat-card dash-card">
                <div>
                    <div class="stat-label">Kiper (GK)</div>
                    <div class="stat-value" style="color:#fbbf24;"><?= number_format($posisiCounts['Kiper']) ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
                </div>
                <div class="stat-icon" style="color:#fbbf24; background:rgba(251,191,36,0.18);">
                    🧤
                </div>
            </div>

            <div class="stat-card dash-card">
                <div>
                    <div class="stat-label">Bek (Defender)</div>
                    <div class="stat-value" style="color:#a855f7;"><?= number_format($posisiCounts['Bek']) ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
                </div>
                <div class="stat-icon" style="color:#a855f7; background:rgba(168,85,247,0.18);">
                    🛡️
                </div>
            </div>

            <div class="stat-card dash-card">
                <div>
                    <div class="stat-label">Gelandang (Midfielder)</div>
                    <div class="stat-value" style="color:#38bdf8;"><?= number_format($posisiCounts['Gelandang']) ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
                </div>
                <div class="stat-icon" style="color:#38bdf8; background:rgba(6,182,212,0.18);">
                    🎯
                </div>
            </div>

            <div class="stat-card dash-card">
                <div>
                    <div class="stat-label">Striker / Penyerang</div>
                    <div class="stat-value" style="color:#34d399;"><?= number_format($posisiCounts['Penyerang']) ?> <span style="font-size:0.85rem; font-weight:500;">Pemain</span></div>
                </div>
                <div class="stat-icon" style="color:#34d399; background:rgba(16,185,129,0.18);">
                    ⚽
                </div>
            </div>
        </div>

        <!-- STAT CARDS KEGIATAN UNTUK PELATIH -->
        <div class="grid-2" style="margin-bottom:1.75rem;">
            <div class="stat-card dash-card">
                <div>
                    <div class="stat-label">Evaluasi Bulan Ini</div>
                    <div class="stat-value" style="color:#38bdf8;"><?= number_format($totalEvaluasiThisMonth) ?> <span style="font-size:0.85rem; font-weight:500;">Raport</span></div>
                </div>
                <div class="stat-icon" style="color:var(--cyan); background:rgba(6,182,212,0.18);">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                </div>
            </div>

            <div class="stat-card dash-card">
                <div>
                    <div class="stat-label">Total Turnamen</div>
                    <div class="stat-value" style="color:#fbbf24;"><?= number_format($totalTurnamen) ?> <span style="font-size:0.85rem; font-weight:500;">Turnamen</span></div>
                </div>
                <div class="stat-icon" style="color:#fbbf24; background:rgba(251,191,36,0.18);">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <!-- MAIN DASHBOARD GRID (KELOMPOK USIA & ANALISIS SPP) -->
    <div class="grid-2" style="margin-bottom:1.75rem;">
        
        <!-- CARD 1: KELOMPOK USIA + TOTAL ATLET AKTIF BADGE -->
        <div class="card">
            <div class="card-header" style="padding-bottom:0.85rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass); flex-wrap:wrap; gap:0.5rem;">
                <div>
                    <h2 class="card-title" style="font-size:1.15rem;">📊 Kelompok Usia</h2>
                    <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Visualisasi persentase & jumlah pemain aktif per kategori usia</p>
                </div>
                <div style="background:rgba(99,102,241,0.15); border:1px solid rgba(99,102,241,0.3); color:#818cf8; padding:0.4rem 0.85rem; border-radius:10px; font-weight:700; font-size:0.85rem; display:flex; align-items:center; gap:6px;">
                    <span>👥 Total Atlet:</span>
                    <strong style="color:#fff; font-size:1rem;"><?= number_format($totalAtlet) ?></strong>
                </div>
            </div>
            
            <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <!-- Pie Chart Container -->
                <div style="position:relative; height:200px; width:100%; display:flex; justify-content:center; align-items:center;">
                    <canvas id="kuPieChart"></canvas>
                </div>

                <!-- Mini Stat Tiles Grid -->
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(85px, 1fr)); gap:8px;">
                    <?php 
                    $kuColorList = ['#818cf8', '#38bdf8', '#34d399', '#fbbf24', '#f472b6', '#a78bfa', '#fb7185'];
                    $idx = 0;
                    foreach ($kuCounts as $ku): 
                        $accentColor = $kuColorList[$idx % count($kuColorList)];
                        $pct = $totalAtlet > 0 ? round(($ku['total'] / $totalAtlet) * 100) : 0;
                        $idx++;
                    ?>
                        <div class="ku-stat-item" style="background:rgba(15,23,42,0.65); padding:0.6rem 0.4rem; border-radius:12px; border:1px solid var(--border-glass); border-top:3px solid <?= $accentColor ?>; text-align:center;">
                            <span class="badge" style="background:rgba(255,255,255,0.08); color:#fff; font-size:0.65rem; padding:1px 5px; margin-bottom:3px; border-radius:4px;"><?= htmlspecialchars($ku['kelompok_usia']) ?></span>
                            <div style="font-family:'Outfit', sans-serif; font-size:1.3rem; font-weight:800; color:#fff; margin:1px 0;"><?= $ku['total'] ?></div>
                            <div style="font-size:0.68rem; color:var(--text-muted); font-weight:600;"><?= $pct ?>%</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($role === 'admin'): ?>
            <!-- CARD 2: ANALISIS KEUANGAN SPP (PEMASUKAN & TUNGGAKAN DISATUKAN DENGAN BAR CHART) -->
            <div class="card">
                <div class="card-header" style="padding-bottom:0.85rem; margin-bottom:1rem; border-bottom:1px solid var(--border-glass);">
                    <div>
                        <h2 class="card-title" style="font-size:1.15rem;">💳 Status Pembayaran SPP (<?= $bulanIndo[$currentMonth] ?> <?= $currentYear ?>)</h2>
                        <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Perbandingan pemasukan lunas vs atlet menunggak</p>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; gap:1.1rem;">
                    <!-- Ringkasan Nilai Pemasukan & Tunggakan -->
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div style="background:rgba(16,185,129,0.12); border:1px solid rgba(16,185,129,0.3); padding:0.75rem; border-radius:12px;">
                            <div style="font-size:0.72rem; color:#34d399; font-weight:700; text-transform:uppercase;">Pemasukan (Lunas)</div>
                            <div style="font-family:'Outfit'; font-size:1.25rem; font-weight:800; color:#fff; margin-top:2px;">Rp <?= number_format($totalIuranLunas, 0, ',', '.') ?></div>
                            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:1px;"><?= $totalLunasCount ?> Atlet Lunas</div>
                        </div>

                        <div style="background:rgba(244,63,94,0.12); border:1px solid rgba(244,63,94,0.3); padding:0.75rem; border-radius:12px;">
                            <div style="font-size:0.72rem; color:#f87171; font-weight:700; text-transform:uppercase;">Tunggakan (Belum Bayar)</div>
                            <div style="font-family:'Outfit'; font-size:1.25rem; font-weight:800; color:#f87171; margin-top:2px;"><?= number_format($totalTunggakan) ?> Atlet</div>
                            <div style="font-size:0.72rem; color:var(--text-muted); margin-top:1px;">Perlu Penagihan</div>
                        </div>
                    </div>

                    <!-- Diagram Bar Chart Keuangan SPP -->
                    <div style="position:relative; height:185px; width:100%;">
                        <canvas id="sppBarChart"></canvas>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- CARD 2 UNTUK PELATIH: AKSES CEPAT OPERASIONAL -->
            <div class="card">
                <div class="card-header" style="padding-bottom:0.85rem; margin-bottom:1.1rem; border-bottom:1px solid var(--border-glass);">
                    <div>
                        <h2 class="card-title" style="font-size:1.15rem;">⚡ Akses Cepat Operasional</h2>
                        <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Navigasi cepat untuk tugas manajemen harian</p>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:10px;">
                    <a href="atlet/index.php" class="action-tile">
                        <div class="action-icon" style="background:rgba(168,85,247,0.18); color:#a78bfa;">
                            👥
                        </div>
                        <div>
                            <strong style="font-size:0.88rem; display:block;">Daftar Semua Atlet</strong>
                            <span style="font-size:0.74rem; color:var(--text-muted);">Direktori data pemain</span>
                        </div>
                    </a>

                    <a href="evaluasi/index.php" class="action-tile">
                        <div class="action-icon" style="background:rgba(6,182,212,0.18); color:#38bdf8;">
                            📝
                        </div>
                        <div>
                            <strong style="font-size:0.88rem; display:block;">Input & Lihat Raport</strong>
                            <span style="font-size:0.74rem; color:var(--text-muted);">Evaluasi atribut fisik & teknis</span>
                        </div>
                    </a>

                    <a href="turnamen/index.php" class="action-tile">
                        <div class="action-icon" style="background:rgba(251,191,36,0.18); color:#fbbf24;">
                            🏆
                        </div>
                        <div>
                            <strong style="font-size:0.88rem; display:block;">Statistik Turnamen</strong>
                            <span style="font-size:0.74rem; color:var(--text-muted);">Jadwal & scouting pemain</span>
                        </div>
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <?php if ($role === 'admin'): ?>
        <!-- AKSI CEPAT OPERASIONAL UNTUK ADMIN -->
        <div class="card" style="margin-bottom:1.75rem;">
            <div class="card-header" style="padding-bottom:0.85rem; margin-bottom:1.1rem; border-bottom:1px solid var(--border-glass);">
                <div>
                    <h2 class="card-title" style="font-size:1.15rem;">⚡ Akses Cepat Operasional</h2>
                    <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Navigasi cepat untuk tugas manajemen harian</p>
                </div>
            </div>
            
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:10px;">
                <a href="atlet/tambah.php" class="action-tile">
                    <div class="action-icon" style="background:rgba(99,102,241,0.18); color:#818cf8;">
                        ➕
                    </div>
                    <div>
                        <strong style="font-size:0.88rem; display:block;">Tambah Atlet Baru</strong>
                        <span style="font-size:0.74rem; color:var(--text-muted);">Registrasi pemain baru</span>
                    </div>
                </a>

                <a href="atlet/index.php" class="action-tile">
                    <div class="action-icon" style="background:rgba(168,85,247,0.18); color:#a78bfa;">
                        👥
                    </div>
                    <div>
                        <strong style="font-size:0.88rem; display:block;">Daftar Semua Atlet</strong>
                        <span style="font-size:0.74rem; color:var(--text-muted);">Direktori data pemain</span>
                    </div>
                </a>

                <a href="evaluasi/index.php" class="action-tile">
                    <div class="action-icon" style="background:rgba(6,182,212,0.18); color:#38bdf8;">
                        📝
                    </div>
                    <div>
                        <strong style="font-size:0.88rem; display:block;">Input & Lihat Raport</strong>
                        <span style="font-size:0.74rem; color:var(--text-muted);">Evaluasi atribut fisik & teknis</span>
                    </div>
                </a>

                <a href="iuran/index.php" class="action-tile">
                    <div class="action-icon" style="background:rgba(16,185,129,0.18); color:#34d399;">
                        💳
                    </div>
                    <div>
                        <strong style="font-size:0.88rem; display:block;">Kelola Iuran SPP</strong>
                        <span style="font-size:0.74rem; color:var(--text-muted);">Catat pembayaran bulanan</span>
                    </div>
                </a>

                <a href="turnamen/index.php" class="action-tile">
                    <div class="action-icon" style="background:rgba(251,191,36,0.18); color:#fbbf24;">
                        🏆
                    </div>
                    <div>
                        <strong style="font-size:0.88rem; display:block;">Statistik Turnamen</strong>
                        <span style="font-size:0.74rem; color:var(--text-muted);">Jadwal & scouting pemain</span>
                    </div>
                </a>
            </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
// Prepare KU Chart data
$kuLabels = array_column($kuCounts ?? [], 'kelompok_usia');
$kuValues = array_column($kuCounts ?? [], 'total');
$kuColors = ['#818cf8', '#38bdf8', '#34d399', '#fbbf24', '#f472b6', '#a78bfa', '#fb7185'];
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. CHART PIE KELOMPOK USIA
    const ctxPie = document.getElementById('kuPieChart');
    if (ctxPie) {
        new Chart(ctxPie, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($kuLabels) ?>,
                datasets: [{
                    data: <?= json_encode($kuValues) ?>,
                    backgroundColor: <?= json_encode(array_slice($kuColors, 0, count($kuLabels))) ?>,
                    borderColor: '#0f172a',
                    borderWidth: 3,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' },
                            padding: 10,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: 'rgba(99, 102, 241, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                let val = context.raw || 0;
                                let total = context.chart._metasets[0].total;
                                let pct = Math.round((val / total) * 100);
                                return ' ' + label + ': ' + val + ' Atlet (' + pct + '%)';
                            }
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // 2. DIAGRAM BAR ANALISIS SPP (LUNAS VS TUNGGAKAN)
    const ctxSpp = document.getElementById('sppBarChart');
    if (ctxSpp) {
        new Chart(ctxSpp, {
            type: 'bar',
            data: {
                labels: ['Lunas (Pemasukan)', 'Belum Bayar (Tunggakan)'],
                datasets: [{
                    label: 'Jumlah Atlet',
                    data: [<?= (int)$totalLunasCount ?>, <?= (int)$totalTunggakan ?>],
                    backgroundColor: ['rgba(52, 211, 153, 0.75)', 'rgba(248, 113, 113, 0.75)'],
                    borderColor: ['#34d399', '#f87171'],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#cbd5e1',
                        borderColor: 'rgba(99, 102, 241, 0.4)',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let val = context.raw || 0;
                                return ' ' + context.label + ': ' + val + ' Atlet';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.06)' },
                        ticks: { color: '#94a3b8', font: { size: 11 }, precision: 0 }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#e2e8f0', font: { size: 11, weight: '600' } }
                    }
                }
            }
        });
    // 3. RADAR CHART SKILL ATLET
    const ctxRadar = document.getElementById('atletSkillRadarChart');
    if (ctxRadar) {
        new Chart(ctxRadar, {
            type: 'radar',
            data: {
                labels: ['Passing & Control', 'Dribbling & Agility', 'Shooting & Akurasi', 'Tackling & Defending', 'Stamina & Fisik'],
                datasets: [
                    {
                        label: 'Skor Anda',
                        data: [<?= (int)($passingVal ?? 70) ?>, <?= (int)($dribblingVal ?? 70) ?>, <?= (int)($shootingVal ?? 70) ?>, <?= (int)($tacklingVal ?? 70) ?>, <?= (int)($staminaVal ?? 70) ?>],
                        backgroundColor: 'rgba(99, 102, 241, 0.35)',
                        borderColor: '#818cf8',
                        borderWidth: 3,
                        pointBackgroundColor: '#818cf8',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#818cf8'
                    },
                    {
                        label: 'Rata-rata <?= $userKu ?? "Tim" ?>',
                        data: [
                            <?= round((float)($kuAvg['avg_passing'] ?? 70)) ?>,
                            <?= round((float)($kuAvg['avg_dribbling'] ?? 70)) ?>,
                            <?= round((float)($kuAvg['avg_shooting'] ?? 70)) ?>,
                            <?= round((float)($kuAvg['avg_tackling'] ?? 70)) ?>,
                            <?= round((float)($kuAvg['avg_stamina'] ?? 70)) ?>
                        ],
                        backgroundColor: 'rgba(56, 189, 248, 0.15)',
                        borderColor: 'rgba(56, 189, 248, 0.8)',
                        borderWidth: 2,
                        borderDash: [4, 4],
                        pointBackgroundColor: '#38bdf8'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        angleLines: { color: 'rgba(255, 255, 255, 0.1)' },
                        grid: { color: 'rgba(255, 255, 255, 0.08)' },
                        pointLabels: {
                            color: '#cbd5e1',
                            font: { size: 10, weight: '700' }
                        },
                        ticks: {
                            backdropColor: 'transparent',
                            color: '#64748b',
                            stepSize: 20
                        },
                        suggestedMin: 30,
                        suggestedMax: 100
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#94a3b8', font: { size: 11 } }
                    }
                }
            }
        });
    }
});
</script>

<?php include_once __DIR__ . '/includes/footer.php'; ?>
