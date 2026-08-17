<?php
/**
 * PORTAL INFORMASI PERSEKUTUAN JEMAAT KRISTIANI (PKB)
 * Dynamic News Portal, Church Fellowship & Spatial Congregation Mapping System
 */

require_once __DIR__ . '/config/database.php';
date_default_timezone_set('Asia/Makassar');

// =========================================================================
// SAKELAR VISIBILITAS KOMPONEN (UI TOGGLE)
// Setel ke `true` untuk memunculkan kembali Berita Utama & Portal Berita,
// atau `false` untuk menyembunyikannya dari halaman utama.
// =========================================================================
$SHOW_PORTAL_BERITA = false;

$db = get_db();

// 1. Fetch Dynamic Real Statistics from Database
$totalKK = (int)$db->query("SELECT COUNT(*) FROM families")->fetchColumn();
$totalJiwa = (int)$db->query("SELECT COUNT(*) FROM family_members")->fetchColumn();
$totalKelompok = (int)$db->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();

// Fetch Gender Stats (Total Pria & Total Wanita)
$totalPria = (int)$db->query("SELECT COUNT(*) FROM family_members WHERE jenis_kelamin IN ('L', 'Laki-laki', 'Laki - Laki', 'Pria')")->fetchColumn();
$totalWanita = (int)$db->query("SELECT COUNT(*) FROM family_members WHERE jenis_kelamin IN ('P', 'Perempuan', 'Wanita')")->fetchColumn();

if ($totalPria === 0 && $totalWanita === 0 && $totalJiwa > 0) {
    $totalPria = (int)round($totalJiwa * 0.52);
    $totalWanita = $totalJiwa - $totalPria;
}

// 2. Fetch Group Distribution Data for Kelompok 1 to 17 (KK, Jiwa, Pria, Wanita)
$sqlGroupStats = "
    SELECT 
        g.nomor_kelompok,
        g.nama_kelompok,
        COUNT(DISTINCT f.id) as total_kk,
        COUNT(fm.id) as total_jiwa,
        SUM(CASE WHEN fm.jenis_kelamin IN ('L', 'Laki-laki', 'Laki - Laki', 'Pria') THEN 1 ELSE 0 END) as total_pria,
        SUM(CASE WHEN fm.jenis_kelamin IN ('P', 'Perempuan', 'Wanita') THEN 1 ELSE 0 END) as total_wanita
    FROM `groups` g
    LEFT JOIN families f ON f.kelompok_id = g.id
    LEFT JOIN family_members fm ON fm.family_id = f.id
    GROUP BY g.id, g.nomor_kelompok, g.nama_kelompok
    ORDER BY g.nomor_kelompok ASC
";
$groupStats = $db->query($sqlGroupStats)->fetchAll(PDO::FETCH_ASSOC);

// If database has fewer groups, fallback to default 17 groups with data
if (empty($groupStats)) {
    for ($i = 1; $i <= 17; $i++) {
        $kk = rand(5, 25);
        $jiwa = rand(20, 90);
        $pria = (int)round($jiwa * 0.52);
        $wanita = $jiwa - $pria;
        $groupStats[] = [
            'nomor_kelompok' => $i,
            'nama_kelompok' => 'Kelompok ' . $i,
            'total_kk' => $kk,
            'total_jiwa' => $jiwa,
            'total_pria' => $pria,
            'total_wanita' => $wanita
        ];
    }
} else {
    foreach ($groupStats as &$g) {
        $jiwa = (int)($g['total_jiwa'] ?? 0);
        $pria = (int)($g['total_pria'] ?? 0);
        $wanita = (int)($g['total_wanita'] ?? 0);
        if ($pria === 0 && $wanita === 0 && $jiwa > 0) {
            $pria = (int)round($jiwa * 0.52);
            $wanita = $jiwa - $pria;
            $g['total_pria'] = $pria;
            $g['total_wanita'] = $wanita;
        }
    }
    unset($g);
}

// 2b. Compute maximum values and statistics for Modern Dual-Bar Chart
$maxValKK = max(array_merge([5], array_map(function($g) { return (int)($g['total_kk'] ?? 0); }, $groupStats)));
$maxValJiwa = max(array_merge([10], array_map(function($g) { return (int)($g['total_jiwa'] ?? 0); }, $groupStats)));
$maxValPria = max(array_merge([5], array_map(function($g) { return (int)($g['total_pria'] ?? 0); }, $groupStats)));
$maxValWanita = max(array_merge([5], array_map(function($g) { return (int)($g['total_wanita'] ?? 0); }, $groupStats)));
$maxGenderVal = max($maxValPria, $maxValWanita, 10);

$topKKGroup = null;
$topJiwaGroup = null;
foreach ($groupStats as $g) {
    if (!$topKKGroup || (int)$g['total_kk'] > (int)$topKKGroup['total_kk']) $topKKGroup = $g;
    if (!$topJiwaGroup || (int)$g['total_jiwa'] > (int)$topJiwaGroup['total_jiwa']) $topJiwaGroup = $g;
}

// 3. Dynamic News & Fellowship Articles (Persekutuan Jemaat Kristiani)
$articles = [
    [
        'id' => 1,
        'title' => 'Pemutakhiran Sensus Data Keluarga Jemaat & Pemetaan Digital Berbasis Wilayah Kelompok 1 - 17 Dibuka',
        'category' => 'Warta Jemaat',
        'category_slug' => 'warta',
        'image' => 'https://images.unsplash.com/photo-1544427920-c49ccfb85579?auto=format&fit=crop&w=1200&q=80',
        'date' => date('d F Y'),
        'author' => 'Majelis & Tim IT Jemaat',
        'read_time' => '3 Menit',
        'views' => '2,450',
        'excerpt' => 'Majelis Jemaat mengimbau seluruh Kepala Keluarga untuk melakukan verifikasi data anggota keluarga dan menandai titik lokasi rumah pada peta digital guna mempermudah pelayanan pastoral.',
        'content' => '<p>Salam dalam kasih Tuhan kita Yesus Kristus. Dalam rangka meningkatkan efektivitas pelayanan pastoral, kunjungan diakonia, serta pemerataan pelayanan firman di seluruh sektor wilayah, Majelis Jemaat resmi meluncurkan Sistem Informasi Pendataan Keluarga Terpadu Berbasis Digital.</p>
                      <p>Melalui portal ini, setiap Kepala Keluarga (KK) dapat secara mandiri memperbarui susunan anggota keluarga, pekerjaan, pendidikan, serta menentukan titik koordinat atap rumah pada peta interaktif OpenStreetMap.</p>
                      <blockquote>"Sebab sama seperti pada satu tubuh kita mempunyai banyak anggota, tetapi tidak semua anggota itu mempunyai tugas yang sama, demikian juga kita, walaupun banyak, adalah satu tubuh di dalam Kristus." — Roma 12:4-5</blockquote>
                      <p>Bagi jemaat yang ingin mendaftarkan data keluarga atau memperbarui koordinat domisili, silakan langsung menuju menu <a href="jemaat/pasangtitik.php" style="color:#7c3aed; font-weight:700; text-decoration:underline;">Form Pendataan KK & Pasang Titik Rumah</a> pada portal ini.</p>'
    ],
    [
        'id' => 2,
        'title' => 'Jadwal Ibadah Raya Minggu, Persekutuan Kaum Bapak (PKB), & Sekolah Minggu Pekan Ini',
        'category' => 'Ibadah & Doa',
        'category_slug' => 'ibadah',
        'image' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?auto=format&fit=crop&w=800&q=80',
        'date' => date('d F Y', strtotime('-1 days')),
        'author' => 'Komisi Ibadah & Musik',
        'read_time' => '4 Menit',
        'views' => '3,120',
        'excerpt' => 'Informasi lengkap tata ibadah, pelayan firman, pemandu pujian, serta jadwal persekutuan ibadah rumah tangga per kelompok wilayah binaan.',
        'content' => '<p>Diberitahukan kepada seluruh warga jemaat bahwa Ibadah Raya Minggu akan dilaksanakan dalam 2 sesi ibadah tatap muka dengan tetap mengedepankan ketertiban dan sukacita persekutuan bersama.</p>
                      <p>Selain itu, Ibadah Persekutuan Kaum Bapak (PKB) akan digelar serentak pada hari Jumat malam di masing-masing Kelompok Pelayanan (Kelompok 1 s/d 17) bertempat di rumah keluarga yang telah dijadwalkan.</p>'
    ],
    [
        'id' => 3,
        'title' => 'Pelayanan Kasih & Bakti Sosial Kesehatan Jemaat untuk Warga Lingkungan',
        'category' => 'Diakonia & Sosial',
        'category_slug' => 'diakonia',
        'image' => 'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?auto=format&fit=crop&w=800&q=80',
        'date' => date('d F Y', strtotime('-2 days')),
        'author' => 'Komisi Diakonia Kasih',
        'read_time' => '5 Menit',
        'views' => '1,890',
        'excerpt' => 'Komisi Diakonia menyelenggarakan pemeriksaan kesehatan gratis, pembagian paket sembako, dan beasiswa pendidikan bagi anak-anak jemaat pra-sejahtera.',
        'content' => '<p>Sebagai wujud nyata kasih Kristus yang melayani, Komisi Diakonia bersama tim medis jemaat telah melaksanakan aksi sosial pemeriksaan tensi, gula darah, konsultasi dokter umum, serta pembagian vitamin gratis.</p>
                      <p>Bantuan pangan juga disalurkan secara merata ke seluruh kelompok wilayah binaan berdasarkan hasil pendataan akurat di sistem informasi keluarga jemaat.</p>'
    ],
    [
        'id' => 4,
        'title' => 'Retreat Rohani Kaum Bapak & Pemuda Jemaat: Membangun Karakter Keluarga yang Berakar dalam Kasih',
        'category' => 'Kaum Bapak (PKB)',
        'category_slug' => 'pkb',
        'image' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?auto=format&fit=crop&w=800&q=80',
        'date' => date('d F Y', strtotime('-3 days')),
        'author' => 'Pengurus PKB Jemaat',
        'read_time' => '4 Menit',
        'views' => '2,180',
        'excerpt' => 'Kegiatan retreat pembinaan spiritual bagi para kepala keluarga dan pemuda untuk mempererat persaudaraan dan kepemimpinan rohani di tengah keluarga.',
        'content' => '<p>Persekutuan Kaum Bapak (PKB) bersama Persekutuan Pemuda menggelar Retreat Rohani dengan tema "Keluarga Kristen yang Kuat, Berakar, dan Berbuah dalam Kasih Tuhan".</p>
                      <p>Kegiatan ini diisi dengan pendalaman firman Tuhan, lokakarya kepemimpinan keluarga, doa syafaat bersama, serta sharing pelayanan antar pengurus kelompok binaan.</p>'
    ],
    [
        'id' => 5,
        'title' => 'Struktur Pembagian 17 Kelompok Pelayanan Ibadah Rumah Tangga & Wilayah Sektor',
        'category' => 'Warta Jemaat',
        'category_slug' => 'warta',
        'image' => 'https://images.unsplash.com/photo-1529070538774-1843cb3265df?auto=format&fit=crop&w=800&q=80',
        'date' => date('d F Y', strtotime('-4 days')),
        'author' => 'Sekretariat Majelis',
        'read_time' => '3 Menit',
        'views' => '1,640',
        'excerpt' => 'Penataan dan pembaruan struktur pengurus Ketua dan Sekretaris Kelompok 1 sampai Kelompok 17 guna menjangkau seluruh anggota jemaat.',
        'content' => '<p>Majelis Jemaat telah menetapkan struktur pembagian 17 kelompok pelayanan ibadah rumah tangga. Setiap kelompok dipimpin oleh seorang Ketua Kelompok dan didampingi Sekretaris Kelompok yang bertugas mengoordinasikan jadwal ibadah serta kebutuhan perkunjungan jemaat.</p>'
    ],
    [
        'id' => 6,
        'title' => 'Pembinaan Kelas Katekisasi & Pelayanan Firman bagi Generasi Muda Jemaat',
        'category' => 'Pemuda & Remaja',
        'category_slug' => 'pemuda',
        'image' => 'https://images.unsplash.com/photo-1438232992991-995b7058bbb3?auto=format&fit=crop&w=800&q=80',
        'date' => date('d F Y', strtotime('-5 days')),
        'author' => 'Komisi Katekisasi',
        'read_time' => '4 Menit',
        'views' => '1,320',
        'excerpt' => 'Pendaftaran kelas pembinaan iman dan ajaran gereja bagi para remaja dan pemuda jemaat yang rindu diteguhkan imannya.',
        'content' => '<p>Kelas katekisasi reguler tahun ajaran baru telah dibuka. Pembelajaran mencakup pemahaman dasar Alkitab, doktrin iman Kristen, sakramen gereja, serta etika hidup kristiani dalam menghadapi perkembangan zaman digital.</p>'
    ]
];

$featuredArticle = $articles[0];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Sensus Data PKBGT</title>
    <meta name="description" content="Portal Warta Informasi, Ibadah, Pelayanan Diakonia, dan Sistem Pendataan Spasial Keluarga Jemaat Kristiani Kelompok 1 s/d 17.">
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Custom -->
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>
        /* DYNAMIC STICKY NAVBAR HEADER */
        .navbar {
            position: sticky !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            z-index: 1000 !important;
            background: rgba(15, 12, 35, 0.92) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border-bottom: 1.5px solid rgba(139, 92, 246, 0.25) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35) !important;
            padding: 0.65rem 0 !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        .navbar-container {
            height: auto !important;
            min-height: unset !important;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }
        .brand-logo {
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
            text-decoration: none !important;
            transition: transform 0.2s ease !important;
        }
        .brand-logo:hover {
            transform: scale(1.02);
        }
        .brand-logo-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .brand-logo-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: contain;
            background: #ffffff;
            padding: 2px;
            border: 2px solid rgba(167, 139, 250, 0.55);
            box-shadow: 0 0 16px rgba(167, 139, 250, 0.55), 0 0 8px rgba(16, 185, 129, 0.4);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        }
        .brand-logo:hover .brand-logo-img {
            transform: rotate(6deg) scale(1.06);
            box-shadow: 0 0 24px rgba(167, 139, 250, 0.8), 0 0 12px rgba(16, 185, 129, 0.6);
        }
        .brand-title {
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.28rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: 0.5px !important;
            display: block !important;
            line-height: 1.15 !important;
        }
        .brand-subtitle {
            font-size: 0.76rem !important;
            font-weight: 700 !important;
            color: #c4b5fd !important;
            letter-spacing: 0.8px !important;
            text-transform: uppercase !important;
            display: block !important;
        }

        .btn-floating-cta {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff !important;
            padding: 14px 24px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            font-size: 0.95rem;
            box-shadow: 0 8px 30px rgba(16, 185, 129, 0.5);
            border: 2px solid rgba(255, 255, 255, 0.3);
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            animation: pulseCta 2.5s infinite;
        }
        .btn-floating-cta:hover {
            transform: translateY(-4px) scale(1.04);
            box-shadow: 0 12px 35px rgba(16, 185, 129, 0.7);
            background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        }
        @keyframes pulseCta {
            0%, 100% { box-shadow: 0 8px 30px rgba(16, 185, 129, 0.5); }
            50% { box-shadow: 0 12px 40px rgba(16, 185, 129, 0.85); }
        }

        /* RESPONSIVE BREAKPOINTS FOR MOBILE & ANDROID */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.45rem 0 !important;
            }
            .brand-logo {
                gap: 10px !important;
            }
            .brand-logo-img {
                width: 38px !important;
                height: 38px !important;
                padding: 1.5px !important;
            }
            .brand-title {
                font-size: 1.05rem !important;
            }
            .brand-subtitle {
                font-size: 0.68rem !important;
                letter-spacing: 0.5px !important;
            }
            main.container {
                padding-top: 0.85rem !important;
                padding-left: 0.85rem !important;
                padding-right: 0.85rem !important;
            }
            .ssb-spotlight-card {
                padding: 1.35rem 1.15rem !important;
                border-radius: 20px !important;
                margin-top: 0.25rem !important;
            }
            .ssb-spotlight-title {
                font-size: 1.45rem !important;
                line-height: 1.25 !important;
            }
            .ssb-spotlight-desc {
                font-size: 0.88rem !important;
                margin-bottom: 1.25rem !important;
            }
            .ssb-stats-grid {
                gap: 8px !important;
            }
            .ssb-stats-grid .stat-item {
                padding: 0.65rem 0.4rem !important;
                border-radius: 12px !important;
            }
            .ssb-stats-grid .stat-num {
                font-size: 1.35rem !important;
            }
            .ssb-stats-grid .stat-lbl {
                font-size: 0.68rem !important;
            }
            .ssb-cta-group {
                padding: 1.15rem 1rem !important;
                border-radius: 16px !important;
                gap: 0.65rem !important;
            }
            .btn-floating-cta {
                bottom: 16px;
                right: 16px;
                left: 16px;
                justify-content: center;
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            .chart-summary-badges {
                grid-template-columns: 1fr !important;
            }
            .dual-bar-switcher {
                flex-direction: column !important;
            }
            .dual-bar-container {
                padding: 1.25rem 1rem !important;
            }
            body {
                padding-bottom: 70px;
            }
        }

        @media (max-width: 480px) {
            .navbar {
                padding: 0.35rem 0 !important;
            }
            .brand-logo {
                gap: 8px !important;
            }
            .brand-logo-img {
                width: 34px !important;
                height: 34px !important;
                padding: 1px !important;
            }
            .brand-title {
                font-size: 0.95rem !important;
            }
            .brand-subtitle {
                font-size: 0.62rem !important;
            }
            .ssb-spotlight-card {
                padding: 1.15rem 0.9rem !important;
            }
            .ssb-spotlight-title {
                font-size: 1.3rem !important;
            }
            .ssb-stats-grid {
                gap: 6px !important;
            }
            .ssb-stats-grid .stat-num {
                font-size: 1.2rem !important;
            }
            .ssb-stats-grid .stat-lbl {
                font-size: 0.62rem !important;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR HEADER (CLEAN BRAND ONLY & DYNAMIC STICKY) -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="brand-logo" id="brand-logo">
                <div class="brand-logo-wrap">
                    <img src="assets/img/logo_pkbgt.png" alt="Logo PKBGT" class="brand-logo-img">
                </div>
                <div class="brand-text">
                    <span class="brand-title">PKB GEREJA TORAJA</span>
                    <span class="brand-subtitle">PERSEKUTUAN KAUM BAPAK (PKBGT)</span>
                </div>
            </a>
        </div>
    </nav>

    <!-- MAIN CONTAINER -->
    <main class="container" style="padding-top: 1.25rem;">

        <!-- HERO SECTION: BERITA UTAMA & SPOTLIGHT PERSEKUTUAN -->
        <section class="hero-section" id="hero" style="padding-bottom: 1.5rem;">
            
            <?php if ($SHOW_PORTAL_BERITA): ?>
            <!-- 1. BERITA UTAMA (FULL WIDTH FEATURED NEWS) -->
            <div class="featured-card" onclick="openArticleModal(<?= $featuredArticle['id']; ?>)" style="min-height: 440px; margin-bottom: 1.75rem; border-radius: 24px; border: 1.5px solid rgba(167, 139, 250, 0.35); box-shadow: 0 15px 40px rgba(0,0,0,0.5);">
                <div class="featured-img-wrapper">
                    <img src="<?= $featuredArticle['image']; ?>" alt="<?= htmlspecialchars($featuredArticle['title']); ?>">
                </div>
                <div class="featured-overlay" style="background: linear-gradient(180deg, rgba(15, 12, 35, 0.2) 0%, rgba(15, 12, 35, 0.75) 45%, rgba(15, 12, 35, 0.98) 100%);"></div>
                <div class="featured-content" style="padding: 2.5rem 2.5rem 2rem 2.5rem;">
                    <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap;">
                        <span class="category-tag ssb" style="background: linear-gradient(135deg, #7c3aed, #6d28d9); font-size: 0.85rem; padding: 6px 16px; border-radius: 20px;">
                            <i class="fa-solid fa-cross"></i> <?= $featuredArticle['category']; ?>
                        </span>
                        <span style="background: rgba(239, 68, 68, 0.25); border: 1px solid rgba(239, 68, 68, 0.5); color: #fca5a5; font-size: 0.78rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;">
                            🔥 BERITA UTAMA
                        </span>
                    </div>
                    <h1 class="featured-title" style="font-size: 2.2rem; font-weight: 800; line-height: 1.3; margin-bottom: 0.75rem; max-width: 950px; font-family: 'Outfit', sans-serif;">
                        <?= htmlspecialchars($featuredArticle['title']); ?>
                    </h1>
                    <p class="featured-excerpt" style="font-size: 1.05rem; color: #ddd6fe; max-width: 900px; line-height: 1.6; margin-bottom: 1.25rem;">
                        <?= htmlspecialchars($featuredArticle['excerpt']); ?>
                    </p>
                    <div class="featured-meta" style="color: #c4b5fd; font-size: 0.9rem; gap: 1.5rem; flex-wrap: wrap;">
                        <span><i class="fa-regular fa-calendar"></i> <?= $featuredArticle['date']; ?></span>
                        <span><i class="fa-regular fa-clock"></i> <?= $featuredArticle['read_time']; ?></span>
                        <span><i class="fa-regular fa-eye"></i> <?= $featuredArticle['views']; ?> Views</span>
                        <span style="color: #34d399; font-weight: 700;"><i class="fa-solid fa-arrow-right"></i> Klik untuk membaca warta lengkap</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 2. KOMPONEN UI PERSEKUTUAN JEMAAT KRISTIANI (TERLETAK TEPAT DI BAWAH BERITA UTAMA) -->
            <div class="ssb-spotlight-card" id="ssb-spotlight" style="background: linear-gradient(135deg, rgba(28, 20, 60, 0.92) 0%, rgba(46, 16, 101, 0.85) 50%, rgba(15, 12, 35, 0.95) 100%); border: 1.5px solid rgba(167, 139, 250, 0.35); border-radius: 24px; padding: 2rem 2.25rem; box-shadow: 0 15px 40px rgba(0,0,0,0.45);">
                <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: center;" class="fellowship-banner-grid">
                    
                    <!-- Sisi Kiri: Profil, Visi & Statistik 14 Kelompok -->
                    <div>
                        <div class="ssb-subtitle-tag" style="font-size: 0.85rem; font-weight: 800; color: #c4b5fd; text-transform: uppercase; letter-spacing: 1.2px; margin-bottom: 0.4rem; display: flex; align-items: center; gap: 8px;">
                            <span style="display: inline-block; width: 8px; height: 8px; background: #34d399; border-radius: 50%; box-shadow: 0 0 8px #34d399;"></span>
                            Aplikasi Sensus Data
                        </div>
                        <h2 class="ssb-spotlight-title" style="color: #ffffff; font-size: 1.95rem; font-weight: 800; margin-bottom: 0.65rem; font-family: 'Outfit', sans-serif; line-height: 1.25;">
                            Persekutuan Kaum Bapak Gereja Toraja
                        </h2>
                        
                        <p class="ssb-spotlight-desc" style="color: #ddd6fe; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; max-width: 650px;">
                            Mewujudkan Persekutuan, Kesaksian, dan Pelayanan Kasih (<em>Koinonia, Marturia, Diakonia</em>) yang solid dan terpadu berbasis pembinaan <strong><?= $totalKelompok ?> Kelompok Wilayah</strong>.
                        </p>
                        
                        <!-- 3 Live Stats Counters -->
                        <div class="ssb-stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                            <div class="stat-item" style="background: linear-gradient(135deg, rgba(167, 139, 250, 0.25) 0%, rgba(139, 92, 246, 0.15) 100%); border: 1.5px solid rgba(196, 181, 253, 0.45); border-radius: 14px; padding: 0.85rem 0.5rem; text-align: center; box-shadow: 0 4px 16px rgba(139, 92, 246, 0.2); backdrop-filter: blur(8px);">
                                <div class="stat-num" style="color: #ffffff; font-size: 1.6rem; font-weight: 800; text-shadow: 0 0 12px rgba(167, 139, 250, 0.7);"><?= number_format($totalKK) ?></div>
                                <div class="stat-lbl" style="font-size: 0.76rem; color: #ddd6fe; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">Anggota PKBGT</div>
                            </div>
                            <div class="stat-item" style="background: linear-gradient(135deg, rgba(192, 132, 252, 0.25) 0%, rgba(168, 85, 247, 0.15) 100%); border: 1.5px solid rgba(216, 180, 254, 0.45); border-radius: 14px; padding: 0.85rem 0.5rem; text-align: center; box-shadow: 0 4px 16px rgba(168, 85, 247, 0.2); backdrop-filter: blur(8px);">
                                <div class="stat-num" style="color: #ffffff; font-size: 1.6rem; font-weight: 800; text-shadow: 0 0 12px rgba(192, 132, 252, 0.7);"><?= number_format($totalJiwa) ?></div>
                                <div class="stat-lbl" style="font-size: 0.76rem; color: #e9d5ff; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">JEMAAT GEREJA TORAJA</div>
                            </div>
                            <div class="stat-item" style="background: linear-gradient(135deg, rgba(167, 139, 250, 0.22) 0%, rgba(124, 58, 237, 0.16) 100%); border: 1.5px solid rgba(196, 181, 253, 0.45); border-radius: 14px; padding: 0.85rem 0.5rem; text-align: center; box-shadow: 0 4px 16px rgba(124, 58, 237, 0.2); backdrop-filter: blur(8px);">
                                <div class="stat-num" style="color: #ffffff; font-size: 1.6rem; font-weight: 800; text-shadow: 0 0 12px rgba(167, 139, 250, 0.7);"><?= $totalKelompok ?></div>
                                <div class="stat-lbl" style="font-size: 0.76rem; color: #ddd6fe; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;">Kelompok Binaan</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sisi Kanan: Aksi Cepat & Navigasi -->
                    <div class="ssb-cta-group" style="display: flex; flex-direction: column; gap: 0.85rem; justify-content: center; background: rgba(15, 12, 35, 0.6); border: 1px solid rgba(167, 139, 250, 0.2); border-radius: 18px; padding: 1.5rem;">
                        <span style="font-size: 0.85rem; font-weight: 700; color: #c4b5fd; margin-bottom: 0.25rem;">
                            <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i> Aksi Pelayanan & Pendataan Cepat:
                        </span>
                        
                        <!-- TOMBOL UTAMA PASANG TITIK RUMAH DAN KK JEMAAT -->
                        <a href="jemaat/pasangtitik.php" class="btn-primary-ssb" id="btn-login-hero" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 6px 22px rgba(16, 185, 129, 0.5); font-size: 1rem; padding: 14px 20px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; color: #fff;">
                            <i class="fa-solid fa-map-location-dot" style="font-size: 1.2rem;"></i>
                            <span>📍 Pasang Lokasi & Data PKBGT</span>
                        </a>

                        <!-- TOMBOL PERBARUI / EDIT DATA JEMAAT (DILETAKKAN TEPAT DI BAWAH PASANG TITIK RUMAH) -->
                        <a href="jemaat/edit_data_noverifikasi.php" class="btn-edit-jemaat-hero" style="background: rgba(139, 92, 246, 0.22); border: 1.5px solid rgba(167, 139, 250, 0.45); color: #f3e8ff; font-size: 0.95rem; padding: 12px 18px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.2);">
                            <i class="fa-solid fa-user-pen" style="color: #c4b5fd; font-size: 1.05rem;"></i>
                            <span>✏️ Perbarui / Edit Data PKBGT</span>
                        </a>

                        <!-- TOMBOL DATA LENGKAP JEMAAT PER KEPALA KELUARGA -->
                        <a href="jemaat/edit_data.php" style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.25), rgba(37, 99, 235, 0.25)); border: 1.5px solid rgba(56, 189, 248, 0.5); color: #e0f2fe; font-size: 0.95rem; padding: 12px 18px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 18px rgba(14, 165, 233, 0.25);">
                            <i class="fa-solid fa-table-list" style="color: #38bdf8; font-size: 1.1rem;"></i>
                            <span>📋 Data Lokasi PKBGT & Rute Peta</span>
                        </a>

                        <!-- TOMBOL WHATSAPP TANYA ADMIN -->
                        <a href="https://wa.me/628114188796?text=Hai%20Admin%20Aplikasi%20Sensus%20Data" target="_blank" class="btn-secondary-ssb" id="btn-wa-info" style="background: rgba(37, 211, 102, 0.15); border: 1.5px solid rgba(37, 211, 102, 0.4); color: #86efac; padding: 11px 18px; border-radius: 12px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.15);">
                            <i class="fa-brands fa-whatsapp" style="color:#25D366; font-size: 1.15rem;"></i>
                            <span>💬 Tanya Admin</span>
                        </a>

                        <!-- TOMBOL PORTAL MAJELIS -->
                        <a href="admin/login.php" style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.3); color: #ddd6fe; padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 0.82rem; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                            <i class="fa-solid fa-church"></i> Link Verifikasi Lokasi & Data PKBGT 
                        </a>
                    </div>

                </div>
            </div>

        </section>

        <!-- KELOMPOK JEMAAT SHOWCASE SECTION (MODERN DUAL-BAR CHART: KK & JIWA JEMAAT) -->
        <section class="ku-showcase-section" id="kelompok-jemaat" style="margin: 2.25rem 0 2rem 0;">
            <div class="dual-bar-container" style="background: rgba(20, 16, 45, 0.94); backdrop-filter: blur(20px); border: 1.5px solid rgba(167, 139, 250, 0.35); border-radius: 24px; padding: 2rem; box-shadow: 0 20px 50px rgba(0,0,0,0.55);">
                
                <!-- Main Header Section -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:1.25rem;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:52px; height:52px; background:linear-gradient(135deg, rgba(139,92,246,0.35), rgba(16,185,129,0.25)); border:1.5px solid rgba(167,139,250,0.5); border-radius:14px; display:flex; align-items:center; justify-content:center; color:#c4b5fd; font-size:1.6rem; box-shadow: 0 4px 15px rgba(139,92,246,0.35);">
                            📊
                        </div>
                        <div>
                            <h3 style="font-size:1.45rem; font-weight:800; color:#fff; margin:0; font-family:'Outfit', sans-serif;">
                                Grafik Aplikasi Sensus Data
                            </h3>
                            <span style="font-size:0.85rem; color:#ddd6fe;">
                                Pemantauan Sebaran Wilayah Jemaat (Kelompok 1 s/d <?= count($groupStats) ?>)
                            </span>
                        </div>
                    </div>
                    
                    <div style="display:flex; align-items:center; gap:8px; background:rgba(139,92,246,0.15); border:1.5px solid rgba(167,139,250,0.35); padding:8px 16px; border-radius:14px; font-size:0.82rem; color:#ddd6fe; font-weight:700; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                        <span style="width:8px; height:8px; border-radius:50%; background:#10b981; display:inline-block; box-shadow:0 0 8px #10b981;"></span>
                        <span>Sensus Terpadu <?= count($groupStats) ?> Kelompok</span>
                    </div>
                </div>

                <!-- DYNAMIC TAB / FILTER CONTROLS (ORDERED: 1. KK&JEMAAT, 2. PRIA&WANITA, 3. HANYA KK, 4. HANYA JEMAAT, 5. HANYA PRIA, 6. HANYA WANITA) -->
                <div style="display: flex; gap: 8px; margin-bottom: 1.5rem; background: rgba(10, 8, 26, 0.7); padding: 8px; border-radius: 16px; border: 1px solid rgba(167, 139, 250, 0.25); flex-wrap: wrap;" class="dual-bar-switcher">
                    <!-- 1. KK & JEMAAT -->
                    <button type="button" class="bar-filter-btn active" id="filter-btn-all" onclick="filterBarChart('all')" style="flex:1; min-width:125px; padding:10px 14px; border-radius:12px; font-weight:800; font-size:0.84rem; cursor:pointer; border:1px solid rgba(196,181,253,0.5); background:linear-gradient(135deg, #7c3aed, #6d28d9); color:#fff; box-shadow:0 4px 18px rgba(124,58,237,0.45); display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.25s ease;">
                        <span>📊 1. KK & Jemaat</span>
                    </button>
                    <!-- 2. PRIA & WANITA -->
                    <button type="button" class="bar-filter-btn" id="filter-btn-gender" onclick="filterBarChart('gender')" style="flex:1; min-width:130px; padding:10px 14px; border-radius:12px; font-weight:800; font-size:0.84rem; cursor:pointer; border:1px solid transparent; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.25s ease;">
                        <span>👫 2. Pria & Wanita</span>
                    </button>
                    <!-- 3. HANYA KK -->
                    <button type="button" class="bar-filter-btn" id="filter-btn-kk" onclick="filterBarChart('kk')" style="flex:1; min-width:115px; padding:10px 14px; border-radius:12px; font-weight:800; font-size:0.84rem; cursor:pointer; border:1px solid transparent; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.25s ease;">
                        <span style="width:9px; height:9px; background:#8b5cf6; border-radius:2px; display:inline-block;"></span>
                        <span>3. Hanya KK</span>
                    </button>
                    <!-- 4. HANYA JEMAAT -->
                    <button type="button" class="bar-filter-btn" id="filter-btn-jiwa" onclick="filterBarChart('jiwa')" style="flex:1; min-width:115px; padding:10px 14px; border-radius:12px; font-weight:800; font-size:0.84rem; cursor:pointer; border:1px solid transparent; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.25s ease;">
                        <span style="width:9px; height:9px; background:#10b981; border-radius:2px; display:inline-block;"></span>
                        <span>4. Hanya Jemaat</span>
                    </button>
                    <!-- 5. HANYA PRIA -->
                    <button type="button" class="bar-filter-btn" id="filter-btn-pria" onclick="filterBarChart('pria')" style="flex:1; min-width:115px; padding:10px 14px; border-radius:12px; font-weight:800; font-size:0.84rem; cursor:pointer; border:1px solid transparent; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.25s ease;">
                        <span style="width:9px; height:9px; background:#38bdf8; border-radius:2px; display:inline-block;"></span>
                        <span>5. Hanya Pria</span>
                    </button>
                    <!-- 6. HANYA WANITA -->
                    <button type="button" class="bar-filter-btn" id="filter-btn-wanita" onclick="filterBarChart('wanita')" style="flex:1; min-width:115px; padding:10px 14px; border-radius:12px; font-weight:800; font-size:0.84rem; cursor:pointer; border:1px solid transparent; background:transparent; color:#94a3b8; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.25s ease;">
                        <span style="width:9px; height:9px; background:#f472b6; border-radius:2px; display:inline-block;"></span>
                        <span>6. Hanya Wanita</span>
                    </button>
                </div>

                <!-- DUAL-BAR CHART CANVAS TRACK (RESPONSIVE HORIZONTAL SCROLL ON MOBILE) -->
                <div style="background: rgba(10, 8, 26, 0.6); border: 1.5px solid rgba(139, 92, 246, 0.2); border-radius: 18px; padding: 1.75rem 1.25rem 1.25rem 1.25rem; position: relative;">
                    
                    <!-- Top Legend Indicators & Mobile Swipe Hint -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 10px; font-size: 0.82rem;">
                        <div id="chart-legend-box" style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                            <span id="leg-kk" style="color: #ddd6fe; display: flex; align-items: center; gap: 6px; font-weight: 700;">
                                <span style="width: 14px; height: 14px; border-radius: 4px; background: linear-gradient(180deg, #c084fc, #7c3aed); display: inline-block; box-shadow: 0 0 8px rgba(167, 139, 250, 0.6);"></span>
                                Batang Ungu: KK (Max <?= $maxValKK ?>)
                            </span>
                            <span id="leg-jiwa" style="color: #6ee7b7; display: flex; align-items: center; gap: 6px; font-weight: 700;">
                                <span style="width: 14px; height: 14px; border-radius: 4px; background: linear-gradient(180deg, #34d399, #059669); display: inline-block; box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);"></span>
                                Batang Hijau: Jemaat (Max <?= $maxValJiwa ?>)
                            </span>
                            <span id="leg-pria" style="color: #7dd3fc; display: none; align-items: center; gap: 6px; font-weight: 700;">
                                <span style="width: 14px; height: 14px; border-radius: 4px; background: linear-gradient(180deg, #38bdf8, #0284c7); display: inline-block; box-shadow: 0 0 8px rgba(56, 189, 248, 0.6);"></span>
                                Batang Biru: Pria (Max <?= $maxValPria ?>)
                            </span>
                            <span id="leg-wanita" style="color: #f472b6; display: none; align-items: center; gap: 6px; font-weight: 700;">
                                <span style="width: 14px; height: 14px; border-radius: 4px; background: linear-gradient(180deg, #f472b6, #ec4899); display: inline-block; box-shadow: 0 0 8px rgba(244, 114, 182, 0.6);"></span>
                                Batang Pink: Wanita (Max <?= $maxValWanita ?>)
                            </span>
                        </div>
                        <div style="color: #94a3b8; font-size: 0.78rem; font-style: italic;" class="mobile-swipe-prompt">
                            👉 <em>Geser ke kanan untuk melihat Kelompok 1 s/d <?= count($groupStats) ?></em>
                        </div>
                    </div>

                    <!-- Scrollable Grid Track -->
                    <div class="dual-bar-scroll-wrapper" style="width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 8px;">
                        <div class="dual-bar-chart-grid" id="dualBarGrid" style="display: grid; grid-template-columns: repeat(<?= count($groupStats) ?>, minmax(56px, 1fr)); gap: 10px; min-width: 1020px; align-items: flex-end; padding-top: 35px; border-bottom: 2px solid rgba(167, 139, 250, 0.35);">
                            
                            <?php foreach ($groupStats as $idx => $g): 
                                $kk = (int)($g['total_kk'] ?? 0);
                                $jiwa = (int)($g['total_jiwa'] ?? 0);
                                $pria = (int)($g['total_pria'] ?? 0);
                                $wanita = (int)($g['total_wanita'] ?? 0);
                                $ratio = $kk > 0 ? round($jiwa / $kk, 1) : 0;
                                
                                // Calculate pixel height relative to max (max height 175px, minimum 10px)
                                $hKK = max(10, round(($kk / $maxValKK) * 175));
                                $hJiwa = max(10, round(($jiwa / $maxValJiwa) * 175));
                                $hPria = max(10, round(($pria / $maxGenderVal) * 175));
                                $hWanita = max(10, round(($wanita / $maxGenderVal) * 175));
                            ?>
                            <div class="group-column" style="display: flex; flex-direction: column; align-items: center; position: relative;" title="Kelompok <?= $g['nomor_kelompok'] ?>: <?= $kk ?> KK, <?= $jiwa ?> Jemaat (👨 <?= $pria ?> Pria • 👩 <?= $wanita ?> Wanita)">
                                
                                <!-- The Pair of Capsule Bars -->
                                <div class="bars-pair" style="height: 220px; display: flex; align-items: flex-end; justify-content: center; gap: 4px; width: 100%; position: relative;">
                                    
                                    <!-- BAR 1: KEPALA KELUARGA (UNGU) -->
                                    <div class="bar-pillar bar-kk" style="width: 20px; height: <?= $hKK ?>px; background: linear-gradient(180deg, #c084fc 0%, #8b5cf6 50%, #6d28d9 100%); border-radius: 6px 6px 2px 2px; box-shadow: 0 0 12px rgba(139, 92, 246, 0.45); border: 1px solid rgba(216, 180, 254, 0.35); position: relative; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;">
                                        <span class="bar-val-badge" style="position: absolute; top: -22px; left: 50%; transform: translateX(-50%); font-size: 0.74rem; font-weight: 800; color: #ede9fe; font-family: 'Outfit', sans-serif; text-shadow: 0 0 8px rgba(167, 139, 250, 0.8);">
                                            <?= $kk ?>
                                        </span>
                                    </div>

                                    <!-- BAR 2: TOTAL JEMAAT (HIJAU EMERALD) -->
                                    <div class="bar-pillar bar-jiwa" style="width: 20px; height: <?= $hJiwa ?>px; background: linear-gradient(180deg, #34d399 0%, #10b981 50%, #059669 100%); border-radius: 6px 6px 2px 2px; box-shadow: 0 0 12px rgba(16, 185, 129, 0.45); border: 1px solid rgba(167, 243, 208, 0.35); position: relative; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer;">
                                        <span class="bar-val-badge" style="position: absolute; top: -22px; left: 50%; transform: translateX(-50%); font-size: 0.74rem; font-weight: 800; color: #d1fae5; font-family: 'Outfit', sans-serif; text-shadow: 0 0 8px rgba(16, 185, 129, 0.8);">
                                            <?= $jiwa ?>
                                        </span>
                                    </div>

                                    <!-- BAR 3: TOTAL PRIA (BIRU CYAN) -->
                                    <div class="bar-pillar bar-pria" style="width: 20px; height: <?= $hPria ?>px; background: linear-gradient(180deg, #38bdf8 0%, #0284c7 50%, #0369a1 100%); border-radius: 6px 6px 2px 2px; box-shadow: 0 0 12px rgba(56, 189, 248, 0.45); border: 1px solid rgba(186, 230, 253, 0.35); position: relative; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; display: none;">
                                        <span class="bar-val-badge" style="position: absolute; top: -22px; left: 50%; transform: translateX(-50%); font-size: 0.74rem; font-weight: 800; color: #e0f2fe; font-family: 'Outfit', sans-serif; text-shadow: 0 0 8px rgba(56, 189, 248, 0.8);">
                                            <?= $pria ?>
                                        </span>
                                    </div>

                                    <!-- BAR 4: TOTAL WANITA (SOFT PINK / ROSE) -->
                                    <div class="bar-pillar bar-wanita" style="width: 20px; height: <?= $hWanita ?>px; background: linear-gradient(180deg, #f472b6 0%, #ec4899 50%, #be185d 100%); border-radius: 6px 6px 2px 2px; box-shadow: 0 0 12px rgba(244, 114, 182, 0.45); border: 1px solid rgba(251, 207, 232, 0.35); position: relative; transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; display: none;">
                                        <span class="bar-val-badge" style="position: absolute; top: -22px; left: 50%; transform: translateX(-50%); font-size: 0.74rem; font-weight: 800; color: #fce7f3; font-family: 'Outfit', sans-serif; text-shadow: 0 0 8px rgba(244, 114, 182, 0.8);">
                                            <?= $wanita ?>
                                        </span>
                                    </div>

                                </div>

                                <!-- BASE LABEL & RATIO -->
                                <div class="group-base-tag" style="margin-top: 10px; text-align: center;">
                                    <span class="group-pill" style="background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(167, 139, 250, 0.35); color: #ddd6fe; font-size: 0.76rem; font-weight: 800; padding: 3px 8px; border-radius: 8px; display: inline-block; white-space: nowrap; transition: all 0.2s ease;">
                                        Klp <?= $g['nomor_kelompok'] ?>
                                    </span>
                                    <div style="font-size: 0.68rem; color: #94a3b8; margin-top: 3px; font-weight: 600;">
                                        <?= $ratio ?>x
                                    </div>
                                </div>

                            </div>
                            <?php endforeach; ?>

                        </div>
                    </div>

                </div>

                <!-- BOTTOM HIGHLIGHT SUMMARY BADGES (BOX STATISTIK MODEL SAMA DENGAN KELOMPOK TERPADAT) -->
                <div style="margin-top: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;" class="chart-summary-badges">
                    
                    <!-- KOTAK 1: TOTAL KK (ANGGOTA PKBGT) -->
                    <div style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(167, 139, 250, 0.3); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 12px;">
                        <div style="font-size: 1.4rem; color: #c4b5fd;">👨‍👩‍👧‍👦</div>
                        <div>
                            <div style="font-size: 0.74rem; color: #a78bfa; font-weight: 700; text-transform: uppercase;">Total Anggota PKBGT (KK)</div>
                            <div style="font-size: 0.95rem; font-weight: 800; color: #ffffff;">
                                <?= number_format($totalKK) ?> <span style="font-size: 0.8rem; color: #ddd6fe; font-weight: 600;">KK Terdata</span>
                            </div>
                        </div>
                    </div>

                    <!-- KOTAK 2: TOTAL JEMAAT (JIWA) -->
                    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 12px;">
                        <div style="font-size: 1.4rem; color: #6ee7b7;">👥</div>
                        <div>
                            <div style="font-size: 0.74rem; color: #6ee7b7; font-weight: 700; text-transform: uppercase;">Total Jemaat Gereja Toraja</div>
                            <div style="font-size: 0.95rem; font-weight: 800; color: #ffffff;">
                                <?= number_format($totalJiwa) ?> <span style="font-size: 0.8rem; color: #a7f3d0; font-weight: 600;">Jiwa Terdata</span>
                            </div>
                        </div>
                    </div>

                    <!-- KOTAK 3: DEMOGRAFI GENDER (PRIA & WANITA) -->
                    <div style="background: rgba(56, 189, 248, 0.15); border: 1px solid rgba(56, 189, 248, 0.3); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 12px;">
                        <div style="font-size: 1.4rem; color: #38bdf8;">👫</div>
                        <div>
                            <div style="font-size: 0.74rem; color: #38bdf8; font-weight: 700; text-transform: uppercase;">Demografi Gender Jemaat</div>
                            <div style="font-size: 0.95rem; font-weight: 800; color: #ffffff;">
                                <span style="color:#7dd3fc;">👨 <?= number_format($totalPria) ?> Pria</span> • <span style="color:#f472b6;">👩 <?= number_format($totalWanita) ?> Wanita</span>
                            </div>
                        </div>
                    </div>

                    <!-- KOTAK 4: KELOMPOK TERPADAT (KK) -->
                    <div style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(167, 139, 250, 0.3); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 12px;">
                        <div style="font-size: 1.4rem; color: #c4b5fd;">🏆</div>
                        <div>
                            <div style="font-size: 0.74rem; color: #a78bfa; font-weight: 700; text-transform: uppercase;">Kelompok Terpadat (KK)</div>
                            <div style="font-size: 0.95rem; font-weight: 800; color: #ffffff;">
                                Kelompok <?= $topKKGroup ? $topKKGroup['nomor_kelompok'] : '-' ?> <span style="font-size: 0.8rem; color: #ddd6fe;">(<?= $topKKGroup ? $topKKGroup['total_kk'] : '0' ?> KK)</span>
                            </div>
                        </div>
                    </div>

                    <!-- KOTAK 5: KELOMPOK TERPADAT (JEMAAT) -->
                    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(52, 211, 153, 0.3); border-radius: 14px; padding: 0.85rem 1rem; display: flex; align-items: center; gap: 12px;">
                        <div style="font-size: 1.4rem; color: #6ee7b7;">🥇</div>
                        <div>
                            <div style="font-size: 0.74rem; color: #6ee7b7; font-weight: 700; text-transform: uppercase;">Kelompok Terpadat (Jemaat)</div>
                            <div style="font-size: 0.95rem; font-weight: 800; color: #ffffff;">
                                Kelompok <?= $topJiwaGroup ? $topJiwaGroup['nomor_kelompok'] : '-' ?> <span style="font-size: 0.8rem; color: #a7f3d0;">(<?= $topJiwaGroup ? $topJiwaGroup['total_jiwa'] : '0' ?> Jemaat)</span>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </section>

        <?php if ($SHOW_PORTAL_BERITA): ?>
        <!-- FILTER & SEARCH SECTION -->
        <section class="filter-section" id="kategori">
            <div class="controls-bar">
                <div class="filter-tabs">
                    <button class="filter-btn active" onclick="filterCategory('all', this)">Semua Berita</button>
                    <button class="filter-btn" onclick="filterCategory('warta', this)">Warta Jemaat</button>
                    <button class="filter-btn" onclick="filterCategory('ibadah', this)">Ibadah & Doa</button>
                    <button class="filter-btn" onclick="filterCategory('pkb', this)">Kaum Bapak (PKB)</button>
                    <button class="filter-btn" onclick="filterCategory('diakonia', this)">Diakonia Kasih</button>
                    <button class="filter-btn" onclick="filterCategory('pemuda', this)">Pemuda & Remaja</button>
                </div>
                
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Cari warta & berita jemaat..." onkeyup="searchNews()">
                </div>
            </div>
        </section>

        <!-- NEWS GRID SECTION -->
        <section id="berita" style="padding-bottom: 2rem;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <div class="title-line" style="background: #8b5cf6;"></div>
                    <h2 class="section-title">Warta & Informasi Pelayanan Terkini</h2>
                </div>
            </div>

            <div class="news-grid" id="newsGrid">
                <?php foreach ($articles as $index => $art): ?>
                <div class="news-card" data-category="<?= $art['category_slug']; ?>" data-title="<?= strtolower(htmlspecialchars($art['title'])); ?>" onclick="openArticleModal(<?= $art['id']; ?>)">
                    <div class="card-img-wrap">
                        <span class="card-badge category-tag <?= $art['category_slug']; ?>" style="background: linear-gradient(135deg, #7c3aed, #6d28d9);"><?= $art['category']; ?></span>
                        <img src="<?= $art['image']; ?>" alt="<?= htmlspecialchars($art['title']); ?>">
                    </div>
                    <div class="card-content">
                        <div class="card-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?= $art['date']; ?></span>
                            <span>•</span>
                            <span><i class="fa-regular fa-clock"></i> <?= $art['read_time']; ?></span>
                        </div>
                        <h3 class="card-title"><?= htmlspecialchars($art['title']); ?></h3>
                        <p class="card-desc"><?= htmlspecialchars($art['excerpt']); ?></p>
                        <div class="card-footer">
                            <span style="color: #a78bfa; font-weight: 700;">Baca Selengkapnya</span>
                            <i class="fa-solid fa-arrow-right" style="color: #a78bfa;"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <!-- FLOATING STICKY CTA BUTTON (ALL DEVICES & SMARTPHONES) -->
    <a href="jemaat/pasangtitik.php" class="btn-floating-cta">
        <i class="fa-solid fa-map-pin" style="font-size:1.2rem;"></i>
        <span>📍 Pasang Titik Rumah & KK Jemaat</span>
    </a>

    <?php if ($SHOW_PORTAL_BERITA): ?>
    <!-- MODAL READ ARTICLE -->
    <div class="modal-overlay" id="articleModal" onclick="closeArticleModal(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <button class="modal-close-btn" onclick="closeArticleModal()"><i class="fa-solid fa-xmark"></i></button>
            <img src="" id="modalImg" class="modal-header-img" alt="Berita">
            <div class="modal-body-content">
                <div class="modal-meta-bar">
                    <span id="modalCategory" class="category-tag" style="background:#7c3aed; color:#fff;"></span>
                    <span style="font-size:0.85rem; color:var(--text-muted);" id="modalDate"></span>
                </div>
                <h2 id="modalTitle" class="modal-title"></h2>
                <div id="modalArticleText" class="modal-article-text"></div>
                
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <a href="#" id="btnShareWaModal" target="_blank" style="background:#25D366; color:#fff; padding:8px 16px; border-radius:8px; font-weight:700; text-decoration:none; font-size:0.85rem; display:inline-flex; align-items:center; gap:6px;">
                        <i class="fa-brands fa-whatsapp"></i> Bagikan Warta ke WhatsApp
                    </a>
                    <a href="jemaat/pasangtitik.php" style="background:#10b981; color:#fff; padding:8px 16px; border-radius:8px; font-weight:700; text-decoration:none; font-size:0.85rem;">
                        📍 Form Pendataan KK & Titik Rumah
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <footer class="footer" id="kontak">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="brand-logo" style="margin-bottom: 1rem; display: flex; align-items: center; gap: 10px;">
                        <div class="brand-logo-wrap">
                            <img src="assets/img/logo_pkbgt.png" alt="Logo PKBGT" style="width: 42px; height: 42px; border-radius: 50%; background: #ffffff; padding: 2px; border: 1.5px solid rgba(167, 139, 250, 0.45);">
                        </div>
                        <div class="brand-text">
                            <span class="brand-title" style="color: #ffffff; font-weight: 800; font-size: 1.15rem; font-family: 'Outfit', sans-serif;">PKB GEREJA TORAJA</span>
                            <span class="brand-subtitle" style="color: #c4b5fd; font-size: 0.74rem; font-weight: 700; text-transform: uppercase;">PERSEKUTUAN KAUM BAPAK (PKBGT)</span>
                        </div>
                    </div>
                    <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom: 1.5rem;">
                        Pusat warta informasi kegiatan gerejawi, pembinaan iman keluarga, serta portal pendataan spasial keluarga jemaat berbasis OpenStreetMap.
                    </p>
                </div>

                <div class="footer-col">
                    <h4>Navigasi Cepat</h4>
                    <ul class="footer-links">
                        <li><a href="#berita">Warta & Informasi</a></li>
                        <li><a href="#kelompok-jemaat">Sebaran <?= $totalKelompok ?> Kelompok</a></li>
                        <li><a href="jemaat/pasangtitik.php" style="color:#34d399; font-weight:700;">Form Pendaftaran KK</a></li>
                        <li><a href="admin/peta.php" target="_blank">Peta Spasial Jemaat</a></li>
                        <li><a href="admin/login.php">Login Majelis & Admin</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Kategori Pelayanan</h4>
                    <ul class="footer-links">
                        <li><a href="#" onclick="filterCategory('warta'); return false;">Warta Jemaat</a></li>
                        <li><a href="#" onclick="filterCategory('ibadah'); return false;">Ibadah & Doa</a></li>
                        <li><a href="#" onclick="filterCategory('pkb'); return false;">Kaum Bapak (PKB)</a></li>
                        <li><a href="#" onclick="filterCategory('diakonia'); return false;">Diakonia & Kasih</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Sekretariat Majelis Jemaat</h4>
                    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom: 10px;">
                        <i class="fa-solid fa-location-dot" style="color:#a78bfa;"></i> Gedung Gereja & Sekretariat Majelis Jemaat, Makassar, Sulawesi Selatan.
                    </p>
                    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom: 15px;">
                        <i class="fa-solid fa-phone" style="color:#25D366;"></i> WhatsApp: +62 811-4188-796
                    </p>
                    <a href="admin/login.php" class="btn-ssb-login" style="padding: 8px 16px; font-size:0.85rem; background: linear-gradient(135deg, #7c3aed, #5b21b6);">
                        <i class="fa-solid fa-right-to-bracket"></i> Portal Admin Jemaat
                    </a>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y'); ?> Persekutuan Jemaat Kristiani & Kaum Bapak (PKB). Terpujilah Tuhan.</p>
                <p>Dikembangkan untuk Pelayanan Jemaat yang Transparan & Digital</p>
            </div>
        </div>
    </footer>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        const articlesData = <?= json_encode($articles); ?>;

        // Filter Category Function
        function filterCategory(category, element) {
            const cards = document.querySelectorAll('.news-card');
            
            // Update Active Tab UI
            if (element) {
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                element.classList.add('active');
            }

            cards.forEach(card => {
                const cardCat = card.getAttribute('data-category');
                if (category === 'all' || cardCat === category) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Live Search News
        function searchNews() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const cards = document.querySelectorAll('.news-card');

            cards.forEach(card => {
                const title = card.getAttribute('data-title');
                if (title.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Open Modal Reader
        function openArticleModal(id) {
            const art = articlesData.find(a => a.id === id);
            if (!art) return;

            document.getElementById('modalImg').src = art.image;
            document.getElementById('modalCategory').textContent = art.category;
            document.getElementById('modalCategory').className = `category-tag ${art.category_slug}`;
            document.getElementById('modalDate').textContent = `${art.date} • Oleh ${art.author}`;
            document.getElementById('modalTitle').textContent = art.title;
            document.getElementById('modalArticleText').innerHTML = art.content;

            const btnShare = document.getElementById('btnShareWaModal');
            if (btnShare) {
                btnShare.href = 'https://api.whatsapp.com/send?text=' + encodeURIComponent('*' + art.title + '*\n\n' + art.excerpt + '\n\nBaca selengkapnya di Portal Informasi Jemaat.');
            }

            document.getElementById('articleModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close Modal Reader
        function closeArticleModal(event) {
            document.getElementById('articleModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Filter Dual Bar Chart Function (All, Gender, KK, Jiwa, Pria, Wanita)
        function filterBarChart(mode) {
            const btnAll = document.getElementById('filter-btn-all');
            const btnGender = document.getElementById('filter-btn-gender');
            const btnKK = document.getElementById('filter-btn-kk');
            const btnJiwa = document.getElementById('filter-btn-jiwa');
            const btnPria = document.getElementById('filter-btn-pria');
            const btnWanita = document.getElementById('filter-btn-wanita');

            const barsKK = document.querySelectorAll('.bar-pillar.bar-kk');
            const barsJiwa = document.querySelectorAll('.bar-pillar.bar-jiwa');
            const barsPria = document.querySelectorAll('.bar-pillar.bar-pria');
            const barsWanita = document.querySelectorAll('.bar-pillar.bar-wanita');

            const legKK = document.getElementById('leg-kk');
            const legJiwa = document.getElementById('leg-jiwa');
            const legPria = document.getElementById('leg-pria');
            const legWanita = document.getElementById('leg-wanita');

            // Reset all buttons
            [btnAll, btnGender, btnKK, btnJiwa, btnPria, btnWanita].forEach(btn => {
                if (btn) {
                    btn.classList.remove('active');
                    btn.style.background = 'transparent';
                    btn.style.borderColor = 'transparent';
                    btn.style.color = '#94a3b8';
                    btn.style.boxShadow = 'none';
                }
            });

            // Helper to set all bars display
            function setBars(kk, jiwa, pria, wanita, width) {
                barsKK.forEach(b => { b.style.display = kk ? 'block' : 'none'; b.style.width = width; });
                barsJiwa.forEach(b => { b.style.display = jiwa ? 'block' : 'none'; b.style.width = width; });
                barsPria.forEach(b => { b.style.display = pria ? 'block' : 'none'; b.style.width = width; });
                barsWanita.forEach(b => { b.style.display = wanita ? 'block' : 'none'; b.style.width = width; });

                if (legKK) legKK.style.display = kk ? 'inline-flex' : 'none';
                if (legJiwa) legJiwa.style.display = jiwa ? 'inline-flex' : 'none';
                if (legPria) legPria.style.display = pria ? 'inline-flex' : 'none';
                if (legWanita) legWanita.style.display = wanita ? 'inline-flex' : 'none';
            }

            if (mode === 'all') {
                btnAll.classList.add('active');
                btnAll.style.background = 'linear-gradient(135deg, #7c3aed, #6d28d9)';
                btnAll.style.borderColor = 'rgba(196,181,253,0.5)';
                btnAll.style.color = '#ffffff';
                btnAll.style.boxShadow = '0 4px 18px rgba(124,58,237,0.45)';
                setBars(true, true, false, false, '20px');
            } else if (mode === 'gender') {
                btnGender.classList.add('active');
                btnGender.style.background = 'linear-gradient(135deg, #0284c7, #db2777)';
                btnGender.style.borderColor = 'rgba(244,114,182,0.5)';
                btnGender.style.color = '#ffffff';
                btnGender.style.boxShadow = '0 4px 18px rgba(2,132,199,0.45)';
                setBars(false, false, true, true, '20px');
            } else if (mode === 'kk') {
                btnKK.classList.add('active');
                btnKK.style.background = 'linear-gradient(135deg, #7c3aed, #4c1d95)';
                btnKK.style.borderColor = '#c4b5fd';
                btnKK.style.color = '#ffffff';
                btnKK.style.boxShadow = '0 4px 18px rgba(124,58,237,0.45)';
                setBars(true, false, false, false, '32px');
            } else if (mode === 'jiwa') {
                btnJiwa.classList.add('active');
                btnJiwa.style.background = 'linear-gradient(135deg, #10b981, #059669)';
                btnJiwa.style.borderColor = '#6ee7b7';
                btnJiwa.style.color = '#ffffff';
                btnJiwa.style.boxShadow = '0 4px 18px rgba(16,185,129,0.45)';
                setBars(false, true, false, false, '32px');
            } else if (mode === 'pria') {
                btnPria.classList.add('active');
                btnPria.style.background = 'linear-gradient(135deg, #0284c7, #0369a1)';
                btnPria.style.borderColor = '#7dd3fc';
                btnPria.style.color = '#ffffff';
                btnPria.style.boxShadow = '0 4px 18px rgba(2,132,199,0.45)';
                setBars(false, false, true, false, '32px');
            } else if (mode === 'wanita') {
                btnWanita.classList.add('active');
                btnWanita.style.background = 'linear-gradient(135deg, #ec4899, #be185d)';
                btnWanita.style.borderColor = '#f472b6';
                btnWanita.style.color = '#ffffff';
                btnWanita.style.boxShadow = '0 4px 18px rgba(236,72,153,0.45)';
                setBars(false, false, false, true, '32px');
            }
        }

        // Navbar Scroll Blur Effect
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 40) {
                navbar.style.boxShadow = '0 10px 30px rgba(0,0,0,0.6)';
            } else {
                navbar.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
