<?php
require_once __DIR__ . '/../config/auth.php';
requireAuth();
$authUser = getAuthUser();

$current_dir = basename(dirname($_SERVER['PHP_SELF']));
$baseUrl = ($current_dir === 'atlet' || $current_dir === 'evaluasi' || $current_dir === 'iuran' || $current_dir === 'turnamen') ? '../' : './';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Database Atlet SSB Tamalanrea' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrl ?>assets/css/style.css">
</head>
<body>
    <?php include_once __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <h1 class="page-title"><?= $pageTitle ?? 'Dashboard System' ?></h1>
            <div class="topbar-actions">
                <?php
                $roleClass = 'badge-primary';
                if ($authUser['role'] === 'admin') $roleClass = 'badge-rose';
                elseif ($authUser['role'] === 'pelatih') $roleClass = 'badge-amber';
                elseif ($authUser['role'] === 'atlet') $roleClass = 'badge-emerald';
                ?>
                <span class="badge <?= $roleClass ?>">
                    <span style="width:6px; height:6px; background:currentColor; border-radius:50%; display:inline-block;"></span>
                    <?= strtoupper($authUser['role']) ?>
                </span>

                <div style="display:flex; align-items:center; gap:8px; font-size:0.88rem; color:var(--text-heading); font-weight:600;">
                    <span><?= htmlspecialchars($authUser['nama_lengkap']) ?></span>
                </div>

                <a href="<?= $baseUrl ?>logout.php" class="btn btn-secondary btn-sm" style="color:#f87171; border-color:rgba(244,63,94,0.3);" title="Keluar dari Sistem">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Logout
                </a>

                <button id="menuToggle" class="btn btn-secondary btn-sm" style="display:none;">Menu</button>
            </div>
        </header>

        <main class="content-area">

