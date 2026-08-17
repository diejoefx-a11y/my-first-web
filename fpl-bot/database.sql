-- ====================================================================
-- FPL-BOT DATABASE SCHEMA & INITIAL DATA
-- Engine: MySQL / MariaDB (XAMPP & Niagahoster cPanel Compatible)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `fpl_bot` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fpl_bot`;

-- --------------------------------------------------------
-- 1. Table structure for table `settings` (Bobot & Parameter)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `param_key` VARCHAR(64) UNIQUE NOT NULL,
    `param_name` VARCHAR(128) NOT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `weight` INT DEFAULT 20,
    `description` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Dumping initial data for table `settings`
-- --------------------------------------------------------
INSERT INTO `settings` (`param_key`, `param_name`, `is_active`, `weight`, `description`) VALUES
('xg_xa_form', 'Form & Underlying Stats (xG, xA, Form)', 1, 30, 'Bobot produktivitas gol, assist & konsistensi form pemain'),
('fdr_schedule', 'Jadwal & FDR (3-5 Gameweek ke depan)', 1, 25, 'Tingkat kemudahan jadwal fixture dan Home/Away advantage'),
('youtube_consensus', 'YouTube Analyst Consensus Score', 1, 20, 'Frekuensi rekomendasi dari video analis YouTube FPL'),
('effective_ownership', 'Proteksi Effective Ownership (EO)', 1, 15, 'Menjaga kepemilikan pemain kunci template agar ranking aman'),
('ict_index', 'ICT Index (Influence, Creativity, Threat)', 1, 10, 'Metrik resmi FPL untuk pengaruh pemain di lapangan'),
('injury_filter', 'Hard-Filter Cedera & Suspensi (<75%)', 1, 0, 'Selalu aktif: Memblokir transfer pemain yang diragukan tampil')
ON DUPLICATE KEY UPDATE `param_name`=VALUES(`param_name`);

-- --------------------------------------------------------
-- 2. Table structure for table `youtube_consensus`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `youtube_consensus` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gameweek` INT NOT NULL,
    `video_url` VARCHAR(255) NOT NULL,
    `video_title` VARCHAR(255) NULL,
    `channel_name` VARCHAR(128) NULL,
    `recommended_buys` TEXT NULL,
    `recommended_sells` TEXT NULL,
    `recommended_captains` TEXT NULL,
    `summary_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Table structure for table `execution_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `execution_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `gameweek` INT NOT NULL,
    `execution_type` ENUM('MANUAL', 'AUTO_FALLBACK') NOT NULL,
    `status` ENUM('SUCCESS', 'FAILED', 'SKIPPED') NOT NULL,
    `transfer_out_id` INT NULL,
    `transfer_out_name` VARCHAR(128) NULL,
    `transfer_in_id` INT NULL,
    `transfer_in_name` VARCHAR(128) NULL,
    `captain_id` INT NULL,
    `captain_name` VARCHAR(128) NULL,
    `vice_captain_id` INT NULL,
    `vice_captain_name` VARCHAR(128) NULL,
    `response_message` TEXT NULL,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Table structure for table `fpl_cache`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fpl_cache` (
    `cache_key` VARCHAR(128) PRIMARY KEY,
    `cache_data` LONGTEXT NOT NULL,
    `expires_at` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
