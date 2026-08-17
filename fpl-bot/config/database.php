<?php
/**
 * FPL-BOT - Database Connection & Auto-Migration
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $config = require __DIR__ . '/config.php';
            $dbConf = $config['db'];

            $host = $dbConf['host'];
            $port = $dbConf['port'];
            $dbName = $dbConf['name'];
            $user = $dbConf['user'];
            $pass = $dbConf['pass'];

            try {
                $dsnInit = "mysql:host={$host};port={$port};charset=utf8mb4";
                $pdoInit = new PDO($dsnInit, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                self::runMigrations(self::$instance);

            } catch (PDOException $e) {
                try {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
                    self::$instance = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    self::runMigrations(self::$instance);
                } catch (PDOException $ex) {
                    die(json_encode([
                        'status' => 'error',
                        'message' => 'Database connection failed: ' . $ex->getMessage()
                    ]));
                }
            }
        }

        return self::$instance;
    }

    private static function runMigrations(PDO $pdo): void {
        // 1. Tabel Settings (Parameter, Bobot, Toggle)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `param_key` VARCHAR(64) UNIQUE NOT NULL,
                `param_name` VARCHAR(128) NOT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `weight` INT DEFAULT 20,
                `description` TEXT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 2. Tabel Custom Skuad Pilihan Manajer (Untuk Pre-Season / Custom Squad)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `user_squad` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `element_id` INT NOT NULL,
                `position` INT NOT NULL, -- 1-11 starter, 12-15 bench
                `is_captain` TINYINT(1) DEFAULT 0,
                `is_vice_captain` TINYINT(1) DEFAULT 0,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 3. Tabel YouTube Consensus & Video Insights
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 4. Tabel Execution Logs (Riwayat Eksekusi Manual & Auto Fallback)
        $pdo->exec("
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // 5. Tabel Cache API FPL
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `fpl_cache` (
                `cache_key` VARCHAR(128) PRIMARY KEY,
                `cache_data` LONGTEXT NOT NULL,
                `expires_at` INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Seed default parameters jika kosong
        $count = $pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
        if ($count == 0) {
            $defaultSettings = [
                ['xg_xa_form', 'Form & Underlying Stats (xG, xA, Form)', 1, 30, 'Bobot produktivitas gol, assist & konsistensi form pemain'],
                ['fdr_schedule', 'Jadwal & FDR (3-5 Gameweek ke depan)', 1, 25, 'Tingkat kemudahan jadwal fixture dan Home/Away advantage'],
                ['youtube_consensus', 'YouTube Analyst Consensus Score', 1, 20, 'Frekuensi rekomendasi dari video analis YouTube FPL'],
                ['effective_ownership', 'Proteksi Effective Ownership (EO)', 1, 15, 'Menjaga kepemilikan pemain kunci template agar ranking aman'],
                ['ict_index', 'ICT Index (Influence, Creativity, Threat)', 1, 10, 'Metrik resmi FPL untuk pengaruh pemain di lapangan'],
                ['injury_filter', 'Hard-Filter Cedera & Suspensi (<75%)', 1, 0, 'Selalu aktif: Memblokir transfer pemain yang diragukan tampil']
            ];

            $stmt = $pdo->prepare("
                INSERT INTO `settings` (`param_key`, `param_name`, `is_active`, `weight`, `description`)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($defaultSettings as $setting) {
                $stmt->execute($setting);
            }
        }
    }
}
