<?php
/**
 * Logout Handler (logout.php)
 */

require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    $user = currentUser();
    if ($user) {
        logActivity($user['id'], 'Keluar (logout) dari sistem');
    }
}

// Clear Session
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: login.php?msg=logout");
exit;
