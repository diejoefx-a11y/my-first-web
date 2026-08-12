<?php
/**
 * Halaman Registrasi Akun Baru (register.php)
 */

require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrf)) {
        $error = 'Token keamanan tidak valid. Silakan coba lagi.';
    } elseif (empty($fullname) || empty($username) || empty($email) || empty($password)) {
        $error = 'Semua bidang formulir wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format alamat email tidak valid.';
    } elseif (strlen($username) < 4 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username minimal 4 karakter dan hanya boleh berisi huruf, angka, dan underscore (_).';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal terdiri dari 6 karakter.';
    } elseif ($password !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        try {
            // Cek ketersediaan username atau email
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :u OR email = :e");
            $stmt->execute([':u' => $username, ':e' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Username atau Email sudah terdaftar dalam sistem.';
            } else {
                // Hashing Password secara aman dengan BCRYPT
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                
                // Avatar acak default dari Unsplash
                $defaultAvatars = [
                    'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&q=80',
                    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=150&q=80',
                    'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&w=150&q=80'
                ];
                $avatar = $defaultAvatars[array_rand($defaultAvatars)];

                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, fullname, role, avatar, status) 
                    VALUES (:u, :e, :p, :fn, 'user', :av, 'active')
                ");
                $insertStmt->execute([
                    ':u'  => $username,
                    ':e'  => $email,
                    ':p'  => $hashedPassword,
                    ':fn' => $fullname,
                    ':av' => $avatar
                ]);

                $newUserId = $pdo->lastInsertId();
                logActivity($newUserId, 'Mendaftar akun baru sebagai ' . $username);

                header("Location: login.php?msg=registered");
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Gagal mendaftarkan akun: ' . e($e->getMessage());
        }
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Baru - Portal MySQL</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 480px;">
            <div class="auth-header">
                <div class="logo">
                    <i class="fa-solid fa-user-plus" style="color: #c084fc;"></i>
                </div>
                <h1>Buat Akun Baru</h1>
                <p>Isi formulir di bawah ini untuk mendaftar ke portal</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= e($error); ?></span>
                </div>
            <?php endif; ?>

            <form action="register.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">

                <div class="form-group">
                    <label for="fullname" class="form-label">Nama Lengkap</label>
                    <div class="input-container">
                        <input type="text" id="fullname" name="fullname" class="form-control" placeholder="Contoh: Ahmad Subagyo" value="<?= e($_POST['fullname'] ?? ''); ?>" required>
                        <i class="fa-regular fa-id-card input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-container">
                        <input type="text" id="username" name="username" class="form-control" placeholder="Contoh: ahmad_subagyo" value="<?= e($_POST['username'] ?? ''); ?>" required>
                        <i class="fa-regular fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Alamat Email</label>
                    <div class="input-container">
                        <input type="email" id="email" name="email" class="form-control" placeholder="ahmad@example.com" value="<?= e($_POST['email'] ?? ''); ?>" required>
                        <i class="fa-regular fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
                        <i class="fa-solid fa-lock input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                    <div class="input-container">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Ulangi password di atas" required>
                        <i class="fa-solid fa-check-double input-icon"></i>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">
                    <i class="fa-solid fa-user-check"></i> Daftar Akun
                </button>
            </form>

            <div class="auth-footer">
                Sudah memiliki akun? <a href="login.php">Masuk di sini</a>
            </div>
        </div>
    </div>
</body>
</html>
