-- Migrasi Penambahan Fitur Kelompok Lengkap (Sekretaris, Galeri Foto, Relasi Berita, dan Akun Admin Kelompok)
USE `db_data_keluarga`;

-- 1. Tambah Kolom di Tabel groups
SET @col1 = 0;
SELECT COUNT(*) INTO @col1 FROM information_schema.columns WHERE table_schema = 'db_data_keluarga' AND table_name = 'groups' AND column_name = 'nama_sekretaris';
SET @q1 = IF(@col1 = 0, 'ALTER TABLE `groups` ADD COLUMN `nama_sekretaris` VARCHAR(100) DEFAULT "" AFTER `nama_ketua`, ADD COLUMN `no_hp_sekretaris` VARCHAR(20) DEFAULT "" AFTER `nama_sekretaris`, ADD COLUMN `foto_sampul` VARCHAR(255) DEFAULT NULL AFTER `keterangan`, ADD COLUMN `deskripsi_profil` TEXT DEFAULT NULL AFTER `foto_sampul`;', 'SELECT "Kolom groups sudah ada";');
PREPARE stmt1 FROM @q1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- 2. Tambah Kolom group_id di Tabel articles
SET @col2 = 0;
SELECT COUNT(*) INTO @col2 FROM information_schema.columns WHERE table_schema = 'db_data_keluarga' AND table_name = 'articles' AND column_name = 'group_id';
SET @q2 = IF(@col2 = 0, 'ALTER TABLE `articles` ADD COLUMN `group_id` INT NULL DEFAULT NULL AFTER `category`, ADD INDEX `idx_article_group` (`group_id`), ADD CONSTRAINT `fk_articles_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL;', 'SELECT "Kolom articles.group_id sudah ada";');
PREPARE stmt2 FROM @q2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 3. Tambah Kolom group_id di Tabel users
SET @col3 = 0;
SELECT COUNT(*) INTO @col3 FROM information_schema.columns WHERE table_schema = 'db_data_keluarga' AND table_name = 'users' AND column_name = 'group_id';
SET @q3 = IF(@col3 = 0, 'ALTER TABLE `users` ADD COLUMN `group_id` INT NULL DEFAULT NULL AFTER `role`, ADD INDEX `idx_user_group` (`group_id`), ADD CONSTRAINT `fk_users_group` FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE SET NULL;', 'SELECT "Kolom users.group_id sudah ada";');
PREPARE stmt3 FROM @q3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- 4. Tabel Galeri Foto Kelompok
CREATE TABLE IF NOT EXISTS `group_galleries` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT NOT NULL,
    `judul_foto` VARCHAR(255) NOT NULL,
    `file_foto` VARCHAR(255) NOT NULL,
    `deskripsi` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`group_id`) REFERENCES `groups`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update Sekretaris default untuk Kelompok 1 - 14
UPDATE `groups` SET `nama_sekretaris` = 'Ahmad Dahlan, S.Kom', `no_hp_sekretaris` = '081234567901' WHERE `nomor_kelompok` = 1;
UPDATE `groups` SET `nama_sekretaris` = 'Sitti Aminah, S.Sos', `no_hp_sekretaris` = '081234567902' WHERE `nomor_kelompok` = 2;
UPDATE `groups` SET `nama_sekretaris` = 'Faisal Basri', `no_hp_sekretaris` = '081234567903' WHERE `nomor_kelompok` = 3;
UPDATE `groups` SET `nama_sekretaris` = 'Sri Wahyuni, S.Pd', `no_hp_sekretaris` = '081234567904' WHERE `nomor_kelompok` = 4;
UPDATE `groups` SET `nama_sekretaris` = 'Muhammad Ikhsan', `no_hp_sekretaris` = '081234567905' WHERE `nomor_kelompok` = 5;
UPDATE `groups` SET `nama_sekretaris` = 'Rina Anggraeni', `no_hp_sekretaris` = '081234567906' WHERE `nomor_kelompok` = 6;
UPDATE `groups` SET `nama_sekretaris` = 'H. Usman', `no_hp_sekretaris` = '081234567907' WHERE `nomor_kelompok` = 7;
UPDATE `groups` SET `nama_sekretaris` = 'Dewi Sartika', `no_hp_sekretaris` = '081234567908' WHERE `nomor_kelompok` = 8;
UPDATE `groups` SET `nama_sekretaris` = 'Surya Pratama', `no_hp_sekretaris` = '081234567909' WHERE `nomor_kelompok` = 9;
UPDATE `groups` SET `nama_sekretaris` = 'Fitriani, S.E.', `no_hp_sekretaris` = '081234567910' WHERE `nomor_kelompok` = 10;
UPDATE `groups` SET `nama_sekretaris` = 'Lukman Hakim', `no_hp_sekretaris` = '081234567911' WHERE `nomor_kelompok` = 11;
UPDATE `groups` SET `nama_sekretaris` = 'Nur Hasnah', `no_hp_sekretaris` = '081234567912' WHERE `nomor_kelompok` = 12;
UPDATE `groups` SET `nama_sekretaris` = 'Arif Rahman', `no_hp_sekretaris` = '081234567913' WHERE `nomor_kelompok` = 13;
UPDATE `groups` SET `nama_sekretaris` = 'Kasmawati', `no_hp_sekretaris` = '081234567914' WHERE `nomor_kelompok` = 14;

-- Buat Akun Admin Default untuk Kelompok 1 s/d 14 (Username: kelompok1, Password: admin123)
-- Password hash untuk admin123: $2y$10$UnsMjcuNUx6jsVGumP6bneY4qBKnpLEZAbnSo3UOIs/Bf.zrTvVYC
INSERT INTO `users` (`username`, `password`, `nama`, `role`, `group_id`) VALUES
('kelompok1', '$2y$10$UnsMjcuNUx6jsVGumP6bneY4qBKnpLEZAbnSo3UOIs/Bf.zrTvVYC', 'Admin Kelompok 1', 'admin_kelompok', 1),
('kelompok2', '$2y$10$UnsMjcuNUx6jsVGumP6bneY4qBKnpLEZAbnSo3UOIs/Bf.zrTvVYC', 'Admin Kelompok 2', 'admin_kelompok', 2),
('kelompok3', '$2y$10$UnsMjcuNUx6jsVGumP6bneY4qBKnpLEZAbnSo3UOIs/Bf.zrTvVYC', 'Admin Kelompok 3', 'admin_kelompok', 3)
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

-- Sample Galeri Foto
INSERT INTO `group_galleries` (`group_id`, `judul_foto`, `file_foto`, `deskripsi`) VALUES
(1, 'Rapat Koordinasi Pengurus Kelompok 1', 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=800&q=80', 'Pertemuan bulanan evaluasi pendataan warga dan program sosial di RT 01.'),
(1, 'Kerja Bakti Lingkungan Bersama Warga Kelompok 1', 'https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?auto=format&fit=crop&w=800&q=80', 'Pembersihan saluran air dan penataan taman lorong.'),
(2, 'Posyandu Terpadu Balita & Lansia Kelompok 2', 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80', 'Pelayanan imunisasi balita dan skrining tensi gula darah lansia.');
