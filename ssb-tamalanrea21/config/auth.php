<?php
// Session & Auth Management Helper for SSB Tamalanrea

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in.
 */
function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Get current logged in user details.
 */
function getAuthUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Get dynamic relative base URL for login redirect based on current script path.
 */
function getAuthBaseUrl() {
    $current_dir = basename(dirname($_SERVER['PHP_SELF']));
    $subdirs = ['atlet', 'evaluasi', 'iuran', 'turnamen'];
    return in_array($current_dir, $subdirs) ? '../' : './';
}

/**
 * Require user to be logged in, otherwise redirect to login.php.
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $baseUrl = getAuthBaseUrl();
        header("Location: " . $baseUrl . "login.php");
        exit;
    }
}

/**
 * Check if the logged-in user has one of the specified roles.
 * @param string|array $roles Single role string or array of roles (e.g. 'admin', ['admin', 'pelatih'])
 */
function hasRole($roles) {
    if (!isLoggedIn()) return false;
    $user = getAuthUser();
    $allowed = is_array($roles) ? $roles : [$roles];
    return in_array($user['role'], $allowed);
}

/**
 * Require user to have specific role(s). If unauthorized, display access denied page.
 */
function requireRole($roles) {
    requireAuth();
    if (!hasRole($roles)) {
        $baseUrl = getAuthBaseUrl();
        $user = getAuthUser();
        http_response_code(403);
        echo "<!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <title>Akses Ditolak - SSB Tamalanrea</title>
            <link rel='stylesheet' href='{$baseUrl}assets/css/style.css'>
        </head>
        <body style='align-items:center; justify-content:center; display:flex;'>
            <div class='card' style='max-width:480px; text-align:center; padding:2.5rem;'>
                <div style='font-size:3.5rem; margin-bottom:1rem;'>🔒</div>
                <h2 style='color:#f87171; margin-bottom:0.5rem;'>Akses Ditolak</h2>
                <p style='color:var(--text-muted); margin-bottom:1.5rem;'>
                    Akun Anda (<strong>" . htmlspecialchars($user['nama_lengkap']) . "</strong> - Role: <code>" . strtoupper($user['role']) . "</code>) tidak memiliki izin untuk mengakses halaman ini.
                </p>
                <a href='{$baseUrl}index.php' class='btn btn-primary'>Kembali ke Dashboard</a>
            </div>
        </body>
        </html>";
        exit;
    }
}

/**
 * Authenticate and store user info in session.
 */
function loginUser($userData) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user'] = $userData;
}

/**
 * Destroy user session and logout.
 */
function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    unset($_SESSION['user']);
    session_destroy();
    $baseUrl = getAuthBaseUrl();
    header("Location: " . $baseUrl . "login.php?logout=success");
    exit;
}

/**
 * Helper to compute uniform score color style from Red (0) to Metallic Emerald Green (100).
 * @param int|float $score Score value from 0 to 100
 * @return array Array containing hue, color, bg, border, badge, and barFill
 */
function getScoreStyle($score) {
    $val = max(0, min(100, (float)$score));
    // Hue ranges smoothly from 0 (Deep Red) to 135 (Metallic Emerald Green)
    $hue = round($val * 1.35);
    $bgOpacity = 0.25;
    $borderOpacity = 0.5;

    // Metallic glowing shadow for high performance scores (>= 80)
    $glow = $val >= 80 ? "box-shadow: 0 0 10px hsla($hue, 95%, 50%, 0.5);" : "";
    
    $colorCss = "hsl($hue, 95%, 65%)";
    $bgCss = "hsla($hue, 85%, 35%, $bgOpacity)";
    $borderCss = "hsla($hue, 90%, 50%, $borderOpacity)";

    return [
        'hue' => $hue,
        'color' => $colorCss,
        'bg' => $bgCss,
        'border' => $borderCss,
        'badge' => "background: $bgCss; color: $colorCss; border: 1px solid $borderCss; padding: 2px 7px; border-radius: 6px; font-weight: 700; font-size: 0.78rem; display: inline-block; text-align: center; $glow",
        'barFill' => "width: {$val}%; background: linear-gradient(90deg, hsl(0, 85%, 50%) 0%, hsl($hue, 95%, 48%) 100%); $glow"
    ];
}

