<?php
/**
 * File Pembantu Sesi & Keamanan Portal (auth.php)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

/**
 * Memeriksa apakah pengguna sudah login
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Middleware untuk mewajibkan login sebelum mengakses halaman
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header("Location: login.php?msg=required");
        exit;
    }
}

/**
 * Mendapatkan data pengguna yang sedang login
 */
function currentUser(): ?array {
    global $pdo;
    if (!isLoggedIn()) {
        return null;
    }
    
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }

    try {
        $stmt = $pdo->prepare("SELECT id, username, email, fullname, role, bio, avatar, status, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['user_data'] = $user;
            return $user;
        }
    } catch (PDOException $e) {
        // Abaikan atau tangani error
    }
    return null;
}

/**
 * Mencatat aktivitas pengguna ke dalam database MySQL
 */
function logActivity(int $userId, string $activity): void {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO user_activities (user_id, activity, ip_address) VALUES (:user_id, :activity, :ip)");
        $stmt->execute([
            ':user_id' => $userId,
            ':activity' => $activity,
            ':ip' => $ip
        ]);
    } catch (PDOException $e) {
        // Log gagal tidak menghentikan eksekusi utama
    }
}

/**
 * Fungsi sanitasi string untuk mencegah XSS
 */
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Membuat Token CSRF
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Memverifikasi Token CSRF
 */
function verifyCsrfToken(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}
