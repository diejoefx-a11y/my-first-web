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

// Kelompok Usia Breakdown for Stat Cards (Sorted U-8 to Senior)
$kuCountsAll = $pdo->query("SELECT kelompok_usia, COUNT(*) as total FROM atlet WHERE status_keanggotaan = 'Aktif' GROUP BY kelompok_usia ORDER BY kelompok_usia ASC")->fetchAll();
$kuMasterOrder = ['U-8', 'U-10', 'U-12', 'U-14', 'U-16', 'U-18', 'Senior'];
if (!empty($kuCountsAll)) {
    usort($kuCountsAll, function($a, $b) use ($kuMasterOrder) {
        $posA = array_search($a['kelompok_usia'], $kuMasterOrder);
        $posB = array_search($b['kelompok_usia'], $kuMasterOrder);
        if ($posA === false) $posA = 999;
        if ($posB === false) $posB = 999;
        return $posA <=> $posB;
    });
}
$kuStatColorsAll = [];
$countKuAll = count($kuCountsAll);
foreach ($kuCountsAll as $i => $ku) {
    $ratio = ($countKuAll > 1) ? ($i / ($countKuAll - 1)) : 0;
    $lightness = round(68 - ($ratio * 43));
    $hue = round(140 + ($ratio * 15));
    $kuStatColorsAll[] = "hsl({$hue}, 82%, {$lightness}%)";
}

// Prepare Data & SVG Calculations for "Kelompok Usia - Admin" Pie Chart (Exclusive for Admin Role)
$pieLabelsAdmin = [];
$pieValuesAdmin = [];
$pieColorsAdmin = [];
$pieBorderColorsAdmin = [];
$totalAtletPieAdmin = 0;

foreach ($kuCountsAll as $i => $ku) {
    $val = (int)$ku['total'];
    $pieLabelsAdmin[] = $ku['kelompok_usia'];
    $pieValuesAdmin[] = $val;
    $totalAtletPieAdmin += $val;
    
    $ratio = ($countKuAll > 1) ? ($i / ($countKuAll - 1)) : 0;
    $lightness = round(68 - ($ratio * 43));
    $hue = round(140 + ($ratio * 15));
    
    $pieColorsAdmin[] = "hsl({$hue}, 82%, {$lightness}%)";
    $pieBorderColorsAdmin[] = "hsl({$hue}, 85%, " . min(90, $lightness + 15) . "%)";
}

// Compute SVG Polar Area (Rose Segment) Coordinates for Admin UI Component
$svgPolarSlicesAdmin = [];
$numCategories = count($kuCountsAll);
$angleStep = 360 / max(1, $numCategories);
$startAnglePolar = -90; // Start 12 o'clock

$cxPolar = 125;
$cyPolar = 125;
$rMinPolar = 28;  // Center inner offset
$rMaxPolar = 95;  // Outer maximum radius
$maxValPolar = max(array_merge([1], $pieValuesAdmin));

foreach ($kuCountsAll as $i => $ku) {
    $val = (int)$ku['total'];
    $ratioVal = $val / $maxValPolar;
    $rSector = $rMinPolar + ($ratioVal * ($rMaxPolar - $rMinPolar));
    
    $endAnglePolar = $startAnglePolar + $angleStep;
    
    $radStart = deg2rad($startAnglePolar);
    $radEnd = deg2rad($endAnglePolar);
    $radMid = deg2rad(($startAnglePolar + $endAnglePolar) / 2);
    
    // Wedge Corner Points
    $x1 = $cxPolar + $rSector * cos($radStart);
    $y1 = $cyPolar + $rSector * sin($radStart);
    $x2 = $cxPolar + $rSector * cos($radEnd);
    $y2 = $cyPolar + $rSector * sin($radEnd);
    
    // Value Label Position (Outer Edge)
    $xLabel = $cxPolar + ($rSector + 14) * cos($radMid);
    $yLabel = $cyPolar + ($rSector + 14) * sin($radMid);

    // Inner Label Position (Inside Sector Body)
    $rInnerLabel = max(26, $rSector * 0.55);
    $xIn = $cxPolar + $rInnerLabel * cos($radMid);
    $yIn = $cyPolar + $rInnerLabel * sin($radMid);
    
    $largeArc = ($angleStep > 180) ? 1 : 0;
    
    // Wedge Path
    $dPath = sprintf(
        "M %.1f,%.1f L %.1f,%.1f A %.1f,%.1f 0 %d,1 %.1f,%.1f Z",
        $cxPolar, $cyPolar,
        $x1, $y1,
        $rSector, $rSector,
        $largeArc,
        $x2, $y2
    );
    
    $color = $pieColorsAdmin[$i] ?? '#34d399';
    $borderColor = $pieBorderColorsAdmin[$i] ?? '#4ade80';
    $pct = $totalAtletPieAdmin > 0 ? round(($val / $totalAtletPieAdmin) * 100, 1) : 0;
    
    $svgPolarSlicesAdmin[] = [
        'd' => $dPath,
        'color' => $color,
        'borderColor' => $borderColor,
        'label' => $ku['kelompok_usia'],
        'total' => $val,
        'pct' => $pct,
        'x1' => round($x1, 1),
        'y1' => round($y1, 1),
        'xIn' => round($xIn, 1),
        'yIn' => round($yIn, 1),
        'xLabel' => round($xLabel, 1),
        'yLabel' => round($yLabel, 1),
        'rSector' => round($rSector, 1)
    ];
    
    $startAnglePolar = $endAnglePolar;
}

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

/* RESPONSIVE DESIGN UNTUK HP ANDROID & SMARTPHONE */
.ku-admin-card {
    margin-bottom: 1.5rem;
    background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.85));
    border: 1.5px solid rgba(99, 102, 241, 0.4);
    box-shadow: 0 12px 35px rgba(0,0,0,0.5);
    padding: 1.5rem;
    border-radius: 18px;
    position: relative;
    overflow: hidden;
}

.ku-admin-grid {
    display: grid;
    grid-template-columns: minmax(220px, 260px) 1fr;
    gap: 1.5rem;
    align-items: center;
}

.ku-pie-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    background: rgba(10, 15, 30, 0.6);
    padding: 1.25rem;
    border-radius: 16px;
    border: 1px solid rgba(255,255,255,0.06);
    width: 100%;
}

.ku-pie-svg-wrapper {
    position: relative;
    width: 220px;
    height: 220px;
    max-width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ku-legend-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
    width: 100%;
}

.ku-legend-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    width: 100%;
}

@media (max-width: 768px) {
    .ku-admin-card {
        padding: 1.1rem !important;
        border-radius: 14px !important;
    }
    .ku-admin-grid {
        grid-template-columns: 1fr !important;
        gap: 1.25rem !important;
    }
    .ku-pie-svg-wrapper {
        width: 190px !important;
        height: 190px !important;
    }
    .ku-legend-grid {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
        gap: 8px !important;
    }
}

@media (max-width: 480px) {
    .ku-admin-card {
        padding: 0.95rem !important;
    }
    .ku-pie-svg-wrapper {
        width: 170px !important;
        height: 170px !important;
    }
    .ku-legend-grid {
        grid-template-columns: 1fr 1fr !important;
    }
}
</style>

<?php if ($isAdmin): ?>
<!-- ========================================================= -->
<!-- KOMPONEN UI: KELOMPOK USIA - ADMIN (RESPONSIVE ANDROID)   -->
<!-- ========================================================= -->
<div class="card ku-admin-card">
    
    <!-- Top Decorative Line Glow -->
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #6366f1, #34d399, #10b981);"></div>

    <!-- Header Component -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; flex-wrap:wrap; gap:0.75rem; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:1rem;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:42px; height:42px; background:rgba(99, 102, 241, 0.2); border:1px solid rgba(99, 102, 241, 0.4); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#818cf8; font-size:1.2rem; flex-shrink:0;">
                👑
            </div>
            <div>
                <h3 style="font-size:1.15rem; font-weight:800; color:#fff; margin:0; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    Kelompok Usia - Admin (Grafik Polar Area)
                    <span style="font-size:0.65rem; background:rgba(99, 102, 241, 0.25); color:#818cf8; border:1px solid rgba(99, 102, 241, 0.4); padding:2px 7px; border-radius:6px; font-weight:800;">ADMIN EXCLUSIVE</span>
                </h3>
                <span style="font-size:0.78rem; color:#94a3b8; margin-top:2px; display:block;">Visualisasi Model Kelopak Mawar Segmented Area Atlet</span>
            </div>
        </div>

        <div style="background:rgba(34, 197, 94, 0.15); border:1px solid rgba(34, 197, 94, 0.3); color:#4ade80; padding:0.4rem 0.85rem; border-radius:10px; font-weight:700; font-size:0.82rem; display:flex; align-items:center; gap:6px;">
            <span>👥 Total Atlet:</span>
            <strong style="color:#fff; font-size:1rem;"><?= number_format($totalAtletPieAdmin) ?></strong>
        </div>
    </div>

    <!-- Body Section (Centered Dynamic SVG Polar Rose Chart) -->
    <div style="display:flex; justify-content:center; align-items:center; width:100%;">
        
        <!-- Dynamic SVG Polar Rose Visual Diagram (100% Centered & Responsive) -->
        <div class="ku-pie-container" style="max-width: 520px; margin: 0 auto;">
            <div class="ku-pie-svg-wrapper" style="width: 260px; height: 260px;">
                <svg viewBox="0 0 250 250" style="width:100%; height:100%; overflow:visible;">
                    <defs>
                        <filter id="roseGlowAdmin" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#34d399" flood-opacity="0.4"/>
                        </filter>
                    </defs>

                    <!-- Concentric Grid Circles -->
                    <circle cx="125" cy="125" r="30" fill="none" stroke="rgba(255,255,255,0.06)" stroke-dasharray="3" />
                    <circle cx="125" cy="125" r="55" fill="none" stroke="rgba(255,255,255,0.08)" stroke-dasharray="3" />
                    <circle cx="125" cy="125" r="80" fill="none" stroke="rgba(255,255,255,0.1)" stroke-dasharray="3" />
                    <circle cx="125" cy="125" r="95" fill="none" stroke="rgba(255,255,255,0.15)" />

                    <!-- Polar Rose Petal Sectors -->
                    <?php foreach ($svgPolarSlicesAdmin as $sector): ?>
                        <!-- Petal Sector Shape -->
                        <path d="<?= $sector['d'] ?>" fill="<?= $sector['color'] ?>" fill-opacity="0.75" stroke="<?= $sector['borderColor'] ?>" stroke-width="2.5" filter="url(#roseGlowAdmin)" style="transition: all 0.3s ease; cursor:pointer;">
                            <title>Kelompok Usia <?= htmlspecialchars($sector['label']) ?>: <?= $sector['total'] ?> Atlet (<?= $sector['pct'] ?>%)</title>
                        </path>

                        <!-- Label Kelompok Usia LANGSUNG DI DALAM BUKAN/KELOPAK MAWAR -->
                        <text x="<?= $sector['xIn'] ?>" y="<?= $sector['yIn'] + 3 ?>" fill="#ffffff" font-family="'Outfit', sans-serif" font-weight="900" font-size="10.5" text-anchor="middle" style="text-shadow: 0px 1px 4px rgba(0,0,0,0.9); pointer-events:none;">
                            <?= htmlspecialchars($sector['label']) ?>
                        </text>

                        <!-- Node Circle Point at Outer Peak -->
                        <circle cx="<?= $sector['x1'] ?>" cy="<?= $sector['y1'] ?>" r="4" fill="<?= $sector['borderColor'] ?>" stroke="#ffffff" stroke-width="1.8" />

                        <!-- Badge Label Outer Info (KU + Jumlah) -->
                        <g transform="translate(<?= $sector['xLabel'] ?>, <?= $sector['yLabel'] ?>)">
                            <rect x="-24" y="-12" width="48" height="23" rx="7" fill="rgba(15,23,42,0.92)" stroke="<?= $sector['borderColor'] ?>" stroke-width="1.2" />
                            <text x="0" y="-1" fill="<?= $sector['color'] ?>" font-family="'Outfit', sans-serif" font-weight="900" font-size="9" text-anchor="middle"><?= htmlspecialchars($sector['label']) ?></text>
                            <text x="0" y="8" fill="#ffffff" font-family="'Outfit', sans-serif" font-weight="800" font-size="9" text-anchor="middle"><?= $sector['total'] ?> Atlet</text>
                        </g>
                    <?php endforeach; ?>

                    <!-- Center Hub Circle -->
                    <circle cx="125" cy="125" r="18" fill="rgba(15,23,42,0.95)" stroke="#34d399" stroke-width="2" />
                    <text x="125" y="129" text-anchor="middle" fill="#34d399" font-family="'Outfit', sans-serif" font-size="10" font-weight="800">KU</text>
                </svg>
            </div>
            <div style="font-size:0.75rem; color:#4ade80; font-weight:700; margin-top:8px; text-transform:uppercase; letter-spacing:0.5px;">
                🌹 Grafik Polar Area (Kelopak Mawar)
            </div>
        </div>

    </div>

</div>
<?php endif; ?>

<!-- =================================================================== -->
<!-- KOMPONEN UI: KARTU STATISTIK KELOMPOK USIA (CYBER-GLASS FILTER TILES) -->
<!-- =================================================================== -->
<div class="card ku-interactive-filter-card" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, rgba(15, 23, 42, 0.9), rgba(20, 30, 48, 0.85)); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 18px; padding: 1.35rem; position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.45); overflow: hidden;">
    
    <!-- Top Animated Accent Line -->
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #34d399, #38bdf8, #818cf8, #a78bfa);"></div>

    <!-- Header Section -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.75rem; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:0.85rem;">
        <div style="display:flex; align-items:center; gap:10px;">
            <div style="width:38px; height:38px; background:rgba(52, 211, 153, 0.18); border:1px solid rgba(52, 211, 153, 0.35); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#34d399; font-size:1.15rem; flex-shrink:0;">
                ⚡
            </div>
            <div>
                <h3 style="font-size:1.15rem; font-weight:800; color:#fff; margin:0; display:flex; align-items:center; gap:8px;">
                    Kartu Filter Kelompok Usia (KU)
                    <span style="font-size:0.65rem; background:rgba(52, 211, 153, 0.2); color:#34d399; border:1px solid rgba(52, 211, 153, 0.35); padding:2px 7px; border-radius:6px; font-weight:800;">INTERAKTIF</span>
                </h3>
                <span style="font-size:0.78rem; color:#94a3b8; margin-top:2px; display:block;">Pilih kartu di bawah untuk menyaring daftar atlet secara instan</span>
            </div>
        </div>

        <?php if ($filterKu): ?>
            <a href="index.php" class="btn btn-secondary btn-sm" style="font-size:0.78rem; border-color:rgba(244,63,94,0.4); color:#f87171; background:rgba(244,63,94,0.12); border-radius:10px; padding:0.4rem 0.85rem; font-weight:700;">
                <i class="fa-solid fa-xmark"></i> Reset Filter (<?= htmlspecialchars($filterKu) ?>)
            </a>
        <?php endif; ?>
    </div>

    <!-- Interactive Holographic Tile Grid -->
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap:10px;">
        
        <!-- SEMUA KU TILE -->
        <?php $isAllSelected = empty($filterKu); ?>
        <a href="index.php" style="background:<?= $isAllSelected ? 'linear-gradient(135deg, rgba(99,102,241,0.35), rgba(79,70,229,0.2))' : 'rgba(15,23,42,0.65)' ?>; border:1px solid <?= $isAllSelected ? '#818cf8' : 'rgba(255,255,255,0.08)' ?>; border-top:3.5px solid #818cf8; padding:0.85rem 0.6rem; border-radius:14px; text-decoration:none; text-align:center; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow:<?= $isAllSelected ? '0 6px 20px rgba(99,102,241,0.3)' : 'none' ?>;" class="ku-tile-item">
            <div style="display:flex; justify-content:center; align-items:center; gap:4px; margin-bottom:4px;">
                <span style="font-size:0.65rem; background:rgba(255,255,255,0.12); color:#fff; padding:2px 6px; border-radius:5px; font-weight:800;">SEMUA</span>
            </div>
            <div style="font-family:'Outfit', sans-serif; font-size:1.5rem; font-weight:900; color:#fff; line-height:1.1; margin:4px 0;"><?= $totalAktif ?></div>
            <div style="font-size:0.68rem; color:#94a3b8; font-weight:700;">Total Atlet</div>
            
            <!-- Mini Progress Bar Line -->
            <div style="width:100%; height:4px; background:rgba(255,255,255,0.1); border-radius:4px; margin-top:8px; overflow:hidden;">
                <div style="width:100%; height:100%; background:#818cf8; border-radius:4px;"></div>
            </div>
        </a>

        <!-- CATEGORY KU TILES (U-8 to Senior) -->
        <?php 
        $maxKuVal = max(array_merge([1], array_column($kuCountsAll, 'total')));
        foreach ($kuCountsAll as $idx => $ku): 
            $accentColor = $kuStatColorsAll[$idx] ?? '#34d399';
            $isSelected = ($filterKu === $ku['kelompok_usia']);
            $val = (int)$ku['total'];
            $pctVal = $totalAktif > 0 ? round(($val / $totalAktif) * 100) : 0;
            $barWidth = round(($val / $maxKuVal) * 100);
        ?>
            <a href="index.php?ku=<?= urlencode($ku['kelompok_usia']) ?>" style="background:<?= $isSelected ? 'linear-gradient(135deg, rgba(16,185,129,0.35), rgba(6,95,70,0.25))' : 'rgba(15,23,42,0.65)' ?>; border:1px solid <?= $isSelected ? $accentColor : 'rgba(255,255,255,0.08)' ?>; border-top:3.5px solid <?= $accentColor ?>; padding:0.85rem 0.6rem; border-radius:14px; text-decoration:none; text-align:center; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow:<?= $isSelected ? "0 6px 20px rgba(16,185,129,0.35)" : "none" ?>;" class="ku-tile-item">
                <div style="display:flex; justify-content:center; align-items:center; gap:4px; margin-bottom:4px;">
                    <span style="font-size:0.68rem; background:rgba(255,255,255,0.12); color:#fff; padding:2px 7px; border-radius:5px; font-weight:800; letter-spacing:0.3px;"><?= htmlspecialchars($ku['kelompok_usia']) ?></span>
                </div>
                <div style="font-family:'Outfit', sans-serif; font-size:1.5rem; font-weight:900; color:#fff; line-height:1.1; margin:4px 0;"><?= $val ?></div>
                <div style="font-size:0.68rem; color:#94a3b8; font-weight:700; display:flex; justify-content:center; align-items:center; gap:3px;">
                    <span>Atlet</span>
                    <span style="font-size:0.62rem; color:<?= $accentColor ?>; background:rgba(255,255,255,0.08); padding:1px 4px; border-radius:4px; font-weight:800;"><?= $pctVal ?>%</span>
                </div>

                <!-- Sleek Mini Progress Meter Bar -->
                <div style="width:100%; height:4px; background:rgba(255,255,255,0.1); border-radius:4px; margin-top:8px; overflow:hidden;">
                    <div style="width:<?= $barWidth ?>%; height:100%; background:<?= $accentColor ?>; border-radius:4px; transition:width 0.8s ease;"></div>
                </div>
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


<!-- =================================================================== -->
<!-- KOMPONEN UI: DAFTAR ATLET SEKOLAH SEPAK BOLA (CYBER-GLASS DIRECTORY) -->
<!-- =================================================================== -->
<div class="card atlet-directory-card" style="padding: 1.5rem; background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(20, 30, 48, 0.88)); border: 1.5px solid rgba(99, 102, 241, 0.35); border-radius: 20px; position: relative; box-shadow: 0 15px 35px rgba(0,0,0,0.5); overflow: hidden;">
    
    <!-- Top Animated Gradient Accent Bar -->
    <div style="position: absolute; top: 0; left: 0; right: 0; height: 3.5px; background: linear-gradient(90deg, #6366f1, #38bdf8, #34d399, #10b981);"></div>

    <!-- HEADER BAR WITH VIEW TOGGLE & TAMBAH BUTTON -->
    <div class="card-header" style="flex-wrap:wrap; gap:1rem; margin-bottom:1.25rem; padding-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.1);">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="width:44px; height:44px; background:rgba(99, 102, 241, 0.2); border:1px solid rgba(99, 102, 241, 0.4); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#818cf8; font-size:1.3rem; flex-shrink:0;">
                🏃‍♂️
            </div>
            <div>
                <h2 class="card-title" style="font-size:1.3rem; font-weight:800; color:#fff; margin:0; display:flex; align-items:center; gap:8px;">
                    Daftar Atlet Sekolah Sepak Bola
                    <span style="font-size:0.68rem; background:rgba(56, 189, 248, 0.2); color:#38bdf8; border:1px solid rgba(56, 189, 248, 0.35); padding:2px 8px; border-radius:6px; font-weight:800;">DIREKTORI RESMI</span>
                </h2>
                <p style="font-size:0.8rem; color:#94a3b8; margin-top:2px; display:block;">Database Resmi Data Pemain, Statistik Profil, & Raport SSB Tamalanrea Makassar</p>
            </div>
        </div>
        
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <!-- View Mode Switcher -->
            <div style="display:flex; background:rgba(15,23,42,0.85); padding:4px; border-radius:12px; border:1px solid rgba(255,255,255,0.12);">
                <button type="button" id="btnTableView" onclick="switchViewMode('table')" class="btn btn-secondary btn-sm view-btn-active" style="padding:5px 12px; font-size:0.8rem; font-weight:700; border-radius:8px;">📋 Mode Tabel</button>
                <button type="button" id="btnCardView" onclick="switchViewMode('card')" class="btn btn-secondary btn-sm" style="padding:5px 12px; font-size:0.8rem; font-weight:700; border-radius:8px;">🎴 Kartu Scouting</button>
            </div>

            <?php if ($isAdmin): ?>
                <a href="tambah.php" class="btn btn-primary btn-sm" style="font-size:0.85rem; padding:0.55rem 1.1rem; border-radius:10px; font-weight:800; box-shadow: 0 4px 15px rgba(99,102,241,0.4);">
                    <i class="fa-solid fa-user-plus"></i> + Tambah Atlet Baru
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['success']) && $_GET['success'] === 'deleted'): ?>
        <div style="background:rgba(244,63,94,0.15); border:1px solid var(--rose); color:#f87171; padding:0.75rem 1rem; border-radius:10px; margin-bottom:1.25rem; font-size:0.85rem; display:flex; align-items:center; gap:8px;">
            <span>✓</span> Data atlet beserta riwayat raport & SPP berhasil dihapus dari sistem.
        </div>
    <?php endif; ?>

    <!-- FILTER & SEARCH BAR WITH REALTIME JAVASCRIPT FILTERING -->
    <form method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:0.85rem; margin-bottom:1.35rem; background:rgba(10,15,30,0.65); padding:1rem; border-radius:16px; border:1px solid rgba(255,255,255,0.08);">
        <div>
            <label style="font-size:0.72rem; color:#38bdf8; font-weight:800; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">🔍 CARI PEMAIN / NISN</label>
            <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Ketik nama atau NISN..." class="form-control" style="font-size:0.82rem; padding:0.5rem 0.85rem; background:rgba(15,23,42,0.8); border-color:rgba(255,255,255,0.12); color:#fff; border-radius:10px;" oninput="applyLiveFilter()">
        </div>

        <div>
            <label style="font-size:0.72rem; color:#34d399; font-weight:800; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">⚽ KELOMPOK USIA (KU)</label>
            <select name="ku" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; padding:0.5rem 0.85rem; background:rgba(15,23,42,0.8); border-color:rgba(255,255,255,0.12); color:#fff; border-radius:10px;">
                <option value="">Semua Kelompok Usia</option>
                <?php foreach (['U-8','U-10','U-12','U-14','U-16','U-18','Senior'] as $ku): ?>
                    <option value="<?= $ku ?>" <?= $filterKu == $ku ? 'selected' : '' ?>><?= $ku ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="font-size:0.72rem; color:#a78bfa; font-weight:800; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:0.5px;">🎯 POSISI UTAMA</label>
            <select name="posisi" class="form-control" onchange="this.form.submit()" style="font-size:0.82rem; padding:0.5rem 0.85rem; background:rgba(15,23,42,0.8); border-color:rgba(255,255,255,0.12); color:#fff; border-radius:10px;">
                <option value="">Semua Posisi</option>
                <option value="Kiper" <?= $filterPosisi == 'Kiper' ? 'selected' : '' ?>>🧤 Kiper (GK)</option>
                <option value="Bek" <?= $filterPosisi == 'Bek' ? 'selected' : '' ?>>🛡️ Bek / Defender</option>
                <option value="Gelandang" <?= $filterPosisi == 'Gelandang' ? 'selected' : '' ?>>🎯 Gelandang / Midfielder</option>
                <option value="Penyerang" <?= $filterPosisi == 'Penyerang' ? 'selected' : '' ?>>⚽ Penyerang / Forward</option>
            </select>
        </div>

        <div style="display:flex; align-items:flex-end;">
            <a href="index.php" class="btn btn-secondary btn-sm" style="width:100%; justify-content:center; padding:0.55rem; font-size:0.82rem; border-radius:10px; font-weight:700;">Reset Filter</a>
        </div>
    </form>

    <!-- 1. VIEW MODE: TABEL SLEEK HIGH-CONTRAST -->
    <div id="containerTableView" style="width:100%; overflow:hidden; border-radius:14px; border:1px solid rgba(255,255,255,0.12);">
        <table class="data-table" style="width:100%; border-collapse:separate; border-spacing:0; font-size:0.83rem;">
            <thead>
                <tr style="background:rgba(10,15,30,0.95);">
                    <th style="padding:12px 12px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); color:#38bdf8;">Nama Atlet</th>
                    <th style="padding:12px 10px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center; color:#34d399;">KU</th>
                    <th style="padding:12px 10px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center; color:#a78bfa;">Posisi Utama</th>
                    <th style="padding:12px 10px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center; color:#cbd5e1;">Tinggi / Berat</th>
                    <th style="padding:12px 10px; border-bottom:1px solid rgba(255,255,255,0.12); border-right:1px solid rgba(255,255,255,0.08); text-align:center; color:#94a3b8;">Kaki</th>
                    <th style="padding:12px 12px; border-bottom:1px solid rgba(255,255,255,0.12); text-align:center; color:#fbbf24;">Aksi Management</th>
                </tr>
            </thead>
            <tbody id="atletTableBody">
                <?php if (count($displayAtletList) == 0): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:2.5rem; color:var(--text-muted);">Tidak ada data atlet yang ditemukan.</td>
                    </tr>
                <?php else: ?>
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
                        <tr class="atlet-row-hover atlet-data-row" data-name="<?= strtolower(htmlspecialchars($a['nama_lengkap'])) ?>" data-nisn="<?= strtolower(htmlspecialchars($a['nisn_nik'])) ?>" style="border-bottom:1px solid rgba(255,255,255,0.06); transition:background 0.2s;">
                            <td style="padding:10px 12px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06);">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="background:#1e293b; display:flex; align-items:center; justify-content:center; font-weight:800; color:#818cf8; overflow:hidden; width:36px; height:36px; border-radius:50%; flex-shrink:0; border:2px solid rgba(52,211,153,0.4); box-shadow:0 0 10px rgba(52,211,153,0.2);">
                                        <?php if ($hasPhoto): ?>
                                            <img src="../assets/img/atlet/<?= htmlspecialchars($a['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($a['nama_lengkap'], 0, 2)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <strong style="color:#fff; font-size:0.9rem; display:block;"><?= htmlspecialchars($a['nama_lengkap']) ?></strong>
                                        <span style="font-size:0.73rem; color:#94a3b8;"><?= htmlspecialchars($a['tempat_lahir'] ?: '-') ?>, <?= !empty($a['tanggal_lahir']) ? date('d/m/Y', strtotime($a['tanggal_lahir'])) : '-' ?></span>
                                    </div>
                                </div>
                            </td>

                            <td style="padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center;">
                                <span class="badge" style="padding:3px 8px; font-size:0.72rem; font-weight:800; background:rgba(52,211,153,0.18); color:#34d399; border:1px solid rgba(52,211,153,0.35); border-radius:6px;"><?= htmlspecialchars($a['kelompok_usia']) ?></span>
                            </td>

                            <td style="padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center;">
                                <strong style="color:#e2e8f0; font-size:0.83rem;"><?= $posIcon ?> <?= htmlspecialchars($a['posisi_utama']) ?></strong>
                            </td>

                            <td style="padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center; font-size:0.8rem; color:#cbd5e1;">
                                <?= $a['tinggi_badan'] ?> cm / <?= $a['berat_badan'] ?> kg
                            </td>

                            <td style="padding:10px 10px; border-bottom:1px solid rgba(255,255,255,0.06); border-right:1px solid rgba(255,255,255,0.06); text-align:center; color:#94a3b8; font-size:0.8rem;">
                                <?= htmlspecialchars($a['kaki_dominan']) ?>
                            </td>

                            <td style="padding:10px 12px; border-bottom:1px solid rgba(255,255,255,0.06); text-align:center; white-space:nowrap;">
                                <div style="display:flex; gap:5px; justify-content:center;">
                                    <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 9px; font-size:0.76rem; font-weight:700; background:rgba(56,189,248,0.15); color:#38bdf8; border-color:rgba(56,189,248,0.3);" title="Lihat Profil & Raport">👁️ Profil</a>
                                    <?php if ($isAdmin): ?>
                                        <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 9px; font-size:0.76rem; font-weight:700; color:#fbbf24; background:rgba(251,191,36,0.15); border-color:rgba(251,191,36,0.3);" title="Edit Data Atlet">✏️ Edit</a>
                                        <a href="hapus.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="padding:4px 9px; font-size:0.76rem; font-weight:700; color:#f87171; background:rgba(244,63,94,0.15); border-color:rgba(244,63,94,0.3);" title="Hapus Atlet" onclick="return confirm('Apakah Anda yakin ingin menghapus atlet <?= htmlspecialchars(addslashes($a['nama_lengkap'])) ?>? Seluruh riwayat raport & SPP atlet ini akan terhapus.');">🗑️ Hapus</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. VIEW MODE: KARTU SCOUTING PEMAIN (FUT HOLOGRAPHIC GRID) -->
    <div id="containerCardView" style="display:none; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:1.1rem; margin-top:0.75rem;">
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
            <div class="atlet-card-item atlet-data-card" data-name="<?= strtolower(htmlspecialchars($a['nama_lengkap'])) ?>" data-nisn="<?= strtolower(htmlspecialchars($a['nisn_nik'])) ?>" style="background:linear-gradient(135deg, rgba(15,23,42,0.85), rgba(30,41,59,0.7)); border:1px solid rgba(255,255,255,0.1); border-radius:16px; padding:1.1rem; box-shadow:0 8px 25px rgba(0,0,0,0.4);">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.85rem;">
                    <span class="badge" style="background:rgba(52,211,153,0.18); color:#34d399; border:1px solid rgba(52,211,153,0.35); font-size:0.72rem; font-weight:800; padding:3px 8px; border-radius:6px;"><?= htmlspecialchars($a['kelompok_usia']) ?></span>
                </div>

                <div style="display:flex; align-items:center; gap:12px; margin-bottom:0.95rem;">
                    <div style="background:#1e293b; width:54px; height:54px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.2rem; font-weight:800; color:#818cf8; overflow:hidden; flex-shrink:0; border:2.5px solid rgba(52,211,153,0.45); box-shadow:0 0 12px rgba(52,211,153,0.25);">
                        <?php if ($hasPhoto): ?>
                            <img src="../assets/img/atlet/<?= htmlspecialchars($a['foto_profil']) ?>" alt="Foto" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($a['nama_lengkap'], 0, 2)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong style="color:#fff; font-size:0.98rem; display:block;"><?= htmlspecialchars($a['nama_lengkap']) ?></strong>
                    </div>
                </div>

                <div style="background:rgba(10,15,30,0.65); padding:0.75rem; border-radius:12px; border:1px solid rgba(255,255,255,0.06); margin-bottom:0.95rem; font-size:0.78rem; display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div><span style="color:#94a3b8;">Posisi:</span> <strong style="color:#fff;"><?= $posIcon ?> <?= htmlspecialchars($a['posisi_utama']) ?></strong></div>
                    <div><span style="color:#94a3b8;">Kaki:</span> <strong style="color:#fff;"><?= htmlspecialchars($a['kaki_dominan']) ?></strong></div>
                    <div><span style="color:#94a3b8;">Tinggi:</span> <strong style="color:#fff;"><?= $a['tinggi_badan'] ?> cm</strong></div>
                    <div><span style="color:#94a3b8;">Berat:</span> <strong style="color:#fff;"><?= $a['berat_badan'] ?> kg</strong></div>
                </div>

                <div style="display:flex; gap:6px;">
                    <a href="detail.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="flex:1; justify-content:center; font-size:0.78rem; font-weight:700; background:rgba(56,189,248,0.15); color:#38bdf8; border-color:rgba(56,189,248,0.3);">Profil & Raport</a>
                    <?php if ($isAdmin): ?>
                        <a href="edit.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="color:#fbbf24; background:rgba(251,191,36,0.15); border-color:rgba(251,191,36,0.3); padding:0 10px; font-size:0.78rem; font-weight:700;">Edit</a>
                        <a href="hapus.php?id=<?= $a['id'] ?>" class="btn btn-secondary btn-sm" style="color:#f87171; background:rgba(244,63,94,0.15); border-color:rgba(244,63,94,0.3); padding:0 10px; font-size:0.78rem; font-weight:700;" onclick="return confirm('Yakin hapus atlet ini?');">Hapus</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PAGINATION CONTROLS (10 ATLET PER HALAMAN) -->
    <?php if ($totalPages > 1): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1.35rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.1); flex-wrap:wrap; gap:0.75rem; font-size:0.82rem;">
            <div style="color:#94a3b8;">
                Menampilkan <strong style="color:#fff;"><?= count($displayAtletList) ?></strong> dari total <strong style="color:#fff;"><?= $totalAtletCount ?></strong> atlet (Halaman <?= $page ?> dari <?= $totalPages ?>)
            </div>
            <div style="display:flex; gap:5px; align-items:center;">
                <?php
                $queryParams = $_GET;
                ?>
                <?php if ($page > 1): ?>
                    <?php $queryParams['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:0.78rem; border-radius:8px; font-weight:700;">&laquo; Prev</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $queryParams['page'] = $i; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm" style="padding:4px 10px; font-size:0.78rem; border-radius:8px; font-weight:800;"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <?php $queryParams['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:0.78rem; border-radius:8px; font-weight:700;">Next &raquo;</a>
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
