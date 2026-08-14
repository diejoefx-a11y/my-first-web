<?php
$pageTitle = "Dashboard Utama & Statistik";
require_once __DIR__ . '/header.php';

// Set Timezone to Makassar (WITA / UTC+8)
date_default_timezone_set('Asia/Makassar');

$db = get_db();

// 1. Calculate Stats
// Total KK
$totalKK = $db->query("SELECT COUNT(*) FROM families")->fetchColumn();

// Total Jiwa
$totalMembers = $db->query("SELECT COUNT(*) FROM family_members")->fetchColumn();

// Status breakdown
$totalPending = $db->query("SELECT COUNT(*) FROM families WHERE status_verifikasi = 'pending'")->fetchColumn();
$totalVerified = $db->query("SELECT COUNT(*) FROM families WHERE status_verifikasi = 'terverifikasi'")->fetchColumn();
$totalRejected = $db->query("SELECT COUNT(*) FROM families WHERE status_verifikasi = 'ditolak'")->fetchColumn();

// Total RT Count
$totalRT = $db->query("SELECT COUNT(DISTINCT rt) FROM families WHERE rt != ''")->fetchColumn();

// Percentage of verified
$percentVerified = $totalKK > 0 ? round(($totalVerified / $totalKK) * 100) : 0;
$avgMember = $totalKK > 0 ? round(($totalMembers / $totalKK), 1) : 0;

// 2. Fetch RT Distribution
$stmtRTDist = $db->query("
    SELECT rt, COUNT(*) as count 
    FROM families 
    WHERE rt != '' 
    GROUP BY rt 
    ORDER BY count DESC 
    LIMIT 5
");
$rtDistribution = $stmtRTDist->fetchAll();

// 3. Recent Registrations
$stmtRecent = $db->query("
    SELECT 
        f.*, 
        g.nama_kelompok,
        (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as total_anggota
    FROM families f 
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    ORDER BY f.created_at DESC 
    LIMIT 6
");
$recentFamilies = $stmtRecent->fetchAll();

// Total Groups
$totalGroups = $db->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();

// 4. All Families for Map
$stmtMap = $db->query("SELECT id, no_kk, nama_kepala, no_hp, alamat_lengkap, rt, rw, latitude, longitude, status_verifikasi FROM families");
$mapFamilies = $stmtMap->fetchAll();

// Greeting logic based on Makassar Time (WITA)
$hour = (int)date('H');
$greeting = "Selamat Datang";
$greetingIcon = "✨";
$greetingSub = "Sistem pemetaan digital siap melayani warga.";

if ($hour >= 4 && $hour < 11) {
    $greeting = "Selamat Pagi";
    $greetingIcon = "🌅";
    $greetingSub = "Awali hari dengan pelayanan terbaik untuk pendataan warga.";
} else if ($hour >= 11 && $hour < 15) {
    $greeting = "Selamat Siang";
    $greetingIcon = "☀️";
    $greetingSub = "Semangat melanjutkan verifikasi data keluarga & pemetaan lokasi.";
} else if ($hour >= 15 && $hour < 18) {
    $greeting = "Selamat Sore";
    $greetingIcon = "🌇";
    $greetingSub = "Pantau perkembangan titik rumah & laporan warga sore ini.";
} else {
    $greeting = "Selamat Malam";
    $greetingIcon = "🌙";
    $greetingSub = "Sistem informasi PKB tetap aktif memantau data wilayah secara digital.";
}

// Indonesian Day and Date format
$daysIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$monthsIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$currentDay = $daysIndo[date('l')];
$currentDateFormatted = $currentDay . ', ' . date('d') . ' ' . $monthsIndo[(int)date('m')] . ' ' . date('Y');
?>

<!-- 1. DYNAMIC PURPLE LIGHT WELCOME COMPONENT (MAKASSAR / WITA TIME) -->
<style>
.welcome-hero-card {
    background: linear-gradient(135deg, #5b21b6 0%, #7c3aed 50%, #4c1d95 100%);
    border-radius: 20px;
    padding: 2rem 2.25rem;
    color: #ffffff;
    margin-bottom: 2rem;
    box-shadow: 0 12px 30px -4px rgba(109, 40, 217, 0.35);
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 1.75rem;
    align-items: center;
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(233, 213, 255, 0.25);
}

.welcome-hero-card::before {
    content: '';
    position: absolute;
    top: -60px;
    right: -60px;
    width: 260px;
    height: 260px;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.35) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.welcome-hero-card::after {
    content: '';
    position: absolute;
    bottom: -40px;
    left: 20%;
    width: 180px;
    height: 180px;
    background: radial-gradient(circle, rgba(233, 213, 255, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.welcome-badge-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.85rem;
}

.welcome-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    background: rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.775rem;
    font-weight: 700;
    color: #f3e8ff;
    letter-spacing: 0.02em;
}

.welcome-title {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.25;
    margin-bottom: 0.4rem;
    color: #ffffff;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.welcome-desc {
    color: #e9d5ff;
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1.25rem;
    max-width: 580px;
}

/* Time & Clock Box (Makassar WITA) */
.clock-widget-box {
    background: rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(12px);
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    position: relative;
    z-index: 2;
}

.clock-time-display {
    font-size: 2.2rem;
    font-weight: 800;
    font-family: monospace;
    letter-spacing: 0.05em;
    color: #ffffff;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
    line-height: 1.1;
    margin-bottom: 0.25rem;
}

.clock-tz-badge {
    background: #10b981;
    color: #ffffff;
    font-size: 0.7rem;
    font-weight: 800;
    padding: 0.15rem 0.55rem;
    border-radius: 9999px;
    text-transform: uppercase;
    display: inline-block;
    margin-left: 0.35rem;
    vertical-align: middle;
}

.clock-date-text {
    font-size: 0.85rem;
    color: #f3e8ff;
    font-weight: 600;
    margin-bottom: 1rem;
}

.welcome-btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    width: 100%;
}

.welcome-btn-primary {
    background: #ffffff;
    color: #6d28d9 !important;
    font-weight: 800;
    padding: 0.65rem 1.2rem;
    border-radius: 12px;
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    flex: 1;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
    transition: all 0.25s ease;
}

.welcome-btn-primary:hover {
    background: #fdfdfd;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.18);
}

.welcome-btn-secondary {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff !important;
    font-weight: 700;
    padding: 0.65rem 1.2rem;
    border-radius: 12px;
    text-decoration: none;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: 1px solid rgba(255, 255, 255, 0.35);
    flex: 1;
    transition: all 0.25s ease;
}

.welcome-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

/* Responsif Mobile & Tablet */
@media (max-width: 992px) {
    .welcome-hero-card {
        grid-template-columns: 1fr;
        padding: 1.75rem;
        gap: 1.5rem;
    }
    .welcome-title {
        font-size: 1.5rem;
    }
}

@media (max-width: 600px) {
    .welcome-hero-card {
        padding: 1.25rem;
        border-radius: 16px;
    }
    .welcome-title {
        font-size: 1.3rem;
    }
    .clock-time-display {
        font-size: 1.8rem;
    }
    .welcome-btn-group {
        flex-direction: column;
    }
}
</style>

<div class="welcome-hero-card">
    
    <!-- Left Section: Greeting & Info -->
    <div>
        <div class="welcome-badge-row">
            <span class="welcome-pill">
                <span style="color: #4ade80;">●</span> Zona Waktu: Makassar (WITA)
            </span>
            <span class="welcome-pill">
                📍 Wilayah PKB Terpadu
            </span>
            <span class="welcome-pill" style="background: rgba(16, 185, 129, 0.25); border-color: rgba(16, 185, 129, 0.4); color: #86efac;">
                🛡️ Sesi Aman (Auto-Lock 30m)
            </span>
            <?php if ($totalPending > 0): ?>
                <span class="welcome-pill" style="background: rgba(245, 158, 11, 0.3); border-color: rgba(245, 158, 11, 0.5); color: #fef08a;">
                    ⏳ <?= $totalPending ?> Data Perlu Diverifikasi
                </span>
            <?php endif; ?>
        </div>

        <h1 class="welcome-title">
            <span><?= $greetingIcon ?></span>
            <span><?= $greeting ?>, <?= htmlspecialchars($user['nama']) ?>!</span>
        </h1>

        <p class="welcome-desc">
            <?= $greetingSub ?> Saat ini ada <strong><?= number_format($totalKK) ?> Kartu Keluarga</strong> terdaftar dengan total <strong><?= number_format($totalMembers) ?> jiwa</strong> yang tersebar di <strong><?= $totalRT ?> RT aktif</strong>.
        </p>

        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="background: rgba(255, 255, 255, 0.12); padding: 0.4rem 0.85rem; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.825rem;">
                <span style="color: #e9d5ff;">Status Verifikasi:</span>
                <strong style="color: #4ade80; margin-left: 4px;"><?= $percentVerified ?>% Lengkap</strong>
            </div>
            <div style="background: rgba(255, 255, 255, 0.12); padding: 0.4rem 0.85rem; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.825rem;">
                <span style="color: #e9d5ff;">Rata-rata Anggota:</span>
                <strong style="color: #ffffff; margin-left: 4px;"><?= $avgMember ?> Jiwa / KK</strong>
            </div>
            <div style="background: rgba(255, 255, 255, 0.12); padding: 0.4rem 0.85rem; border-radius: 10px; border: 1px solid rgba(255, 255, 255, 0.2); font-size: 0.825rem;">
                <span style="color: #e9d5ff;">Protokol Keamanan:</span>
                <strong style="color: #6ee7b7; margin-left: 4px;">Anti-Hijack & Brute Force Protected</strong>
            </div>
        </div>
    </div>

    <!-- Right Section: Live Makassar Clock Widget & Quick Action Buttons -->
    <div class="clock-widget-box">
        <div style="font-size: 0.75rem; color: #e9d5ff; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.35rem;">
            Waktu Makassar (WITA)
        </div>
        <div class="clock-time-display">
            <span id="makassar-live-clock">--:--:--</span>
            <span class="clock-tz-badge">WITA</span>
        </div>
        <div class="clock-date-text" id="makassar-live-date">
            <?= $currentDateFormatted ?>
        </div>

        <div class="welcome-btn-group">
            <a href="peta.php" class="welcome-btn-primary">
                <span>🗺️</span> Peta Sebaran
            </a>
            <a href="../jemaat/pasangtitik.php" target="_blank" class="welcome-btn-secondary">
                <span>📝</span> Form Pendataan KK
            </a>
        </div>
    </div>

</div>

<!-- Real-time Live Clock Script (Makassar WITA UTC+8) -->
<script>
function updateMakassarClock() {
    const clockElem = document.getElementById('makassar-live-clock');
    const dateElem = document.getElementById('makassar-live-date');
    if (!clockElem) return;

    // Get current time in Asia/Makassar timezone
    const now = new Date();
    const makassarTimeStr = now.toLocaleTimeString('en-US', {
        timeZone: 'Asia/Makassar',
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    clockElem.textContent = makassarTimeStr;

    if (dateElem) {
        const dateOptions = {
            timeZone: 'Asia/Makassar',
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };
        dateElem.textContent = now.toLocaleDateString('id-ID', dateOptions);
    }
}
setInterval(updateMakassarClock, 1000);
updateMakassarClock();
</script>

<!-- 2. STATS CARDS GRID -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-purple">
            <span>👨‍👩‍👧‍👦</span>
        </div>
        <div class="stat-info">
            <h4>Total Kepala Keluarga</h4>
            <div class="stat-number"><?= number_format($totalKK) ?></div>
            <div class="stat-subtext" style="color: #7c3aed; font-weight: 600;">
                ✓ <?= $percentVerified ?>% terverifikasi
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-indigo">
            <span>👥</span>
        </div>
        <div class="stat-info">
            <h4>Total Jiwa / Anggota</h4>
            <div class="stat-number"><?= number_format($totalMembers) ?></div>
            <div class="stat-subtext">
                Rata-rata <?= $avgMember ?> jiwa/KK
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-amber">
            <span>⏳</span>
        </div>
        <div class="stat-info">
            <h4>Menunggu Verifikasi</h4>
            <div class="stat-number" style="color: #d97706;"><?= number_format($totalPending) ?></div>
            <div class="stat-subtext" style="color: #d97706;">
                Perlu ditinjau pengurus
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-emerald">
            <span>✅</span>
        </div>
        <div class="stat-info">
            <h4>Data Terverifikasi</h4>
            <div class="stat-number" style="color: #059669;"><?= number_format($totalVerified) ?></div>
            <div class="stat-subtext" style="color: #059669;">
                Status valid & terpetakan
            </div>
        </div>
    </div>
</div>

<!-- 3. DASHBOARD MAIN GRID (MAP & DISTRIBUTION) -->
<div class="dash-grid">
    
    <!-- Left: Live Overview Map -->
    <div class="card-purple">
        <div class="card-title-header">
            <h3><span>🗺️</span> Peta Sebaran Titik Rumah Real-Time</h3>
            <div style="display: flex; gap: 0.4rem;">
                <button type="button" class="btn btn-outline btn-sm map-filter-btn active" data-filter="all" style="padding: 0.25rem 0.65rem; font-size: 0.75rem;">Semua</button>
                <button type="button" class="btn btn-outline btn-sm map-filter-btn" data-filter="terverifikasi" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; color: #059669;">Terverifikasi (Hijau)</button>
                <button type="button" class="btn btn-outline btn-sm map-filter-btn" data-filter="pending" style="padding: 0.25rem 0.65rem; font-size: 0.75rem; color: #dc2626;">Belum Verifikasi (Merah)</button>
            </div>
        </div>

        <div id="overview-map"></div>

        <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center; font-size: 0.825rem;">
            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <span style="color: #059669; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #10b981; box-shadow: 0 0 6px #10b981; display: inline-block;"></span>
                    Terverifikasi (Hijau)
                </span>
                <span style="color: #dc2626; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 8px #ef4444; display: inline-block;"></span>
                    Belum Terverifikasi (Merah Metalik)
                </span>
                <span style="color: #64748b; font-weight: 700; display: inline-flex; align-items: center; gap: 5px;">
                    <span style="width: 10px; height: 10px; border-radius: 50%; background: #64748b; display: inline-block;"></span>
                    Ditolak
                </span>
            </div>
            <a href="peta.php" style="color: #7c3aed; font-weight: 700; text-decoration: none;">
                Buka Peta Master Lengkap &rarr;
            </a>
        </div>
    </div>

    <!-- Right: RT Distribution & Quick Actions -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        
        <!-- RT Distribution Card -->
        <div class="card-purple">
            <div class="card-title-header">
                <h3><span>📍</span> Sebaran per Wilayah RT</h3>
                <span style="font-size: 0.75rem; font-weight: 700; color: #7c3aed;"><?= $totalRT ?> RT Aktif</span>
            </div>

            <div class="progress-list">
                <?php if (empty($rtDistribution)): ?>
                    <p style="font-size: 0.85rem; color: var(--adm-text-muted);">Belum ada data RT yang tercatat.</p>
                <?php else: ?>
                    <?php foreach ($rtDistribution as $dist): ?>
                        <?php 
                            $pct = $totalKK > 0 ? round(($dist['count'] / $totalKK) * 100) : 0;
                        ?>
                        <div>
                            <div class="progress-item-header">
                                <span>RT <?= htmlspecialchars($dist['rt']) ?></span>
                                <span><?= $dist['count'] ?> KK (<?= $pct ?>%)</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= $pct ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Action Tiles -->
        <div class="card-purple">
            <div class="card-title-header" style="margin-bottom: 0.75rem;">
                <h3><span>⚡</span> Aksi Cepat</h3>
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                <a href="kelompok.php" class="action-tile">
                    <div class="action-tile-icon" style="color: #7c3aed; background: #ede9fe;">🏷️</div>
                    <div class="action-tile-text">
                        <h5>Kelola Kelompok</h5>
                        <small>Ketua, sekretaris, berita, & foto</small>
                    </div>
                </a>
                <a href="keluarga.php" class="action-tile">
                    <div class="action-tile-icon" style="color: #4f46e5; background: #e0e7ff;">📋</div>
                    <div class="action-tile-text">
                        <h5>Kelola Data KK</h5>
                        <small>Lihat, edit, dan verifikasi warga</small>
                    </div>
                </a>
                <a href="export_excel.php" class="action-tile">
                    <div class="action-tile-icon" style="color: #059669; background: #d1fae5;">📥</div>
                    <div class="action-tile-text">
                        <h5>Ekspor Laporan Excel</h5>
                        <small>Unduh seluruh data keluarga</small>
                    </div>
                </a>
                <a href="../index.php" target="_blank" class="action-tile">
                    <div class="action-tile-icon" style="color: #2563eb; background: #dbeafe;">🌐</div>
                    <div class="action-tile-text">
                        <h5>Portal Berita Publik</h5>
                        <small>Pratinjau tampilan warga</small>
                    </div>
                </a>
            </div>
        </div>

    </div>

</div>

<!-- 4. RECENT REGISTRATIONS TABLE (PURPLE LIGHT) -->
<div class="data-table-container">
    <div class="table-header-filter">
        <div>
            <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--adm-secondary);">
                <span>📝</span> Pendaftaran Kartu Keluarga Terbaru
            </h3>
            <small style="color: var(--adm-text-muted);">Menampilkan 6 pendaftaran data keluarga terakhir yang masuk ke sistem</small>
        </div>
        <a href="keluarga.php" class="btn btn-outline btn-sm" style="border-radius: var(--adm-radius-full); font-weight: 700; color: #7c3aed; border-color: #ddd6fe;">
            Lihat Semua Data (<?= number_format($totalKK) ?>) &rarr;
        </a>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>No. KK</th>
                    <th>Nama Kepala Keluarga</th>
                    <th>Kelompok</th>
                    <th>WhatsApp</th>
                    <th>RT / RW</th>
                    <th>Anggota</th>
                    <th>Titik Koordinat</th>
                    <th>Status</th>
                    <th>Waktu Daftar</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentFamilies)): ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 2.5rem; color: var(--adm-text-muted);">
                            Belum ada data pendaftaran keluarga.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentFamilies as $f): ?>
                        <tr>
                            <td>
                                <a href="detail.php?id=<?= $f['id'] ?>" style="font-family: monospace; font-weight: 800; color: #7c3aed; text-decoration: none;">
                                    <?= htmlspecialchars($f['no_kk']) ?>
                                </a>
                            </td>
                            <td>
                                <div style="font-weight: 700; color: var(--adm-secondary);"><?= htmlspecialchars($f['nama_kepala']) ?></div>
                                <small style="color: var(--adm-text-muted);">NIK: <?= htmlspecialchars($f['nik_kepala']) ?></small>
                            </td>
                            <td>
                                <?php if (!empty($f['nama_kelompok'])): ?>
                                    <span style="background: #ede9fe; color: #6d28d9; padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.725rem; font-weight: 800; border: 1px solid #ddd6fe;">
                                        <?= htmlspecialchars($f['nama_kelompok']) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: var(--adm-text-muted); font-size: 0.75rem;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="https://wa.me/<?= preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $f['no_hp'])) ?>" target="_blank" style="color: #059669; text-decoration: none; font-weight: 700;">
                                    💬 <?= htmlspecialchars($f['no_hp']) ?>
                                </a>
                            </td>
                            <td><strong>RT <?= htmlspecialchars($f['rt']) ?></strong> / RW <?= htmlspecialchars($f['rw']) ?></td>
                            <td><strong><?= $f['total_anggota'] ?></strong> Jiwa</td>
                            <td>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= $f['latitude'] ?>,<?= $f['longitude'] ?>" target="_blank" class="coords-badge" style="text-decoration: none; font-size: 0.75rem; background: #ede9fe; color: #6d28d9; border-color: #ddd6fe;">
                                    📍 <?= number_format($f['latitude'], 4) ?>, <?= number_format($f['longitude'], 4) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge-status badge-<?= $f['status_verifikasi'] ?>">
                                    <?= ucfirst($f['status_verifikasi']) ?>
                                </span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--adm-text-muted);">
                                <?= date('d M Y, H:i', strtotime($f['created_at'])) ?> WITA
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <a href="detail.php?id=<?= $f['id'] ?>" class="btn btn-outline btn-sm" style="padding: 0.3rem 0.7rem; border-radius: var(--adm-radius-sm); font-size: 0.8rem;">
                                    Detail
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mapData = <?= json_encode($mapFamilies) ?>;
    
    // Default center (Makassar)
    let centerLat = -5.147665;
    let centerLng = 119.432731;

    if (mapData.length > 0) {
        centerLat = parseFloat(mapData[0].latitude);
        centerLng = parseFloat(mapData[0].longitude);
    }

    const map = L.map('overview-map').setView([centerLat, centerLng], mapData.length > 0 ? 14 : 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    let markersLayer = L.layerGroup().addTo(map);

    function renderMarkers(filter = 'all') {
        markersLayer.clearLayers();
        const bounds = [];

        mapData.forEach(function(item) {
            if (filter !== 'all' && item.status_verifikasi !== filter) return;

            const lat = parseFloat(item.latitude);
            const lng = parseFloat(item.longitude);

            if (!isNaN(lat) && !isNaN(lng)) {
                bounds.push([lat, lng]);

                let markerColor = '#dc2626'; // Belum Terverifikasi (Merah Metalik)
                let statusLabel = '<span style="color: #dc2626; font-weight: 800;">Belum Terverifikasi</span>';
                if (item.status_verifikasi === 'terverifikasi') {
                    markerColor = '#059669'; // Terverifikasi (Hijau Emerald)
                    statusLabel = '<span style="color: #059669; font-weight: 800;">Terverifikasi</span>';
                } else if (item.status_verifikasi === 'ditolak') {
                    markerColor = '#64748b'; // Ditolak
                    statusLabel = '<span style="color: #64748b; font-weight: 800;">Ditolak</span>';
                }

                const circleMarker = L.circleMarker([lat, lng], {
                    radius: 9,
                    fillColor: markerColor,
                    color: "#ffffff",
                    weight: 2.5,
                    opacity: 1,
                    fillOpacity: 0.95
                }).addTo(markersLayer);

                const popupContent = `
                    <div style="font-size: 0.85rem; padding: 6px; font-family: 'Plus Jakarta Sans', sans-serif;">
                        <div style="font-size: 0.72rem; margin-bottom: 2px;">Status: ${statusLabel}</div>
                        <strong style="color: #1e1b4b; font-size: 0.95rem;">${item.nama_kepala}</strong><br>
                        <small style="color: #6b7280;">No. KK: ${item.no_kk}</small><br>
                        <div style="margin-top: 4px;"><strong>RT ${item.rt} / RW ${item.rw}</strong> • ${item.alamat_lengkap}</div>
                        <div style="margin-top: 8px; display: flex; gap: 6px;">
                            <a href="detail.php?id=${item.id}" style="color: #ffffff; background: #7c3aed; padding: 4px 9px; border-radius: 6px; font-size: 0.75rem; text-decoration: none; font-weight: 700;">Buka Detail</a>
                            <a href="https://www.google.com/maps/search/?api=1&query=${lat},${lng}" target="_blank" style="color: #7c3aed; background: #ede9fe; padding: 4px 9px; border-radius: 6px; font-size: 0.75rem; text-decoration: none; font-weight: 700;">Google Maps</a>
                        </div>
                    </div>
                `;
                circleMarker.bindPopup(popupContent);
            }
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [35, 35] });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 16);
        }
    }

    renderMarkers('all');

    // Filter Buttons Listener
    const filterBtns = document.querySelectorAll('.map-filter-btn');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            filterBtns.forEach(b => {
                b.classList.remove('active');
                b.style.backgroundColor = '';
                b.style.color = '';
            });
            this.classList.add('active');
            this.style.backgroundColor = '#7c3aed';
            this.style.color = '#ffffff';
            renderMarkers(this.getAttribute('data-filter'));
        });
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
