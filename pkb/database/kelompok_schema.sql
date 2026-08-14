-- Penambahan Tabel Master Kelompok (Groups) dan Relasi ke Families
USE `db_data_keluarga`;

-- 1. Tabel Master Kelompok
CREATE TABLE IF NOT EXISTS `groups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nomor_kelompok` INT NOT NULL UNIQUE,
    `nama_kelompok` VARCHAR(100) NOT NULL,
    `nama_ketua` VARCHAR(100) DEFAULT '',
    `no_hp_ketua` VARCHAR(20) DEFAULT '',
    `wilayah_cakupan` VARCHAR(150) DEFAULT '',
    `keterangan` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Seed 14 Kelompok Default
INSERT INTO `groups` (`id`, `nomor_kelompok`, `nama_kelompok`, `nama_ketua`, `no_hp_ketua`, `wilayah_cakupan`, `keterangan`) VALUES
(1, 1, 'Kelompok 1', 'Drs. H. M. Said', '081234567801', 'RT 01 / RW 01', 'Cakupan Wilayah Barat'),
(2, 2, 'Kelompok 2', 'Ir. H. Rasyid', '081234567802', 'RT 02 / RW 01', 'Cakupan Wilayah Barat'),
(3, 3, 'Kelompok 3', 'Abdul Rahman, S.Pd', '081234567803', 'RT 03 / RW 01', 'Cakupan Wilayah Barat'),
(4, 4, 'Kelompok 4', 'Hj. Mardiana', '081234567804', 'RT 04 / RW 01', 'Cakupan Wilayah Sentral'),
(5, 5, 'Kelompok 5', 'Drs. Syamsuddin', '081234567805', 'RT 01 / RW 02', 'Cakupan Wilayah Sentral'),
(6, 6, 'Kelompok 6', 'M. Yusuf, S.E.', '081234567806', 'RT 02 / RW 02', 'Cakupan Wilayah Sentral'),
(7, 7, 'Kelompok 7', 'Dra. Nurhayati', '081234567807', 'RT 03 / RW 02', 'Cakupan Wilayah Sentral'),
(8, 8, 'Kelompok 8', 'H. Basri, S.H.', '081234567808', 'RT 04 / RW 02', 'Cakupan Wilayah Timur'),
(9, 9, 'Kelompok 9', 'Muh. Ridwan', '081234567809', 'RT 01 / RW 03', 'Cakupan Wilayah Timur'),
(10, 10, 'Kelompok 10', 'Andi Mansyur', '081234567810', 'RT 02 / RW 03', 'Cakupan Wilayah Timur'),
(11, 11, 'Kelompok 11', 'H. Iskandar', '081234567811', 'RT 03 / RW 03', 'Cakupan Wilayah Utara'),
(12, 12, 'Kelompok 12', 'Baharuddin, S.Ag', '081234567812', 'RT 04 / RW 03', 'Cakupan Wilayah Utara'),
(13, 13, 'Kelompok 13', 'Zainal Abidin', '081234567813', 'RT 01 / RW 04', 'Cakupan Wilayah Selatan'),
(14, 14, 'Kelompok 14', 'Hj. Rosdiana', '081234567814', 'RT 02 / RW 04', 'Cakupan Wilayah Selatan')
ON DUPLICATE KEY UPDATE `nama_kelompok` = VALUES(`nama_kelompok`);

-- 3. Tambah Kolom kelompok_id di Tabel families
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.columns 
WHERE table_schema = 'db_data_keluarga' 
  AND table_name = 'families' 
  AND column_name = 'kelompok_id';

SET @query = IF(@col_exists = 0, 
    'ALTER TABLE families ADD COLUMN kelompok_id INT NULL DEFAULT NULL AFTER nama_kepala, ADD INDEX idx_kelompok (kelompok_id), ADD CONSTRAINT fk_families_group FOREIGN KEY (kelompok_id) REFERENCES `groups`(id) ON DELETE SET NULL;', 
    'SELECT "Kolom kelompok_id sudah ada";');
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update sample data families to have sample kelompok
UPDATE families SET kelompok_id = 1 WHERE id = 1;
UPDATE families SET kelompok_id = 1 WHERE id = 2;
UPDATE families SET kelompok_id = 2 WHERE id = 3;
UPDATE families SET kelompok_id = 3 WHERE id = 4;
