<?php
// config/database.php
// Konfigurasi Database & Helper untuk Sistem Timer Catur Multi-Meja

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_catur');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * Mendapatkan koneksi PDO Database (Auto-create database & table jika belum ada)
 */
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            // Coba koneksi langsung ke database yang sudah ada
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            create_tables_if_not_exists($pdo);
        } catch (PDOException $e) {
            // Jika database belum ada (biasanya di localhost baru), coba buatkan otomatis
            try {
                $pdoInit = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
                $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                create_tables_if_not_exists($pdo);
            } catch (PDOException $ex) {
                die("Koneksi Database Gagal: " . $ex->getMessage());
            }
        }
    }
    return $pdo;
}

/**
 * Inisialisasi Skema Tabel
 */
function create_tables_if_not_exists($pdo) {
    $sql = "CREATE TABLE IF NOT EXISTS `meja_catur` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nomor_meja` VARCHAR(50) NOT NULL,
        `kategori_babak` VARCHAR(100) DEFAULT 'Babak 1',
        `nama_putih` VARCHAR(100) NOT NULL DEFAULT 'Pemain Putih',
        `nama_hitam` VARCHAR(100) NOT NULL DEFAULT 'Pemain Hitam',
        `time_base_minutes` INT NOT NULL DEFAULT 5,
        `time_increment_seconds` INT NOT NULL DEFAULT 0,
        `time_mode` ENUM('fischer', 'delay', 'sudden_death') NOT NULL DEFAULT 'fischer',
        `sisa_waktu_putih_ms` BIGINT NOT NULL DEFAULT 300000,
        `sisa_waktu_hitam_ms` BIGINT NOT NULL DEFAULT 300000,
        `status` ENUM('standby', 'running', 'paused', 'finished') NOT NULL DEFAULT 'standby',
        `giliran` ENUM('putih', 'hitam') NOT NULL DEFAULT 'putih',
        `jumlah_langkah` INT NOT NULL DEFAULT 0,
        `pemenang` ENUM('belum', 'putih', 'hitam', 'remis') NOT NULL DEFAULT 'belum',
        `keterangan_selesai` VARCHAR(255) NULL,
        `last_sync_timestamp` BIGINT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql);
}

/**
 * Mendapatkan Base URL yang ramah jaringan lokal (untuk QR Code di HP)
 */
function get_server_ip() {
    // Coba ambil IP lokal dari host
    $localIp = gethostbyname(gethostname());
    if ($localIp && filter_var($localIp, FILTER_VALIDATE_IP) && $localIp !== '127.0.0.1') {
        return $localIp;
    }
    
    // Fallback dari SERVER_ADDR jika ada
    if (!empty($_SERVER['SERVER_ADDR']) && $_SERVER['SERVER_ADDR'] !== '::1' && $_SERVER['SERVER_ADDR'] !== '127.0.0.1') {
        return $_SERVER['SERVER_ADDR'];
    }
    
    // Fallback ke HTTP_HOST saat ini
    return $_SERVER['HTTP_HOST'] ?? 'localhost';
}

function get_base_url($useIpForMobile = false) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Jika untuk mobile QR code dan host saat ini adalah localhost/127.0.0.1, gunakan IP lokal agar bisa dibuka di HP lain
    if ($useIpForMobile && (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || php_sapi_name() === 'cli')) {
        $ip = get_server_ip();
        if ($ip !== 'localhost' && $ip !== '127.0.0.1') {
            $host = $ip;
            if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != '80' && $_SERVER['SERVER_PORT'] != '443' && strpos($host, ':') === false) {
                $host .= ':' . $_SERVER['SERVER_PORT'];
            }
        }
    }
    
    // Deteksi Web Path URL (default '/olahraga/catur')
    $webPath = '/olahraga/catur';
    if (isset($_SERVER['SCRIPT_NAME']) && php_sapi_name() !== 'cli') {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if (substr($scriptDir, -4) === '/api') {
            $scriptDir = substr($scriptDir, 0, -4);
        } elseif (substr($scriptDir, -7) === '/config') {
            $scriptDir = substr($scriptDir, 0, -7);
        }
        $webPath = '/' . trim($scriptDir, '/');
    }
    
    return rtrim($protocol . $host . $webPath, '/');
}

/**
 * Sanitasi string input
 */
function clean_input($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Helper JSON Response
 */
function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
