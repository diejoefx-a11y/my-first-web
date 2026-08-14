<?php
require_once __DIR__ . '/../config/database.php';

// Unset all session variables
$_SESSION = [];

// Invalidate session cookie in browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Start new session for flash notification
session_start();
session_regenerate_id(true);

set_flash('info', 'Anda telah berhasil keluar dari sistem dengan aman.');
header("Location: login.php");
exit;
