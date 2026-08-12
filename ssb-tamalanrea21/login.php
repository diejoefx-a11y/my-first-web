<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
$success = '';

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = "Anda telah berhasil logout dari sistem.";
}

// Process Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = trim($_POST['role'] ?? 'admin');
    $identifier = trim($_POST['identifier'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($identifier) || empty($password)) {
        $error = "Silakan isi NIK/Username dan Password Anda!";
    } else {
        $pdo = getPdo();

        if ($role === 'atlet') {
            // Login for Atlet / Siswa via NISN/NIK
            $stmt = $pdo->prepare("SELECT * FROM atlet WHERE nisn_nik = :identifier OR id = :id_alt LIMIT 1");
            $stmt->execute([
                ':identifier' => $identifier,
                ':id_alt' => is_numeric($identifier) ? (int)$identifier : 0
            ]);
            $atlet = $stmt->fetch();

            if ($atlet) {
                // Verify password (or default password fallback)
                $isValidPass = false;

                if (!empty($atlet['password']) && password_verify($password, $atlet['password'])) {
                    $isValidPass = true;
                } elseif (empty($atlet['password']) && ($password === 'atlet123' || $password === '123456')) {
                    // Update default password hash if not set yet
                    $newHash = password_hash('atlet123', PASSWORD_DEFAULT);
                    $upStmt = $pdo->prepare("UPDATE atlet SET password = :p WHERE id = :id");
                    $upStmt->execute([':p' => $newHash, ':id' => $atlet['id']]);
                    $isValidPass = true;
                }

                if ($isValidPass) {
                    if ($atlet['status_keanggotaan'] !== 'Aktif') {
                        $error = "Akun atlet Anda berstatus " . htmlspecialchars($atlet['status_keanggotaan']) . ". Silakan hubungi admin SSB.";
                    } else {
                        loginUser([
                            'id' => 'atlet_' . $atlet['id'],
                            'atlet_id' => $atlet['id'],
                            'username' => $atlet['nisn_nik'],
                            'nama_lengkap' => $atlet['nama_lengkap'],
                            'role' => 'atlet',
                            'kelompok_usia' => $atlet['kelompok_usia'],
                            'posisi_utama' => $atlet['posisi_utama'],
                            'foto_profil' => $atlet['foto_profil'] ?? 'default_avatar.png'
                        ]);
                        header("Location: index.php");
                        exit;
                    }
                } else {
                    $error = "Password Atlet / NIK yang dimasukkan salah!";
                }
            } else {
                $error = "Data Atlet dengan NIK/NISN tersebut tidak ditemukan!";
            }
        } else {
            // Login for Admin or Pelatih via users table
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Check if user role matches selected role
                if ($user['role'] !== $role) {
                    $error = "Akun ini terdaftar sebagai " . strtoupper($user['role']) . ", bukan " . strtoupper($role) . ". Silakan ganti tab login!";
                } else {
                    loginUser([
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'nama_lengkap' => $user['nama_lengkap'],
                        'role' => $user['role']
                    ]);
                    header("Location: index.php");
                    exit;
                }
            } else {
                $error = "Username atau Password pengelola salah!";
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
    <title>Portal Login - SSB Tamalanrea</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-dark);
            background-image: 
                radial-gradient(at 10% 10%, rgba(99, 102, 241, 0.25) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(168, 85, 247, 0.2) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(6, 182, 212, 0.15) 0px, transparent 60%);
            background-attachment: fixed;
            padding: 1.5rem;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo-box {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .login-logo-text {
            font-family: 'Outfit', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 1px;
        }

        .login-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--text-heading);
            margin-bottom: 4px;
        }

        .login-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
        }

        .login-card {
            background: rgba(26, 35, 50, 0.85);
            backdrop-filter: blur(25px);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }

        /* Role Selector Tabs */
        .role-tabs {
            display: flex;
            background: rgba(15, 23, 42, 0.8);
            padding: 5px;
            border-radius: 14px;
            border: 1px solid var(--border-glass);
            margin-bottom: 1.5rem;
            gap: 4px;
        }

        .role-tab-btn {
            flex: 1;
            padding: 8px 10px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-size: 0.82rem;
            font-weight: 700;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .role-tab-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(244, 63, 94, 0.15);
            color: #f87171;
            border: 1px solid rgba(244, 63, 94, 0.3);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .demo-box {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px dashed var(--border-glass);
            text-align: center;
        }

        .demo-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .demo-btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
        }

        .demo-chip {
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--border-glass);
            color: var(--text-body);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .demo-chip:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: var(--primary);
            color: #fff;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-brand">
        <div class="login-logo-box">
            <span class="login-logo-text">SSB</span>
        </div>
        <h1 class="login-title">SSB TAMALANREA</h1>
        <p class="login-subtitle">Portal Database & Manajemen Sekolah Sepak Bola</p>
    </div>

    <div class="login-card">
        <!-- Role Selector Tabs -->
        <div class="role-tabs">
            <button type="button" class="role-tab-btn active" onclick="switchRole('admin')">
                <span>🛡️</span> Admin
            </button>
            <button type="button" class="role-tab-btn" onclick="switchRole('pelatih')">
                <span>⚽</span> Pelatih
            </button>
            <button type="button" class="role-tab-btn" onclick="switchRole('atlet')">
                <span>🏃</span> Atlet / Siswa
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span>⚠️</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <span>✓</span> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="role" id="roleInput" value="admin">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label id="identifierLabel" for="identifier">Username Admin</label>
                <input type="text" name="identifier" id="identifier" class="form-control" placeholder="Masukkan username" required autocomplete="username">
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.9rem; font-size: 1rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
                Masuk Sistem
            </button>
        </form>

        <!-- Quick 1-Click Demo Fill -->
        <div class="demo-box">
            <div class="demo-title">Quick Demo Login (Uji Coba Akun):</div>
            <div class="demo-btn-group">
                <button type="button" class="demo-chip" onclick="fillDemo('admin', 'admin', 'admin123')">🔑 Admin Demo</button>
                <button type="button" class="demo-chip" onclick="fillDemo('pelatih', 'coach_andi', 'admin123')">⚽ Pelatih Demo</button>
                <button type="button" class="demo-chip" onclick="fillDemo('atlet', '7371011205120001', 'atlet123')">🏃 Atlet Fikri (U-14)</button>
            </div>
        </div>
    </div>
</div>

<script>
    function switchRole(role) {
        document.getElementById('roleInput').value = role;
        const buttons = document.querySelectorAll('.role-tab-btn');
        buttons.forEach(btn => btn.classList.remove('active'));

        const label = document.getElementById('identifierLabel');
        const input = document.getElementById('identifier');

        if (role === 'admin') {
            buttons[0].classList.add('active');
            label.innerText = 'Username Admin';
            input.placeholder = 'Masukkan username (mis: admin)';
        } else if (role === 'pelatih') {
            buttons[1].classList.add('active');
            label.innerText = 'Username Pelatih';
            input.placeholder = 'Masukkan username (mis: coach_andi)';
        } else if (role === 'atlet') {
            buttons[2].classList.add('active');
            label.innerText = 'NISN / NIK Atlet';
            input.placeholder = 'Masukkan NISN / NIK (mis: 7371011205120001)';
        }
    }

    function fillDemo(role, username, password) {
        switchRole(role);
        document.getElementById('identifier').value = username;
        document.getElementById('password').value = password;
    }
</script>

</body>
</html>
