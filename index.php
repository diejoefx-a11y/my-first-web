<?php
/**
 * Halaman Utama Dinamis (index.php)
 * Terkoneksi Database MySQL & Wajib Login
 */

require_once __DIR__ . '/auth.php';

// Memastikan Pengguna Sudah Login
requireLogin();

// Ambil data user yang sedang login
$user = currentUser();

// Parameter Halaman Dinamis (Default: dashboard)
$page = $_GET['page'] ?? 'dashboard';
$validPages = ['dashboard', 'contents', 'profile', 'activities'];
if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

$notice = '';
$noticeType = 'success';

// Handle Tambah Konten Dinamis Baru via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_content') {
    $title    = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $excerpt  = trim($_POST['excerpt'] ?? '');
    $body     = trim($_POST['body'] ?? '');
    $badge    = trim($_POST['badge_color'] ?? 'cyan');
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $notice = 'Token CSRF tidak valid.';
        $noticeType = 'danger';
    } elseif (empty($title) || empty($excerpt) || empty($body)) {
        $notice = 'Judul, ringkasan, dan isi konten tidak boleh kosong.';
        $noticeType = 'danger';
    } else {
        try {
            // Generate slug otomatis
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) . '-' . time();
            
            $stmt = $pdo->prepare("
                INSERT INTO contents (title, slug, category, badge_color, excerpt, body, author_id)
                VALUES (:t, :s, :c, :bc, :e, :b, :aid)
            ");
            $stmt->execute([
                ':t'   => $title,
                ':s'   => $slug,
                ':c'   => $category,
                ':bc'  => $badge,
                ':e'   => $excerpt,
                ':b'   => $body,
                ':aid' => $user['id']
            ]);

            logActivity($user['id'], 'Membuat konten dinamis baru: ' . $title);
            $notice = 'Berhasil menambah konten dinamis baru ke dalam MySQL!';
            $noticeType = 'success';
        } catch (PDOException $e) {
            $notice = 'Gagal menyimpan konten: ' . e($e->getMessage());
            $noticeType = 'danger';
        }
    }
}

// Query Statistika Dinamis dari Database MySQL
try {
    $totalUsers     = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalContents  = $pdo->query("SELECT COUNT(*) FROM contents")->fetchColumn();
    $totalActivities= $pdo->query("SELECT COUNT(*) FROM user_activities")->fetchColumn();
} catch (PDOException $e) {
    $totalUsers = $totalContents = $totalActivities = 0;
}

// Query Data Dinamis Konten dari MySQL
try {
    $contentsStmt = $pdo->query("
        SELECT c.*, u.fullname as author_name, u.avatar as author_avatar
        FROM contents c
        JOIN users u ON c.author_id = u.id
        ORDER BY c.created_at DESC
    ");
    $contents = $contentsStmt->fetchAll();
} catch (PDOException $e) {
    $contents = [];
}

// Query Log Aktivitas Terakhir
try {
    $activitiesStmt = $pdo->query("
        SELECT a.*, u.fullname, u.username
        FROM user_activities a
        JOIN users u ON a.user_id = u.id
        ORDER BY a.created_at DESC LIMIT 10
    ");
    $activities = $activitiesStmt->fetchAll();
} catch (PDOException $e) {
    $activities = [];
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Utama Dinamis - MySQL PHP</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <!-- Top Navigation Bar Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">
                <div class="nav-brand-icon">
                    <i class="fa-solid fa-cube"></i>
                </div>
                <span>Portal Dynamic</span>
            </a>

            <div class="user-profile-menu">
                <img src="<?= e($user['avatar']); ?>" alt="Avatar" class="user-avatar">
                <div class="user-details">
                    <span class="user-name"><?= e($user['fullname']); ?></span>
                    <span class="user-role"><i class="fa-solid fa-circle" style="color: #10b981; font-size: 8px;"></i> <?= e($user['role']); ?></span>
                </div>
                <a href="logout.php" class="btn btn-danger" style="padding: 0.4rem 0.9rem; font-size: 0.85rem;" title="Keluar">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="dashboard-container">

        <!-- Banner Selamat Datang Dinamis -->
        <section class="welcome-banner">
            <div class="welcome-text">
                <h2>Halo, <?= e($user['fullname']); ?>! 👋</h2>
                <p>Selamat datang di portal dinamis PHP & MySQL. Anda masuk sebagai akun <strong><?= e($user['username']); ?></strong> (<?= e($user['role']); ?>).</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('modalNewContent').style.display='flex'" style="width: auto; height: fit-content;">
                <i class="fa-solid fa-plus"></i> Tambah Konten Dinamis
            </button>
        </section>

        <?php if (!empty($notice)): ?>
            <div class="alert alert-<?= $noticeType; ?>">
                <i class="fa-solid fa-info-circle"></i>
                <span><?= e($notice); ?></span>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Dinamis dari MySQL -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h4>Total Pengguna</h4>
                    <div class="value"><?= number_format($totalUsers); ?></div>
                </div>
                <div class="stat-icon cyan">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h4>Konten Dinamis</h4>
                    <div class="value"><?= number_format($totalContents); ?></div>
                </div>
                <div class="stat-icon purple">
                    <i class="fa-solid fa-newspaper"></i>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-info">
                    <h4>Aktivitas Log</h4>
                    <div class="value"><?= number_format($totalActivities); ?></div>
                </div>
                <div class="stat-icon emerald">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
            </div>
        </section>

        <!-- Navigation Tab Dinamis -->
        <nav class="tab-navigation">
            <a href="index.php?page=dashboard" class="tab-item <?= $page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fa-solid fa-table-columns"></i> Dashboard
            </a>
            <a href="index.php?page=contents" class="tab-item <?= $page === 'contents' ? 'active' : ''; ?>">
                <i class="fa-solid fa-folder-open"></i> Semua Konten (<?= count($contents); ?>)
            </a>
            <a href="index.php?page=activities" class="tab-item <?= $page === 'activities' ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-check"></i> Audit Log MySQL
            </a>
            <a href="index.php?page=profile" class="tab-item <?= $page === 'profile' ? 'active' : ''; ?>">
                <i class="fa-regular fa-id-card"></i> Profil Saya
            </a>
        </nav>

        <!-- Dynamic Page View Sections -->
        <?php if ($page === 'dashboard' || $page === 'contents'): ?>
            <div class="content-grid">
                <!-- Panel Utama Konten Dinamis -->
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="fa-solid fa-database" style="color: #38bdf8;"></i> Konten Real-time MySQL
                        </h3>
                        <span style="font-size: 0.85rem; color: #94a3b8;">Diambil langsung dari `contents`</span>
                    </div>

                    <?php if (empty($contents)): ?>
                        <p style="color: #94a3b8; text-align: center; padding: 2rem 0;">Belum ada konten dinamis. Klik tombol "+ Tambah Konten Dinamis" untuk menambahkan!</p>
                    <?php else: ?>
                        <?php foreach ($contents as $item): ?>
                            <article class="article-card">
                                <div class="article-header">
                                    <span class="badge badge-<?= e($item['badge_color']); ?>"><?= e($item['category']); ?></span>
                                    <span style="font-size: 0.8rem; color: #64748b;">• <?= date('d M Y, H:i', strtotime($item['created_at'])); ?></span>
                                </div>
                                <h4 class="article-title"><?= e($item['title']); ?></h4>
                                <p class="article-excerpt"><?= e($item['excerpt']); ?></p>
                                <div style="font-size: 0.85rem; color: #cbd5e1; background: rgba(0,0,0,0.2); padding: 0.75rem; border-radius: 8px; margin-bottom: 0.75rem; border-left: 3px solid #0284c7;">
                                    <?= nl2br(e($item['body'])); ?>
                                </div>
                                <div class="article-meta">
                                    <span style="display: flex; align-items: center; gap: 6px;">
                                        <img src="<?= e($item['author_avatar']); ?>" style="width: 22px; height: 22px; border-radius: 50%;" alt="Author">
                                        <?= e($item['author_name']); ?>
                                    </span>
                                    <span><i class="fa-regular fa-eye"></i> <?= $item['views']; ?>x dilihat</span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Panel Log Aktivitas Samping -->
                <div class="panel">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="fa-solid fa-bolt" style="color: #c084fc;"></i> Log Aktivitas Terakhir
                        </h3>
                    </div>

                    <ul class="activity-list">
                        <?php foreach ($activities as $act): ?>
                            <li class="activity-item">
                                <div class="activity-dot"></div>
                                <div>
                                    <div class="activity-text">
                                        <strong><?= e($act['fullname']); ?></strong> <?= e($act['activity']); ?>
                                    </div>
                                    <div class="activity-time">
                                        <i class="fa-regular fa-clock"></i> <?= date('d M H:i', strtotime($act['created_at'])); ?> • IP: <?= e($act['ip_address']); ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

        <?php elseif ($page === 'activities'): ?>
            <div class="panel">
                <div class="panel-header">
                    <h3 class="panel-title"><i class="fa-solid fa-list-check" style="color: #34d399;"></i> Riwayat Aktivitas Lengkap MySQL</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                                <th style="padding: 10px;">ID</th>
                                <th style="padding: 10px;">Pengguna</th>
                                <th style="padding: 10px;">Aktivitas</th>
                                <th style="padding: 10px;">IP Address</th>
                                <th style="padding: 10px;">Waktu Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $act): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 10px; color: #64748b;">#<?= $act['id']; ?></td>
                                    <td style="padding: 10px;"><strong><?= e($act['fullname']); ?></strong> (@<?= e($act['username']); ?>)</td>
                                    <td style="padding: 10px; color: #e2e8f0;"><?= e($act['activity']); ?></td>
                                    <td style="padding: 10px; font-family: monospace; color: #38bdf8;"><?= e($act['ip_address']); ?></td>
                                    <td style="padding: 10px; color: #94a3b8;"><?= date('d F Y - H:i:s', strtotime($act['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($page === 'profile'): ?>
            <div class="panel" style="max-width: 600px; margin: 0 auto;">
                <div class="panel-header">
                    <h3 class="panel-title"><i class="fa-regular fa-address-card" style="color: #38bdf8;"></i> Informasi Profil Saya</h3>
                </div>
                <div style="text-align: center; margin-bottom: 2rem;">
                    <img src="<?= e($user['avatar']); ?>" style="width: 90px; height: 90px; border-radius: 50%; border: 3px solid #38bdf8; margin-bottom: 1rem;" alt="Profile Picture">
                    <h2><?= e($user['fullname']); ?></h2>
                    <p style="color: #94a3b8; font-size: 0.9rem;">@<?= e($user['username']); ?> • <span class="badge badge-cyan"><?= e($user['role']); ?></span></p>
                </div>

                <div style="background: rgba(15,23,42,0.5); padding: 1.25rem; border-radius: 12px; border: 1px solid var(--border-color);">
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: #94a3b8;">Email:</span>
                        <strong><?= e($user['email']); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <span style="color: #94a3b8;">Status Akun:</span>
                        <strong style="color: #10b981;"><i class="fa-solid fa-check-circle"></i> <?= ucfirst(e($user['status'])); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: 0.75rem 0;">
                        <span style="color: #94a3b8;">Bergabung Sejak:</span>
                        <strong><?= date('d F Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </main>

    <!-- Modal Form Tambah Konten Dinamis -->
    <div id="modalNewContent" style="display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1rem;">
        <div class="auth-card" style="max-width: 540px;">
            <div class="panel-header" style="margin-bottom: 1rem;">
                <h3 class="panel-title"><i class="fa-solid fa-pen-to-square"></i> Tambah Konten Dinamis</h3>
                <button type="button" onclick="document.getElementById('modalNewContent').style.display='none'" style="background: none; border: none; color: white; cursor: pointer; font-size: 1.2rem;">&times;</button>
            </div>

            <form action="index.php" method="POST">
                <input type="hidden" name="action" value="create_content">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">

                <div class="form-group">
                    <label class="form-label">Judul Konten</label>
                    <input type="text" name="title" class="form-control" placeholder="Contoh: Fitur Baru Sistem" required style="padding-left: 1rem;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <input type="text" name="category" class="form-control" placeholder="Teknologi, News, dll" required style="padding-left: 1rem;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Warna Badge</label>
                        <select name="badge_color" class="form-control" style="padding-left: 1rem;">
                            <option value="cyan">Cyan (Biru Muda)</option>
                            <option value="emerald">Emerald (Hijau)</option>
                            <option value="purple">Purple (Ungu)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Ringkasan Singkat (Excerpt)</label>
                    <input type="text" name="excerpt" class="form-control" placeholder="Ringkasan 1 kalimat" required style="padding-left: 1rem;">
                </div>

                <div class="form-group">
                    <label class="form-label">Isi Lengkap Konten</label>
                    <textarea name="body" class="form-control" rows="4" placeholder="Tuliskan isi konten secara lengkap..." required style="padding-left: 1rem;"></textarea>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="document.getElementById('modalNewContent').style.display='none'" class="btn btn-secondary">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan ke Database</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
