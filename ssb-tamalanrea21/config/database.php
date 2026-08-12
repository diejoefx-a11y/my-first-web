<?php
// ==============================================================================
// CONFIGURATION FOR MYSQL DATABASE CONNECTION (XAMPP LOCAL / ONLINE HOSTING)
// ==============================================================================
// Jika dipindahkan ke Hosting Online (cPanel), sesuaikan parameter di bawah ini
// sesuai dengan database yang dibuat di phpMyAdmin / MySQL Database Wizard hosting.

$host   = getenv('DB_HOST') ?: 'localhost';
$port   = getenv('DB_PORT') ?: 3306;
$user   = getenv('DB_USER') ?: 'root';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''; 
$dbname = getenv('DB_NAME') ?: 'db_ssb_tamalanrea';

try {
    // 1. Coba koneksi langsung ke database yang ditentukan
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // 2. Jika database belum ada (misal di XAMPP lokal), coba buat database otomatis
    try {
        $pdoInit = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e2) {
        die("<div style='padding:20px; font-family:sans-serif; color:red; background:#fee2e2; border-radius:8px;'>
            <h3>Koneksi Database Gagal!</h3>
            <p>Pastikan MySQL server sudah aktif dan pengaturan di <code>config/database.php</code> sudah sesuai dengan hosting Anda.</p>
            <p>Error: " . htmlspecialchars($e2->getMessage()) . "</p>
        </div>");
    }
}

// Auto-migrate new columns for Kartu Keluarga & Akta if not existing
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `no_kk` VARCHAR(30) DEFAULT NULL AFTER `nisn_nik`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `no_akta` VARCHAR(50) DEFAULT NULL AFTER `no_kk`"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `file_kk` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE `atlet` ADD COLUMN `file_akta` VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}

function getPdo() {
    global $pdo;
    return $pdo;
}
