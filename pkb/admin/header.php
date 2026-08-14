<?php
// admin/header.php
require_once __DIR__ . '/../config/database.php';
require_admin();

$currentPage = basename($_SERVER['PHP_SELF']);
$user = $_SESSION['admin_user'] ?? ['nama' => 'Administrator', 'username' => 'admin'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin Panel' ?> - Data Keluarga</title>
    
    <!-- Font Awesome & Leaflet CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <!-- Mobile Backdrop Overlay -->
    <div id="sidebar-backdrop" class="sidebar-backdrop"></div>

    <div class="admin-layout">
        <!-- Sidebar (Side navigation on Desktop, Off-Canvas Drawer on Mobile) -->
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-inner">
                    <div class="sidebar-brand-icon">🏡</div>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 1.1rem; font-weight: 800; color: #5b21b6; line-height: 1.2;">PKB ADMIN</span>
                        <span style="font-size: 0.7rem; font-weight: 700; color: #7c3aed; text-transform: uppercase;">Portal & Pemetaan</span>
                    </div>
                </div>
                <!-- Close Button on Mobile -->
                <button type="button" id="sidebar-close-btn" class="sidebar-close-btn" aria-label="Tutup Menu">✕</button>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                        <span>📊</span> Dashboard Utama
                    </a>
                </li>
                <li>
                    <a href="peta.php" class="<?= $currentPage === 'peta.php' ? 'active' : '' ?>">
                        <span>🗺️</span> Peta Master Sebaran
                    </a>
                </li>
                <li>
                    <a href="keluarga.php" class="<?= in_array($currentPage, ['keluarga.php', 'detail.php', 'edit.php']) ? 'active' : '' ?>">
                        <span>👨‍👩‍👧‍👦</span> Data Keluarga (KK)
                    </a>
                </li>
                <li>
                    <a href="kelompok.php" class="<?= $currentPage === 'kelompok.php' ? 'active' : '' ?>">
                        <span>🏷️</span> Data Kelompok (1-14)
                    </a>
                </li>
                <li>
                    <a href="export_excel.php" target="_blank">
                        <span>📥</span> Unduh Data (Excel)
                    </a>
                </li>
                <li style="margin-top: auto; border-top: 1px dashed var(--adm-border); padding-top: 0.75rem;">
                    <a href="../index.php" target="_blank">
                        <span>🌐</span> Portal Berita Warga
                    </a>
                </li>
                <li>
                    <a href="../jemaat/pasangtitik.php" target="_blank">
                        <span>📝</span> Form Pendaftaran KK
                    </a>
                </li>
                <li>
                    <a href="logout.php" style="color: #ef4444;" onclick="return confirm('Yakin ingin logout?')">
                        <span>🚪</span> Keluar Sistem
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <div style="font-size: 0.72rem; color: #6b7280; display: flex; align-items: center; gap: 4px;">
                    <i class="fa-solid fa-shield-check" style="color: #10b981;"></i> Sesi Terproteksi:
                </div>
                <div style="font-weight: 700; color: #5b21b6; font-size: 0.85rem;"><?= htmlspecialchars($user['nama']) ?></div>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="admin-main">
            <div class="admin-topbar">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <!-- Mobile Hamburger Toggle Button -->
                    <button type="button" id="sidebar-toggle-btn" class="sidebar-toggle-btn" aria-label="Buka Menu">
                        <span>☰</span> Menu
                    </button>
                    <div class="topbar-title">
                        <h2><?= $pageTitle ?? 'Dashboard Admin' ?></h2>
                    </div>
                </div>
                
                <div class="topbar-user" style="display: flex; align-items: center; gap: 0.75rem;">
                    <span style="font-size: 0.75rem; background: #ede9fe; color: #6d28d9; padding: 0.3rem 0.75rem; border-radius: 9999px; font-weight: 700; border: 1px solid #ddd6fe; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa-solid fa-lock" style="color: #10b981;"></i> Sesi Aktif
                    </span>
                    <span class="topbar-user-badge">
                        <span>👤</span> <?= htmlspecialchars($user['nama']) ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline btn-sm" style="padding: 0.35rem 0.85rem; border-radius: var(--adm-radius-full); font-size: 0.8rem; color: #ef4444; border-color: #fca5a5;">Keluar</a>
                </div>
            </div>

            <div class="admin-content">
                <?php $flash = get_flash(); if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] ?>">
                        <?= $flash['message'] ?>
                    </div>
                <?php endif; ?>
