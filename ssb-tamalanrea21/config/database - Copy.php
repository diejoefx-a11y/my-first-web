<?php
// ==============================================================================
// CONFIGURATION FOR MYSQL DATABASE CONNECTION (XAMPP LOCAL / ONLINE HOSTING)
// ==============================================================================
// Jika dipindahkan ke Hosting Online (cPanel), sesuaikan parameter di bawah ini
// sesuai dengan database yang dibuat di phpMyAdmin / MySQL Database Wizard hosting.

$host   = getenv('DB_HOST') ?: 'localhost';
$port   = getenv('DB_PORT') ?: 3306;
$user   = getenv('DB_USER') ?: 'u5518691_ssb21';
$pass   = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '!Myh34rth97'; 
$dbname = getenv('DB_NAME') ?: 'u5518691_dbssb21';

$pdo = null;

// Percobaan koneksi: Coba kredensial utama (Hosting), jika di localhost & gagal, fallback ke root (XAMPP Lokal)
$credentialsToTry = [
    ['user' => $user, 'pass' => $pass],
];

if (in_array($host, ['localhost', '127.0.0.1', '::1']) && $user !== 'root') {
    $credentialsToTry[] = ['user' => 'root', 'pass' => ''];
}

$lastException = null;
foreach ($credentialsToTry as $cred) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $cred['user'], $cred['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $lastException = null;
        break;
    } catch (PDOException $e) {
        $lastException = $e;
        // Jika database belum ada, coba buat otomatis
        try {
            $pdoInit = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $cred['user'], $cred['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $cred['user'], $cred['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $lastException = null;
            break;
        } catch (PDOException $e2) {
            $lastException = $e2;
        }
    }
}

if (!$pdo) {
    die("<div style='padding:20px; font-family:sans-serif; color:red; background:#fee2e2; border-radius:8px;'>
        <h3>Koneksi Database Gagal!</h3>
        <p>Pastikan MySQL server sudah aktif dan pengaturan di <code>config/database.php</code> sudah sesuai dengan hosting Anda.</p>
        <p>Error: " . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unknown Error') . "</p>
    </div>");
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
