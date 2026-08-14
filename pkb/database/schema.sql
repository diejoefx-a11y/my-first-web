-- Skema Database Aplikasi Data Keluarga (db_data_keluarga)
CREATE DATABASE IF NOT EXISTS `db_data_keluarga` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_data_keluarga`;

-- 1. Tabel Users (Admin / Petugas)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `role` VARCHAR(20) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Admin (username: admin, password: admin123)
INSERT INTO `users` (`id`, `username`, `password`, `nama`, `role`) 
VALUES (1, 'admin', '$2y$10$UnsMjcuNUx6jsVGumP6bneY4qBKnpLEZAbnSo3UOIs/Bf.zrTvVYC', 'Administrator Sistem', 'admin')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- 2. Tabel Families (Data Kepala Keluarga & Lokasi Rumah)
CREATE TABLE IF NOT EXISTS `families` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `no_kk` VARCHAR(16) NOT NULL UNIQUE,
    `nik_kepala` VARCHAR(16) NOT NULL,
    `nama_kepala` VARCHAR(100) NOT NULL,
    `no_hp` VARCHAR(20) NOT NULL,
    `rt` VARCHAR(10) DEFAULT '',
    `rw` VARCHAR(10) DEFAULT '',
    `kelurahan` VARCHAR(100) DEFAULT '',
    `kecamatan` VARCHAR(100) DEFAULT '',
    `alamat_lengkap` TEXT NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `jumlah_tanggungan` INT DEFAULT 0,
    `foto_rumah` VARCHAR(255) DEFAULT NULL,
    `status_verifikasi` ENUM('pending', 'terverifikasi', 'ditolak') DEFAULT 'pending',
    `catatan_admin` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_kk` (`no_kk`),
    INDEX `idx_nik` (`nik_kepala`),
    INDEX `idx_coords` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabel Family Members (Detail Anggota Keluarga)
CREATE TABLE IF NOT EXISTS `family_members` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `family_id` INT NOT NULL,
    `nik` VARCHAR(16) NOT NULL,
    `nama_lengkap` VARCHAR(100) NOT NULL,
    `hubungan_keluarga` VARCHAR(50) NOT NULL,
    `jenis_kelamin` ENUM('L', 'P') NOT NULL,
    `tempat_lahir` VARCHAR(50) DEFAULT '',
    `tanggal_lahir` DATE NULL,
    `agama` VARCHAR(30) DEFAULT 'Islam',
    `pendidikan_terakhir` VARCHAR(50) DEFAULT '',
    `pekerjaan` VARCHAR(100) DEFAULT '',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`family_id`) REFERENCES `families`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
