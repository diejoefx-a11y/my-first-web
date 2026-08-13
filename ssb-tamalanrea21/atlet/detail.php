<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
requireAuth();

$pdo = getPdo();
$user = getAuthUser();

$id = (int)($_GET['id'] ?? 0);
if ($user['role'] === 'atlet') {
    $id = $user['atlet_id'];
}

// Fetch Athlete Data
$stmt = $pdo->prepare("SELECT a.*, o.nama_ayah, o.nama_ibu, o.no_whatsapp, o.alamat FROM atlet a LEFT JOIN orang_tua o ON a.id = o.atlet_id WHERE a.id = ?");
$stmt->execute([$id]);
$atlet = $stmt->fetch();

if (!$atlet) {
    die("<div style='padding:2rem; color:red;'>Data atlet tidak ditemukan! <a href='index.php'>Kembali</a></div>");
}


$pageTitle = "Profil - " . htmlspecialchars($atlet['nama_lengkap']);

// Check photo existence
$photoPath = __DIR__ . '/../assets/img/atlet/' . ($atlet['foto_profil'] ?? '');
$hasPhoto = !empty($atlet['foto_profil']) && $atlet['foto_profil'] !== 'default_avatar.png' && file_exists($photoPath);

// Fetch Latest Evaluation / Raport
$stmtEval = $pdo->prepare("SELECT * FROM evaluasi_atlet WHERE atlet_id = ? ORDER BY tanggal_evaluasi DESC LIMIT 1");
$stmtEval->execute([$id]);
$evaluasi = $stmtEval->fetch();

// Fetch SPP History
$stmtSpp = $pdo->prepare("SELECT * FROM iuran_spp WHERE atlet_id = ? ORDER BY tahun DESC, bulan DESC");
$stmtSpp->execute([$id]);
$sppList = $stmtSpp->fetchAll();

// Fetch Tournament Stats
$stmtStats = $pdo->prepare("SELECT s.*, t.nama_turnamen, t.lokasi FROM statistik_pertandingan s JOIN turnamen t ON s.turnamen_id = t.id WHERE s.atlet_id = ?");
$stmtStats->execute([$id]);
$tournamentStats = $stmtStats->fetchAll();

// Fetch Kelompok Usia Average Scores for Radar Comparison
$stmtKuAvg = $pdo->prepare("SELECT 
    AVG(e.nilai_passing) as avg_passing,
    AVG(e.nilai_dribbling) as avg_dribbling,
    AVG(e.nilai_shooting) as avg_shooting,
    AVG(e.nilai_tackling) as avg_tackling,
    AVG(e.nilai_stamina) as avg_stamina
    FROM evaluasi_atlet e 
    JOIN atlet a ON e.atlet_id = a.id 
    WHERE a.kelompok_usia = ?");
$stmtKuAvg->execute([$atlet['kelompok_usia']]);
$kuAvg = $stmtKuAvg->fetch();

$avgPassing   = round((float)($kuAvg['avg_passing'] ?? 70));
$avgDribbling = round((float)($kuAvg['avg_dribbling'] ?? 70));
$avgShooting  = round((float)($kuAvg['avg_shooting'] ?? 70));
$avgTackling  = round((float)($kuAvg['avg_tackling'] ?? 70));
$avgStamina   = round((float)($kuAvg['avg_stamina'] ?? 70));

include_once __DIR__ . '/../includes/header.php';
?>

<?php
// Calculate Dynamic Overall Rating (OVR)
if ($evaluasi) {
    $passingVal   = (int)($evaluasi['nilai_passing'] ?? 70);
    $dribblingVal = (int)($evaluasi['nilai_dribbling'] ?? 70);
    $shootingVal  = (int)($evaluasi['nilai_shooting'] ?? 70);
    $tacklingVal  = (int)($evaluasi['nilai_tackling'] ?? 70);
    $staminaVal   = (int)($evaluasi['nilai_stamina'] ?? 70);
    $ovr = (int)round(($passingVal + $dribblingVal + $shootingVal + $tacklingVal + $staminaVal) / 5);
} else {
    $ovr = 75;
}

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
?>
<div class="card" style="position:relative; overflow:hidden; background: linear-gradient(135deg, rgba(30, 27, 75, 0.95) 0%, rgba(15, 23, 42, 0.98) 50%, rgba(17, 24, 39, 0.95) 100%); border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.7); margin-bottom: 1.5rem; padding: 1.5rem 1.75rem;">
    <!-- Glowing Ambient Orbs -->
    <div style="position:absolute; right:-40px; top:-40px; width:280px; height:280px; background:radial-gradient(circle, rgba(99, 102, 241, 0.3) 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>
    <div style="position:absolute; left:25%; bottom:-60px; width:220px; height:220px; background:radial-gradient(circle, <?= $ovrColor ?>33 0%, transparent 70%); border-radius:50%; pointer-events:none;"></div>

    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:1.75rem; position:relative; z-index:2;">
        
        <!-- LEFT COLUMN: INFO UTAMA & TOMBOL AKSI -->
        <div style="flex:1; min-width:280px;">
            <!-- BADGE HEADER -->
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-wrap:wrap;">
                <span style="background:<?= $ovrBadgeBg ?>; border:1px solid <?= $ovrColor ?>; color:<?= $ovrColor ?>; padding:3px 10px; border-radius:20px; font-size:0.72rem; font-weight:800; letter-spacing:0.5px; text-transform:uppercase;">
                    <?= $ovrGrade ?>
                </span>
                <span class="badge badge-primary" style="font-weight:700; border:1px solid rgba(99,102,241,0.4);">
                    KATEGORI <?= htmlspecialchars($atlet['kelompok_usia']) ?>
                </span>
                <span class="badge badge-emerald" style="font-weight:700; border:1px solid rgba(52,211,153,0.4);">
                    <span style="width:6px; height:6px; background:#34d399; border-radius:50%; display:inline-block; margin-right:4px;"></span>
                    <?= htmlspecialchars($atlet['status_keanggotaan']) ?>
                </span>
            </div>

            <!-- NAMA ATLET -->
            <h1 style="font-family:'Outfit', sans-serif; font-size:1.9rem; font-weight:800; color:#fff; margin-bottom:8px; letter-spacing:-0.5px;">
                <?= htmlspecialchars($atlet['nama_lengkap']) ?>
            </h1>

            <!-- METRIK STRUKTURAL -->
            <div style="font-size:0.85rem; color:#cbd5e1; display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:1.25rem;">
                <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                    ⚽ <strong>Utama:</strong> <span style="color:#38bdf8; font-weight:700;"><?= htmlspecialchars($atlet['posisi_utama'] ?: '-') ?></span>
                </span>
                <?php if (!empty($atlet['posisi_sekunder']) && $atlet['posisi_sekunder'] !== '-'): ?>
                    <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(56,189,248,0.1); padding:4px 10px; border-radius:8px; border:1px solid rgba(56,189,248,0.25);">
                        🔄 <strong>Sekunder:</strong> <span style="color:#7dd3fc; font-weight:700;"><?= htmlspecialchars($atlet['posisi_sekunder']) ?></span>
                    </span>
                <?php endif; ?>
                <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                    ⚡ <strong>Kaki Dominan:</strong> <span style="color:#a78bfa; font-weight:700;"><?= htmlspecialchars($atlet['kaki_dominan'] ?: 'Kanan') ?></span>
                </span>
                <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1);">
                    📏 <strong>Fisik:</strong> <span style="color:#fbbf24; font-weight:700;"><?= $atlet['tinggi_badan'] ?> cm / <?= $atlet['berat_badan'] ?> kg</span>
                </span>
                <span style="display:inline-flex; align-items:center; gap:5px; background:rgba(251,191,36,0.12); padding:4px 10px; border-radius:8px; border:1px solid rgba(251,191,36,0.3);">
                    🏆 <strong>Turnamen:</strong> <span style="color:#fbbf24; font-weight:700;"><?= count($tournamentStats) ?> Event</span>
                </span>
            </div>

            <!-- GROUP TOMBOL AKSI OPERASIONAL -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <a href="../idcard.php?id=<?= $atlet['id'] ?>" class="btn btn-primary btn-sm" target="_blank" style="padding:0.65rem 1.1rem; box-shadow:0 4px 15px rgba(99,102,241,0.4);">
                    💳 Cetak Kartu ID
                </a>
                
                <?php if ($user['role'] === 'admin'): ?>
                    <a href="edit.php?id=<?= $atlet['id'] ?>" class="btn btn-secondary btn-sm" style="padding:0.65rem 1rem;">
                        ✏️ Edit Profil
                    </a>
                    <a href="hapus.php?id=<?= $atlet['id'] ?>" class="btn btn-secondary btn-sm" style="color:#f87171; border-color:rgba(244,63,94,0.3); padding:0.65rem 1rem;" onclick="return confirm('Apakah Anda yakin ingin menghapus atlet <?= htmlspecialchars(addslashes($atlet['nama_lengkap'])) ?>? Seluruh riwayat raport & SPP atlet ini akan terhapus.');">
                        🗑️ Hapus
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN: FOTO ATLET BESAR & BADGE OVR -->
        <div style="position:relative; flex-shrink:0;">
            <div style="width:135px; height:135px; border-radius:24px; background:linear-gradient(135deg, var(--primary), var(--secondary)); display:flex; align-items:center; justify-content:center; overflow:hidden; border:3.5px solid <?= $ovrColor ?>; box-shadow:0 12px 35px rgba(0,0,0,0.55), 0 0 25px <?= $ovrColor ?>45;">
                <?php if ($hasPhoto): ?>
                    <img src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Foto <?= htmlspecialchars($atlet['nama_lengkap']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <span style="font-size:2.8rem; font-weight:800; color:#fff;"><?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?></span>
                <?php endif; ?>
            </div>

            <!-- OVERALL RATING (OVR) BADGE PILL OVERLAY -->
            <div style="position:absolute; bottom:-12px; right:50%; transform:translateX(50%); background:rgba(15, 23, 42, 0.95); border:2px solid <?= $ovrColor ?>; color:<?= $ovrColor ?>; padding:3px 12px; border-radius:14px; font-family:'Outfit', sans-serif; font-weight:800; font-size:0.95rem; box-shadow:0 6px 15px rgba(0,0,0,0.5); display:flex; align-items:center; gap:5px; white-space:nowrap;">
                <span><?= $ovr ?></span>
                <span style="font-size:0.65rem; opacity:0.85; letter-spacing:0.5px;">OVR</span>
            </div>
        </div>

    </div>
</div>

<div class="grid-2">
    <!-- 1. RAPORT PERFORMA ATLET (DYNAMIC RADAR + SKILL BARS + CATATAN PELATIH) -->
    <div class="card" style="border: 1px solid rgba(99, 102, 241, 0.25);">
        <div class="card-header" style="border-bottom:1px solid var(--border-glass); padding-bottom:0.85rem; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h2 class="card-title" style="font-size:1.15rem; display:flex; align-items:center; gap:8px;">
                    <span>📊</span> Raport Performa Atlet
                </h2>
                <?php if ($evaluasi): ?>
                    <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                        Evaluasi Terakhir: <strong><?= date('d F Y', strtotime($evaluasi['tanggal_evaluasi'])) ?></strong>
                    </p>
                <?php endif; ?>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <?php if ($evaluasi && ($user['role'] === 'admin' || $user['role'] === 'pelatih')): ?>
                    <a href="../evaluasi/edit.php?id=<?= $evaluasi['id'] ?>" class="btn btn-secondary btn-sm" style="color:#fbbf24; border-color:rgba(251,191,36,0.4);">✏️ Edit Raport</a>
                <?php endif; ?>
                <?php if ($user['role'] === 'admin' || $user['role'] === 'pelatih'): ?>
                    <a href="../evaluasi/tambah.php?atlet_id=<?= $atlet['id'] ?>" class="btn btn-primary btn-sm" style="font-size:0.78rem;">➕ Input Evaluasi</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($evaluasi): ?>
            <?php
                $stPassing   = getScoreStyle($evaluasi['nilai_passing']);
                $stDribbling = getScoreStyle($evaluasi['nilai_dribbling']);
                $stShooting  = getScoreStyle($evaluasi['nilai_shooting']);
                $stTackling  = getScoreStyle($evaluasi['nilai_tackling']);
                $stStamina   = getScoreStyle($evaluasi['nilai_stamina']);
                $stDisiplin  = getScoreStyle($evaluasi['nilai_disiplin']);
            ?>

            <!-- RADAR SKILL CHART CONTAINER (COMPARISON OVERLAY) -->
            <div style="background:rgba(15,23,42,0.65); padding:1rem; border-radius:16px; border:1px solid var(--border-glass); margin-bottom:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem; flex-wrap:wrap; gap:6px;">
                    <span style="font-size:0.75rem; font-weight:700; color:var(--primary-light); text-transform:uppercase; letter-spacing:0.5px;">
                        🎯 Radar Perbandingan Skill vs Rata-Rata KU <?= htmlspecialchars($atlet['kelompok_usia']) ?>
                    </span>
                    <span class="badge" style="background:rgba(56,189,248,0.15); color:#38bdf8; border:1px solid rgba(56,189,248,0.3); font-size:0.7rem;">
                        VO2Max: <?= htmlspecialchars($evaluasi['vo2max'] ?: '-') ?>
                    </span>
                </div>
                <div style="position:relative; height:240px; width:100%;">
                    <canvas id="detailRadarChart"></canvas>
                </div>
            </div>

            <!-- BREAKDOWN SKILL BARS GRID -->
            <div style="display:grid; gap:0.85rem;">
                <div style="background:rgba(15,23,42,0.5); padding:0.75rem 0.9rem; border-radius:12px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                        <span>⚽ Passing & Ball Control</span>
                        <span style="color:<?= $stPassing['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_passing'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stPassing['barFill'] ?>"></div></div>
                </div>

                <div style="background:rgba(15,23,42,0.5); padding:0.75rem 0.9rem; border-radius:12px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                        <span>⚡ Dribbling & Agility</span>
                        <span style="color:<?= $stDribbling['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_dribbling'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stDribbling['barFill'] ?>"></div></div>
                </div>

                <div style="background:rgba(15,23,42,0.5); padding:0.75rem 0.9rem; border-radius:12px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                        <span>🎯 Shooting & Akurasi Tendangan</span>
                        <span style="color:<?= $stShooting['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_shooting'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stShooting['barFill'] ?>"></div></div>
                </div>

                <div style="background:rgba(15,23,42,0.5); padding:0.75rem 0.9rem; border-radius:12px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                        <span>🛡️ Tackling & Defending</span>
                        <span style="color:<?= $stTackling['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_tackling'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stTackling['barFill'] ?>"></div></div>
                </div>

                <div style="background:rgba(15,23,42,0.5); padding:0.75rem 0.9rem; border-radius:12px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                        <span>🫁 Stamina & Fisik</span>
                        <span style="color:<?= $stStamina['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_stamina'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stStamina['barFill'] ?>"></div></div>
                </div>

                <div style="background:rgba(15,23,42,0.5); padding:0.75rem 0.9rem; border-radius:12px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; font-size:0.82rem; font-weight:600; margin-bottom:4px;">
                        <span>🤝 Disiplin & Sikap</span>
                        <span style="color:<?= $stDisiplin['color'] ?>; font-weight:700;"><?= $evaluasi['nilai_disiplin'] ?> / 100</span>
                    </div>
                    <div class="skill-bar"><div class="skill-fill" style="<?= $stDisiplin['barFill'] ?>"></div></div>
                </div>
            </div>

            <!-- CATATAN PELATIH HIGH TECH QUOTE BOX -->
            <?php if (!empty($evaluasi['catatan_pelatih'])): ?>
                <div style="margin-top:1.25rem; background:linear-gradient(135deg, rgba(30,27,75,0.7), rgba(15,23,42,0.8)); padding:1rem 1.1rem; border-radius:14px; border:1px solid rgba(99,102,241,0.3); position:relative;">
                    <div style="display:flex; align-items:center; gap:6px; font-size:0.72rem; text-transform:uppercase; color:#818cf8; font-weight:700; margin-bottom:6px;">
                        <span>📋 Catatan & Rekomendasi Pelatih:</span>
                    </div>
                    <p style="font-size:0.88rem; color:#f1f5f9; font-style:italic; line-height:1.5; margin:0;">
                        "<?= htmlspecialchars($evaluasi['catatan_pelatih']) ?>"
                    </p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align:center; padding:2rem 1rem; color:var(--text-muted);">
                <div style="font-size:2.5rem; margin-bottom:0.5rem;">📝</div>
                <p style="margin-bottom:1rem;">Belum ada raport evaluasi tercatat untuk atlet ini.</p>
                <?php if ($user['role'] === 'admin' || $user['role'] === 'pelatih'): ?>
                    <a href="../evaluasi/tambah.php?atlet_id=<?= $atlet['id'] ?>" class="btn btn-primary btn-sm">➕ Input Evaluasi Sekarang</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 2. Detail Biodata, Dokumen Legalitas & Kontak Orang Tua -->
    <?php
    $umurFormatted = '-';
    if (!empty($atlet['tanggal_lahir'])) {
        $birthDate = new DateTime($atlet['tanggal_lahir']);
        $today = new DateTime('today');
        $ageY = $birthDate->diff($today)->y;
        $ageM = $birthDate->diff($today)->m;
        $umurFormatted = "{$ageY} Thn {$ageM} Bln";
    }
    ?>
    <div class="card" style="border: 1px solid rgba(99, 102, 241, 0.25);">
        <div class="card-header" style="border-bottom:1px solid var(--border-glass); padding-bottom:0.85rem; margin-bottom:1.25rem;">
            <div>
                <h2 class="card-title" style="font-size:1.15rem; display:flex; align-items:center; gap:8px;">
                    <span>👨‍👩‍👦</span> Informasi Legalitas & Wali
                </h2>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Dokumen verifikasi identitas & kontak darurat orang tua atlet</p>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:1.1rem;">

            <!-- MODULE 1: BIODATA & USIA -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.85rem;">
                <div style="background:rgba(15,23,42,0.65); padding:0.9rem 1rem; border-radius:14px; border:1px solid var(--border-glass); border-left:3.5px solid #38bdf8;">
                    <div style="font-size:0.72rem; color:#38bdf8; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">
                        🎂 Tempat & Tgl Lahir
                    </div>
                    <div style="font-size:0.92rem; color:#fff; font-weight:700;">
                        <?= htmlspecialchars($atlet['tempat_lahir'] ?: '-') ?>
                    </div>
                    <div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                        <?= !empty($atlet['tanggal_lahir']) ? date('d F Y', strtotime($atlet['tanggal_lahir'])) : '-' ?>
                        <?php if ($umurFormatted !== '-'): ?>
                            <span style="color:#a78bfa; font-weight:600;">(<?= $umurFormatted ?>)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="background:rgba(15,23,42,0.65); padding:0.9rem 1rem; border-radius:14px; border:1px solid var(--border-glass); border-left:3.5px solid #a78bfa;">
                    <div style="font-size:0.72rem; color:#a78bfa; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:3px;">
                        🆔 NISN / NIK Nomor
                    </div>
                    <div style="font-family:'Courier New', monospace; font-size:1.05rem; color:#a5b4fc; font-weight:700; margin-top:2px;">
                        <?= htmlspecialchars($atlet['nisn_nik'] ?: '-') ?>
                    </div>
                    <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;">Identitas Resmi Atlet</div>
                </div>
            </div>

            <!-- MODULE 2: DOKUMEN LEGALITAS (KK & AKTA) -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:0.85rem;">
                <!-- KK CARD -->
                <div style="background:rgba(15,23,42,0.65); padding:0.9rem 1rem; border-radius:14px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">📜 Kartu Keluarga (KK)</div>
                        <?php if (!empty($atlet['file_kk'])): ?>
                            <span style="background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.3); color:#34d399; padding:2px 7px; border-radius:10px; font-size:0.65rem; font-weight:700;">VERIFIED ✓</span>
                        <?php else: ?>
                            <span style="background:rgba(244,63,94,0.15); border:1px solid rgba(244,63,94,0.3); color:#f87171; padding:2px 7px; border-radius:10px; font-size:0.65rem; font-weight:700;">BELUM ADA</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-family:monospace; font-size:0.9rem; color:#fff; font-weight:600; margin-bottom:8px;">
                        <?= htmlspecialchars($atlet['no_kk'] ?: '-') ?>
                    </div>

                    <?php if (!empty($atlet['file_kk'])): ?>
                        <a href="../assets/docs/<?= htmlspecialchars($atlet['file_kk']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center; padding:0.4rem; font-size:0.75rem; color:#38bdf8; border-color:rgba(56,189,248,0.3);">
                            📂 Buka Berkas KK
                        </a>
                    <?php else: ?>
                        <div style="font-size:0.72rem; color:var(--text-muted); font-style:italic;">Berkas fisik belum diupload</div>
                    <?php endif; ?>
                </div>

                <!-- AKTA CARD -->
                <div style="background:rgba(15,23,42,0.65); padding:0.9rem 1rem; border-radius:14px; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;">📑 Akta Kelahiran</div>
                        <?php if (!empty($atlet['file_akta'])): ?>
                            <span style="background:rgba(52,211,153,0.15); border:1px solid rgba(52,211,153,0.3); color:#34d399; padding:2px 7px; border-radius:10px; font-size:0.65rem; font-weight:700;">VERIFIED ✓</span>
                        <?php else: ?>
                            <span style="background:rgba(244,63,94,0.15); border:1px solid rgba(244,63,94,0.3); color:#f87171; padding:2px 7px; border-radius:10px; font-size:0.65rem; font-weight:700;">BELUM ADA</span>
                        <?php endif; ?>
                    </div>

                    <div style="font-family:monospace; font-size:0.9rem; color:#fff; font-weight:600; margin-bottom:8px;">
                        <?= htmlspecialchars($atlet['no_akta'] ?: '-') ?>
                    </div>

                    <?php if (!empty($atlet['file_akta'])): ?>
                        <a href="../assets/docs/<?= htmlspecialchars($atlet['file_akta']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center; padding:0.4rem; font-size:0.75rem; color:#38bdf8; border-color:rgba(56,189,248,0.3);">
                            📂 Buka Berkas Akta
                        </a>
                    <?php else: ?>
                        <div style="font-size:0.72rem; color:var(--text-muted); font-style:italic;">Berkas fisik belum diupload</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MODULE 3: ORANG TUA / WALI -->
            <div style="background:rgba(15,23,42,0.65); padding:1rem; border-radius:14px; border:1px solid var(--border-glass);">
                <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-bottom:8px;">
                    👨‍👩‍👦 Data Wali & Orang Tua
                </div>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.75rem; margin-bottom:0.75rem;">
                    <div style="background:rgba(255,255,255,0.04); padding:0.6rem 0.8rem; border-radius:10px; border:1px solid rgba(255,255,255,0.06);">
                        <div style="font-size:0.7rem; color:#94a3b8;">👨 Nama Ayah Kandung:</div>
                        <div style="font-size:0.88rem; color:#fff; font-weight:700; margin-top:2px;"><?= htmlspecialchars($atlet['nama_ayah'] ?: '-') ?></div>
                    </div>

                    <div style="background:rgba(255,255,255,0.04); padding:0.6rem 0.8rem; border-radius:10px; border:1px solid rgba(255,255,255,0.06);">
                        <div style="font-size:0.7rem; color:#94a3b8;">👩 Nama Ibu Kandung:</div>
                        <div style="font-size:0.88rem; color:#fff; font-weight:700; margin-top:2px;"><?= htmlspecialchars($atlet['nama_ibu'] ?: '-') ?></div>
                    </div>
                </div>

                <!-- WHATSAPP DIRECT CALLOUT CARD -->
                <div style="background:linear-gradient(135deg, rgba(16,185,129,0.12), rgba(5,150,105,0.18)); border:1px solid rgba(52,211,153,0.3); padding:0.85rem 1rem; border-radius:12px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-size:0.7rem; color:#34d399; font-weight:700; text-transform:uppercase;">💬 WhatsApp Wali Siswa</div>
                        <div style="font-family:'Outfit', sans-serif; font-size:1.1rem; color:#fff; font-weight:800; margin-top:2px;">
                            <?= htmlspecialchars($atlet['no_whatsapp'] ?: '-') ?>
                        </div>
                    </div>

                    <?php if ($atlet['no_whatsapp']): ?>
                        <?php 
                            $waNum = preg_replace('/[^0-9]/', '', $atlet['no_whatsapp']);
                            if (strpos($waNum, '0') === 0) $waNum = '62' . substr($waNum, 1);
                            $waMessage = rawurlencode("Halo Wali dari Atlet " . $atlet['nama_lengkap'] . " (SSB Tamalanrea), ");
                        ?>
                        <a href="https://wa.me/<?= $waNum ?>?text=<?= $waMessage ?>" target="_blank" class="btn" style="background:#22c55e; color:#fff; font-weight:700; padding:0.5rem 1rem; font-size:0.8rem; border-radius:10px; box-shadow:0 4px 15px rgba(34,197,94,0.4); text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                            💬 Chat WA Wali
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MODULE 4: ALAMAT TINGGAL -->
            <div style="background:rgba(15,23,42,0.65); padding:0.9rem 1rem; border-radius:14px; border:1px solid var(--border-glass);">
                <div style="font-size:0.72rem; color:var(--text-muted); font-weight:700; text-transform:uppercase; margin-bottom:4px;">
                    📍 Alamat Domisili Tempat Tinggal
                </div>
                <div style="font-size:0.88rem; color:#cbd5e1; line-height:1.4; font-weight:500;">
                    <?= nl2br(htmlspecialchars($atlet['alamat'] ?: '-')) ?>
                </div>
            </div>

        </div>
    </div>

</div>

<!-- 3. Riwayat SPP & Turnamen Stats -->
<?php
$currentMonth = (int)date('n');
$currentYear  = (int)date('Y');
$totalSppLunas = 0;
$lunasCount = 0;
$tunggakanCount = 0;
$statusSppBulanIni = 'Belum Bayar';

foreach ($sppList as $s) {
    if ($s['status_bayar'] === 'Lunas') {
        $totalSppLunas += (int)$s['jumlah'];
        $lunasCount++;
    } else {
        $tunggakanCount++;
    }
    if ((int)$s['bulan'] === $currentMonth && (int)$s['tahun'] === $currentYear) {
        $statusSppBulanIni = $s['status_bayar'];
    }
}
?>

<div class="grid-2">
    <!-- DYNAMIC RIWAYAT SPP CARD -->
    <div class="card" style="border: 1px solid rgba(99, 102, 241, 0.25);">
        <div class="card-header" style="border-bottom:1px solid var(--border-glass); padding-bottom:0.85rem; margin-bottom:1.1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h2 class="card-title" style="font-size:1.15rem; display:flex; align-items:center; gap:8px;">
                    <span>💳</span> Riwayat Pembayaran SPP
                </h2>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Rekapitulasi iuran bulanan & transaksi historis</p>
            </div>
        </div>

        <!-- SUMMARY MINI TILES -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap:0.75rem; margin-bottom:1.25rem;">
            <div style="background:rgba(16,185,129,0.12); border:1px solid rgba(52,211,153,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.7rem; color:#34d399; font-weight:700; text-transform:uppercase;">SPP Bulan Ini</div>
                <div style="font-family:'Outfit', sans-serif; font-size:0.95rem; font-weight:800; color:<?= ($statusSppBulanIni === 'Lunas') ? '#34d399' : '#f87171' ?>; margin-top:2px;">
                    <?= ($statusSppBulanIni === 'Lunas') ? '✓ LUNAS' : '⚠️ BELUM BAYAR' ?>
                </div>
            </div>

            <div style="background:rgba(99,102,241,0.12); border:1px solid rgba(99,102,241,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.7rem; color:#818cf8; font-weight:700; text-transform:uppercase;">Total Terbayar</div>
                <div style="font-family:'Outfit', sans-serif; font-size:0.95rem; font-weight:800; color:#fff; margin-top:2px;">
                    Rp <?= number_format($totalSppLunas, 0, ',', '.') ?>
                </div>
            </div>

            <div style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.7rem; color:#fbbf24; font-weight:700; text-transform:uppercase;">Bulan Lunas</div>
                <div style="font-family:'Outfit', sans-serif; font-size:0.95rem; font-weight:800; color:#fff; margin-top:2px;">
                    <?= $lunasCount ?> <span style="font-size:0.75rem; color:var(--text-muted);">Bulan</span>
                </div>
            </div>
        </div>

        <!-- DYNAMIC TRANSACTION LIST / TABLE -->
        <?php if (!empty($sppList)): ?>
            <div style="overflow-x:auto;">
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="font-size:0.75rem; text-transform:uppercase;">Periode Tagihan</th>
                            <th style="font-size:0.75rem; text-transform:uppercase;">Nominal</th>
                            <th style="font-size:0.75rem; text-transform:uppercase;">Status Bayar</th>
                            <th style="font-size:0.75rem; text-transform:uppercase;">Tgl Pembayaran</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $bulanMap = [1=>'Januari', 2=>'Februari', 3=>'Maret', 4=>'April', 5=>'Mei', 6=>'Juni', 7=>'Juli', 8=>'Agustus', 9=>'September', 10=>'Oktober', 11=>'November', 12=>'Desember'];
                        foreach ($sppList as $spp): 
                            $isLunas = ($spp['status_bayar'] === 'Lunas');
                        ?>
                            <tr style="transition:all 0.2s ease;">
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="width:32px; height:32px; border-radius:8px; background:<?= $isLunas ? 'rgba(52,211,153,0.15)' : 'rgba(244,63,94,0.15)' ?>; color:<?= $isLunas ? '#34d399' : '#f87171' ?>; display:inline-flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0;">
                                            <?= $isLunas ? '✓' : '⚠️' ?>
                                        </span>
                                        <div>
                                            <strong style="color:#fff; font-size:0.88rem;"><?= $bulanMap[$spp['bulan']] ?> <?= $spp['tahun'] ?></strong>
                                            <div style="font-size:0.72rem; color:var(--text-muted);">SPP Bulanan SSB</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family:'Outfit', sans-serif; font-weight:700; color:#fff; font-size:0.9rem;">
                                        Rp <?= number_format($spp['jumlah'], 0, ',', '.') ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isLunas): ?>
                                        <span class="badge badge-emerald" style="font-weight:700; font-size:0.72rem; border:1px solid rgba(52,211,153,0.4);">
                                            ✓ LUNAS
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-rose" style="font-weight:700; font-size:0.72rem; border:1px solid rgba(244,63,94,0.4);">
                                            ⚠️ BELUM BAYAR
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.82rem; color:<?= $isLunas ? '#cbd5e1' : 'var(--text-muted)' ?>;">
                                        <?= $spp['tanggal_bayar'] ? date('d M Y', strtotime($spp['tanggal_bayar'])) : '-' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:2rem 1rem; color:var(--text-muted);">
                <div style="font-size:2.2rem; margin-bottom:0.5rem;">💳</div>
                <p>Belum ada riwayat transaksi SPP untuk atlet ini.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- TOURNAMENT PERFORMANCE STATS CARD -->
    <?php
    $totalMain = 0;
    $totalGol = 0;
    $totalAssist = 0;
    $totalKuning = 0;
    $totalMerah = 0;

    foreach ($tournamentStats as $ts) {
        $totalMain += (int)$ts['main'];
        $totalGol += (int)$ts['gol'];
        $totalAssist += (int)$ts['assist'];
        $totalKuning += (int)$ts['kartu_kuning'];
        $totalMerah += (int)$ts['kartu_merah'];
    }
    $totalGoalInvolvement = $totalGol + $totalAssist;
    $gaRatio = ($totalMain > 0) ? round($totalGoalInvolvement / $totalMain, 2) : 0;
    ?>
    <div class="card" style="border: 1px solid rgba(99, 102, 241, 0.25);">
        <div class="card-header" style="border-bottom:1px solid var(--border-glass); padding-bottom:0.85rem; margin-bottom:1.1rem; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <h2 class="card-title" style="font-size:1.15rem; display:flex; align-items:center; gap:8px;">
                    <span>🏆</span> Statistik Pertandingan & Turnamen
                </h2>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Akumulasi kontribusi gol, assist, dan penampilan turnamen</p>
            </div>
        </div>

        <!-- SUMMARY MINI TILES -->
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap:0.75rem; margin-bottom:1.25rem;">
            <div style="background:rgba(56,189,248,0.12); border:1px solid rgba(56,189,248,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.68rem; color:#38bdf8; font-weight:700; text-transform:uppercase;">Main (Apps)</div>
                <div style="font-family:'Outfit', sans-serif; font-size:1.05rem; font-weight:800; color:#fff; margin-top:2px;">
                    <?= $totalMain ?> <span style="font-size:0.7rem; color:var(--text-muted);">Match</span>
                </div>
            </div>

            <div style="background:rgba(52,211,153,0.12); border:1px solid rgba(52,211,153,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.68rem; color:#34d399; font-weight:700; text-transform:uppercase;">Total Gol ⚽</div>
                <div style="font-family:'Outfit', sans-serif; font-size:1.05rem; font-weight:800; color:#34d399; margin-top:2px;">
                    <?= $totalGol ?>
                </div>
            </div>

            <div style="background:rgba(167,139,250,0.12); border:1px solid rgba(167,139,250,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.68rem; color:#a78bfa; font-weight:700; text-transform:uppercase;">Total Assist 🎯</div>
                <div style="font-family:'Outfit', sans-serif; font-size:1.05rem; font-weight:800; color:#a78bfa; margin-top:2px;">
                    <?= $totalAssist ?>
                </div>
            </div>

            <div style="background:rgba(251,191,36,0.12); border:1px solid rgba(251,191,36,0.3); padding:0.75rem; border-radius:12px; text-align:center;">
                <div style="font-size:0.68rem; color:#fbbf24; font-weight:700; text-transform:uppercase;">G+A / Match</div>
                <div style="font-family:'Outfit', sans-serif; font-size:1.05rem; font-weight:800; color:#fbbf24; margin-top:2px;">
                    <?= $gaRatio ?>
                </div>
            </div>
        </div>

        <!-- DYNAMIC TOURNAMENT STATS TABLE -->
        <?php if (!empty($tournamentStats)): ?>
            <div style="overflow-x:auto;">
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="font-size:0.75rem; text-transform:uppercase;">Nama Turnamen</th>
                            <th style="font-size:0.75rem; text-transform:uppercase; text-align:center;">Tampil</th>
                            <th style="font-size:0.75rem; text-transform:uppercase; text-align:center;">Gol ⚽</th>
                            <th style="font-size:0.75rem; text-transform:uppercase; text-align:center;">Assist 🎯</th>
                            <th style="font-size:0.75rem; text-transform:uppercase; text-align:center;">Kartu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tournamentStats as $t): ?>
                            <tr style="transition:all 0.2s ease;">
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span style="width:32px; height:32px; border-radius:8px; background:rgba(251,191,36,0.15); color:#fbbf24; display:inline-flex; align-items:center; justify-content:center; font-size:0.9rem; flex-shrink:0;">
                                            🏆
                                        </span>
                                        <div>
                                            <strong style="color:#fff; font-size:0.88rem;"><?= htmlspecialchars($t['nama_turnamen']) ?></strong>
                                            <div style="font-size:0.72rem; color:var(--text-muted);">
                                                📍 <?= htmlspecialchars($t['lokasi'] ?: 'Makassar') ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:center;">
                                    <span class="badge" style="background:rgba(255,255,255,0.08); color:#fff; font-weight:700; font-size:0.78rem;">
                                        <?= $t['main'] ?> Match
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-family:'Outfit', sans-serif; font-weight:800; color:#34d399; font-size:0.95rem;">
                                        <?= $t['gol'] ?> ⚽
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <span style="font-family:'Outfit', sans-serif; font-weight:800; color:#a78bfa; font-size:0.95rem;">
                                        <?= $t['assist'] ?> 🎯
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:flex; align-items:center; justify-content:center; gap:6px; font-size:0.75rem; font-weight:700;">
                                        <span style="background:rgba(251,191,36,0.2); color:#fbbf24; padding:2px 6px; border-radius:6px; border:1px solid rgba(251,191,36,0.3);">
                                            🟨 <?= $t['kartu_kuning'] ?>
                                        </span>
                                        <span style="background:rgba(244,63,94,0.2); color:#f87171; padding:2px 6px; border-radius:6px; border:1px solid rgba(244,63,94,0.3);">
                                            🟥 <?= $t['kartu_merah'] ?>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align:center; padding:2rem 1rem; color:var(--text-muted);">
                <div style="font-size:2.2rem; margin-bottom:0.5rem;">🏆</div>
                <p>Belum ada catatan statistik turnamen untuk atlet ini.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ID Card Modal Container (Hidden until printed) -->
<div style="display:none;">
    <div id="idCardPlayer" class="id-card">
        <div class="id-card-header">
            <h3 style="font-family:'Outfit'; font-size:1.2rem; font-weight:800; color:#fff; letter-spacing:1px;">SSB TAMALANREA</h3>
            <div style="font-size:0.75rem; color:#818cf8; font-weight:600;">KARTU TANDA ANGGOTA ATLET</div>
        </div>

        <div style="width:90px; height:90px; border-radius:50%; background:#6366f1; color:#fff; font-size:2rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin:0 auto 1rem auto; border:3px solid #818cf8; overflow:hidden;">
            <?php if ($hasPhoto): ?>
                <img src="../assets/img/atlet/<?= htmlspecialchars($atlet['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
            <?php else: ?>
                <?= strtoupper(substr($atlet['nama_lengkap'], 0, 2)) ?>
            <?php endif; ?>
        </div>

        <h2 style="font-family:'Outfit'; font-size:1.3rem; font-weight:700; color:#fff; margin-bottom:4px;"><?= htmlspecialchars($atlet['nama_lengkap']) ?></h2>
        <div style="font-size:0.85rem; color:#38bdf8; font-weight:600; margin-bottom:1rem;"><?= htmlspecialchars($atlet['posisi_utama']) ?> &bull; <?= htmlspecialchars($atlet['kelompok_usia']) ?></div>

        <div style="text-align:left; background:rgba(255,255,255,0.08); padding:0.85rem; border-radius:12px; font-size:0.8rem; line-height:1.6; margin-bottom:1rem;">
            <div><strong>NISN/NIK:</strong> <?= htmlspecialchars($atlet['nisn_nik'] ?: '-') ?></div>
            <div><strong>Tgl Lahir:</strong> <?= date('d/m/Y', strtotime($atlet['tanggal_lahir'])) ?></div>
            <div><strong>Kaki Dominan:</strong> <?= htmlspecialchars($atlet['kaki_dominan']) ?></div>
        </div>

        <div style="font-size:0.65rem; color:#94a3b8; text-transform:uppercase; letter-spacing:1px;">
            Terverifikasi &bull; SSB Tamalanrea Makassar
        </div>
    </div>
<?php if ($evaluasi): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctxRadar = document.getElementById('detailRadarChart');
    if (ctxRadar) {
        new Chart(ctxRadar, {
            type: 'radar',
            data: {
                labels: ['Passing & Control', 'Dribbling & Agility', 'Shooting & Akurasi', 'Tackling & Defending', 'Stamina & Fisik'],
                datasets: [
                    {
                        label: 'Skor <?= htmlspecialchars(addslashes($atlet['nama_lengkap'])) ?>',
                        data: [
                            <?= (int)$evaluasi['nilai_passing'] ?>,
                            <?= (int)$evaluasi['nilai_dribbling'] ?>,
                            <?= (int)$evaluasi['nilai_shooting'] ?>,
                            <?= (int)$evaluasi['nilai_tackling'] ?>,
                            <?= (int)$evaluasi['nilai_stamina'] ?>
                        ],
                        backgroundColor: 'rgba(99, 102, 241, 0.35)',
                        borderColor: '#818cf8',
                        borderWidth: 3,
                        pointBackgroundColor: '#818cf8',
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: '#818cf8'
                    },
                    {
                        label: 'Rata-rata Kategori <?= htmlspecialchars(addslashes($atlet['kelompok_usia'])) ?>',
                        data: [
                            <?= $avgPassing ?>,
                            <?= $avgDribbling ?>,
                            <?= $avgShooting ?>,
                            <?= $avgTackling ?>,
                            <?= $avgStamina ?>
                        ],
                        backgroundColor: 'rgba(251, 191, 36, 0.18)',
                        borderColor: '#fbbf24',
                        borderWidth: 2.5,
                        borderDash: [4, 4],
                        pointBackgroundColor: '#fbbf24',
                        pointBorderColor: '#fff'
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
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: '#cbd5e1',
                            font: { size: 11, weight: '600' },
                            usePointStyle: true,
                            padding: 12
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php include_once __DIR__ . '/../includes/footer.php'; ?>
