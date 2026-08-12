<?php
/**
 * Halaman Login Portal (login.php)
 * Terhubung ke Database MySQL (XAMPP)
 */

require_once __DIR__ . '/auth.php';

// Jika pengguna sudah login, langsung arahkan ke index.php
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

// Notifikasi dari URL query parameter
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'required') {
        $error = 'Silakan login terlebih dahulu untuk mengakses portal.';
    } elseif ($_GET['msg'] === 'registered') {
        $success = 'Pendaftaran akun berhasil! Silakan login dengan akun Anda.';
    } elseif ($_GET['msg'] === 'logout') {
        $success = 'Anda telah berhasil keluar dari sistem.';
    }
}

// Proses Form Submission Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $csrfToken  = $_POST['csrf_token'] ?? '';

    // Validasi CSRF Token
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Token sesi tidak valid. Silakan refresh halaman dan coba lagi.';
    } elseif (empty($identifier) || empty($password)) {
        $error = 'Username/Email dan Password wajib diisi.';
    } else {
        try {
            // Cari pengguna berdasarkan username atau email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = :identifier OR email = :identifier) LIMIT 1");
            $stmt->execute([':identifier' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] !== 'active') {
                    $error = 'Akun Anda sedang dinonaktifkan. Hubungi administrator.';
                } else {
                    // Berhasil Login: Set Session
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_data'] = $user;

                    // Log Aktivitas ke Database MySQL
                    logActivity($user['id'], 'Berhasil login ke dalam portal');

                    // Redireksi ke halaman utama dinamis
                    header("Location: index.php");
                    exit;
                }
            } else {
                $error = 'Username/Email atau Password yang Anda masukkan salah.';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . e($e->getMessage());
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
    <title>Login - Portal Portal MySQL Dinamis</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- FontAwesome untuk Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="bg-glow-1"></div>
    <div class="bg-glow-2"></div>

    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo">
                    <i class="fa-solid fa-shield-halved" style="color: #38bdf8;"></i>
                </div>
                <h1>Selamat Datang</h1>
                <p>Silakan masuk menggunakan akun portal Anda</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= e($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?= e($success); ?></span>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken); ?>">

                <div class="form-group">
                    <label for="identifier" class="form-label">Username atau Email</label>
                    <div class="input-container">
                        <input type="text" id="identifier" name="identifier" class="form-control" placeholder="Contoh: admin atau admin@example.com" value="<?= e($_POST['identifier'] ?? ''); ?>" required autofocus>
                        <i class="fa-regular fa-user input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-container">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Masukkan password Anda" required>
                        <i class="fa-solid fa-lock input-icon"></i>
                        <button type="button" class="toggle-password" id="togglePasswordBtn" title="Tampilkan/Sembunyikan Password">
                            <i class="fa-regular fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-actions">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Ingat saya
                    </label>
                    <a href="#" onclick="alert('Gunakan akun admin default:\nUsername: admin\nPassword: admin123'); return false;">Lupa Password?</a>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket"></i> Masuk Sekarang
                </button>
            </form>

            <div style="margin-top: 1.25rem; padding: 0.75rem; background: rgba(56, 189, 248, 0.08); border-radius: 10px; font-size: 0.8rem; color: #94a3b8;">
                <div style="font-weight: 600; color: #38bdf8; margin-bottom: 4px;"><i class="fa-solid fa-key"></i> Akun Demo bawaan database:</div>
                • Admin: <code>admin</code> / <code>admin123</code><br>
                • User: <code>user</code> / <code>user123</code>
            </div>

            <div class="auth-footer">
                Belum memiliki akun? <a href="register.php">Daftar Akun Baru</a>
            </div>
        </div>
    </div>

    <script>
        // Feature Show/Hide Password
        const togglePasswordBtn = document.getElementById('togglePasswordBtn');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

        togglePasswordBtn.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'text') {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
