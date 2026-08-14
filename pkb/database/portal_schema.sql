-- Penambahan Tabel Berita & Agenda untuk Portal Berita Dinamis
USE `db_data_keluarga`;

CREATE TABLE IF NOT EXISTS `articles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'Berita',
    `excerpt` TEXT NOT NULL,
    `content` LONGTEXT NOT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `author` VARCHAR(100) DEFAULT 'Redaksi PKB',
    `views` INT DEFAULT 0,
    `is_featured` TINYINT(1) DEFAULT 0,
    `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_category` (`category`),
    INDEX `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agendas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `event_date` DATE NOT NULL,
    `event_time` VARCHAR(50) DEFAULT '08:00 - Selesai',
    `location` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `category` VARCHAR(50) DEFAULT 'Kegiatan',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Sample Dynamic Articles
INSERT INTO `articles` (`id`, `title`, `slug`, `category`, `excerpt`, `content`, `image`, `author`, `views`, `is_featured`, `published_at`) VALUES
(1, 'Peluncuran Sistem Pemetaan Titik Rumah & Pendataan Keluarga Digital PKB', 'peluncuran-sistem-pemetaan-titik-rumah-keluarga', 'Pengumuman', 'Warga kini dapat mendaftarkan kartu keluarga serta memplot titik koordinat rumah secara mandiri melalui portal online.', '<p>Pemerintah lingkungan bersama pengurus PKB resmi meluncurkan portal pendataan keluarga berbasis titik lokasi peta (OpenStreetMap). Melalui platform digital ini, setiap kepala keluarga diharapkan berpartisipasi memperbarui data anggota keluarga serta menandai titik koordinat atap rumah masing-masing.</p><p>Sistem ini dirancang untuk memudahkan perencanaan pembangunan, pemetaan bantuan sosial, serta penanganan situasi tanggap darurat di tingkat RT dan RW.</p>', 'https://images.unsplash.com/photo-1577495508048-b635879837f1?auto=format&fit=crop&w=1200&q=80', 'Tim Humas PKB', 432, 1, NOW() - INTERVAL 1 DAY),

(2, 'Jadwal Pemeriksaan Kesehatan Gratis & Posyandu Balita-Lansia Bulan Ini', 'jadwal-pemeriksaan-kesehatan-posyandu', 'Kesehatan', 'Puskesmas setempat bersama kader Posyandu menggelar imunisasi rutin dan cek tensi gula darah gratis untuk warga.', '<p>Kegiatan Posyandu terpadu untuk balita dan pemeriksaan kesehatan berkala bagi lansia akan dilaksanakan serentak pada akhir pekan ini di Balai Pertemuan Warga. Seluruh warga diharapkan membawa Kartu Menuju Sehat (KMS) dan fotokopi KK.</p><p>Layanan yang disediakan mencakup imunisasi lengkap, penimbangan balita, penyuluhan gizi seimbang, serta skrining penyakit tidak menular (gula darah, kolesterol, dan tekanan darah).</p>', 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80', 'Kader Kesehatan', 285, 0, NOW() - INTERVAL 2 DAY),

(3, 'Gotong Royong Kebersihan Lingkungan & Penghijauan Serentak di Tiap RT', 'gotong-royong-kebersihan-lingkungan-rt', 'Kegiatan Warga', 'Aksi bersih saluran drainase dan penanaman pohon buah untuk antisipasi musim hujan dan menjaga keasrian lingkungan.', '<p>Dalam rangka menyambut musim hujan dan menjaga kebersihan sanitasi lingkungan, warga diinstruksikan untuk berpartisipasi dalam agenda kerja bakti massal. Fokus utama gotong royong kali ini adalah pembersihan saluran air limbah rumah tangga dan normalisasi parit utama.</p><p>Selain itu, disediakan 100 bibit tanaman produktif untuk ditanam di pekarangan rumah warga yang telah didata.</p>', 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80', 'Pengurus Lingkungan', 198, 0, NOW() - INTERVAL 3 DAY),

(4, 'Penyaluran Bantuan Pangan & Verifikasi Data Penerima Manfaat Sosial', 'penyaluran-bantuan-pangan-verifikasi-bansos', 'Bansos', 'Verifikasi faktual penerima bantuan sosial dilakukan mengacu pada koordinat data keluarga yang telah terdaftar.', '<p>Proses penyaluran bantuan sosial tahap kedua akan segera direalisasikan. Verifikasi lapangan dilakukan oleh tim pendamping dengan memverifikasi langsung titik koordinat rumah yang terdata di sistem PKB guna memastikan bantuan tepat sasaran kepada keluarga yang berhak.</p>', 'https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?auto=format&fit=crop&w=800&q=80', 'Seksi Sosial PKB', 312, 0, NOW() - INTERVAL 4 DAY),

(5, 'Sosialisasi Keamanan Lingkungan: Pengaktifan Pos Ronda Malam & Tombol Darurat', 'sosialisasi-keamanan-pos-ronda-darurat', 'Pemberdayaan', 'Pengurus mengimbau warga mengaktifkan kembali jadwal ronda malam demi meningkatkan ketertiban dan keamanan bersama.', '<p>Untuk menjaga rasa aman di lingkungan warga, sistem keamanan terpadu berbasis giliran ronda malam kembali diaktifkan. Warga juga dapat mengakses nomor kontak darurat pengurus lingkungan melalui portal web ini.</p>', 'https://images.unsplash.com/photo-1521791136064-7986c2920216?auto=format&fit=crop&w=800&q=80', 'Satgas Keamanan', 154, 0, NOW() - INTERVAL 5 DAY)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

-- Seed Sample Agendas
INSERT INTO `agendas` (`id`, `title`, `event_date`, `event_time`, `location`, `description`, `category`) VALUES
(1, 'Posyandu Terpadu Cempaka', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '08:30 - 11:30 WITA', 'Balai Warga RT 02 / RW 01', 'Pemeriksaan balita, timbang badan, dan pemberian vitamin A.', 'Kesehatan'),
(2, 'Kerja Bakti Bersih Saluran Air', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '06:30 - 09:30 WITA', 'Lingkungan RT 01 s/d RT 04', 'Pembersihan parit dan pemilahan sampah anorganik.', 'Gotong Royong'),
(3, 'Rapat Koordinasi Pengurus & Sosialisasi Web PKB', DATE_ADD(CURDATE(), INTERVAL 7 DAY), '19:30 - Selesai', 'Aula Kantor Sekretariat', 'Evaluasi hasil pemetaan titik rumah warga & validasi data bantuan.', 'Musyawarah')
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);
