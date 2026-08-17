<?php
/**
 * DIREKTORI & DATA LENGKAP ANGGOTA JEMAAT BERDASARKAN KEPALA KELUARGA
 * Persekutuan Kaum Bapak (PKB) - Jemaat Kristiani
 * Fitur: Pencarian Interaktif, Data Tanggungan Lengkap, Filter Kelompok, Peta Sinkronisasi Google Maps Rute
 */

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Makassar');

$db = get_db();

// =========================================================================
// KEAMANAN & AUTENTIKASI SESI JEMAAT TERVERIFIKASI
// Pengguna TIDAK BISA mengakses halaman ini tanpa Nomor KK yang Terverifikasi
// =========================================================================

// 1. Tangani Aksi Logout / Keluar Sesi
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset(
        $_SESSION['jemaat_verified_kk'],
        $_SESSION['jemaat_verified_id'],
        $_SESSION['jemaat_verified_nama'],
        $_SESSION['jemaat_verified_kelompok'],
        $_SESSION['jemaat_verified_time']
    );
    header("Location: cek_kk.php?logged_out=1");
    exit;
}

// 2. Cek apakah ada Sesi KK Jemaat yang aktif
if (empty($_SESSION['jemaat_verified_kk'])) {
    header("Location: cek_kk.php?auth_required=1");
    exit;
}

// 3. Cek Masa Berlaku Sesi (Session Timeout: 30 Menit / 1800 detik)
if (isset($_SESSION['jemaat_verified_time']) && (time() - $_SESSION['jemaat_verified_time'] > 1800)) {
    unset(
        $_SESSION['jemaat_verified_kk'],
        $_SESSION['jemaat_verified_id'],
        $_SESSION['jemaat_verified_nama'],
        $_SESSION['jemaat_verified_kelompok'],
        $_SESSION['jemaat_verified_time']
    );
    header("Location: cek_kk.php?timeout=1");
    exit;
}
$_SESSION['jemaat_verified_time'] = time(); // Refresh waktu aktif

// 4. Verifikasi Real-time ke Database untuk memastikan KK masih terdaftar & berstatus 'terverifikasi'
$sessionKK = $_SESSION['jemaat_verified_kk'];
$stmtAuthCheck = $db->prepare("
    SELECT f.id, f.no_kk, f.nama_kepala, f.kelompok_id, f.status_verifikasi, g.nama_kelompok, g.nomor_kelompok 
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    WHERE f.no_kk = ? 
    LIMIT 1
");
$stmtAuthCheck->execute([$sessionKK]);
$authFamily = $stmtAuthCheck->fetch();

if (!$authFamily || strtolower(trim((string)($authFamily['status_verifikasi'] ?? ''))) !== 'terverifikasi') {
    // Jika di database statusnya berubah menjadi pending/batal, cabut sesi dan arahkan ke info verifikasi
    unset(
        $_SESSION['jemaat_verified_kk'],
        $_SESSION['jemaat_verified_id'],
        $_SESSION['jemaat_verified_nama'],
        $_SESSION['jemaat_verified_kelompok'],
        $_SESSION['jemaat_verified_time']
    );
    header("Location: cek_kk.php?kk=" . urlencode($sessionKK) . "&status_revoked=1");
    exit;
}
$groups = $db->query("SELECT id, nomor_kelompok, nama_kelompok, nama_ketua, no_hp_ketua FROM `groups` ORDER BY nomor_kelompok ASC")->fetchAll();

// 2. Fetch All Families with Group info
$sql = "
    SELECT 
        f.*,
        g.nomor_kelompok,
        g.nama_kelompok,
        g.nama_ketua,
        g.no_hp_ketua,
        (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as total_jiwa
    FROM families f
    LEFT JOIN `groups` g ON f.kelompok_id = g.id
    ORDER BY g.nomor_kelompok ASC, f.nama_kepala ASC
";
$families = $db->query($sql)->fetchAll();

// 3. Fetch all family members indexed by family_id
$membersRaw = $db->query("SELECT * FROM family_members ORDER BY family_id ASC, id ASC")->fetchAll();
$membersByFamily = [];
foreach ($membersRaw as $m) {
    $membersByFamily[$m['family_id']][] = $m;
}

// 4. Calculate summary stats
$totalKKCount = count($families);
$totalJiwaCount = count($membersRaw);
$totalWithCoords = count(array_filter($families, function($f) {
    return !empty($f['latitude']) && !empty($f['longitude']) && $f['latitude'] != '0' && $f['latitude'] != '';
}));

/**
 * Helper: Resolve family photo URL with real disk verification
 */
function get_family_photo_url($fotoKeluarga, $fotoRumah) {
    $candidates = array_filter([$fotoKeluarga, $fotoRumah]);
    foreach ($candidates as $cand) {
        $cand = trim((string)$cand);
        if (empty($cand)) continue;
        
        $filename = basename($cand);
        $fullPath = __DIR__ . '/../uploads/' . $filename;
        if (file_exists($fullPath) && is_file($fullPath)) {
            return base_url('uploads/' . $filename);
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Data Lengkap Jemaat & Rute Peta | PKB</title>
    
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../assets/css/landing.css">

    <style>
        :root {
            --primary: #7c3aed;
            --primary-glow: rgba(124, 58, 237, 0.4);
            --secondary: #10b981;
            --secondary-glow: rgba(16, 185, 129, 0.4);
            --accent: #0ea5e9;
            --bg-dark: #0b091a;
            --card-dark: rgba(18, 15, 38, 0.88);
            --border-dark: rgba(139, 92, 246, 0.25);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 10% 15%, rgba(124, 58, 237, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 90% 85%, rgba(16, 185, 129, 0.08) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(14, 165, 233, 0.05) 0%, transparent 50%);
            color: var(--text-main);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .container-wide {
            max-width: 1380px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Top Header Navigation */
        .top-navbar {
            background: rgba(15, 12, 35, 0.95);
            backdrop-filter: blur(16px);
            border-bottom: 1.5px solid var(--border-dark);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .top-navbar-inner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        /* Hero Banner */
        .page-hero {
            background: linear-gradient(135deg, rgba(26, 21, 56, 0.9), rgba(15, 12, 35, 0.95));
            border: 1.5px solid var(--border-dark);
            border-radius: 24px;
            padding: 2rem;
            margin: 1.75rem 0 1.5rem 0;
            box-shadow: 0 20px 45px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }
        .page-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 320px;
            height: 320px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.25) 0%, transparent 70%);
            pointer-events: none;
        }

        /* Stat Badges */
        .hero-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .stat-card-pill {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.25s;
        }

        /* Filter Controls Box */
        .filter-panel {
            background: var(--card-dark);
            border: 1.5px solid var(--border-dark);
            border-radius: 20px;
            padding: 1.75rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: 1.2fr 2fr auto;
            gap: 1.25rem;
            align-items: flex-end;
        }
        @media (max-width: 900px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .filter-label {
            font-size: 0.85rem;
            font-weight: 800;
            color: #c4b5fd;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-input, .filter-select {
            width: 100%;
            background: rgba(10, 8, 25, 0.85);
            border: 1.5px solid rgba(139, 92, 246, 0.35);
            color: #fff;
            padding: 0.85rem 1.15rem;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.25s;
            font-family: inherit;
            box-sizing: border-box;
        }
        .filter-input:focus, .filter-select:focus {
            border-color: #a78bfa;
            box-shadow: 0 0 15px var(--primary-glow);
        }

        /* Quick Group Selector Pills */
        .group-pills-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 1rem;
            padding-top: 0.85rem;
            border-top: 1px dashed rgba(255,255,255,0.08);
        }
        .group-pill-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ddd6fe;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .group-pill-btn:hover, .group-pill-btn.active {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            border-color: #c4b5fd;
            color: #fff;
            box-shadow: 0 2px 10px rgba(124,58,237,0.4);
        }

        /* Initial State Prompt Box */
        .initial-prompt-card {
            background: rgba(18, 15, 38, 0.9);
            border: 1.5px dashed rgba(167, 139, 250, 0.35);
            border-radius: 24px;
            padding: 3.5rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        .prompt-icon-ring {
            width: 80px;
            height: 80px;
            background: rgba(139, 92, 246, 0.18);
            border: 2px solid rgba(167, 139, 250, 0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: #c4b5fd;
            margin: 0 auto 1.25rem auto;
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.3);
        }

        /* Family Full Data Card */
        .family-full-card {
            background: var(--card-dark);
            border: 1.5px solid var(--border-dark);
            border-radius: 20px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            transition: all 0.25s;
        }
        .family-full-card:hover {
            border-color: rgba(167, 139, 250, 0.5);
            box-shadow: 0 20px 45px rgba(0,0,0,0.5);
        }

        .family-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 1.5px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 1.25rem;
        }

        /* Member Tanggungan Table - Mobile Smooth Horizontal Scroll */
        .member-table-wrap {
            background: rgba(10, 8, 25, 0.85);
            border: 1.5px solid rgba(139, 92, 246, 0.3);
            border-radius: 14px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.5);
            position: relative;
        }
        .member-table-wrap::-webkit-scrollbar {
            height: 6px;
        }
        .member-table-wrap::-webkit-scrollbar-track {
            background: rgba(15, 12, 35, 0.8);
            border-radius: 10px;
        }
        .member-table-wrap::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.45);
            border-radius: 10px;
        }
        .member-table-wrap::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.75);
        }

        .member-data-table {
            width: 100%;
            min-width: 580px; /* Memastikan tabel leluasa digeser di smartphone */
            border-collapse: collapse;
            text-align: left;
        }
        .member-data-table thead {
            background: rgba(28, 22, 60, 0.95);
            border-bottom: 1.5px solid rgba(139, 92, 246, 0.35);
        }
        .member-data-table th {
            padding: 0.85rem 1rem;
            font-size: 0.8rem;
            font-weight: 800;
            color: #ddd6fe;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        .member-data-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
            font-size: 0.88rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        .member-data-table td:nth-child(2) {
            white-space: normal;
            min-width: 160px;
        }
        .member-data-table tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }

        .mobile-swipe-hint {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.75rem;
            color: #a78bfa;
            background: rgba(139, 92, 246, 0.12);
            border: 1px solid rgba(167, 139, 250, 0.25);
            padding: 3px 9px;
            border-radius: 8px;
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-action-gmap {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 9px 16px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
            border: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .btn-action-gmap:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.55);
            color: #fff;
        }

        .btn-action-focus {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(14, 165, 233, 0.18);
            border: 1px solid rgba(56, 189, 248, 0.4);
            color: #7dd3fc;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 9px 14px;
            border-radius: 10px;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .btn-action-focus:hover {
            background: rgba(14, 165, 233, 0.35);
            color: #fff;
        }

        /* Badges */
        .badge-kelompok {
            display: inline-block;
            background: rgba(139, 92, 246, 0.22);
            border: 1px solid rgba(167, 139, 250, 0.4);
            color: #ddd6fe;
            padding: 4px 10px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.82rem;
        }
        .badge-gender-l {
            background: rgba(14, 165, 233, 0.2);
            border: 1px solid rgba(56, 189, 248, 0.4);
            color: #7dd3fc;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
        }
        .badge-gender-p {
            background: rgba(236, 72, 153, 0.2);
            border: 1px solid rgba(244, 114, 182, 0.4);
            color: #f472b6;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 700;
        }

        /* Avatar Thumbnail */
        .avatar-thumb {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            object-fit: cover;
            border: 1.5px solid rgba(139, 92, 246, 0.4);
            background: #1e1b4b;
        }
        .avatar-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.3), rgba(16, 185, 129, 0.3));
            border: 1.5px solid rgba(167, 139, 250, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: #fff;
            flex-shrink: 0;
        }

        /* Map Section Card */
        .map-section-card {
            background: var(--card-dark);
            border: 1.5px solid var(--border-dark);
            border-radius: 20px;
            padding: 1.25rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
        }
        #syncMap {
            height: 380px;
            width: 100%;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>

    <!-- TOP NAVBAR -->
    <nav class="top-navbar">
        <div class="container-wide top-navbar-inner">
            <a href="../index.php" style="text-decoration: none; display: flex; align-items: center; gap: 12px;">
                <div style="width: 42px; height: 42px; background: linear-gradient(135deg, #8b5cf6, #6d28d9); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: #fff; box-shadow: 0 0 15px rgba(139, 92, 246, 0.4);">
                    <i class="fa-solid fa-church"></i>
                </div>
                <div>
                    <span style="font-family: 'Outfit', sans-serif; font-size: 1.15rem; font-weight: 800; color: #ffffff; display: block; line-height: 1.2;">JEMAAT KRISTIANI</span>
                    <span style="font-size: 0.75rem; font-weight: 700; color: #a78bfa; text-transform: uppercase; letter-spacing: 0.5px;"><i class="fa-solid fa-cross"></i> PERSEKUTUAN KAUM BAPAK (PKB)</span>
                </div>
            </a>

            <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <!-- Verified User Indicator Pill -->
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.18); border: 1.5px solid #10b981; padding: 6px 14px; border-radius: 20px; font-size: 0.82rem; color: #a7f3d0; font-weight: 700; box-shadow: 0 0 12px rgba(16, 185, 129, 0.25);">
                    <i class="fa-solid fa-circle-check" style="color: #34d399;"></i>
                    <span><?= htmlspecialchars($authFamily['nama_kepala']) ?> (KK: <?= htmlspecialchars($authFamily['no_kk']) ?>)</span>
                </div>

                <a href="../index.php" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #ddd6fe; padding: 7px 14px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                    <i class="fa-solid fa-house"></i> Beranda Utama
                </a>
                <a href="edit_data.php?kk=<?= urlencode($authFamily['no_kk']) ?>" style="background: rgba(139,92,246,0.25); border: 1px solid rgba(167,139,250,0.4); color: #e9d5ff; padding: 7px 14px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none;">
                    <i class="fa-solid fa-user-pen"></i> Edit Data KK
                </a>
                <a href="data_lengkap.php?action=logout" style="background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.45); color: #fca5a5; padding: 7px 14px; border-radius: 10px; font-weight: 700; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                </a>
            </div>
        </div>
    </nav>

    <div class="container-wide">

        <!-- PAGE HERO BANNER -->
        <section class="page-hero">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                <div>
                    <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(167, 139, 250, 0.4); padding: 5px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 700; color: #c4b5fd; margin-bottom: 0.75rem;">
                        <i class="fa-solid fa-magnifying-glass-location"></i> Pencarian Data Terpadu Jemaat
                    </div>
                    <h1 style="font-family: 'Outfit', sans-serif; font-size: 2rem; font-weight: 800; color: #fff; margin: 0; line-height: 1.25;">
                        Pencarian Data Lengkap Jemaat & Rute Peta Rumah
                    </h1>
                    <p style="color: var(--text-muted); font-size: 0.95rem; margin-top: 0.5rem; max-width: 820px; line-height: 1.6;">
                        Silakan pilih kelompok binaan atau cari nama kepala keluarga untuk menampilkan data keluarga lengkap beserta seluruh data tanggungan anggota keluarga dan navigasi rute Google Maps.
                    </p>
                </div>
            </div>

            <!-- HERO METRIC STATS -->
            <div class="hero-stats-grid">
                <div class="stat-card-pill">
                    <div style="width: 44px; height: 44px; background: rgba(139,92,246,0.25); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #c4b5fd;">
                        👨‍👩‍👧‍👦
                    </div>
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1;"><?= number_format($totalKKCount) ?></div>
                        <div style="font-size: 0.78rem; color: #a78bfa; font-weight: 700; margin-top: 4px;">Total KK Terdaftar</div>
                    </div>
                </div>

                <div class="stat-card-pill">
                    <div style="width: 44px; height: 44px; background: rgba(16,185,129,0.25); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #6ee7b7;">
                        👥
                    </div>
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1;"><?= number_format($totalJiwaCount) ?></div>
                        <div style="font-size: 0.78rem; color: #6ee7b7; font-weight: 700; margin-top: 4px;">Total Jiwa Tanggungan</div>
                    </div>
                </div>

                <div class="stat-card-pill">
                    <div style="width: 44px; height: 44px; background: rgba(14,165,233,0.25); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; color: #7dd3fc;">
                        📍
                    </div>
                    <div>
                        <div style="font-size: 1.4rem; font-weight: 800; color: #fff; line-height: 1;"><?= number_format($totalWithCoords) ?></div>
                        <div style="font-size: 0.78rem; color: #7dd3fc; font-weight: 700; margin-top: 4px;">Titik Peta Terverifikasi</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FILTER PENCARIAN BERTINGKAT (FILTER 1: KELOMPOK, FILTER 2: NAMA KEPALA KELUARGA) -->
        <section class="filter-panel">
            <div class="filter-grid">
                
                <!-- FILTER 1: PILIH KELOMPOK (PERTAMA) -->
                <div>
                    <label class="filter-label" for="filterKelompok">
                        <i class="fa-solid fa-layer-group" style="color: #a78bfa;"></i> 1. Filter Kelompok Jemaat:
                    </label>
                    <select id="filterKelompok" class="filter-select" onchange="executeSearch()">
                        <option value="">-- Pilih Kelompok Pelayanan (1 s/d 17) --</option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?= $g['id'] ?>">Kelompok <?= $g['nomor_kelompok'] ?> - <?= htmlspecialchars($g['nama_kelompok']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- FILTER 2: PENCARIAN NAMA KEPALA KELUARGA (KEDUA) -->
                <div>
                    <label class="filter-label" for="searchKepala">
                        <i class="fa-solid fa-magnifying-glass" style="color: #38bdf8;"></i> 2. Cari Nama Kepala Keluarga / No. WA:
                    </label>
                    <div style="position: relative;">
                        <input type="text" id="searchKepala" class="filter-input" placeholder="Ketik nama kepala keluarga atau no WhatsApp..." oninput="executeSearch()" style="padding-left: 2.75rem;">
                        <i class="fa-solid fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #a78bfa; font-size: 1rem;"></i>
                    </div>
                </div>

                <!-- AKSI CARI & RESET -->
                <div style="display: flex; gap: 8px;">
                    <button type="button" onclick="executeSearch()" style="background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; border: 1px solid #c4b5fd; padding: 0.85rem 1.4rem; border-radius: 12px; font-weight: 800; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 15px rgba(124,58,237,0.4);">
                        <i class="fa-solid fa-search"></i> Cari Data
                    </button>
                    <button type="button" onclick="resetSearch()" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); color: #ddd6fe; padding: 0.85rem 1.15rem; border-radius: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </button>
                </div>

            </div>

            <!-- QUICK SELECTOR KELOMPOK PILLS -->
            <div class="group-pills-row">
                <span style="font-size: 0.78rem; font-weight: 700; color: #a78bfa; align-self: center; margin-right: 4px;">Pilih Cepat:</span>
                <?php foreach ($groups as $g): ?>
                    <button type="button" class="group-pill-btn" onclick="selectQuickGroup('<?= $g['id'] ?>')">
                        Klp <?= $g['nomor_kelompok'] ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- 1. INITIAL STATE (SEBELUM PENCARIAN): PESAN PROMPT -->
        <div id="initialPromptContainer" class="initial-prompt-card">
            <div class="prompt-icon-ring">
                <i class="fa-solid fa-users-viewfinder"></i>
            </div>
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.5rem; font-weight: 800; color: #fff; margin: 0 0 0.5rem 0;">
                Silakan Pilih Kelompok atau Cari Nama Kepala Keluarga
            </h2>
            <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 650px; margin: 0 auto; line-height: 1.6;">
                Pilih salah satu Kelompok Pelayanan di atas atau ketik nama kepala keluarga untuk menampilkan data lengkap jemaat, data seluruh tanggungan keluarga, dan rute lokasi rumah di Google Maps.
            </p>
        </div>

        <!-- 2. HASIL PENCARIAN CONTAINER (DITAMPILKAN HANYA SETELAH PENCARIAN) -->
        <div id="resultsMainContainer" style="display: none;">

            <!-- RESULT SUMMARY BAR -->
            <div style="background: rgba(15, 12, 35, 0.85); border: 1px solid var(--border-dark); border-radius: 14px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="font-size: 0.9rem; color: #c4b5fd;">
                    📊 Ditemukan: <strong id="countDisplay" style="color: #fff; font-size: 1.05rem;">0</strong> Kepala Keluarga (<strong id="jiwaDisplay" style="color: #6ee7b7; font-size: 1.05rem;">0</strong> Jiwa Tanggungan)
                </div>
                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                    <button type="button" onclick="expandAllMembers(true)" style="background: rgba(139,92,246,0.2); border: 1px solid rgba(167,139,250,0.35); color: #e9d5ff; font-size: 0.8rem; font-weight: 700; padding: 6px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-angles-down"></i> Buka Semua Anggota
                    </button>
                    <button type="button" onclick="expandAllMembers(false)" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.15); color: #cbd5e1; font-size: 0.8rem; font-weight: 700; padding: 6px 12px; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-angles-up"></i> Tutup Semua Anggota
                    </button>
                </div>
            </div>

            <!-- MAP CONTAINER (SINKRONISASI PETA & GOOGLE MAPS) -->
            <section class="map-section-card" id="mapCardContainer">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; flex-wrap: wrap; gap: 8px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 10px; height: 10px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></span>
                        <h3 style="font-size: 1.05rem; font-weight: 800; color: #fff; margin: 0;">Peta Titik Rumah Jemaat Sesuai Pencarian</h3>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 0.76rem; font-weight: 700;">
                        <span style="color: #6ee7b7; display: inline-flex; align-items: center; gap: 5px;">
                            <span style="width: 11px; height: 11px; border-radius: 50%; background: linear-gradient(135deg, #10b981, #059669); border: 1.5px solid #fff; box-shadow: 0 0 6px #10b981; display: inline-block;"></span>
                            Terverifikasi (Hijau)
                        </span>
                        <span style="color: #fca5a5; display: inline-flex; align-items: center; gap: 5px;">
                            <span style="width: 11px; height: 11px; border-radius: 50%; background: linear-gradient(135deg, #ef4444, #991b1b); border: 1.5px solid #fff; box-shadow: 0 0 8px #ef4444; display: inline-block;"></span>
                            Belum Terverifikasi (Merah Metalik)
                        </span>
                    </div>
                </div>
                <div id="syncMap"></div>
            </section>

            <!-- DAFTAR KELUARGA & DATA LENGKAP TANGGUNGAN JEMAAT -->
            <div id="familiesCardsList">
                <?php foreach ($families as $idx => $f): 
                    $fMembers = $membersByFamily[$f['id']] ?? [];
                    $hasCoords = !empty($f['latitude']) && !empty($f['longitude']) && $f['latitude'] != '0';
                    $gmapsUrl = $hasCoords ? "https://www.google.com/maps/dir/?api=1&destination=" . $f['latitude'] . "," . $f['longitude'] : "#";
                    $photoUrl = get_family_photo_url($f['foto_keluarga'] ?? '', $f['foto_rumah'] ?? '');
                    $waNum = preg_replace('/[^0-9]/', '', $f['no_hp'] ?? '');
                    if (substr($waNum, 0, 1) === '0') $waNum = '62' . substr($waNum, 1);
                ?>
                    <div class="family-full-card"
                         id="fam-card-<?= $f['id'] ?>"
                         data-id="<?= $f['id'] ?>"
                         data-no-kk="<?= htmlspecialchars($f['no_kk']) ?>"
                         data-kelompok-id="<?= $f['kelompok_id'] ?>"
                         data-nama-kepala="<?= strtolower(htmlspecialchars($f['nama_kepala'])) ?>"
                         data-no-wa="<?= htmlspecialchars($f['no_hp']) ?>"
                         data-alamat="<?= strtolower(htmlspecialchars($f['alamat_lengkap'])) ?>"
                         data-lat="<?= $f['latitude'] ?>"
                         data-lng="<?= $f['longitude'] ?>"
                         data-jiwa="<?= count($fMembers) ?>"
                    >
                        <!-- HEADER KELUARGA -->
                        <div class="family-card-header">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <?php if (!empty($photoUrl)): ?>
                                    <img src="<?= htmlspecialchars($photoUrl) ?>" 
                                         alt="Foto <?= htmlspecialchars($f['nama_kepala']) ?>" 
                                         class="avatar-thumb" 
                                         onclick="openPhotoModal('<?= htmlspecialchars($photoUrl) ?>', '<?= addslashes(htmlspecialchars($f['nama_kepala'])) ?>')"
                                         title="Klik untuk melihat foto lebih besar">
                                <?php else: ?>
                                    <div class="avatar-placeholder" title="Belum ada foto profil keluarga">
                                        <i class="fa-solid fa-user-group"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                        <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #fff; margin: 0;">
                                            <?= htmlspecialchars($f['nama_kepala']) ?>
                                        </h3>
                                        <span class="badge-kelompok">
                                            Kelompok <?= $f['nomor_kelompok'] ?? '1' ?> - <?= htmlspecialchars($f['nama_kelompok'] ?? 'Kelompok') ?>
                                        </span>
                                    </div>

                                    <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-top: 6px; font-size: 0.84rem; color: var(--text-muted);">
                                        <?php if (!empty($f['no_hp'])): ?>
                                            <a href="https://wa.me/<?= $waNum ?>?text=Syalom%20Bpk/Ibu%20<?= urlencode($f['nama_kepala']) ?>" target="_blank" style="color: #6ee7b7; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fa-brands fa-whatsapp" style="color: #25D366;"></i> WA: <?= htmlspecialchars($f['no_hp']) ?>
                                            </a>
                                        <?php endif; ?>
                                        <span>
                                            📍 <?= htmlspecialchars($f['alamat_lengkap'] ?: 'Alamat belum lengkap') ?> (RT <?= htmlspecialchars($f['rt'] ?: '01') ?> / RW <?= htmlspecialchars($f['rw'] ?: '01') ?>)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- TOMBOL RUTE & AKSI -->
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <?php if ($hasCoords): ?>
                                    <!-- TOMBOL SINKRONISASI GOOGLE MAPS RUTE -->
                                    <a href="<?= $gmapsUrl ?>" target="_blank" class="btn-action-gmap" title="Buka Navigasi Rute di Google Maps">
                                        <i class="fa-solid fa-diamond-turn-right"></i> Rute Google Maps (GPS)
                                    </a>
                                    <!-- TOMBOL FOKUSKAN DI PETA INTERAKTIF -->
                                    <button type="button" class="btn-action-focus" onclick="focusOnMap(<?= $f['latitude'] ?>, <?= $f['longitude'] ?>, '<?= addslashes(htmlspecialchars($f['nama_kepala'])) ?>', '<?= addslashes(htmlspecialchars($f['alamat_lengkap'])) ?>', '<?= addslashes(htmlspecialchars($f['no_hp'])) ?>', '<?= $gmapsUrl ?>')" title="Lihat Lokasi di Peta">
                                        <i class="fa-solid fa-location-crosshairs"></i> Lihat di Peta
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TOMBOL INTERAKTIF BUKA/TUTUP SUSUNAN ANGGOTA KELUARGA -->
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <button type="button" 
                                    class="btn-toggle-members" 
                                    onclick="toggleFamilyMembers(<?= $f['id'] ?>)" 
                                    id="btn-toggle-m-<?= $f['id'] ?>"
                                    style="background: rgba(139, 92, 246, 0.16); border: 1px solid rgba(167, 139, 250, 0.35); color: #ddd6fe; padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 0.86rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.25s;"
                            >
                                <i class="fa-solid fa-users" style="color: #a78bfa;"></i>
                                <span id="label-m-<?= $f['id'] ?>">Lihat Susunan Anggota Keluarga (<?= count($fMembers) ?> Jiwa)</span>
                                <i class="fa-solid fa-chevron-down" id="icon-chev-<?= $f['id'] ?>" style="font-size: 0.75rem; transition: transform 0.25s;"></i>
                            </button>

                            <span style="font-size: 0.78rem; color: #94a3b8;">
                                Klik untuk melihat rincian nama, hubungan, dan tanggal lahir
                            </span>
                        </div>

                        <!-- WRAPPER SUSUNAN DATA TANGGUNGAN / ANGGOTA KELUARGA (DEFAULT TERSEMBUNYI) -->
                        <div id="members-wrap-<?= $f['id'] ?>" style="display: none; margin-top: 1.25rem;">
                            <?php if (empty($fMembers)): ?>
                                <div style="font-size: 0.84rem; color: #94a3b8; font-style: italic; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 10px;">
                                    Belum ada data tanggungan anggota keluarga yang didaftarkan pada KK ini.
                                </div>
                            <?php else: ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 8px;">
                                    <span style="font-size: 0.82rem; font-weight: 800; color: #c4b5fd;">
                                        <i class="fa-solid fa-list-check" style="color: #a78bfa;"></i> Susunan <?= count($fMembers) ?> Anggota Keluarga:
                                    </span>
                                    <span class="mobile-swipe-hint">
                                        <i class="fa-solid fa-arrows-left-right"></i> Geser tabel ke samping pada layar HP
                                    </span>
                                </div>
                                <div class="member-table-wrap">
                                    <table class="member-data-table">
                                        <thead>
                                            <tr>
                                                <th style="width: 35px; text-align: center;">No</th>
                                                <th>Nama Lengkap Anggota</th>
                                                <th>Hubungan Keluarga</th>
                                                <th>Jenis Kelamin</th>
                                                <th>Tempat & Tanggal Lahir</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fMembers as $mIdx => $mb): 
                                                $jkBadge = ($mb['jenis_kelamin'] === 'L') ? '<span class="badge-gender-l">Laki-Laki</span>' : '<span class="badge-gender-p">Perempuan</span>';
                                                $tglFormatted = !empty($mb['tanggal_lahir']) ? date('d M Y', strtotime($mb['tanggal_lahir'])) : '-';
                                            ?>
                                                <tr>
                                                    <td style="text-align: center; color: #94a3b8; font-weight: 700;"><?= $mIdx + 1 ?></td>
                                                    <td style="font-weight: 700; color: #fff;">
                                                        <?= htmlspecialchars($mb['nama_lengkap']) ?>
                                                    </td>
                                                    <td>
                                                        <span style="background: rgba(255,255,255,0.06); padding: 3px 8px; border-radius: 6px; font-weight: 600; color: #e2e8f0; font-size: 0.8rem;">
                                                            <?= htmlspecialchars($mb['hubungan_keluarga']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $jkBadge ?></td>
                                                    <td style="color: #cbd5e1;">
                                                        <?= htmlspecialchars($mb['tempat_lahir'] ?: '-') ?>, <?= $tglFormatted ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>

            <!-- EMPTY STATE WHEN SEARCH HAS NO MATCH -->
            <div id="noResultsState" style="display: none; background: rgba(18, 15, 38, 0.9); border: 1px dashed rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 3rem 1.5rem; text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">🔍</div>
                <h3 style="color: #fff; font-weight: 800; margin: 0 0 0.5rem 0;">Data Keluarga Tidak Ditemukan</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; max-width: 500px; margin: 0 auto 1.25rem auto;">
                    Tidak ada data jemaat yang cocok dengan kriteria kelompok atau nama yang Anda masukkan. Silakan coba kata kunci lain.
                </p>
                <button type="button" onclick="resetSearch()" style="background: rgba(139,92,246,0.25); border: 1px solid rgba(167,139,250,0.4); color: #ddd6fe; padding: 8px 16px; border-radius: 10px; font-weight: 700; cursor: pointer;">
                    🔄 Reset Pencarian
                </button>
            </div>

        </div>

    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // Data Families for Map Initialization
        const familiesData = <?= json_encode(array_map(function($f) use ($membersByFamily) {
            $hasCoords = !empty($f['latitude']) && !empty($f['longitude']) && $f['latitude'] != '0';
            return [
                'id' => $f['id'],
                'kelompok_id' => (int)$f['kelompok_id'],
                'nomor_kelompok' => $f['nomor_kelompok'] ?? 1,
                'nama_kelompok' => $f['nama_kelompok'] ?? 'Kelompok',
                'nama_kepala' => $f['nama_kepala'],
                'no_wa' => $f['no_hp'] ?? '',
                'alamat' => $f['alamat_lengkap'],
                'lat' => $hasCoords ? (float)$f['latitude'] : null,
                'lng' => $hasCoords ? (float)$f['longitude'] : null,
                'jiwa' => count($membersByFamily[$f['id']] ?? []),
                'status_verifikasi' => $f['status_verifikasi'] ?? 'pending',
                'gmaps_url' => $hasCoords ? "https://www.google.com/maps/dir/?api=1&destination={$f['latitude']},{$f['longitude']}" : ""
            ];
        }, $families)); ?>;

        let map = null, markersLayer = null;

        // Initialize Map
        function initSyncMap() {
            if (map !== null) return;

            map = L.map('syncMap').setView([-5.147665, 119.432731], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            markersLayer = L.layerGroup().addTo(map);
        }

        // Render Markers based on filtered data
        function renderMapMarkers(data) {
            if (!markersLayer) return;
            markersLayer.clearLayers();

            const bounds = [];

            data.forEach(item => {
                if (item.lat && item.lng) {
                    bounds.push([item.lat, item.lng]);

                    const isVerified = (item.status_verifikasi === 'terverifikasi');
                    let markerBg = 'linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #991b1b 100%)'; // Merah Metalik (Belum Terverifikasi)
                    let glowColor = 'rgba(220, 38, 38, 0.95)';
                    let statusBadge = '<span style="background: linear-gradient(135deg, #ef4444, #991b1b); color: #fff; padding: 2px 7px; border-radius: 5px; font-size: 0.7rem; font-weight: 800; border: 1px solid #fca5a5;">⏳ Belum Terverifikasi</span>';

                    if (isVerified) {
                        markerBg = 'linear-gradient(135deg, #10b981 0%, #059669 50%, #047857 100%)'; // Hijau Emerald (Terverifikasi)
                        glowColor = 'rgba(16, 185, 129, 0.85)';
                        statusBadge = '<span style="background: linear-gradient(135deg, #10b981, #047857); color: #fff; padding: 2px 7px; border-radius: 5px; font-size: 0.7rem; font-weight: 800; border: 1px solid #a7f3d0;">✅ Terverifikasi</span>';
                    }

                    const markerHtml = `
                        <div style="background: ${markerBg}; border: 2.5px solid #ffffff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 11px; font-weight: 800; box-shadow: 0 0 10px ${glowColor}, inset 0 1px 3px rgba(255,255,255,0.7); cursor: pointer;">
                            ${item.nomor_kelompok}
                        </div>
                    `;

                    const customIcon = L.divIcon({
                        html: markerHtml,
                        className: 'custom-pin-marker-metal',
                        iconSize: [32, 32],
                        iconAnchor: [16, 16],
                        popupAnchor: [0, -16]
                    });

                    const popupContent = `
                        <div style="font-family: 'Plus Jakarta Sans', sans-serif; min-width: 230px; padding: 4px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                <div style="font-size: 0.74rem; font-weight: 800; color: #7c3aed; text-transform: uppercase;">
                                    Kelompok ${item.nomor_kelompok}
                                </div>
                                ${statusBadge}
                            </div>
                            <h4 style="margin: 3px 0 2px 0; font-size: 1rem; color: #0f172a; font-weight: 800;">
                                ${item.nama_kepala}
                            </h4>
                            <div style="font-size: 0.82rem; color: #059669; font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-brands fa-whatsapp" style="color: #25D366;"></i> WA: <strong>${item.no_wa || '-'}</strong> • <span style="color: #64748b; font-weight: normal;">${item.jiwa} Jiwa</span>
                            </div>
                            <div style="font-size: 0.8rem; color: #334155; margin-bottom: 10px; line-height: 1.3;">
                                📍 ${item.alamat || 'Alamat tidak tersedia'}
                            </div>
                            <a href="${item.gmaps_url}" target="_blank" style="display: flex; align-items: center; justify-content: center; gap: 6px; background: linear-gradient(135deg, #10b981, #059669); color: #fff; text-decoration: none; padding: 8px 12px; border-radius: 8px; font-weight: 700; font-size: 0.82rem; box-shadow: 0 2px 8px rgba(16,185,129,0.4);">
                                <i class="fa-solid fa-diamond-turn-right"></i> Rute Google Maps (GPS) &rarr;
                            </a>
                        </div>
                    `;

                    const marker = L.marker([item.lat, item.lng], { icon: customIcon }).bindPopup(popupContent);
                    markersLayer.addLayer(marker);
                }
            });

            if (bounds.length > 0) {
                map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            }
        }

        // Focus and Pan to Specific Family on Map
        function focusOnMap(lat, lng, nama, alamat, noWa, gmapsUrl) {
            document.getElementById('mapCardContainer').scrollIntoView({ behavior: 'smooth' });
            if (map) {
                map.setView([lat, lng], 17, { animate: true });
                L.popup()
                    .setLatLng([lat, lng])
                    .setContent(`
                        <div style="font-family: 'Plus Jakarta Sans', sans-serif; padding: 4px;">
                            <h4 style="margin: 0 0 4px 0; color: #0f172a; font-weight: 800;">${nama}</h4>
                            <div style="font-size: 0.82rem; color: #059669; font-weight: 700; margin-bottom: 4px;">
                                <i class="fa-brands fa-whatsapp" style="color: #25D366;"></i> WA: ${noWa || '-'}
                            </div>
                            <p style="margin: 0 0 8px 0; font-size: 0.8rem; color: #64748b;">📍 ${alamat}</p>
                            <a href="${gmapsUrl}" target="_blank" style="display: block; text-align: center; background: #10b981; color: #fff; text-decoration: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; font-size: 0.8rem;">
                                🚗 Buka Navigasi Rute Google Maps
                            </a>
                        </div>
                    `)
                    .openOn(map);
            }
        }

        // Toggle Family Members Accordion Table
        function toggleFamilyMembers(id) {
            const wrap = document.getElementById('members-wrap-' + id);
            const chev = document.getElementById('icon-chev-' + id);
            const btn = document.getElementById('btn-toggle-m-' + id);
            if (!wrap) return;

            if (wrap.style.display === 'none' || wrap.style.display === '') {
                wrap.style.display = 'block';
                if (chev) chev.style.transform = 'rotate(180deg)';
                if (btn) {
                    btn.style.background = 'rgba(139, 92, 246, 0.35)';
                    btn.style.borderColor = '#c4b5fd';
                    btn.style.color = '#fff';
                }
            } else {
                wrap.style.display = 'none';
                if (chev) chev.style.transform = 'rotate(0deg)';
                if (btn) {
                    btn.style.background = 'rgba(139, 92, 246, 0.16)';
                    btn.style.borderColor = 'rgba(167, 139, 250, 0.35)';
                    btn.style.color = '#ddd6fe';
                }
            }
        }

        // Expand or Collapse All Family Members
        function expandAllMembers(expand) {
            const wraps = document.querySelectorAll('div[id^="members-wrap-"]');
            wraps.forEach(wrap => {
                const id = wrap.id.replace('members-wrap-', '');
                const chev = document.getElementById('icon-chev-' + id);
                const btn = document.getElementById('btn-toggle-m-' + id);
                if (expand) {
                    wrap.style.display = 'block';
                    if (chev) chev.style.transform = 'rotate(180deg)';
                    if (btn) {
                        btn.style.background = 'rgba(139, 92, 246, 0.35)';
                        btn.style.borderColor = '#c4b5fd';
                        btn.style.color = '#fff';
                    }
                } else {
                    wrap.style.display = 'none';
                    if (chev) chev.style.transform = 'rotate(0deg)';
                    if (btn) {
                        btn.style.background = 'rgba(139, 92, 246, 0.16)';
                        btn.style.borderColor = 'rgba(167, 139, 250, 0.35)';
                        btn.style.color = '#ddd6fe';
                    }
                }
            });
        }

        // Quick Group Selector Click
        function selectQuickGroup(groupId) {
            document.getElementById('filterKelompok').value = groupId;
            document.getElementById('searchKepala').value = '';
            executeSearch();
        }

        // Execute Search & Show Full Results
        function executeSearch() {
            const selectedKelompok = document.getElementById('filterKelompok').value;
            const searchQuery = document.getElementById('searchKepala').value.toLowerCase().trim();

            const promptContainer = document.getElementById('initialPromptContainer');
            const resultsContainer = document.getElementById('resultsMainContainer');
            const noResultsState = document.getElementById('noResultsState');

            // If no filter or search has been typed/selected, keep initial prompt state
            if (!selectedKelompok && !searchQuery) {
                promptContainer.style.display = 'block';
                resultsContainer.style.display = 'none';
                return;
            }

            // Hide initial prompt and show results area
            promptContainer.style.display = 'none';
            resultsContainer.style.display = 'block';

            // Initialize map if not yet done
            initSyncMap();
            if (map) setTimeout(() => { map.invalidateSize(); }, 200);

            const cards = document.querySelectorAll('.family-full-card');
            let visibleCount = 0;
            let totalJiwaVisible = 0;
            const filteredMapData = [];

            cards.forEach(card => {
                const id = card.getAttribute('data-id');
                const noKK = (card.getAttribute('data-no-kk') || '').toLowerCase();
                const kelompokId = card.getAttribute('data-kelompok-id');
                const namaKepala = card.getAttribute('data-nama-kepala') || '';
                const noWA = card.getAttribute('data-no-wa') || '';
                const alamat = card.getAttribute('data-alamat') || '';
                const jiwa = parseInt(card.getAttribute('data-jiwa') || 0);

                const matchKelompok = (!selectedKelompok || selectedKelompok === 'all' || kelompokId === selectedKelompok);
                const matchSearch = (!searchQuery || namaKepala.includes(searchQuery) || noWA.includes(searchQuery) || alamat.includes(searchQuery) || noKK.includes(searchQuery));

                if (matchKelompok && matchSearch) {
                    card.style.display = 'block';
                    visibleCount++;
                    totalJiwaVisible += jiwa;

                    const fItem = familiesData.find(f => f.id == id);
                    if (fItem) filteredMapData.push(fItem);
                } else {
                    card.style.display = 'none';
                }
            });

            // Update Counter Badges
            document.getElementById('countDisplay').textContent = visibleCount;
            document.getElementById('jiwaDisplay').textContent = totalJiwaVisible;

            // Handle empty state
            if (visibleCount === 0) {
                noResultsState.style.display = 'block';
            } else {
                noResultsState.style.display = 'none';
            }

            // Re-render Map Markers
            renderMapMarkers(filteredMapData);
        }

        // Reset Search
        function resetSearch() {
            document.getElementById('filterKelompok').value = '';
            document.getElementById('searchKepala').value = '';
            document.getElementById('initialPromptContainer').style.display = 'block';
            document.getElementById('resultsMainContainer').style.display = 'none';
        }

        // Photo Lightbox Modal Handlers
        function openPhotoModal(src, title) {
            const modal = document.getElementById('photoLightboxModal');
            const img = document.getElementById('photoModalImg');
            const titleEl = document.getElementById('photoModalTitle');
            if (!modal || !img) return;

            img.src = src;
            if (titleEl) titleEl.textContent = 'Foto Keluarga: ' + title;
            modal.style.display = 'flex';
        }

        function closePhotoModal() {
            const modal = document.getElementById('photoLightboxModal');
            if (modal) modal.style.display = 'none';
        }

        // Auto execute search if ?kk= or ?search= present in URL
        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const kkParam = urlParams.get('kk');
            const searchParam = urlParams.get('search');
            if (kkParam) {
                const searchInput = document.getElementById('searchKepala');
                if (searchInput) {
                    searchInput.value = kkParam;
                    executeSearch();
                }
            } else if (searchParam) {
                const searchInput = document.getElementById('searchKepala');
                if (searchInput) {
                    searchInput.value = searchParam;
                    executeSearch();
                }
            }
        });
    </script>

    <!-- PHOTO LIGHTBOX MODAL -->
    <div id="photoLightboxModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1.5rem;" onclick="closePhotoModal()">
        <div style="max-width: 520px; width: 100%; background: #120f26; border: 1.5px solid rgba(139,92,246,0.4); border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.8); position: relative;" onclick="event.stopPropagation()">
            <div style="padding: 1rem 1.25rem; background: rgba(28,22,60,0.9); display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <h4 id="photoModalTitle" style="color: #fff; margin: 0; font-size: 1.05rem; font-weight: 800;">Foto Keluarga</h4>
                <button type="button" onclick="closePhotoModal()" style="background: rgba(255,255,255,0.1); border: none; color: #fff; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1rem;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div style="padding: 1.25rem; text-align: center;">
                <img id="photoModalImg" src="" alt="Foto Full" style="width: 100%; max-height: 420px; object-fit: contain; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
            </div>
        </div>
    </div>
</body>
</html>
