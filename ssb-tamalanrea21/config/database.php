<?php
// Cek apakah script berjalan di lokal (Laptop A / Laptop B) atau di Server Online (Niagahoster)
$host_server = $_SERVER['HTTP_HOST'];

if ($host_server == 'localhost' || $host_server == '127.0.0.1' || strpos($host_server, '192.168.') !== false) {
    // -------------------------------------------------------------
    // KONFIGURASI LOKAL (Berlaku untuk Laptop A, Laptop B, XAMPP)
    // -------------------------------------------------------------
    $db_host = "localhost";
    $db_user = "root";          // Default XAMPP di semua laptop
    $db_pass = "";              // Default password XAMPP (kosong)
    $db_name = "my_first_web";   // Nama database lokal Anda
} else {
    // -------------------------------------------------------------
    // KONFIGURASI ONLINE (Server Niagahoster)
    // -------------------------------------------------------------
    $db_host = "localhost";
    $db_user = "u12345_ssb";     // Username DB Niagahoster
    $db_pass = "!Myh34rth97";    // Password DB Niagahoster
    $db_name = "u12345_ssb";        // Nama DB Niagahoster
}

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Koneksi Database Gagal: " . mysqli_connect_error());
}
?>