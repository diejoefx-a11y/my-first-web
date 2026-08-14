<?php
/**
 * PORTAL INFORMASI PERSEKUTUAN JEMAAT KRISTIANI (PKB)
 * Dynamic News Portal, Church Fellowship & Spatial Congregation Mapping System
 */

require_once __DIR__ . '/config/database.php';
date_default_timezone_set('Asia/Makassar');

$db = get_db();

// 1. Fetch Dynamic Real Statistics from Database
$totalKK = $db->query("SELECT COUNT(*) FROM families")->fetchColumn();
$totalJiwa = $db->query("SELECT COUNT(*) FROM family_members")->fetchColumn();
$totalKelompok = $db->query("SELECT COUNT(*) FROM `groups`")->fetchColumn();

// 2. Fetch Group Distribution Data for the Smooth Area Wave Chart (Kelompok 1 to 14)
$sqlGroupStats = "
    SELECT 
        g.nomor_kelompok,
        g.nama_kelompok,
        COUNT(f.id) as total_kk,
        (SELECT COUNT(*) FROM family_members fm JOIN families fam ON fm.family_id = fam.id WHERE fam.kelompok_id = g.id) as total_jiwa
    FROM `groups` g
    LEFT JOIN families f ON f.kelompok_id = g.id
    GROUP BY g.id, g.nomor_kelompok, g.nama_kelompok
    ORDER BY g.nomor_kelompok ASC
";
$groupStats = $db->query($sqlGroupStats)->fetchAll(PDO::FETCH_ASSOC);

// If database has fewer groups, fallback to default 14 groups with data
if (empty($groupStats)) {
    for ($i = 1; $i <= 14; $i++) {
        $groupStats[] = [
            'nomor_kelompok' => $i,
            'nama_kelompok' => 'Kelompok ' . $i,
            'total_kk' => rand(5, 25),
            'total_jiwa' => rand(20, 90)
        ];
    }
}

// Helper to compute dynamic smooth SVG spline curves & points
function compute_spline_chart($data, $valueKey, $svgWidth = 700, $svgHeight = 220, $paddingX = 45, $paddingYTop = 35, $paddingYBtm = 175) {
    $availWidth = $svgWidth - ($paddingX * 2);
    $availHeight = $paddingYBtm - $paddingYTop;
    $values = array_map(function($d) use ($valueKey) { return (int)($d[$valueKey] ?? 0); }, $data);
    $maxVal = max(array_merge([5], $values));
    $numPts = count($data);
    $pts = [];

    foreach ($data as $idx => $item) {
        $val = (int)($item[$valueKey] ?? 0);
        $x = $paddingX + ($numPts > 1 ? ($idx / ($numPts - 1)) * $availWidth : 0);
        $y = $paddingYBtm - (($val / $maxVal) * $availHeight);
        $pts[] = [
            'x' => round($x, 1),
            'y' => round($y, 1),
            'val' => $val,
            'label' => 'Klp ' . ($item['nomor_kelompok'] ?? ($idx + 1)),
            'nama' => $item['nama_kelompok'] ?? ('Kelompok ' . ($idx + 1))
        ];
    }

    $dPath = "";
    $dArea = "";
    if (count($pts) > 0) {
        $dPath = "M " . $pts[0]['x'] . "," . $pts[0]['y'];
        for ($i = 0; $i < count($pts) - 1; $i++) {
            $p0 = $pts[max(0, $i - 1)];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $pts[min(count($pts) - 1, $i + 2)];

            $cp1x = $p1['x'] + ($p2['x'] - $p0['x']) / 6;
            $cp1y = $p1['y'] + ($p2['y'] - $p0['y']) / 6;
            $cp2x = $p2['x'] - ($p3['x'] - $p1['x']) / 6;
            $cp2y = $p2['y'] - ($p3['y'] - $p1['y']) / 6;

            $dPath .= sprintf(" C %.1f,%.1f %.1f,%.1f %.1f,%.1f", $cp1x, $cp1y, $cp2x, $cp2y, $p2['x'], $p2['y']);
        }

        $firstX = $pts[0]['x'];
        $lastX = $pts[count($pts) - 1]['x'];
        $dArea = $dPath . sprintf(" L %.1f,%d L %.1f,%d Z", $lastX, $paddingYBtm, $firstX, $paddingYBtm);
    }

    return [
        'pts' => $pts,
        'dPath' => $dPath,
        'dArea' => $dArea,
        'maxVal' => $maxVal
    ];
}

$chartKK = compute_spline_chart($groupStats, 'total_kk');
$chartJiwa = compute_spline_chart($groupStats, 'total_jiwa');

// Legacy variables for backward compatibility
$pts = $chartKK['pts'];
$dPath = $chartKK['dPath'];
$dArea = $chartKK['dArea'];

// 3. Dynamic News & Fellowship Articles (Persekutuan Jemaat Kristiani)
$articles = [
    [
        'id' => 1,
        'title' => 'Pemutakhiran Sensus Data Keluarga Jemaat & Pemetaan Digital Berbasis Wilayah Kelompok 1 - 14 Dibuka',
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
                      <p>Selain itu, Ibadah Persekutuan Kaum Bapak (PKB) akan digelar serentak pada hari Jumat malam di masing-masing Kelompok Pelayanan (Kelompok 1 s/d 14) bertempat di rumah keluarga yang telah dijadwalkan.</p>'
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
        'title' => 'Struktur Pembagian 14 Kelompok Pelayanan Ibadah Rumah Tangga & Wilayah Sektor',
        'category' => 'Warta Jemaat',
        'category_slug' => 'warta',
        'image' => 'https://images.unsplash.com/photo-1529070538774-1843cb3265df?auto=format&fit=crop&w=800&q=80',
        'date' => date('d F Y', strtotime('-4 days')),
        'author' => 'Sekretariat Majelis',
        'read_time' => '3 Menit',
        'views' => '1,640',
        'excerpt' => 'Penataan dan pembaruan struktur pengurus Ketua dan Sekretaris Kelompok 1 sampai Kelompok 14 guna menjangkau seluruh anggota jemaat.',
        'content' => '<p>Majelis Jemaat telah menetapkan struktur pembagian 14 kelompok pelayanan ibadah rumah tangga. Setiap kelompok dipimpin oleh seorang Ketua Kelompok dan didampingi Sekretaris Kelompok yang bertugas mengoordinasikan jadwal ibadah serta kebutuhan perkunjungan jemaat.</p>'
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
    <title>Portal Informasi Persekutuan Jemaat Kristiani & Sistem Pemetaan KK (PKB)</title>
    <meta name="description" content="Portal Warta Informasi, Ibadah, Pelayanan Diakonia, dan Sistem Pendataan Spasial Keluarga Jemaat Kristiani Kelompok 1 s/d 14.">
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Custom -->
    <link rel="stylesheet" href="assets/css/landing.css">
    <style>
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
        @media (max-width: 768px) {
            .btn-floating-cta {
                bottom: 16px;
                right: 16px;
                left: 16px;
                justify-content: center;
                padding: 12px 20px;
                font-size: 0.9rem;
            }
            body {
                padding-bottom: 70px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR HEADER (CLEAN BRAND ONLY) -->
    <nav class="navbar" id="navbar" style="background: rgba(15, 12, 35, 0.95); backdrop-filter: blur(16px); border-bottom: 1.5px solid rgba(139, 92, 246, 0.25); padding: 0.85rem 0;">
        <div class="container navbar-container" style="display: flex; justify-content: center; align-items: center;">
            <a href="index.php" class="brand-logo" id="brand-logo" style="text-decoration: none; display: flex; align-items: center; gap: 12px;">
                <div class="brand-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9); color: #fff; width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; box-shadow: 0 0 15px rgba(139, 92, 246, 0.4);">
                    <i class="fa-solid fa-church"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-title" style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: #ffffff; letter-spacing: 0.5px; display: block; line-height: 1.2;">JEMAAT KRISTIANI</span>
                    <span class="brand-subtitle" style="font-size: 0.78rem; font-weight: 700; color: #a78bfa; letter-spacing: 1px; text-transform: uppercase;"><i class="fa-solid fa-cross"></i> PERSEKUTUAN KAUM BAPAK (PKB)</span>
                </div>
            </a>
        </div>
    </nav>

    <!-- MAIN CONTAINER -->
    <main class="container" style="padding-top: 1.5rem;">

        <!-- HERO SECTION: BERITA UTAMA & SPOTLIGHT PERSEKUTUAN -->
        <section class="hero-section" id="hero" style="padding-bottom: 1.5rem;">
            
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

            <!-- 2. KOMPONEN UI PERSEKUTUAN JEMAAT KRISTIANI (TERLETAK TEPAT DI BAWAH BERITA UTAMA) -->
            <div class="ssb-spotlight-card" id="ssb-spotlight" style="background: linear-gradient(135deg, rgba(28, 20, 60, 0.92) 0%, rgba(46, 16, 101, 0.85) 50%, rgba(15, 12, 35, 0.95) 100%); border: 1.5px solid rgba(167, 139, 250, 0.35); border-radius: 24px; padding: 2rem 2.25rem; box-shadow: 0 15px 40px rgba(0,0,0,0.45);">
                <div style="display: grid; grid-template-columns: 1.3fr 1fr; gap: 2rem; align-items: center;" class="fellowship-banner-grid">
                    
                    <!-- Sisi Kiri: Profil, Visi & Statistik 14 Kelompok -->
                    <div>
                        <div class="ssb-badge-header" style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <span class="ssb-badge-pill" style="background: rgba(139, 92, 246, 0.25); color: #ddd6fe; border: 1px solid rgba(167, 139, 250, 0.4); padding: 4px 14px; border-radius: 20px; font-weight: 700; font-size: 0.8rem;">
                                <i class="fa-solid fa-shield-heart"></i> PERSEKUTUAN RESMI
                            </span>
                            <span style="font-size: 0.82rem; color: #a78bfa; font-weight: 600;">
                                <i class="fa-solid fa-location-dot"></i> Makassar, Sulawesi Selatan (WITA)
                            </span>
                        </div>

                        <h2 class="ssb-spotlight-title" style="color: #ffffff; font-size: 1.85rem; font-weight: 800; margin-bottom: 0.5rem; font-family: 'Outfit', sans-serif;">
                            Persekutuan Kaum Bapak (PKB)
                        </h2>
                        
                        <p class="ssb-spotlight-desc" style="color: #ddd6fe; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem; max-width: 650px;">
                            Mewujudkan Persekutuan, Kesaksian, dan Pelayanan Kasih (<em>Koinonia, Marturia, Diakonia</em>) yang solid dan terpadu berbasis pembinaan <strong>14 Kelompok Wilayah</strong>.
                        </p>
                        
                        <!-- 3 Live Stats Counters -->
                        <div class="ssb-stats-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                            <div class="stat-item" style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.3); border-radius: 14px; padding: 0.85rem; text-align: center;">
                                <div class="stat-num" style="color: #c4b5fd; font-size: 1.6rem; font-weight: 800;"><?= number_format($totalKK) ?></div>
                                <div class="stat-lbl" style="font-size: 0.78rem; color: #a78bfa; font-weight: 600; text-transform: uppercase;">KK Terdata</div>
                            </div>
                            <div class="stat-item" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 14px; padding: 0.85rem; text-align: center;">
                                <div class="stat-num" style="color: #6ee7b7; font-size: 1.6rem; font-weight: 800;"><?= number_format($totalJiwa) ?></div>
                                <div class="stat-lbl" style="font-size: 0.78rem; color: #6ee7b7; font-weight: 600; text-transform: uppercase;">Jiwa Jemaat</div>
                            </div>
                            <div class="stat-item" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 14px; padding: 0.85rem; text-align: center;">
                                <div class="stat-num" style="color: #fcd34d; font-size: 1.6rem; font-weight: 800;">14</div>
                                <div class="stat-lbl" style="font-size: 0.78rem; color: #fcd34d; font-weight: 600; text-transform: uppercase;">Kelompok Binaan</div>
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
                            <span>📍 Pasang Titik Rumah & KK Jemaat</span>
                        </a>

                        <!-- TOMBOL PERBARUI / EDIT DATA JEMAAT (DILETAKKAN TEPAT DI BAWAH PASANG TITIK RUMAH) -->
                        <a href="jemaat/edit_data.php" class="btn-edit-jemaat-hero" style="background: rgba(139, 92, 246, 0.22); border: 1.5px solid rgba(167, 139, 250, 0.45); color: #f3e8ff; font-size: 0.95rem; padding: 12px 18px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(139, 92, 246, 0.2);">
                            <i class="fa-solid fa-user-pen" style="color: #c4b5fd; font-size: 1.05rem;"></i>
                            <span>✏️ Perbarui / Edit Data KK Jemaat</span>
                        </a>

                        <!-- TOMBOL DATA LENGKAP JEMAAT PER KEPALA KELUARGA -->
                        <a href="jemaat/data_lengkap.php" style="background: linear-gradient(135deg, rgba(14, 165, 233, 0.25), rgba(37, 99, 235, 0.25)); border: 1.5px solid rgba(56, 189, 248, 0.5); color: #e0f2fe; font-size: 0.95rem; padding: 12px 18px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 18px rgba(14, 165, 233, 0.25);">
                            <i class="fa-solid fa-table-list" style="color: #38bdf8; font-size: 1.1rem;"></i>
                            <span>📋 Data Lengkap Jemaat & Rute Peta</span>
                        </a>

                        <!-- TOMBOL BUKA PETA SEBARAN JEMAAT -->
                        <a href="admin/peta.php" target="_blank" style="background: rgba(52, 211, 153, 0.15); border: 1.5px solid rgba(52, 211, 153, 0.45); color: #6ee7b7; font-size: 0.95rem; padding: 12px 18px; border-radius: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; transition: all 0.2s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);">
                            <i class="fa-solid fa-map" style="color: #34d399; font-size: 1.05rem;"></i>
                            <span>🗺️ Buka Peta Sebaran Jemaat</span>
                        </a>

                        <!-- TOMBOL WHATSAPP LAYANAN DOA -->
                        <a href="https://wa.me/6281234567890?text=Syalom%20Majelis%20Jemaat,%20saya%20ingin%20konsultasi%20layanan%20doa%20dan%20pelayanan%20keluarga" target="_blank" class="btn-secondary-ssb" id="btn-wa-info" style="background: rgba(37, 211, 102, 0.15); border: 1.5px solid rgba(37, 211, 102, 0.4); color: #86efac; padding: 11px 18px; border-radius: 12px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                            <i class="fa-brands fa-whatsapp" style="color:#25D366; font-size: 1.15rem;"></i> Layanan Doa & Konseling WA
                        </a>

                        <!-- TOMBOL PORTAL MAJELIS -->
                        <a href="admin/login.php" style="background: rgba(139, 92, 246, 0.15); border: 1px solid rgba(139, 92, 246, 0.3); color: #ddd6fe; padding: 9px 18px; border-radius: 12px; font-weight: 700; font-size: 0.82rem; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none;">
                            <i class="fa-solid fa-church"></i> Portal Login Majelis & Admin Kelompok
                        </a>
                    </div>

                </div>
            </div>

        </section>

        <!-- KELOMPOK JEMAAT SHOWCASE SECTION (DUAL DYNAMIC CHARTS: KK & JIWA JEMAAT) -->
        <section class="ku-showcase-section" id="kelompok-jemaat" style="margin: 2.5rem 0 2rem 0;">
            <div style="background: rgba(20, 18, 45, 0.92); backdrop-filter: blur(16px); border: 1.5px solid rgba(139, 92, 246, 0.3); border-radius: 24px; padding: 2rem; box-shadow: 0 20px 45px rgba(0,0,0,0.55);">
                
                <!-- Main Header Section -->
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1.25rem; border-bottom:1px solid rgba(255,255,255,0.08); padding-bottom:1.25rem;">
                    <div style="display:flex; align-items:center; gap:14px;">
                        <div style="width:52px; height:52px; background:linear-gradient(135deg, rgba(139,92,246,0.3), rgba(16,185,129,0.2)); border:1.5px solid rgba(167,139,250,0.4); border-radius:14px; display:flex; align-items:center; justify-content:center; color:#c4b5fd; font-size:1.6rem; box-shadow: 0 4px 15px rgba(139,92,246,0.3);">
                            📊
                        </div>
                        <div>
                            <h3 style="font-size:1.4rem; font-weight:800; color:#fff; margin:0; font-family:'Outfit', sans-serif;">
                                Grafik Dinamis Sebaran Jemaat (Kelompok 1 s/d 14)
                            </h3>
                            <span style="font-size:0.85rem; color:#c4b5fd;">
                                Pantauan Spasial Real-Time: <strong>Kepala Keluarga (KK)</strong> & <strong>Total Jiwa Anggota Jemaat</strong>
                            </span>
                        </div>
                    </div>
                    
                    <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <div style="background:rgba(139,92,246,0.18); border:1px solid rgba(139,92,246,0.35); color:#ddd6fe; padding:0.45rem 1rem; border-radius:12px; font-weight:700; font-size:0.85rem; display:flex; align-items:center; gap:6px;">
                            <span>👨‍👩‍👧‍👦 Total KK:</span>
                            <strong style="color:#fff; font-size:1.05rem;"><?= number_format($totalKK) ?></strong>
                        </div>
                        <div style="background:rgba(16,185,129,0.18); border:1px solid rgba(16,185,129,0.35); color:#6ee7b7; padding:0.45rem 1rem; border-radius:12px; font-weight:700; font-size:0.85rem; display:flex; align-items:center; gap:6px;">
                            <span>👥 Total Jiwa:</span>
                            <strong style="color:#fff; font-size:1.05rem;"><?= number_format($totalJiwa) ?></strong>
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC TAB CONTROLS (FUTURISTIC GLOWING SWITCHER WIDGET) -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 1.75rem; background: rgba(10, 8, 26, 0.85); padding: 10px; border-radius: 20px; border: 1.5px solid rgba(167, 139, 250, 0.35); box-shadow: 0 12px 35px rgba(0,0,0,0.5);" class="chart-switcher-grid">
                    
                    <!-- TAB 1: GRAFIK KEPALA KELUARGA (KK) -->
                    <button type="button" class="chart-tab-btn active" id="tab-btn-kk" onclick="switchChartMode('kk')" style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 14px; cursor: pointer; border: 1.5px solid #c4b5fd; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%); color: #ffffff; box-shadow: 0 8px 25px rgba(124,58,237,0.55); text-align: left;">
                        <div class="tab-icon-box" style="width: 44px; height: 44px; background: rgba(255,255,255,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0; box-shadow: 0 0 12px rgba(255,255,255,0.25);">
                            👨‍👩‍👧‍👦
                        </div>
                        <div style="overflow: hidden;">
                            <div style="font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                1. Grafik Kepala Keluarga
                            </div>
                            <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem; font-weight: 700; color: #ddd6fe; margin-top: 3px; background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 20px;">
                                <span style="color:#a78bfa;">●</span> <?= number_format($totalKK) ?> KK Terdata
                            </div>
                        </div>
                    </button>

                    <!-- TAB 2: GRAFIK JIWA / ANGGOTA JEMAAT -->
                    <button type="button" class="chart-tab-btn" id="tab-btn-jiwa" onclick="switchChartMode('jiwa')" style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 14px; cursor: pointer; border: 1.5px solid rgba(255,255,255,0.08); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: rgba(20, 18, 45, 0.6); color: #94a3b8; text-align: left;">
                        <div class="tab-icon-box" style="width: 44px; height: 44px; background: rgba(16,185,129,0.15); border: 1px solid rgba(52,211,153,0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            👥
                        </div>
                        <div style="overflow: hidden;">
                            <div style="font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                2. Grafik Jiwa Jemaat
                            </div>
                            <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem; font-weight: 700; color: #6ee7b7; margin-top: 3px; background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 20px;">
                                <span style="color:#10b981;">●</span> <?= number_format($totalJiwa) ?> Total Jiwa
                            </div>
                        </div>
                    </button>

                    <!-- TAB 3: TAMPILKAN 2 GRAFIK (BERDAMPINGAN) -->
                    <button type="button" class="chart-tab-btn" id="tab-btn-dual" onclick="switchChartMode('dual')" style="display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 14px; cursor: pointer; border: 1.5px solid rgba(255,255,255,0.08); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: rgba(20, 18, 45, 0.6); color: #94a3b8; text-align: left;">
                        <div class="tab-icon-box" style="width: 44px; height: 44px; background: rgba(2,132,199,0.15); border: 1px solid rgba(56,189,248,0.3); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            ⚡
                        </div>
                        <div style="overflow: hidden;">
                            <div style="font-family: 'Outfit', sans-serif; font-size: 1rem; font-weight: 800; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                3. Tampilkan 2 Grafik
                            </div>
                            <div style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.76rem; font-weight: 700; color: #7dd3fc; margin-top: 3px; background: rgba(0,0,0,0.3); padding: 2px 8px; border-radius: 20px;">
                                <span style="color:#0284c7;">●</span> Dual View (Berdampingan)
                            </div>
                        </div>
                    </button>

                </div>

                <!-- CHARTS CONTAINER GRID -->
                <div id="charts-main-grid" style="display: grid; grid-template-columns: 1fr; gap: 1.5rem;">
                    
                    <!-- ================= GRAFIK 1: KEPALA KELUARGA (KK) ================= -->
                    <div class="chart-box" id="chart-box-kk" style="background: rgba(15, 12, 35, 0.85); border-radius: 18px; padding: 1.5rem; border: 1.5px solid rgba(139, 92, 246, 0.25); position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px dashed rgba(255,255,255,0.1); flex-wrap: wrap; gap: 8px;">
                            <div style="font-size: 0.9rem; font-weight: 800; color: #c4b5fd; display: flex; align-items: center; gap: 8px;">
                                <span style="width:12px; height:12px; background:#8b5cf6; border-radius:50%; display:inline-block; box-shadow:0 0 8px #8b5cf6;"></span>
                                <span>GRAFIK 1: SEBARAN KEPALA KELUARGA (KK)</span>
                            </div>
                            <span style="font-size: 0.8rem; color: #38bdf8; background: rgba(56,189,248,0.12); padding: 3px 10px; border-radius: 8px; font-weight: 700;">
                                Skala Vertikal: <?= $chartKK['maxVal'] ?> KK Max
                            </span>
                        </div>

                        <!-- Pure SVG Glowing Smooth Area Wave Diagram (KK) -->
                        <div style="position:relative; width:100%; height:230px; margin-bottom:0.5rem;">
                            <svg viewBox="0 0 700 220" style="width:100%; height:100%; overflow:visible;">
                                <defs>
                                    <linearGradient id="areaGlowGradKK" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#8b5cf6" stop-opacity="0.55" />
                                        <stop offset="50%" stop-color="#6d28d9" stop-opacity="0.2" />
                                        <stop offset="100%" stop-color="#2e1065" stop-opacity="0.01" />
                                    </linearGradient>
                                    <filter id="lineShadowKK" x="-20%" y="-20%" width="140%" height="140%">
                                        <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#8b5cf6" flood-opacity="0.7"/>
                                    </filter>
                                </defs>

                                <!-- Horizontal Grid Lines -->
                                <line x1="35" y1="35" x2="665" y2="35" stroke="rgba(255,255,255,0.06)" stroke-dasharray="4" />
                                <line x1="35" y1="105" x2="665" y2="105" stroke="rgba(255,255,255,0.06)" stroke-dasharray="4" />
                                <line x1="35" y1="175" x2="665" y2="175" stroke="rgba(255,255,255,0.12)" />

                                <!-- Wave Filled Area -->
                                <path d="<?= $chartKK['dArea'] ?>" fill="url(#areaGlowGradKK)" />

                                <!-- Wave Smooth Line -->
                                <path d="<?= $chartKK['dPath'] ?>" fill="none" stroke="#a78bfa" stroke-width="3.5" filter="url(#lineShadowKK)" stroke-linecap="round" />

                                <!-- Dynamic Point Nodes & Labels -->
                                <?php foreach ($chartKK['pts'] as $idx => $pt): ?>
                                    <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="6" fill="#8b5cf6" stroke="#ffffff" stroke-width="2.5" />
                                    <text x="<?= $pt['x'] ?>" y="<?= $pt['y'] - 10 ?>" fill="#ffffff" font-family="'Outfit', sans-serif" font-weight="800" font-size="11.5" text-anchor="middle"><?= $pt['val'] ?></text>
                                    <text x="<?= $pt['x'] ?>" y="198" fill="#ddd6fe" font-family="'Outfit', sans-serif" font-weight="700" font-size="10.5" text-anchor="middle">
                                        <?= htmlspecialchars($pt['label']) ?>
                                    </text>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                        <div style="font-size: 0.78rem; color: #a78bfa; text-align: center; margin-top: 4px;">
                            * Menampilkan jumlah Kepala Keluarga (KK) per Kelompok Binaan (Kelompok 1 s/d 14).
                        </div>
                    </div>

                    <!-- ================= GRAFIK 2: JIWA / ANGGOTA JEMAAT ================= -->
                    <div class="chart-box" id="chart-box-jiwa" style="background: rgba(15, 12, 35, 0.85); border-radius: 18px; padding: 1.5rem; border: 1.5px solid rgba(16, 185, 129, 0.3); position: relative; box-shadow: 0 10px 30px rgba(0,0,0,0.3); display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px dashed rgba(255,255,255,0.1); flex-wrap: wrap; gap: 8px;">
                            <div style="font-size: 0.9rem; font-weight: 800; color: #6ee7b7; display: flex; align-items: center; gap: 8px;">
                                <span style="width:12px; height:12px; background:#10b981; border-radius:50%; display:inline-block; box-shadow:0 0 8px #10b981;"></span>
                                <span>GRAFIK 2: SEBARAN TOTAL JIWA / ANGGOTA JEMAAT</span>
                            </div>
                            <span style="font-size: 0.8rem; color: #34d399; background: rgba(16,185,129,0.15); padding: 3px 10px; border-radius: 8px; font-weight: 700;">
                                Skala Vertikal: <?= $chartJiwa['maxVal'] ?> Jiwa Max
                            </span>
                        </div>

                        <!-- Pure SVG Glowing Smooth Area Wave Diagram (Jiwa) -->
                        <div style="position:relative; width:100%; height:230px; margin-bottom:0.5rem;">
                            <svg viewBox="0 0 700 220" style="width:100%; height:100%; overflow:visible;">
                                <defs>
                                    <linearGradient id="areaGlowGradJiwa" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.55" />
                                        <stop offset="50%" stop-color="#059669" stop-opacity="0.2" />
                                        <stop offset="100%" stop-color="#064e3b" stop-opacity="0.01" />
                                    </linearGradient>
                                    <filter id="lineShadowJiwa" x="-20%" y="-20%" width="140%" height="140%">
                                        <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#10b981" flood-opacity="0.7"/>
                                    </filter>
                                </defs>

                                <!-- Horizontal Grid Lines -->
                                <line x1="35" y1="35" x2="665" y2="35" stroke="rgba(255,255,255,0.06)" stroke-dasharray="4" />
                                <line x1="35" y1="105" x2="665" y2="105" stroke="rgba(255,255,255,0.06)" stroke-dasharray="4" />
                                <line x1="35" y1="175" x2="665" y2="175" stroke="rgba(255,255,255,0.12)" />

                                <!-- Wave Filled Area -->
                                <path d="<?= $chartJiwa['dArea'] ?>" fill="url(#areaGlowGradJiwa)" />

                                <!-- Wave Smooth Line -->
                                <path d="<?= $chartJiwa['dPath'] ?>" fill="none" stroke="#34d399" stroke-width="3.5" filter="url(#lineShadowJiwa)" stroke-linecap="round" />

                                <!-- Dynamic Point Nodes & Labels -->
                                <?php foreach ($chartJiwa['pts'] as $idx => $pt): ?>
                                    <circle cx="<?= $pt['x'] ?>" cy="<?= $pt['y'] ?>" r="6" fill="#10b981" stroke="#ffffff" stroke-width="2.5" />
                                    <text x="<?= $pt['x'] ?>" y="<?= $pt['y'] - 10 ?>" fill="#6ee7b7" font-family="'Outfit', sans-serif" font-weight="800" font-size="11.5" text-anchor="middle"><?= $pt['val'] ?></text>
                                    <text x="<?= $pt['x'] ?>" y="198" fill="#a7f3d0" font-family="'Outfit', sans-serif" font-weight="700" font-size="10.5" text-anchor="middle">
                                        <?= htmlspecialchars($pt['label']) ?>
                                    </text>
                                <?php endforeach; ?>
                            </svg>
                        </div>
                        <div style="font-size: 0.78rem; color: #6ee7b7; text-align: center; margin-top: 4px;">
                            * Menampilkan total individu/jiwa warga jemaat yang terdata di tiap Kelompok 1 s/d 14.
                        </div>
                    </div>

                </div>

                <!-- CHART FOOTER TIP -->
                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed rgba(255,255,255,0.1); display: flex; justify-content: center; align-items: center; text-align: center;">
                    <div style="font-size: 0.88rem; color: #c4b5fd;">
                        💡 <em>Pilih tab di atas untuk berganti tampilan grafik atau melihat kedua grafik secara bersamaan.</em>
                    </div>
                </div>

            </div>
        </section>

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

        <!-- FEATURE SHOWCASE PERSEKUTUAN JEMAAT KRISTIANI -->
        <section class="ssb-showcase-section" id="tentang-pelayanan">
            <div class="showcase-banner" style="background: linear-gradient(135deg, #2e1065 0%, #4c1d95 50%, #1e1b4b 100%); border-color: rgba(167, 139, 250, 0.3);">
                <div class="showcase-text">
                    <h2>Persekutuan Kaum Bapak (PKB)</h2>
                    <p>
                        Menumbuhkan iman, persekutuan yang kokoh, serta aksi nyata kepedulian sosial bagi sesama jemaat dan masyarakat di 14 kelompok binaan.
                    </p>
                    
                    <div class="features-list">
                        <div class="feature-item">
                            <div class="feature-icon" style="background: rgba(139, 92, 246, 0.25); color: #c4b5fd;"><i class="fa-solid fa-cross"></i></div>
                            <div class="feature-item-text">
                                <h4>Ibadah & Doa Rutin</h4>
                                <p>Ibadah raya minggu dan ibadah rumah tangga per kelompok.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon" style="background: rgba(52, 211, 153, 0.25); color: #6ee7b7;"><i class="fa-solid fa-map-location-dot"></i></div>
                            <div class="feature-item-text">
                                <h4>Sensus KK Digital</h4>
                                <p>Pemetaan koordinat rumah keluarga jemaat via GPS.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon" style="background: rgba(245, 158, 11, 0.25); color: #fcd34d;"><i class="fa-solid fa-hand-holding-heart"></i></div>
                            <div class="feature-item-text">
                                <h4>Diakonia Kasih</h4>
                                <p>Bantuan sosial, beasiswa pendidikan, & santunan jemaat.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon" style="background: rgba(56, 189, 248, 0.25); color: #7dd3fc;"><i class="fa-solid fa-users-gear"></i></div>
                            <div class="feature-item-text">
                                <h4>14 Kelompok Pelayanan</h4>
                                <p>Pelayanan terstruktur didampingi Ketua & Sekretaris.</p>
                            </div>
                        </div>
                    </div>

                    <!-- DIRECT LINK BUTTON TO PASANG TITIK & EDIT DATA -->
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 1rem;">
                        <a href="jemaat/pasangtitik.php" class="btn-primary-ssb" style="display:inline-flex; width:auto; padding: 14px 24px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 6px 24px rgba(16, 185, 129, 0.5); text-decoration: none;">
                            <i class="fa-solid fa-map-location-dot"></i> <span>Daftarkan KK & Pasang Titik Rumah &rarr;</span>
                        </a>
                        <a href="jemaat/edit_data.php" style="display:inline-flex; align-items:center; gap:8px; padding: 14px 20px; background: rgba(139, 92, 246, 0.25); border: 1.5px solid rgba(167, 139, 250, 0.45); color: #fff; font-weight: 700; border-radius: 12px; text-decoration: none;">
                            <i class="fa-solid fa-user-pen"></i> <span>Perbarui / Edit Data KK</span>
                        </a>
                    </div>
                </div>

                <div class="showcase-action-card" style="background: rgba(15, 12, 35, 0.85); border-color: rgba(167, 139, 250, 0.3);">
                    <div style="width:70px; height:70px; background:linear-gradient(135deg, #8b5cf6, #6d28d9); border-radius:50%; display:flex; align-items:center; justify-content:center; margin: 0 auto 1.5rem; font-size:2rem; color:#fff; box-shadow: 0 0 24px rgba(139, 92, 246, 0.5);">
                        <i class="fa-solid fa-church"></i>
                    </div>
                    <h3>Portal Majelis & Admin</h3>
                    <p>Khusus Majelis Jemaat, Ketua Kelompok, dan Sekretaris 14 Kelompok Binaan.</p>
                    
                    <a href="admin/login.php" class="btn-ssb-login" style="width:100%; justify-content:center; padding: 14px; font-size:1rem; background: linear-gradient(135deg, #7c3aed, #5b21b6);">
                        <i class="fa-solid fa-right-to-bracket"></i> Masuk Sistem Admin
                    </a>
                </div>
            </div>
        </section>

    </main>

    <!-- FLOATING STICKY CTA BUTTON (ALL DEVICES & SMARTPHONES) -->
    <a href="jemaat/pasangtitik.php" class="btn-floating-cta">
        <i class="fa-solid fa-map-pin" style="font-size:1.2rem;"></i>
        <span>📍 Pasang Titik Rumah & KK Jemaat</span>
    </a>

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

    <!-- FOOTER -->
    <footer class="footer" id="kontak">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="brand-logo" style="margin-bottom: 1rem;">
                        <div class="brand-icon" style="background: #7c3aed; color:#fff;"><i class="fa-solid fa-church"></i></div>
                        <div class="brand-text">
                            <span class="brand-title">JEMAAT KRISTIANI</span>
                            <span class="brand-subtitle">PERSEKUTUAN KAUM BAPAK (PKB)</span>
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
                        <li><a href="#kelompok-jemaat">Sebaran 14 Kelompok</a></li>
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
                        <i class="fa-solid fa-phone" style="color:#25D366;"></i> WhatsApp: +62 812-3456-7890
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

        // Switch Chart Mode Function (KK, Jiwa, or Dual Grid)
        function switchChartMode(mode) {
            const btnKK = document.getElementById('tab-btn-kk');
            const btnJiwa = document.getElementById('tab-btn-jiwa');
            const btnDual = document.getElementById('tab-btn-dual');
            const boxKK = document.getElementById('chart-box-kk');
            const boxJiwa = document.getElementById('chart-box-jiwa');
            const grid = document.getElementById('charts-main-grid');

            // Reset all tab styles to inactive
            [
                { btn: btnKK, color: '#94a3b8', border: 'rgba(255,255,255,0.08)', bg: 'rgba(20, 18, 45, 0.6)', iconBg: 'rgba(139,92,246,0.15)', iconBorder: 'rgba(167,139,250,0.3)' },
                { btn: btnJiwa, color: '#94a3b8', border: 'rgba(255,255,255,0.08)', bg: 'rgba(20, 18, 45, 0.6)', iconBg: 'rgba(16,185,129,0.15)', iconBorder: 'rgba(52,211,153,0.3)' },
                { btn: btnDual, color: '#94a3b8', border: 'rgba(255,255,255,0.08)', bg: 'rgba(20, 18, 45, 0.6)', iconBg: 'rgba(2,132,199,0.15)', iconBorder: 'rgba(56,189,248,0.3)' }
            ].forEach(item => {
                if (item.btn) {
                    item.btn.classList.remove('active');
                    item.btn.style.background = item.bg;
                    item.btn.style.borderColor = item.border;
                    item.btn.style.color = item.color;
                    item.btn.style.boxShadow = 'none';
                    item.btn.style.transform = 'translateY(0)';
                    const icon = item.btn.querySelector('.tab-icon-box');
                    if (icon) {
                        icon.style.background = item.iconBg;
                        icon.style.border = `1px solid ${item.iconBorder}`;
                        icon.style.boxShadow = 'none';
                    }
                }
            });

            if (mode === 'kk') {
                btnKK.classList.add('active');
                btnKK.style.background = 'linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%)';
                btnKK.style.borderColor = '#c4b5fd';
                btnKK.style.color = '#ffffff';
                btnKK.style.boxShadow = '0 8px 25px rgba(124,58,237,0.55)';
                btnKK.style.transform = 'translateY(-2px)';
                const iconKK = btnKK.querySelector('.tab-icon-box');
                if (iconKK) {
                    iconKK.style.background = 'rgba(255,255,255,0.2)';
                    iconKK.style.border = '1px solid rgba(255,255,255,0.4)';
                    iconKK.style.boxShadow = '0 0 12px rgba(255,255,255,0.3)';
                }
                
                boxKK.style.display = 'block';
                boxJiwa.style.display = 'none';
                grid.style.gridTemplateColumns = '1fr';
            } else if (mode === 'jiwa') {
                btnJiwa.classList.add('active');
                btnJiwa.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                btnJiwa.style.borderColor = '#6ee7b7';
                btnJiwa.style.color = '#ffffff';
                btnJiwa.style.boxShadow = '0 8px 25px rgba(16,185,129,0.55)';
                btnJiwa.style.transform = 'translateY(-2px)';
                const iconJiwa = btnJiwa.querySelector('.tab-icon-box');
                if (iconJiwa) {
                    iconJiwa.style.background = 'rgba(255,255,255,0.2)';
                    iconJiwa.style.border = '1px solid rgba(255,255,255,0.4)';
                    iconJiwa.style.boxShadow = '0 0 12px rgba(255,255,255,0.3)';
                }
                
                boxKK.style.display = 'none';
                boxJiwa.style.display = 'block';
                grid.style.gridTemplateColumns = '1fr';
            } else if (mode === 'dual') {
                btnDual.classList.add('active');
                btnDual.style.background = 'linear-gradient(135deg, #0284c7 0%, #2563eb 100%)';
                btnDual.style.borderColor = '#7dd3fc';
                btnDual.style.color = '#ffffff';
                btnDual.style.boxShadow = '0 8px 25px rgba(2,132,199,0.55)';
                btnDual.style.transform = 'translateY(-2px)';
                const iconDual = btnDual.querySelector('.tab-icon-box');
                if (iconDual) {
                    iconDual.style.background = 'rgba(255,255,255,0.2)';
                    iconDual.style.border = '1px solid rgba(255,255,255,0.4)';
                    iconDual.style.boxShadow = '0 0 12px rgba(255,255,255,0.3)';
                }
                
                boxKK.style.display = 'block';
                boxJiwa.style.display = 'block';
                grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(380px, 1fr))';
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
