<?php
/**
 * PORTAL BOLA MAKASSAR & LANDING PAGE SSB TAMALANREA 21
 * Dynamic Football News Portal & Integration to SSB Tamalanrea Management System
 */

// Data Berita Bola Dinamis
$articles = [
    [
        'id' => 1,
        'title' => 'SSB Tamalanrea Makassar Buka Pendaftaran Siswa Baru Musim 2026/2027 U-10 hingga U-17',
        'category' => 'SSB Tamalanrea',
        'category_slug' => 'ssb',
        'image' => 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1200&q=80',
        'date' => '12 Agustus 2026',
        'author' => 'Tim Media SSB',
        'read_time' => '3 Menit',
        'views' => '1,420',
        'excerpt' => 'Akademi Sepak Bola SSB Tamalanrea Makassar resmi membuka gelombang penerimaan atlet muda berbakat. Pelatihan didampingi pelatih berlisensi PSSI dengan kurikulum pembinaan modern.',
        'content' => '<p>Sekolah Sepak Bola (SSB) Tamalanrea Makassar kembali mengundang talenta-talenta muda Makassar dan sekitarnya untuk bergabung dalam program pembinaan sepak bola berjenjang untuk kelompok umur U-10, U-12, U-15, dan U-17.</p>
                      <p>Program latihan diselenggarakan secara intensif di Lapangan Sepak Bola Tamalanrea dengan fasilitas penunjang yang lengkap, mencakup evaluasi statistik fisik, analisis teknik individu, serta sistem rapor digital atlet berbasis web.</p>
                      <blockquote>"Komitmen kami adalah membentuk karakter, disiplin, dan penguasaan teknik dasar sepak bola yang kuat bagi generasi muda Sulawesi Selatan," ujar Kepala Pelatih SSB Tamalanrea.</blockquote>
                      <p>Bagi orang tua dan calon atlet yang ingin mendaftar atau mengakses portal informasi internal, dapat langsung menuju <strong>Portal Resmi SSB Tamalanrea21</strong> melalui tombol login yang tersedia di website ini.</p>'
    ],
    [
        'id' => 2,
        'title' => 'Timnas Indonesia Siap Hadapi Laga Krusial Kualifikasi Pertandingan Internasional',
        'category' => 'Timnas Indonesia',
        'category_slug' => 'timnas',
        'image' => 'https://images.unsplash.com/photo-1508098682722-e99c43a406b2?auto=format&fit=crop&w=800&q=80',
        'date' => '11 Agustus 2026',
        'author' => 'Redaksi Bola',
        'read_time' => '4 Menit',
        'views' => '2,890',
        'excerpt' => 'Skuad Garuda menggelar latihan fisik dan taktik intensif. Pelatih menekankan pentingnya transisi cepat dan penyelesaian akhir jelang laga krusial.',
        'content' => '<p>Persiapan Skuad Garuda makin dimaksimalkan jelang pertandingan internasional mendatang. Sesi latihan difokuskan pada peningkatan ketahanan fisik serta taktik pertahanan rapat untuk meredam serangan lawan.</p>
                      <p>Para pemain muda bakat lokal yang bersinar di kompetisi pembinaan usia dini juga terus mendapatkan pantauan langsung dari tim pemandu bakat nasional.</p>'
    ],
    [
        'id' => 3,
        'title' => 'Perkembangan Sepak Bola Usia Dini Makassar: Pentingnya Kurikulum Terstruktur bagi SSB',
        'category' => 'Usia Dini',
        'category_slug' => 'usia-dini',
        'image' => 'https://images.unsplash.com/photo-1517927033932-b3d18e61fb3a?auto=format&fit=crop&w=800&q=80',
        'date' => '10 Agustus 2026',
        'author' => 'Analis Pembinaan',
        'read_time' => '5 Menit',
        'views' => '980',
        'excerpt' => 'Pemerhati sepak bola Sulawesi Selatan menyoroti pentingnya integrasi kurikulum sepak bola modern dan evaluasi berkala pada sekolah sepak bola usia muda.',
        'content' => '<p>Pembinaan usia dini merupakan pondasi utama sepak bola nasional. Penggunaan teknologi digital dalam mencatat perkembangan atlet, seperti yang diterapkan di SSB Tamalanrea, kini menjadi standar baru yang sangat positif.</p>
                      <p>Dengan adanya statistik kehadiran, nilai evaluasi passing, shooting, dribbling, dan kondisi fisik, pelatih dapat memberikan program latihan yang presisi sesuai kebutuhan pemain.</p>'
    ],
    [
        'id' => 4,
        'title' => 'PSM Makassar Matangkan Skuad Utama Jelang Lanjutan Liga 1 Indonesia',
        'category' => 'Liga 1 & Lokal',
        'category_slug' => 'liga1',
        'image' => 'https://images.unsplash.com/photo-1551958219-acbc608c6377?auto=format&fit=crop&w=800&q=80',
        'date' => '09 Agustus 2026',
        'author' => 'Reporter Makassar',
        'read_time' => '3 Menit',
        'views' => '3,150',
        'excerpt' => 'Juku Eja menggelar uji coba tertutup guna memantapkan skema permainan dan adaptasi para pemain baru jelang laga tandang mendatang.',
        'content' => '<p>PSM Makassar terus berbenah dalam menyongsong laga lanjutan Liga 1. Tim pelatih memprioritaskan kekompakan antar lini serta koordinasi di area pertahanan.</p>
                      <p>Manajemen PSM juga mengapresiasi maraknya SSB lokal di Makassar yang konsisten melahirkan bibit-bibit pemain muda berkualitas.</p>'
    ],
    [
        'id' => 5,
        'title' => 'Turnamen Antar SSB Se-Sulsel Musim Ini Diprediksi Berjalan Ketat',
        'category' => 'SSB Tamalanrea',
        'category_slug' => 'ssb',
        'image' => 'https://images.unsplash.com/photo-1431324155629-1a6deb1dec8d?auto=format&fit=crop&w=800&q=80',
        'date' => '08 Agustus 2026',
        'author' => 'Panitia Turnamen',
        'read_time' => '4 Menit',
        'views' => '1,840',
        'excerpt' => 'SSB Tamalanrea siap menerjunkan skuad terbaik di kelompok umur U-12 dan U-15 pada ajang turnamen pembinaan daerah bulan depan.',
        'content' => '<p>Turnamen usia dini se-Sulawesi Selatan kembali digelar sebagai wadah kompetisi sehat bagi anak-anak. SSB Tamalanrea Makassar telah melakukan serangkaian persiapan khusus termasuk laga uji coba mingguan.</p>
                      <p>Para atlet dievaluasi secara berkala melalui platform online SSB Tamalanrea21 untuk memastikan kesiapan fisik dan mental sebelum bertanding.</p>'
    ],
    [
        'id' => 6,
        'title' => 'Tips Nutrisi dan Kebugaran Bagi Atlet Sepak Bola Muda Usia 10-17 Tahun',
        'category' => 'Usia Dini',
        'category_slug' => 'usia-dini',
        'image' => 'https://images.unsplash.com/photo-1526232761682-d26e03ac148e?auto=format&fit=crop&w=800&q=80',
        'date' => '07 Agustus 2026',
        'author' => 'Tim Medis SSB',
        'read_time' => '4 Menit',
        'views' => '1,120',
        'excerpt' => 'Pola makan seimbang, hidrasi cukup, dan waktu istirahat yang efektif menjadi kunci utama menjaga performa puncak pemain muda.',
        'content' => '<p>Bagi atlet muda, pemenuhan nutrisi karbohidrat kompleks, protein tinggi, serta vitamin sangat krusial dalam masa pertumbuhan dan pemulihan pasca latihan intensif.</p>'
    ]
];

$featuredArticle = $articles[0]; // Article utama
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bola Makassar News & Portal Resmi SSB Tamalanrea 21</title>
    <meta name="description" content="Portal Berita Sepak Bola Makassar, Timnas Indonesia, Liga 1, serta Informasi Resmi Akademi Sekolah Sepak Bola SSB Tamalanrea Makassar.">
    
    <!-- Google Fonts & FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS Custom -->
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body>

    <!-- NAVBAR HEADER -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="brand-logo" id="brand-logo">
                <div class="brand-icon">
                    <i class="fa-solid fa-football"></i>
                </div>
                <div class="brand-text">
                    <span class="brand-title">BOLA MAKASSAR</span>
                    <span class="brand-subtitle"><i class="fa-solid fa-circle-check"></i> MITRA SSB TAMALANREA</span>
                </div>
            </a>
            
            <ul class="nav-menu" id="nav-menu">
                <li><a href="#berita" class="nav-link active">Berita Terkini</a></li>
                <li><a href="#kategori" class="nav-link">Kategori</a></li>
                <li><a href="#tentang-ssb" class="nav-link">Tentang SSB</a></li>
                <li><a href="/pos-fotocopy" class="nav-link" target="_blank"><i class="fa-solid fa-print" style="margin-right:4px;"></i> POS Fotocopy</a></li>
                <li><a href="#kontak" class="nav-link">Kontak</a></li>
            </ul>
            
            <div class="nav-actions">
                <!-- LINK DIRECT TO SSB TAMALANREA 21 LOGIN -->
                <a href="ssb-tamalanrea21/login.php" class="btn-ssb-login" id="btn-login-header">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Portal SSB Tamalanrea</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- BREAKING NEWS TICKER -->
    <div class="ticker-wrapper">
        <div class="ticker-label">
            <i class="fa-solid fa-bolt"></i> HOT NEWS
        </div>
        <div class="ticker-content">
            <div class="ticker-item">
                <span class="badge">SSB TAMALANREA</span> Pendaftaran Atlet Baru U-10 s/d U-17 Musim 2026/2027 Telah Dibuka!
            </div>
            <div class="ticker-item">
                <span class="badge">TIMNAS</span> Skuad Garuda Bersiap Hadapi Pertandingan Kualifikasi Internasional.
            </div>
            <div class="ticker-item">
                <span class="badge">LIGA 1</span> PSM Makassar Gelar Latihan Intensif di Stadion Gelora Habibie.
            </div>
            <div class="ticker-item">
                <span class="badge">PEMBINAAN</span> Rapor Digital Atlet SSB Tamalanrea21 Kini Dapat Diakses Wali Murid.
            </div>
        </div>
    </div>

    <!-- MAIN CONTAINER -->
    <main class="container">

        <!-- HERO SECTION -->
        <section class="hero-section" id="hero">
            <div class="hero-grid">
                
                <!-- FEATURED NEWS CARD (LEFT) -->
                <div class="featured-card" onclick="openArticleModal(<?= $featuredArticle['id']; ?>)">
                    <div class="featured-img-wrapper">
                        <img src="<?= $featuredArticle['image']; ?>" alt="<?= htmlspecialchars($featuredArticle['title']); ?>">
                    </div>
                    <div class="featured-overlay"></div>
                    <div class="featured-content">
                        <span class="category-tag ssb"><i class="fa-solid fa-star"></i> <?= $featuredArticle['category']; ?></span>
                        <h1 class="featured-title"><?= htmlspecialchars($featuredArticle['title']); ?></h1>
                        <p class="featured-excerpt"><?= htmlspecialchars($featuredArticle['excerpt']); ?></p>
                        <div class="featured-meta">
                            <span><i class="fa-regular fa-calendar"></i> <?= $featuredArticle['date']; ?></span>
                            <span><i class="fa-regular fa-clock"></i> <?= $featuredArticle['read_time']; ?></span>
                            <span><i class="fa-regular fa-eye"></i> <?= $featuredArticle['views']; ?> Views</span>
                        </div>
                    </div>
                </div>

                <!-- SSB TAMALANREA SPOTLIGHT CARD (RIGHT SIDEBAR) -->
                <div class="ssb-spotlight-card" id="ssb-spotlight">
                    <div>
                        <div class="ssb-badge-header">
                            <span class="ssb-badge-pill"><i class="fa-solid fa-shield-halved"></i> AKADEMI RESMI</span>
                            <span style="font-size:0.8rem; color:var(--accent-green);"><i class="fa-solid fa-location-dot"></i> Makassar</span>
                        </div>
                        <h2 class="ssb-spotlight-title">SSB TAMALANREA MAKASSAR</h2>
                        <p class="ssb-spotlight-desc">
                            Wadah Pembinaan Sepak Bola Usia Dini Berkarakter, Disiplin, dan Berprestasi dengan Evaluasi Rapor Statistik Digital.
                        </p>
                        
                        <div class="ssb-stats-grid">
                            <div class="stat-item">
                                <div class="stat-num">150+</div>
                                <div class="stat-lbl">Siswa</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-num">U-10-17</div>
                                <div class="stat-lbl">Usia</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-num">PSSI</div>
                                <div class="stat-lbl">Lisensi</div>
                            </div>
                        </div>
                    </div>

                    <div class="ssb-cta-group">
                        <a href="ssb-tamalanrea21/login.php" class="btn-primary-ssb" id="btn-login-hero">
                            <i class="fa-solid fa-right-to-bracket"></i> Masuk Sistem SSB Tamalanrea
                        </a>
                        <a href="https://wa.me/6281234567890?text=Halo%20Admin%20SSB%20Tamalanrea,%20saya%20ingin%20tanya%20pendaftaran%20siswa%20baru" target="_blank" class="btn-secondary-ssb" id="btn-wa-info">
                            <i class="fa-brands fa-whatsapp" style="color:#25D366;"></i> Info Pendaftaran WA
                        </a>
                    </div>
                </div>

            </div>
        </section>

        <!-- FILTER & SEARCH SECTION -->
        <section class="filter-section" id="kategori">
            <div class="controls-bar">
                <div class="filter-tabs">
                    <button class="filter-btn active" onclick="filterCategory('all', this)">Semua Berita</button>
                    <button class="filter-btn" onclick="filterCategory('ssb', this)">SSB Tamalanrea</button>
                    <button class="filter-btn" onclick="filterCategory('timnas', this)">Timnas Indonesia</button>
                    <button class="filter-btn" onclick="filterCategory('liga1', this)">Liga 1 & Lokal</button>
                    <button class="filter-btn" onclick="filterCategory('usia-dini', this)">Usia Dini</button>
                </div>
                
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" placeholder="Cari berita sepak bola..." onkeyup="searchNews()">
                </div>
            </div>
        </section>

        <!-- NEWS GRID SECTION -->
        <section id="berita" style="padding-bottom: 2rem;">
            <div class="section-header">
                <div class="section-title-wrap">
                    <div class="title-line"></div>
                    <h2 class="section-title">Berita & Informasi Terkini</h2>
                </div>
            </div>

            <div class="news-grid" id="newsGrid">
                <?php foreach ($articles as $index => $art): ?>
                <div class="news-card" data-category="<?= $art['category_slug']; ?>" data-title="<?= strtolower(htmlspecialchars($art['title'])); ?>" onclick="openArticleModal(<?= $art['id']; ?>)">
                    <div class="card-img-wrap">
                        <span class="card-badge category-tag <?= $art['category_slug']; ?>"><?= $art['category']; ?></span>
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
                            <span>Baca Selengkapnya</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- FEATURE SHOWCASE SSB TAMALANREA -->
        <section class="ssb-showcase-section" id="tentang-ssb">
            <div class="showcase-banner">
                <div class="showcase-text">
                    <h2>Akademi Sepak Bola SSB Tamalanrea Makassar</h2>
                    <p>
                        Membangun karakter pemuda Indonesia melalui pendidikan sepak bola usia dini yang profesional, terukur, dan berbasis teknologi rapor digital.
                    </p>
                    
                    <div class="features-list">
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                            <div class="feature-item-text">
                                <h4>Kurikulum PSSI</h4>
                                <p>Metode latihan terstruktur sesuai kelompok usia.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="feature-item-text">
                                <h4>Rapor Digital</h4>
                                <p>Tracking statistik evaluasi fisik & teknik online.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-trophy"></i></div>
                            <div class="feature-item-text">
                                <h4>Turnamen Resmi</h4>
                                <p>Partisipasi rutin kompetisi daerah & nasional.</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <div class="feature-icon"><i class="fa-solid fa-user-shield"></i></div>
                            <div class="feature-item-text">
                                <h4>Pelatih Lisensi</h4>
                                <p>Didampingi jajaran pelatih berpengalaman.</p>
                            </div>
                        </div>
                    </div>

                    <a href="ssb-tamalanrea21/login.php" class="btn-primary-ssb" style="display:inline-flex; width:auto; padding: 14px 28px;">
                        <i class="fa-solid fa-right-to-bracket"></i> Akses Portal SSB Tamalanrea21
                    </a>
                </div>

                <div class="showcase-action-card">
                    <div style="width:70px; height:70px; background:linear-gradient(135deg, var(--accent-green), #047857); border-radius:50%; display:flex; align-items:center; justify-content:center; margin: 0 auto 1.5rem; font-size:2rem; color:#fff; box-shadow: 0 0 24px var(--accent-green-glow);">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <h3>Portal Manajemen SSB</h3>
                    <p>Khusus Administrator, Pelatih, Atlet, dan Orang Tua Siswa SSB Tamalanrea Makassar.</p>
                    
                    <a href="ssb-tamalanrea21/login.php" class="btn-ssb-login" style="width:100%; justify-content:center; padding: 14px; font-size:1rem;">
                        <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
                    </a>
                </div>
            </div>
        </section>

    </main>

    <!-- MODAL READ ARTICLE -->
    <div class="modal-overlay" id="articleModal" onclick="closeArticleModal(event)">
        <div class="modal-container" onclick="event.stopPropagation()">
            <button class="modal-close-btn" onclick="closeArticleModal()"><i class="fa-solid fa-xmark"></i></button>
            <img src="" id="modalImg" class="modal-header-img" alt="Berita">
            <div class="modal-body-content">
                <div class="modal-meta-bar">
                    <span id="modalCategory" class="category-tag"></span>
                    <span style="font-size:0.85rem; color:var(--text-muted);" id="modalDate"></span>
                </div>
                <h2 id="modalTitle" class="modal-title"></h2>
                <div id="modalArticleText" class="modal-article-text"></div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="footer" id="kontak">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <div class="brand-logo" style="margin-bottom: 1rem;">
                        <div class="brand-icon"><i class="fa-solid fa-football"></i></div>
                        <div class="brand-text">
                            <span class="brand-title">BOLA MAKASSAR</span>
                            <span class="brand-subtitle">PORTAL SEPAK BOLA & SSB TAMALANREA</span>
                        </div>
                    </div>
                    <p style="font-size:0.9rem; color:var(--text-muted); margin-bottom: 1.5rem;">
                        Situs informasi sepak bola lokal Makassar, nasional, serta portal resmi Sekolah Sepak Bola (SSB) Tamalanrea Makassar.
                    </p>
                </div>

                <div class="footer-col">
                    <h4>Navigasi Cepat</h4>
                    <ul class="footer-links">
                        <li><a href="#berita">Berita Terkini</a></li>
                        <li><a href="#kategori">Kategori Berita</a></li>
                        <li><a href="#tentang-ssb">Tentang SSB</a></li>
                        <li><a href="ssb-tamalanrea21/login.php">Login Sistem SSB</a></li>
                        <li><a href="/pos-fotocopy" target="_blank"><i class="fa-solid fa-print" style="color:var(--accent-green);"></i> POS Fotocopy</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Kategori</h4>
                    <ul class="footer-links">
                        <li><a href="#" onclick="filterCategory('ssb'); return false;">SSB Tamalanrea</a></li>
                        <li><a href="#" onclick="filterCategory('timnas'); return false;">Timnas Indonesia</a></li>
                        <li><a href="#" onclick="filterCategory('liga1'); return false;">Liga 1 & PSM</a></li>
                        <li><a href="#" onclick="filterCategory('usia-dini'); return false;">Pembinaan Usia Dini</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h4>Sekolah Sepak Bola (SSB)</h4>
                    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom: 10px;">
                        <i class="fa-solid fa-location-dot" style="color:var(--accent-green);"></i> Lapangan Sepak Bola Tamalanrea, Makassar, Sulawesi Selatan.
                    </p>
                    <p style="font-size:0.88rem; color:var(--text-muted); margin-bottom: 15px;">
                        <i class="fa-solid fa-phone" style="color:var(--accent-green);"></i> WhatsApp: +62 812-3456-7890
                    </p>
                    <a href="ssb-tamalanrea21/login.php" class="btn-ssb-login" style="padding: 8px 16px; font-size:0.85rem;">
                        <i class="fa-solid fa-right-to-bracket"></i> Portal Login SSB
                    </a>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y'); ?> Bola Makassar & SSB Tamalanrea 21. All Rights Reserved.</p>
                <p>Dikembangkan dengan ❤️ untuk Sepak Bola Makassar</p>
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

            document.getElementById('articleModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close Modal Reader
        function closeArticleModal(event) {
            document.getElementById('articleModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Navbar Scroll Blur Effect
        window.addEventListener('scroll', () => {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 40) {
                navbar.style.boxShadow = '0 10px 30px rgba(0,0,0,0.5)';
            } else {
                navbar.style.boxShadow = 'none';
            }
        });
    </script>
</body>
</html>
