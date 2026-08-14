<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

// Determine base URL dynamically
$baseUrl = ($current_dir === 'atlet' || $current_dir === 'evaluasi' || $current_dir === 'iuran' || $current_dir === 'turnamen') ? '../' : './';

$user = getAuthUser();
$role = $user['role'] ?? 'admin';
$userInitials = strtoupper(substr($user['nama_lengkap'] ?? 'ADM', 0, 2));
?>
<aside class="sidebar">
    <div class="brand-header">
        <div style="display:flex; align-items:center; gap:12px;">
            <div class="brand-logo">SSB</div>
            <div class="brand-title">
                SSB TAMALANREA
                <span>PORTAL ATLET</span>
            </div>
        </div>
        <button id="sidebarClose" class="sidebar-close-btn" aria-label="Tutup Sidebar">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    </div>

    <ul class="nav-menu">
        <li>
            <a href="<?= $baseUrl ?>index.php" class="nav-link <?= ($current_page == 'index.php' && $current_dir != 'atlet' && $current_dir != 'evaluasi' && $current_dir != 'iuran' && $current_dir != 'turnamen') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                Dashboard
            </a>
        </li>

        <?php if ($role === 'admin' || $role === 'pelatih'): ?>
            <li>
                <a href="<?= $baseUrl ?>atlet/index.php" class="nav-link <?= ($current_dir == 'atlet' && $current_page != 'detail.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Data Atlet
                </a>
            </li>
            <li>
                <a href="<?= $baseUrl ?>evaluasi/index.php" class="nav-link <?= ($current_dir == 'evaluasi') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                    Raport & Evaluasi
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'admin'): ?>
            <li>
                <a href="<?= $baseUrl ?>iuran/index.php" class="nav-link <?= ($current_dir == 'iuran') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Iuran & SPP
                </a>
            </li>
        <?php endif; ?>

        <?php if ($role === 'atlet'): ?>
            <li>
                <a href="<?= $baseUrl ?>atlet/detail.php?id=<?= $user['atlet_id'] ?>" class="nav-link <?= ($current_dir == 'atlet' && $current_page == 'detail.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"></path></svg>
                    Raport Saya
                </a>
            </li>
            <li>
                <a href="<?= $baseUrl ?>iuran/index.php" class="nav-link <?= ($current_dir == 'iuran') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Status SPP Saya
                </a>
            </li>
            <li>
                <a href="<?= $baseUrl ?>idcard.php?id=<?= $user['atlet_id'] ?>" class="nav-link <?= ($current_page == 'idcard.php') ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><circle cx="9" cy="10" r="2"></circle><line x1="15" y1="8" x2="19" y2="8"></line><line x1="15" y1="12" x2="19" y2="12"></line></svg>
                    Kartu ID Atlet
                </a>
            </li>
        <?php endif; ?>

        <li>
            <a href="<?= $baseUrl ?>turnamen/index.php" class="nav-link <?= ($current_dir == 'turnamen' && $current_page == 'index.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
                Statistik Turnamen
            </a>
        </li>
        <!-- 
        <li>
            <a href="<?= $baseUrl ?>turnamen/pemain.php" class="nav-link <?= ($current_dir == 'turnamen' && $current_page == 'pemain.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><polyline points="16 11 18 13 22 9"></polyline></svg>
                Statistik Pemain
            </a>
        </li>
        -->
    </ul>

    <div class="user-footer">
        <div class="user-avatar"><?= $userInitials ?></div>
        <div class="user-info">
            <strong style="font-size:0.85rem; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($user['nama_lengkap'] ?? 'User') ?></strong>
            <span style="font-size:0.72rem; color:var(--primary); font-weight:700; text-transform:uppercase;"><?= htmlspecialchars($user['role'] ?? 'admin') ?></span>
        </div>
    </div>
</aside>

