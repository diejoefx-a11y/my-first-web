<?php
// config/database.php
// Strict Security & Session Settings

define('SESSION_TIMEOUT_SECONDS', 1800); // 30 Menit Timeout

// Configure secure session parameters before session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cookie_httponly', 1);
    
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie expires on browser close
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_data_keluarga');
define('DB_USER', 'root');
define('DB_PASS', '');

/**
 * PDO Database Connection
 */
function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    return $pdo;
}

/**
 * Apply Security Headers
 */
function apply_security_headers() {
    if (!headers_sent()) {
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}

/**
 * Generate Base URL for the Application
 */
function base_url($path = '') {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    
    if (substr($scriptDir, -6) === '/admin') {
        $scriptDir = substr($scriptDir, 0, -6);
    } elseif (substr($scriptDir, -7) === '/jemaat') {
        $scriptDir = substr($scriptDir, 0, -7);
    }
    
    $baseUrl = rtrim($protocol . $host . $scriptDir, '/');
    return $baseUrl . '/' . ltrim($path, '/');
}

/**
 * Sanitize Output String
 */
function clean($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * Flash Notification System
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * CSRF Protection
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

/**
 * Authentication & Session Validation
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function check_session_validity() {
    if (!is_admin_logged_in()) {
        return false;
    }
    
    // 1. Inactivity Timeout Check (30 Minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_SECONDS)) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        set_flash('warning', 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 30 menit. Silakan login kembali.');
        return false;
    }
    
    // 2. User-Agent Fingerprint Check (Anti Session Hijacking)
    $currentAgentHash = md5($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN');
    if (isset($_SESSION['user_agent_hash']) && $_SESSION['user_agent_hash'] !== $currentAgentHash) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        set_flash('danger', 'Terdeteksi perubahan browser / perangkat. Sesi ditutup demi keamanan.');
        return false;
    }
    
    // Update last active timestamp
    $_SESSION['last_activity'] = time();
    return true;
}

function require_admin() {
    apply_security_headers();
    if (!check_session_validity()) {
        if (!isset($_SESSION['flash'])) {
            set_flash('danger', 'Silakan login terlebih dahulu untuk mengakses panel admin.');
        }
        header('Location: ' . base_url('admin/login.php'));
        exit;
    }
}
