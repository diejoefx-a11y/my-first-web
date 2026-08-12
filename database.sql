-- ============================================================
-- SQL Script untuk Database Portal Login & Index Dinamis
-- Impor file ini melalui phpMyAdmin (http://localhost/phpmyadmin)
-- atau CLI MySQL.
-- ============================================================

CREATE DATABASE IF NOT EXISTS `db_portal` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_portal`;

-- --------------------------------------------------------
-- Tabel: users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `user_activities`;
DROP TABLE IF EXISTS `contents`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `fullname` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  `bio` TEXT DEFAULT NULL,
  `avatar` VARCHAR(255) DEFAULT 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabel: contents (Untuk tampilan dinamis index.php)
-- --------------------------------------------------------
CREATE TABLE `contents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL UNIQUE,
  `category` VARCHAR(50) NOT NULL,
  `badge_color` VARCHAR(20) DEFAULT 'blue',
  `excerpt` TEXT NOT NULL,
  `body` TEXT NOT NULL,
  `author_id` INT NOT NULL,
  `views` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`author_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Tabel: user_activities (Log aktivitas dinamis)
-- --------------------------------------------------------
CREATE TABLE `user_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `activity` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT '127.0.0.1',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Data Sampel: Pengguna (Password: admin123 & user123)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password`, `fullname`, `role`, `bio`, `avatar`, `status`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$NeKMYOWRanK8rtHbrW6/9OS.ihDuoMGQHdkeDfmNvG3/uqL8ewG06', 'Administrator System', 'admin', 'Pengelola sistem portal utama.', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80', 'active'),
(2, 'user', 'user@example.com', '$2y$10$FDuXoLxI9Z1mK7fy21z0H.fUl1EAcKuK1gqXf4S5wXePiHv0zyb6O', 'Budi Santoso', 'user', 'Pengguna aktif portal informasi.', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80', 'active');

-- --------------------------------------------------------
-- Data Sampel: Konten Dinamis
-- --------------------------------------------------------
INSERT INTO `contents` (`id`, `title`, `slug`, `category`, `badge_color`, `excerpt`, `body`, `author_id`, `views`) VALUES
(1, 'Panduan Membangun Website PHP Modern 2026', 'panduan-php-modern', 'Teknologi', 'cyan', 'Langkah-langkah praktis mengintegrasikan PHP 8 dengan database MySQL dan arsitektur visual modern.', 'Di era web modern saat ini, mengombinasikan PHP dengan tampilan dinamis berbasis glassmorphic UI memberikan pengalaman pengguna yang sangat menarik. PDO digunakan untuk menjamin keamanan dari ancaman SQL Injection.', 1, 142),
(2, 'Optimasi Performa Query MySQL di XAMPP', 'optimasi-query-mysql', 'Database', 'emerald', 'Tips mendasar mengatur indeks dan struktur relasi database untuk kecepatan akses maksimal.', 'Menggunakan indexing pada kolom yang sering di-query seperti `username` dan `email` meningkatkan kecepatan otentikasi login secara signifikan.', 1, 98),
(3, 'Desain Antarmuka Glassmorphism dengan Vanilla CSS', 'desain-glassmorphism-css', 'UI/UX', 'purple', 'Memanfaatkan backdrop-filter dan gradien halus untuk menciptakan tampilan web kelas atas.', 'Tampilan premium tidak selalu membutuhkan framework yang berat. Dengan memanfaatkan CSS Variables dan Backdrop Filter, kita dapat membuat efek kaca transparan yang modern.', 2, 76);

-- --------------------------------------------------------
-- Data Sampel: Log Aktivitas Pengguna
-- --------------------------------------------------------
INSERT INTO `user_activities` (`user_id`, `activity`, `ip_address`) VALUES
(1, 'Berhasil login ke dalam portal', '127.0.0.1'),
(1, 'Membuat konten baru: Panduan Membangun Website PHP Modern 2026', '127.0.0.1'),
(2, 'Berhasil login sebagai pengguna', '127.0.0.1');
