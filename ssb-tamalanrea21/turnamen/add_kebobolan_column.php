<?php
/**
 * One-time migration: Add 'kebobolan' column to turnamen table
 * Run this file once via browser: http://localhost/ssb-tamalanrea21/turnamen/add_kebobolan_column.php
 */
require_once __DIR__ . '/../config/database.php';
$pdo = getPdo();

try {
    // Check if column already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM turnamen LIKE 'kebobolan'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE turnamen ADD COLUMN kebobolan INT DEFAULT 0 AFTER pencapaian");
        echo "<h2 style='color:green;'>✅ Kolom 'kebobolan' berhasil ditambahkan ke tabel turnamen!</h2>";
    } else {
        echo "<h2 style='color:orange;'>⚠️ Kolom 'kebobolan' sudah ada di tabel turnamen.</h2>";
    }
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ Error: " . $e->getMessage() . "</h2>";
}

echo "<br><a href='index.php'>← Kembali ke Daftar Turnamen</a>";
