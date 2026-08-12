<?php
require_once __DIR__ . '/config/database.php';

$pdo = getPdo();

$pdo->exec("CREATE DATABASE IF NOT EXISTS `db_ssb_tamalanrea` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `db_ssb_tamalanrea`");

echo "<h2>Inisialisasi Database SSB Tamalanrea...</h2>";



// Table: users (Admin & Pelatih)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'pelatih') DEFAULT 'admin',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

// Table: atlet
$pdo->exec("
CREATE TABLE IF NOT EXISTS `atlet` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nisn_nik` VARCHAR(30) UNIQUE,
    `no_kk` VARCHAR(30) DEFAULT NULL,
    `no_akta` VARCHAR(50) DEFAULT NULL,
    `password` VARCHAR(255) DEFAULT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `tempat_lahir` VARCHAR(50),
    `tanggal_lahir` DATE NOT NULL,
    `jenis_kelamin` ENUM('Laki-laki', 'Perempuan') DEFAULT 'Laki-laki',
    `posisi_utama` VARCHAR(30) NOT NULL,
    `posisi_sekunder` VARCHAR(30) DEFAULT '-',
    `kaki_dominan` ENUM('Kanan', 'Kiri', 'Keduanya') DEFAULT 'Kanan',
    `tinggi_badan` INT DEFAULT 0, -- cm
    `berat_badan` INT DEFAULT 0,  -- kg
    `kelompok_usia` VARCHAR(10) NOT NULL, -- U-8, U-10, U-12, U-14, U-16, U-18, Senior
    `foto_profil` VARCHAR(255) DEFAULT 'default_avatar.png',
    `file_kk` VARCHAR(255) DEFAULT NULL,
    `file_akta` VARCHAR(255) DEFAULT NULL,
    `status_keanggotaan` ENUM('Aktif', 'Non-Aktif', 'Alumni', 'Mutasi') DEFAULT 'Aktif',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

// Check and add columns if table already existed without them
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `password` VARCHAR(255) DEFAULT NULL AFTER `nisn_nik`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `no_kk` VARCHAR(30) DEFAULT NULL AFTER `nisn_nik`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `no_akta` VARCHAR(50) DEFAULT NULL AFTER `no_kk`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `file_kk` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `file_akta` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}



// Table: orang_tua
$pdo->exec("
CREATE TABLE IF NOT EXISTS `orang_tua` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `atlet_id` INT NOT NULL,
    `nama_ayah` VARCHAR(100),
    `nama_ibu` VARCHAR(100),
    `no_whatsapp` VARCHAR(20) NOT NULL,
    `alamat` TEXT,
    FOREIGN KEY (`atlet_id`) REFERENCES `atlet`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// Table: evaluasi_atlet (Raport Atribut & Fisik)
$pdo->exec("
CREATE TABLE IF NOT EXISTS `evaluasi_atlet` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `atlet_id` INT NOT NULL,
    `tanggal_evaluasi` DATE NOT NULL,
    `nilai_passing` INT DEFAULT 70,
    `nilai_dribbling` INT DEFAULT 70,
    `nilai_shooting` INT DEFAULT 70,
    `nilai_tackling` INT DEFAULT 70,
    `nilai_stamina` INT DEFAULT 70,
    `nilai_disiplin` INT DEFAULT 70,
    `vo2max` FLOAT DEFAULT 45.0,
    `catatan_pelatih` TEXT,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`atlet_id`) REFERENCES `atlet`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// Table: iuran_spp
$pdo->exec("
CREATE TABLE IF NOT EXISTS `iuran_spp` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `atlet_id` INT NOT NULL,
    `bulan` INT NOT NULL, -- 1 s/d 12
    `tahun` INT NOT NULL,
    `jumlah` DECIMAL(12, 2) DEFAULT 150000.00,
    `status_bayar` ENUM('Lunas', 'Menunggu', 'Belum Bayar') DEFAULT 'Belum Bayar',
    `tanggal_bayar` DATE DEFAULT NULL,
    `keterangan` VARCHAR(255) DEFAULT '-',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`atlet_id`) REFERENCES `atlet`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
");

// Table: turnamen
$pdo->exec("
CREATE TABLE IF NOT EXISTS `turnamen` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nama_turnamen` VARCHAR(100) NOT NULL,
    `kelompok_usia` VARCHAR(20) DEFAULT 'Semua KU',
    `tanggal_mulai` DATE,
    `tanggal_selesai` DATE,
    `lokasi` VARCHAR(100),
    `pencapaian` VARCHAR(100) DEFAULT '-'
) ENGINE=InnoDB;
");
try { $pdo->exec("ALTER TABLE `turnamen` ADD COLUMN `kelompok_usia` VARCHAR(20) DEFAULT 'Semua KU' AFTER `nama_turnamen`"); } catch (PDOException $e) {}

// Table: statistik_pertandingan
$pdo->exec("
CREATE TABLE IF NOT EXISTS `statistik_pertandingan` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `atlet_id` INT NOT NULL,
    `turnamen_id` INT NOT NULL,
    `main` INT DEFAULT 0,
    `gol` INT DEFAULT 0,
    `assist` INT DEFAULT 0,
    `kartu_kuning` INT DEFAULT 0,
    `kartu_merah` INT DEFAULT 0,
    FOREIGN KEY (`atlet_id`) REFERENCES `atlet`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`turnamen_id`) REFERENCES `turnamen`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
");

echo "<p style='color:green'>✓ Seluruh tabel berhasil dibuat!</p>";

// Seed Admin User
$checkAdmin = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($checkAdmin == 0) {
    $passHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, nama_lengkap, role) VALUES 
        ('admin', '$passHash', 'Administrator SSB', 'admin'),
        ('coach_andi', '$passHash', 'Coach Andi Wijaya', 'pelatih')
    ");
    echo "<p style='color:blue'>✓ Default user created (admin / admin123)</p>";
}

// Seed Athletes if empty
$checkAtlet = $pdo->query("SELECT COUNT(*) FROM atlet")->fetchColumn();
if ($checkAtlet == 0) {
    $defaultAtletPass = password_hash('atlet123', PASSWORD_DEFAULT);
    
    // Insert Sample Athletes
    $atletData = [
        ['7371011205120001', $defaultAtletPass, 'Muhammad Fikri Rahmat', 'Makassar', '2012-05-12', 'Laki-laki', 'Penyerang (FW)', 'Gelandang Serang', 'Kanan', 152, 43, 'U-14', 'Aktif'],
        ['7371011508140002', $defaultAtletPass, 'Andi Ahmad Rizky', 'Makassar', '2014-08-15', 'Laki-laki', 'Gelandang (MF)', 'Sayap Kanan', 'Kanan', 142, 36, 'U-12', 'Aktif'],
        ['7371012010100003', $defaultAtletPass, 'Reza Pratama Putra', 'Gowa', '2010-10-20', 'Laki-laki', 'Kiper (GK)', '-', 'Kanan', 168, 58, 'U-16', 'Aktif'],
        ['7371010303130004', $defaultAtletPass, 'Dimas Satria Utama', 'Maros', '2013-03-03', 'Laki-laki', 'Bek Tengah (CB)', 'Bek Kiri', 'Kiri', 148, 40, 'U-12', 'Aktif'],
        ['7371011711150005', $defaultAtletPass, 'Fauzan Azhiim', 'Makassar', '2015-11-17', 'Laki-laki', 'Penyerang Sayap', 'Gelandang', 'Keduanya', 135, 30, 'U-10', 'Aktif'],
        ['7371010901110006', $defaultAtletPass, 'Rian Hidayat', 'Makassar', '2011-01-09', 'Laki-laki', 'Bek Kanan (RB)', 'Gelandang Bertahan', 'Kanan', 156, 47, 'U-14', 'Aktif'],
        ['7371012504090007', $defaultAtletPass, 'Syahrul Ramadhan', 'Takalar', '2009-04-25', 'Laki-laki', 'Gelandang Serang (CAM)', 'Penyerang', 'Kanan', 170, 61, 'U-16', 'Aktif']
    ];

    $stmtAtlet = $pdo->prepare("INSERT INTO atlet (nisn_nik, password, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, posisi_utama, posisi_sekunder, kaki_dominan, tinggi_badan, berat_badan, kelompok_usia, status_keanggotaan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtOrtu = $pdo->prepare("INSERT INTO orang_tua (atlet_id, nama_ayah, nama_ibu, no_whatsapp, alamat) VALUES (?, ?, ?, ?, ?)");
    $stmtEval = $pdo->prepare("INSERT INTO evaluasi_atlet (atlet_id, tanggal_evaluasi, nilai_passing, nilai_dribbling, nilai_shooting, nilai_tackling, nilai_stamina, nilai_disiplin, vo2max, catatan_pelatih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtIuran = $pdo->prepare("INSERT INTO iuran_spp (atlet_id, bulan, tahun, jumlah, status_bayar, tanggal_bayar, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?)");

    $ortuData = [
        ['Rahmat Hidayat', 'Nurhayati', '081234567890', 'Jl. Tamalanrea Raya No. 12, Makassar'],
        ['Andi Wijaya', 'Siti Rahma', '085299887766', 'BTP Blok AF No. 45, Makassar'],
        ['Bambang Pratama', 'Dewi Susanti', '081344556677', 'Jl. Perintis Kemerdekaan KM 10'],
        ['Syarifuddin', 'Hasmiah', '082199001122', 'Perumahan Dosen Unhas Tamalanrea'],
        ['Zulkifli', 'Maryam', '081987654321', 'Jl. Hamzah Fansuri No. 8, Makassar'],
        ['Ruslan', 'Karmila', '085311223344', 'Jl. Sahabat Kampus Unhas No. 3'],
        ['Kamaruddin', 'Khadijah', '081244332211', 'BTP Blok M No. 88, Makassar']
    ];

    foreach ($atletData as $index => $data) {
        $stmtAtlet->execute($data);
        $atletId = $pdo->lastInsertId();

        // Ortu
        $o = $ortuData[$index];
        $stmtOrtu->execute([$atletId, $o[0], $o[1], $o[2], $o[3]]);

        // Evaluasi
        $stmtEval->execute([
            $atletId, 
            date('Y-m-d'), 
            rand(75, 92), rand(70, 90), rand(68, 95), rand(65, 88), rand(75, 95), rand(80, 98),
            round(rand(420, 560)/10, 1),
            'Perkembangan fisik dan kontrol bola sangat baik. Perlu latihan penempatan posisi.'
        ]);

        // SPP 3 Bulan Terakhir (Juni, Juli, Agustus 2026)
        $stmtIuran->execute([$atletId, 6, 2026, 150000, 'Lunas', '2026-06-05', 'Transfer Mandiri']);
        $stmtIuran->execute([$atletId, 7, 2026, 150000, 'Lunas', '2026-07-04', 'Tunai ke Bendahara']);
        $stmtIuran->execute([$atletId, 8, 2026, 150000, ($index % 2 == 0 ? 'Lunas' : 'Belum Bayar'), ($index % 2 == 0 ? '2026-08-02' : NULL), ($index % 2 == 0 ? 'Transfer BCA' : 'Tunggakan Bulanan')]);
    }

    // Turnamen Seed
    $pdo->exec("INSERT INTO turnamen (nama_turnamen, tanggal_mulai, tanggal_selesai, lokasi, pencapaian) VALUES 
        ('Liga Danone Nations Cup U-12', '2026-03-10', '2026-03-15', 'Lap. Karebosi Makassar', 'Juara 2 Sub-Zona'),
        ('Piala Menpora U-14 & U-16', '2026-06-01', '2026-06-07', 'Stadion Batua Makassar', 'Semifinalis')
    ");

    // Stat Turnamen
    $pdo->exec("INSERT INTO statistik_pertandingan (atlet_id, turnamen_id, main, gol, assist, kartu_kuning, kartu_merah) VALUES
        (1, 2, 5, 4, 2, 1, 0),
        (2, 1, 4, 2, 3, 0, 0),
        (3, 2, 5, 0, 0, 0, 0),
        (4, 1, 4, 1, 0, 2, 0)
    ");

    echo "<p style='color:green'>✓ Sampel data atlet, orang tua, evaluasi, dan iuran berhasil diisikan!</p>";
}

// Ensure all existing athletes have a valid default password hash if NULL
$defaultPassHash = password_hash('atlet123', PASSWORD_DEFAULT);
$pdo->exec("UPDATE atlet SET password = '$defaultPassHash' WHERE password IS NULL OR password = ''");

echo "<br><a href='login.php' style='display:inline-block; padding:10px 20px; background:#6366f1; color:#fff; text-decoration:none; border-radius:8px; font-family:sans-serif;'>&larr; Buka Halaman Login</a>";

