<?php
/**
 * CEK STATUS VERIFIKASI NOMOR KARTU KELUARGA (KK) JEMAAT
 * Persekutuan Kaum Bapak (PKB) - Jemaat Kristiani
 * 
 * Alur:
 * 1. Menerima input Nomor KK dari halaman edit_data.php atau input mandiri.
 * 2. Mengecek status verifikasi di database `families` & relasi `groups`.
 * 3. Jika status = 'terverifikasi' -> Lanjut ke data_lengkap.php.
 * 4. Jika status != 'terverifikasi' -> Tampilkan info "Silahkan hubungi Koordinator Kelompok untuk Verifikasi Lokasi".
 */

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Makassar');

$db = get_db();

// 1. Ambil & bersihkan input nomor KK
$raw_kk = $_GET['kk'] ?? ($_POST['kk'] ?? ($_GET['no_kk'] ?? ($_POST['no_kk'] ?? '')));
$no_kk = preg_replace('/[^0-9]/', '', (string)$raw_kk);

$family = null;
$errorMsg = '';
$isSearched = !empty($no_kk);

if ($isSearched) {
    if (strlen($no_kk) < 10) {
        $errorMsg = "Nomor Kartu Keluarga tidak valid. Minimal 10-16 digit angka.";
    } else {
        try {
            $stmt = $db->prepare("
                SELECT 
                    f.*,
                    g.nomor_kelompok,
                    g.nama_kelompok,
                    g.nama_ketua,
                    g.no_hp_ketua,
                    g.wilayah_cakupan,
                    (SELECT COUNT(*) FROM family_members WHERE family_id = f.id) as total_jiwa
                FROM families f
                LEFT JOIN `groups` g ON f.kelompok_id = g.id
                WHERE f.no_kk = ?
                LIMIT 1
            ");
            $stmt->execute([$no_kk]);
            $family = $stmt->fetch();

            if (!$family) {
                $errorMsg = "Nomor KK <strong>$no_kk</strong> tidak ditemukan di dalam sistem pendataan.";
                unset($_SESSION['jemaat_verified_kk'], $_SESSION['jemaat_verified_id'], $_SESSION['jemaat_verified_nama'], $_SESSION['jemaat_verified_kelompok'], $_SESSION['jemaat_verified_time']);
            } else {
                // Normalisasi status verifikasi (default: pending jika null/kosong)
                $statusVerif = strtolower(trim((string)($family['status_verifikasi'] ?? 'pending')));
                $isVerified = ($statusVerif === 'terverifikasi');
                
                // JIKA TERVERIFIKASI: Simpan Sesi Aman untuk Akses Halaman Terlindungi (data_lengkap.php)
                if ($isVerified) {
                    $_SESSION['jemaat_verified_kk'] = $family['no_kk'];
                    $_SESSION['jemaat_verified_id'] = $family['id'];
                    $_SESSION['jemaat_verified_nama'] = $family['nama_kepala'];
                    $_SESSION['jemaat_verified_kelompok'] = $family['nomor_kelompok'] ?? 1;
                    $_SESSION['jemaat_verified_time'] = time();
                } else {
                    unset($_SESSION['jemaat_verified_kk'], $_SESSION['jemaat_verified_id'], $_SESSION['jemaat_verified_nama'], $_SESSION['jemaat_verified_kelompok'], $_SESSION['jemaat_verified_time']);
                }
            }
        } catch (PDOException $e) {
            $errorMsg = "Terjadi kesalahan sistem saat mengecek database: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Verifikasi KK Jemaat | PKB</title>
    
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #7c3aed;
            --primary-light: #a78bfa;
            --primary-glow: rgba(124, 58, 237, 0.4);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.4);
            --warning: #f59e0b;
            --warning-glow: rgba(245, 158, 11, 0.4);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.4);
            --bg-dark: #0b091a;
            --card-bg: rgba(18, 15, 38, 0.92);
            --border-color: rgba(139, 92, 246, 0.3);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-dark);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(124, 58, 237, 0.18) 0%, transparent 45%),
                radial-gradient(circle at 85% 85%, rgba(16, 185, 129, 0.12) 0%, transparent 45%),
                radial-gradient(circle at 50% 50%, rgba(245, 158, 11, 0.08) 0%, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* NAVBAR HEADER (MATCHING INDEX.PHP) */
        .navbar {
            position: sticky;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background: rgba(11, 9, 26, 0.82) !important;
            backdrop-filter: blur(20px) !important;
            -webkit-backdrop-filter: blur(20px) !important;
            border-bottom: 1.5px solid rgba(139, 92, 246, 0.25) !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.35) !important;
            padding: 0.65rem 0 !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        .navbar-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
        }
        .brand-logo {
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
            text-decoration: none !important;
            transition: transform 0.2s ease !important;
        }
        .brand-logo:hover {
            transform: scale(1.02);
        }
        .brand-logo-wrap {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .brand-logo-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: contain;
            background: #ffffff;
            padding: 2px;
            border: 2px solid rgba(167, 139, 250, 0.55);
            box-shadow: 0 0 16px rgba(167, 139, 250, 0.55), 0 0 8px rgba(16, 185, 129, 0.4);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
        }
        .brand-logo:hover .brand-logo-img {
            transform: rotate(6deg) scale(1.06);
            box-shadow: 0 0 24px rgba(167, 139, 250, 0.8), 0 0 12px rgba(16, 185, 129, 0.6);
        }
        .brand-title {
            font-family: 'Outfit', sans-serif !important;
            font-size: 1.28rem !important;
            font-weight: 800 !important;
            color: #ffffff !important;
            letter-spacing: 0.5px !important;
            display: block !important;
            line-height: 1.15 !important;
        }
        .brand-subtitle {
            font-size: 0.76rem !important;
            font-weight: 700 !important;
            color: #c4b5fd !important;
            letter-spacing: 0.8px !important;
            text-transform: uppercase !important;
            display: block !important;
        }

        /* Container & Cards */
        .main-container {
            max-width: 780px;
            width: 100%;
            margin: 2.5rem auto;
            padding: 0 1.25rem;
            flex: 1;
        }

        .verification-card {
            background: var(--card-bg);
            border-radius: 24px;
            padding: 2.25rem;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        /* State Badges & Glows */
        .card-verified {
            border: 2px solid rgba(16, 185, 129, 0.5);
            box-shadow: 0 20px 50px rgba(16, 185, 129, 0.25);
        }

        .card-unverified {
            border: 2px solid rgba(245, 158, 11, 0.55);
            box-shadow: 0 20px 50px rgba(245, 158, 11, 0.22);
        }

        .card-notfound {
            border: 2px solid rgba(239, 68, 68, 0.5);
            box-shadow: 0 20px 50px rgba(239, 68, 68, 0.2);
        }

        .card-input {
            border: 1.5px solid rgba(139, 92, 246, 0.4);
            box-shadow: 0 20px 50px rgba(124, 58, 237, 0.2);
        }

        /* Status Header Section */
        .status-header {
            text-align: center;
            margin-bottom: 1.75rem;
        }

        .status-icon-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            animation: pulse-icon 2s infinite ease-in-out;
        }

        @keyframes pulse-icon {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .icon-verified {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.4));
            border: 2px solid #34d399;
            color: #34d399;
            box-shadow: 0 0 25px var(--success-glow);
        }

        .icon-unverified {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.25), rgba(217, 119, 6, 0.4));
            border: 2px solid #fbbf24;
            color: #fbbf24;
            box-shadow: 0 0 25px var(--warning-glow);
        }

        .icon-notfound {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(185, 28, 28, 0.4));
            border: 2px solid #f87171;
            color: #f87171;
            box-shadow: 0 0 25px var(--danger-glow);
        }

        .icon-input {
            background: linear-gradient(135deg, rgba(124, 58, 237, 0.25), rgba(76, 29, 149, 0.4));
            border: 2px solid #a78bfa;
            color: #a78bfa;
            box-shadow: 0 0 25px var(--primary-glow);
        }

        .status-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .status-subtitle {
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Banner Peringatan Verifikasi */
        .alert-unverified-box {
            background: linear-gradient(135deg, rgba(120, 53, 15, 0.5) 0%, rgba(69, 26, 3, 0.6) 100%);
            border: 2px solid #f59e0b;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);
        }

        .alert-unverified-box .alert-icon {
            font-size: 2.2rem;
            color: #fbbf24;
            flex-shrink: 0;
        }

        .alert-unverified-box .alert-text h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.2rem;
            font-weight: 800;
            color: #fef3c7;
            margin-bottom: 0.25rem;
        }

        .alert-unverified-box .alert-text p {
            font-size: 0.92rem;
            color: #fde68a;
            line-height: 1.5;
        }

        /* Info Grid Detail Keluarga */
        .info-detail-box {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.9rem;
        }

        .detail-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .detail-label {
            color: var(--text-muted);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value {
            color: #ffffff;
            font-weight: 700;
            text-align: right;
        }

        /* Box Kontak Koordinator */
        .coordinator-contact-card {
            background: linear-gradient(135deg, rgba(6, 78, 59, 0.5) 0%, rgba(4, 47, 46, 0.6) 100%);
            border: 1.5px solid #10b981;
            border-radius: 16px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
        }

        .coord-info h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            color: #d1fae5;
            margin-bottom: 0.25rem;
        }

        .coord-info p {
            font-size: 0.85rem;
            color: #a7f3d0;
        }

        .btn-wa-coord {
            background: linear-gradient(135deg, #10b981, #059669);
            border: 1.5px solid #6ee7b7;
            color: #ffffff !important;
            padding: 0.75rem 1.4rem;
            border-radius: 12px;
            font-size: 0.92rem;
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
            transition: all 0.2s ease;
        }

        .btn-wa-coord:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.6);
            background: linear-gradient(135deg, #059669, #047857);
        }

        /* Buttons Section */
        .action-buttons-grid {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .btn-action-primary {
            flex: 1;
            min-width: 200px;
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            border: 1.5px solid #c4b5fd;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            padding: 0.9rem 1.5rem;
            border-radius: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.4);
            transition: all 0.2s ease;
        }

        .btn-action-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(124, 58, 237, 0.6);
            background: linear-gradient(135deg, #6d28d9 0%, #5b21b6 100%);
        }

        .btn-action-success {
            flex: 1;
            min-width: 200px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border: 1.5px solid #86efac;
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 800;
            padding: 0.9rem 1.5rem;
            border-radius: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.4);
            transition: all 0.2s ease;
        }

        .btn-action-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.6);
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
        }

        .btn-action-secondary {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
            font-size: 0.92rem;
            font-weight: 700;
            padding: 0.9rem 1.25rem;
            border-radius: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .btn-action-secondary:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }

        /* Form Input Section */
        .form-check-kk {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .input-kk-field {
            flex: 1;
            min-width: 260px;
            background: rgba(15, 23, 42, 0.85);
            border: 1.5px solid rgba(167, 139, 250, 0.4);
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 700;
            padding: 0.9rem 1.25rem;
            border-radius: 14px;
            letter-spacing: 1px;
            transition: all 0.2s ease;
        }

        .input-kk-field:focus {
            outline: none;
            border-color: #a78bfa;
            box-shadow: 0 0 20px var(--primary-glow);
            background: rgba(15, 23, 42, 0.95);
        }

        .input-kk-field::placeholder {
            color: rgba(255, 255, 255, 0.35);
            font-weight: 400;
            letter-spacing: normal;
        }

        /* Auto-redirect countdown bar */
        .progress-bar-container {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            overflow: hidden;
            margin: 1.25rem 0;
        }

        .progress-bar-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #34d399, #10b981);
            transition: width 2s linear;
        }

        /* Footer */
        .app-footer {
            text-align: center;
            padding: 1.5rem;
            font-size: 0.82rem;
            color: var(--text-muted);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            margin-top: auto;
        }
    </style>
</head>
<body>

    <!-- NAVBAR HEADER (CLEAN BRAND ONLY & DYNAMIC STICKY - MATCHING INDEX.PHP) -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-container">
            <a href="../index.php" class="brand-logo" id="brand-logo">
                <div class="brand-logo-wrap">
                    <img src="../assets/img/logo_pkbgt.png" alt="Logo PKBGT" class="brand-logo-img">
                </div>
                <div class="brand-text">
                    <span class="brand-title">PKB GEREJA TORAJA</span>
                    <span class="brand-subtitle">PERSEKUTUAN KAUM BAPAK (PKBGT)</span>
                </div>
            </a>
        </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main class="main-container">

        <?php if (isset($_GET['auth_required'])): ?>
            <div style="background: linear-gradient(135deg, rgba(120, 53, 15, 0.6), rgba(69, 26, 3, 0.7)); border: 1.5px solid #f59e0b; border-radius: 16px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 8px 25px rgba(245, 158, 11, 0.25);">
                <i class="fa-solid fa-lock" style="font-size: 1.8rem; color: #fbbf24; flex-shrink: 0;"></i>
                <div>
                    <strong style="color: #fef3c7; font-size: 1.05rem; display: block;">Akses Terbatas: Autentikasi Diperlukan</strong>
                    <span style="color: #fde68a; font-size: 0.88rem;">Halaman Data Lengkap Jemaat diproteksi demi privasi. Silakan masukkan Nomor Kartu Keluarga (KK) Anda yang berstatus <strong>Terverifikasi</strong> untuk membuka data.</span>
                </div>
            </div>
        <?php elseif (isset($_GET['timeout'])): ?>
            <div style="background: linear-gradient(135deg, rgba(127, 29, 29, 0.6), rgba(69, 10, 10, 0.7)); border: 1.5px solid #ef4444; border-radius: 16px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25);">
                <i class="fa-solid fa-hourglass-end" style="font-size: 1.8rem; color: #f87171; flex-shrink: 0;"></i>
                <div>
                    <strong style="color: #fee2e2; font-size: 1.05rem; display: block;">Sesi Berakhir (Timeout)</strong>
                    <span style="color: #fca5a5; font-size: 0.88rem;">Sesi akses Anda telah kedaluwarsa setelah 30 menit tidak ada aktivitas. Silakan periksa kembali Nomor KK Anda.</span>
                </div>
            </div>
        <?php elseif (isset($_GET['status_revoked'])): ?>
            <div style="background: linear-gradient(135deg, rgba(127, 29, 29, 0.6), rgba(69, 10, 10, 0.7)); border: 1.5px solid #ef4444; border-radius: 16px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 8px 25px rgba(239, 68, 68, 0.25);">
                <i class="fa-solid fa-shield-halved" style="font-size: 1.8rem; color: #f87171; flex-shrink: 0;"></i>
                <div>
                    <strong style="color: #fee2e2; font-size: 1.05rem; display: block;">Sesi Tidak Valid</strong>
                    <span style="color: #fca5a5; font-size: 0.88rem;">Status verifikasi KK Anda saat ini tidak lagi berstatus 'Terverifikasi'. Silakan hubungi Koordinator Kelompok Anda.</span>
                </div>
            </div>
        <?php elseif (isset($_GET['logged_out'])): ?>
            <div style="background: linear-gradient(135deg, rgba(6, 78, 59, 0.6), rgba(4, 47, 46, 0.7)); border: 1.5px solid #10b981; border-radius: 16px; padding: 1.15rem 1.5rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 12px; box-shadow: 0 8px 25px rgba(16, 185, 129, 0.25);">
                <i class="fa-solid fa-circle-check" style="font-size: 1.8rem; color: #34d399; flex-shrink: 0;"></i>
                <div>
                    <strong style="color: #d1fae5; font-size: 1.05rem; display: block;">Berhasil Keluar (Logout)</strong>
                    <span style="color: #a7f3d0; font-size: 0.88rem;">Sesi akses terlindungi Anda telah ditutup dengan aman.</span>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($family && $isVerified): ?>
            <!-- ============================================================= -->
            <!-- KASUS 1: NOMOR KK TERVERIFIKASI -> LANJUT KE DATA LENGKAP -->
            <!-- ============================================================= -->
            <div class="verification-card card-verified">
                <div class="status-header">
                    <div class="status-icon-wrap icon-verified">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2 class="status-title">Status: Terverifikasi!</h2>
                    <p class="status-subtitle">
                        Data Kartu Keluarga dan titik lokasi rumah Anda telah disetujui dan terverifikasi secara resmi di database jemaat.
                    </p>
                </div>

                <div class="info-detail-box">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-id-card"></i> Nomor KK</span>
                        <span class="detail-value"><?= htmlspecialchars($family['no_kk']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-user-tie"></i> Kepala Keluarga</span>
                        <span class="detail-value"><?= htmlspecialchars($family['nama_kepala']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-users-rectangle"></i> Kelompok Pelayanan</span>
                        <span class="detail-value">Kelompok <?= $family['nomor_kelompok'] ?? '1' ?> - <?= htmlspecialchars($family['nama_kelompok'] ?? 'Pelayanan') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-shield-halved"></i> Status Database</span>
                        <span class="detail-value" style="color: #34d399; text-transform: uppercase;">
                            <i class="fa-solid fa-check-double"></i> <?= htmlspecialchars($family['status_verifikasi']) ?>
                        </span>
                    </div>
                </div>

                <div style="text-align: center; margin-top: 1rem;">
                    <p style="font-size: 0.9rem; color: #a7f3d0; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-circle-notch fa-spin"></i> Mengalihkan secara otomatis ke halaman Data Lengkap Jemaat...
                    </p>
                    <div class="progress-bar-container">
                        <div id="redirectProgress" class="progress-bar-fill"></div>
                    </div>
                </div>

                <div class="action-buttons-grid">
                    <a href="data_lengkap.php?kk=<?= urlencode($family['no_kk']) ?>&search=<?= urlencode($family['nama_kepala']) ?>" class="btn-action-success" id="btn-manual-redirect">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i> <span>Lanjut ke Data Lengkap Jemaat</span>
                    </a>
                    <a href="edit_data.php?kk=<?= urlencode($family['no_kk']) ?>" class="btn-action-secondary">
                        <i class="fa-solid fa-user-pen"></i> Edit Data
                    </a>
                </div>
            </div>

            <script>
                // Auto redirect setelah 1.5 detik
                document.addEventListener('DOMContentLoaded', () => {
                    const bar = document.getElementById('redirectProgress');
                    if (bar) {
                        setTimeout(() => { bar.style.width = '100%'; }, 50);
                    }
                    setTimeout(() => {
                        window.location.href = "data_lengkap.php?kk=<?= urlencode($family['no_kk']) ?>&search=<?= urlencode($family['nama_kepala']) ?>";
                    }, 1800);
                });
            </script>

        <?php elseif ($family && !$isVerified): ?>
            <!-- ============================================================= -->
            <!-- KASUS 2: DATA DITEMUKAN TETAPI BELUM TERVERIFIKASI -->
            <!-- ============================================================= -->
            <div class="verification-card card-unverified">
                <div class="status-header">
                    <div class="status-icon-wrap icon-unverified">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <h2 class="status-title">Status: Belum Terverifikasi</h2>
                    <p class="status-subtitle">
                        Data Nomor KK Anda ditemukan di sistem, namun titik lokasi rumah atau data keluarga saat ini masih berstatus 
                        <strong style="color: #fbbf24; text-transform: uppercase;">[<?= htmlspecialchars($family['status_verifikasi'] ?: 'PENDING') ?>]</strong>.
                    </p>
                </div>

                <!-- ALERT UTAMA PERSYARATAN USER -->
                <div class="alert-unverified-box">
                    <div class="alert-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="alert-text">
                        <h3>Pemberitahuan Penting</h3>
                        <p>
                            <strong>Silahkan hubungi Koordinator Kelompok untuk Verifikasi Lokasi</strong> rumah dan data keluarga Anda agar dapat diverifikasi secara resmi.
                        </p>
                    </div>
                </div>

                <!-- INFO DETAIL KELUARGA -->
                <div class="info-detail-box">
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-id-card"></i> Nomor Kartu Keluarga</span>
                        <span class="detail-value"><?= htmlspecialchars($family['no_kk']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-user"></i> Nama Kepala Keluarga</span>
                        <span class="detail-value"><?= htmlspecialchars($family['nama_kepala']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-layer-group"></i> Kelompok Pelayanan</span>
                        <span class="detail-value">Kelompok <?= $family['nomor_kelompok'] ?? '1' ?> - <?= htmlspecialchars($family['nama_kelompok'] ?? 'Kelompok Jemaat') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-location-dot"></i> Alamat Tercatat</span>
                        <span class="detail-value" style="font-weight: 500;"><?= htmlspecialchars($family['alamat_lengkap'] ?: 'Belum ada alamat') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label"><i class="fa-solid fa-spinner"></i> Status Verifikasi</span>
                        <span class="detail-value" style="color: #fbbf24; text-transform: uppercase;">
                            <?= htmlspecialchars($family['status_verifikasi'] ?: 'Menunggu Verifikasi') ?>
                        </span>
                    </div>
                </div>

                <!-- KOTAK KONTAK KOORDINATOR KELOMPOK -->
                <?php 
                    $coordName = !empty($family['nama_ketua']) ? $family['nama_ketua'] : 'Koordinator Kelompok ' . ($family['nomor_kelompok'] ?? '1');
                    $coordPhoneRaw = !empty($family['no_hp_ketua']) ? preg_replace('/[^0-9]/', '', $family['no_hp_ketua']) : '628114188796';
                    if (substr($coordPhoneRaw, 0, 1) === '0') $coordPhoneRaw = '62' . substr($coordPhoneRaw, 1);
                    
                    $waText = urlencode("Shalom Bapak/Ibu Koordinator " . ($family['nama_kelompok'] ?? 'Kelompok') . " (" . $coordName . "), saya " . $family['nama_kepala'] . " (No. KK: " . $family['no_kk'] . ") ingin mengajukan verifikasi titik lokasi rumah jemaat di aplikasi sensus PKB.");
                    $waUrl = "https://wa.me/" . $coordPhoneRaw . "?text=" . $waText;
                ?>
                <div class="coordinator-contact-card">
                    <div class="coord-info">
                        <h4><i class="fa-solid fa-user-shield" style="color: #34d399;"></i> <?= htmlspecialchars($coordName) ?></h4>
                        <p>Koordinator Kelompok <?= $family['nomor_kelompok'] ?? '1' ?> | No. HP: <?= htmlspecialchars($family['no_hp_ketua'] ?: '0811-4188-796') ?></p>
                    </div>
                    <a href="<?= $waUrl ?>" target="_blank" class="btn-wa-coord">
                        <i class="fa-brands fa-whatsapp" style="font-size: 1.2rem;"></i>
                        <span>Hubungi via WhatsApp</span>
                    </a>
                </div>

                <div class="action-buttons-grid">
                    <a href="../index.php" class="btn-action-secondary" style="width: 100%; justify-content: center;">
                        <i class="fa-solid fa-house"></i> Kembali ke Beranda
                    </a>
                </div>
            </div>

        <?php elseif ($isSearched && !empty($errorMsg)): ?>
            <!-- ============================================================= -->
            <!-- KASUS 3: DATA KK TIDAK DITEMUKAN ATAU ERROR -->
            <!-- ============================================================= -->
            <div class="verification-card card-notfound">
                <div class="status-header">
                    <div class="status-icon-wrap icon-notfound">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </div>
                    <h2 class="status-title">Data KK Tidak Ditemukan</h2>
                    <p class="status-subtitle">
                        <?= $errorMsg ?>
                    </p>
                </div>

                <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 16px; padding: 1.25rem; margin-bottom: 1.5rem; color: #fca5a5; font-size: 0.92rem; line-height: 1.6;">
                    <p><strong>Saran Tindakan:</strong></p>
                    <ul style="margin-left: 1.5rem; margin-top: 0.35rem;">
                        <li>Periksa kembali apakah 16 digit Nomor KK yang dimasukkan sudah tepat.</li>
                        <li>Jika keluarga Anda belum pernah terdaftar, silakan lakukan pendaftaran baru dan pasang titik koordinat rumah jemaat.</li>
                    </ul>
                </div>

                <!-- FORM CARI ULANG -->
                <form action="cek_kk.php" method="GET" class="form-check-kk">
                    <input type="text" name="kk" class="input-kk-field" maxlength="16" placeholder="Masukkan 16 digit Nomor KK..." value="<?= htmlspecialchars($no_kk) ?>" required>
                    <button type="submit" class="btn-action-primary" style="flex: 0; min-width: 140px;">
                        <i class="fa-solid fa-magnifying-glass"></i> Cari Ulang
                    </button>
                </form>

                <div class="action-buttons-grid" style="margin-top: 1.25rem;">
                    <a href="pasangtitik.php" class="btn-action-success">
                        <i class="fa-solid fa-map-location-dot"></i> <span>Daftarkan KK & Pasang Titik Baru</span>
                    </a>
                    <a href="edit_data.php" class="btn-action-secondary">
                        <i class="fa-solid fa-arrow-left"></i> Kembali ke Form Edit
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- ============================================================= -->
            <!-- KASUS 4: HALAMAN INPUT CEK KK MANDIRI -->
            <!-- ============================================================= -->
            <div class="verification-card card-input">
                <div class="status-header">
                    <div class="status-icon-wrap icon-input">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </div>
                    <h2 class="status-title">Pemeriksaan Status Verifikasi KK</h2>
                    <p class="status-subtitle">
                        Masukkan Nomor Kartu Keluarga (KK) Anda untuk memeriksa status validasi lokasi rumah dan data jemaat PKB.
                    </p>
                </div>

                <form action="cek_kk.php" method="GET" style="margin-top: 1.5rem;">
                    <div style="margin-bottom: 1.25rem;">
                        <label for="kk" style="display: block; font-weight: 700; color: #e9d5ff; font-size: 0.92rem; margin-bottom: 0.5rem;">
                            Nomor Kartu Keluarga (16 Digit):
                        </label>
                        <input type="text" id="kk" name="kk" class="input-kk-field" style="width: 100%;" maxlength="16" placeholder="Contoh: 7371101234560001" required autofocus>
                    </div>

                    <button type="submit" class="btn-action-primary" style="width: 100%;">
                        <i class="fa-solid fa-shield-halved"></i> <span>Periksa Status Verifikasi Sekarang</span>
                    </button>
                </form>

            </div>
        <?php endif; ?>

    </main>

    <footer class="app-footer">
        &copy; <?= date('Y') ?> Persekutuan Kaum Bapak Gereja Toraja (PKBGT). Sistem Informasi & Pemetaan PKBGT.
    </footer>

</body>
</html>
