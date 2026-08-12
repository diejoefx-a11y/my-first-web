<?php
/**
 * File Konfigurasi Database MySQL (XAMPP)
 * Portal Otentikasi & Index Dinamis
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_portal');

$pdo = null;

try {
    // Mencoba koneksi ke database target db_portal
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // Apabila database db_portal belum dibuat, sistem secara otomatis mencoba membuat DB & mengimpor database.sql
    try {
        $dsnServer = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $pdoServer = new PDO($dsnServer, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        // Buat Database db_portal secara otomatis
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Hubungkan kembali ke DB yang baru dibuat
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // Auto-seed dari file database.sql jika file ada
        $sqlPath = __DIR__ . '/database.sql';
        if (file_exists($sqlPath)) {
            $sqlScript = file_get_contents($sqlPath);
            if ($sqlScript) {
                $pdo->exec($sqlScript);
            }
        }
    } catch (PDOException $ex) {
        // Tampilan kesalahan jika MySQL XAMPP mati/error
        die("
        <!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Koneksi MySQL Gagal</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1rem; }
                .card { background: rgba(30, 41, 59, 0.9); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 16px; padding: 2rem; max-width: 520px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
                .icon { font-size: 3rem; margin-bottom: 1rem; }
                h2 { color: #f87171; margin-bottom: 0.5rem; font-size: 1.5rem; }
                p { color: #94a3b8; line-height: 1.6; font-size: 0.95rem; }
                .code-box { background: #1e293b; padding: 0.75rem; border-radius: 8px; font-family: monospace; color: #38bdf8; text-align: left; font-size: 0.85rem; word-break: break-all; margin: 1rem 0; border: 1px solid rgba(255,255,255,0.05); }
                .tip { background: rgba(56, 189, 248, 0.1); border-left: 4px solid #38bdf8; padding: 0.75rem; border-radius: 4px; text-align: left; font-size: 0.875rem; color: #e2e8f0; }
            </style>
        </head>
        <body>
            <div class='card'>
                <div class='icon'>⚠️</div>
                <h2>Gagal Terhubung ke Database MySQL</h2>
                <p>Aplikasi tidak dapat terhubung ke server MySQL di <code>localhost</code>.</p>
                <div class='code-box'>" . htmlspecialchars($ex->getMessage()) . "</div>
                <div class='tip'>
                    <strong>💡 Langkah Solusi:</strong><br>
                    1. Pastikan module <strong>MySQL</strong> pada XAMPP Control Panel sudah dalam posisi <strong>Start</strong>.<br>
                    2. Atau buka phpMyAdmin di <code>http://localhost/phpmyadmin</code> lalu impor file <code>database.sql</code> secara manual.
                </div>
            </div>
        </body>
        </html>
        ");
    }
}
