<?php
require_once __DIR__ . '/../config/database.php';
apply_security_headers();

// Redirect if already logged in
if (is_admin_logged_in() && check_session_validity()) {
    header("Location: index.php");
    exit;
}

$error = null;
$maxAttempts = 5;
$lockoutDuration = 300; // 5 Menit (dalam detik)

// Initialize attempt tracker in session
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['lockout_time'])) {
    $_SESSION['lockout_time'] = 0;
}

// Check if currently locked out
$currentTime = time();
$isLockedOut = false;
$remainingLockout = 0;

if ($_SESSION['lockout_time'] > $currentTime) {
    $isLockedOut = true;
    $remainingLockout = $_SESSION['lockout_time'] - $currentTime;
} else if ($_SESSION['lockout_time'] > 0 && $_SESSION['lockout_time'] <= $currentTime) {
    // Lockout expired, reset attempts
    $_SESSION['login_attempts'] = 0;
    $_SESSION['lockout_time'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isLockedOut) {
        $minutes = ceil($remainingLockout / 60);
        $error = "Terlalu banyak percobaan login gagal. Akun dikunci sementara. Silakan coba lagi dalam <strong>$minutes menit</strong> ($remainingLockout detik).";
    } else {
        // 1. Verify CSRF Token
        $token = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($token)) {
            $error = "Token keamanan tidak valid. Silakan muat ulang halaman.";
        } else {
            $username = clean($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = "Username dan Password wajib diisi.";
            } else {
                $db = get_db();
                $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Reset login attempts on success
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['lockout_time'] = 0;

                    // Regenerate Session ID to defeat Session Fixation
                    session_regenerate_id(true);

                    // Set Secure Session Variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nama' => $user['nama'],
                        'role' => $user['role'],
                        'group_id' => $user['group_id'] ?? null
                    ];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['user_agent_hash'] = md5($_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN');
                    $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                    set_flash('success', 'Selamat datang kembali, ' . htmlspecialchars($user['nama']) . '!');
                    header("Location: index.php");
                    exit;
                } else {
                    $_SESSION['login_attempts']++;
                    $attemptsLeft = $maxAttempts - $_SESSION['login_attempts'];

                    if ($_SESSION['login_attempts'] >= $maxAttempts) {
                        $_SESSION['lockout_time'] = time() + $lockoutDuration;
                        $error = "Terlalu banyak percobaan gagal (5x). Keamanan aktif: login dikunci selama <strong>5 menit</strong>.";
                        $isLockedOut = true;
                        $remainingLockout = $lockoutDuration;
                    } else {
                        $error = "Username atau password salah. Sisa percobaan: <strong>$attemptsLeft kali</strong> sebelum akun dikunci sementara.";
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator Terverifikasi - PKB Jemaat</title>
    
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --adm-purple: #7c3aed;
            --adm-purple-dark: #5b21b6;
            --adm-bg: #0b091a;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--adm-bg);
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(124, 58, 237, 0.22) 0%, transparent 45%),
                radial-gradient(circle at 85% 80%, rgba(16, 185, 129, 0.15) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(15, 23, 42, 0.95) 0%, #06050e 100%);
            margin: 0;
            padding: 1.5rem;
            color: #f8fafc;
        }

        .login-box {
            background: rgba(18, 15, 38, 0.92);
            backdrop-filter: blur(20px);
            width: 100%;
            max-width: 440px;
            border-radius: 24px;
            padding: 2.75rem 2.25rem;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6), 0 0 35px rgba(124, 58, 237, 0.18);
            border: 1.5px solid rgba(139, 92, 246, 0.35);
            position: relative;
            overflow: hidden;
        }

        .login-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #7c3aed, #10b981, #38bdf8);
        }

        .brand-icon-wrapper {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #7c3aed, #4c1d95);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #fff;
            margin: 0 auto 1.25rem auto;
            box-shadow: 0 0 25px rgba(124, 58, 237, 0.5);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
        }

        .form-label {
            display: block;
            font-size: 0.82rem;
            font-weight: 700;
            color: #c4b5fd;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.4rem;
        }

        .form-input-group {
            position: relative;
            margin-bottom: 1.25rem;
        }

        .form-input {
            width: 100%;
            background: rgba(10, 8, 25, 0.85);
            border: 1.5px solid rgba(139, 92, 246, 0.35);
            color: #ffffff;
            padding: 0.85rem 1rem 0.85rem 2.6rem;
            border-radius: 12px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.25s;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-input:focus {
            border-color: #a78bfa;
            box-shadow: 0 0 15px rgba(124, 58, 237, 0.4);
            background: rgba(15, 12, 35, 0.95);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a78bfa;
            font-size: 0.95rem;
        }

        .toggle-password-btn {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 0.95rem;
            padding: 0;
        }
        .toggle-password-btn:hover {
            color: #c4b5fd;
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            color: #ffffff;
            border: none;
            padding: 0.95rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(124, 58, 237, 0.4);
            transition: all 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 0.5rem;
        }
        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(124, 58, 237, 0.6);
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        .btn-submit:disabled {
            background: #475569;
            cursor: not-allowed;
            opacity: 0.7;
            box-shadow: none;
        }

        .security-badge-footer {
            margin-top: 1.75rem;
            padding-top: 1.25rem;
            border-top: 1px dashed rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <div class="login-box">
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div class="brand-icon-wrapper">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.6rem; font-weight: 800; color: #ffffff; margin: 0 0 0.35rem 0;">
                Portal Administrator
            </h2>
            <p style="color: #c4b5fd; font-size: 0.85rem; margin: 0; font-weight: 600;">
                Sistem Pendataan & Pemetaan Jemaat Kristiani (PKB)
            </p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.18); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.85rem; margin-bottom: 1.25rem; line-height: 1.4;">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right: 5px;"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <?php $flash = get_flash(); if ($flash): ?>
            <div style="background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(16, 185, 129, 0.4); color: #86efac; padding: 0.85rem 1rem; border-radius: 12px; font-size: 0.85rem; margin-bottom: 1.25rem; line-height: 1.4;">
                <i class="fa-solid fa-circle-check" style="margin-right: 5px;"></i> <?= $flash['message'] ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" autocomplete="off">
            <?= csrf_field() ?>

            <div>
                <label for="username" class="form-label">Username Administrator</label>
                <div class="form-input-group">
                    <i class="fa-solid fa-user input-icon"></i>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input" 
                           placeholder="Masukkan username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                           <?= $isLockedOut ? 'disabled' : 'required autofocus' ?>
                    >
                </div>
            </div>

            <div>
                <label for="password" class="form-label">Password Keamanan</label>
                <div class="form-input-group">
                    <i class="fa-solid fa-lock input-icon"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input" 
                           placeholder="Masukkan password" 
                           style="padding-right: 2.75rem;"
                           <?= $isLockedOut ? 'disabled' : 'required' ?>
                    >
                    <button type="button" class="toggle-password-btn" onclick="togglePasswordVisibility()" aria-label="Lihat Password">
                        <i class="fa-solid fa-eye" id="toggleEyeIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit" <?= $isLockedOut ? 'disabled' : '' ?>>
                <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Dashboard
            </button>
        </form>

        <div class="security-badge-footer">
            <span style="display: flex; align-items: center; gap: 5px;">
                <i class="fa-solid fa-lock" style="color: #10b981;"></i> Sesi Terenkripsi
            </span>
            <a href="../index.php" style="color: #a78bfa; text-decoration: none; font-weight: 700;">
                &larr; Portal Warga
            </a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passInput = document.getElementById('password');
            const eyeIcon = document.getElementById('toggleEyeIcon');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
