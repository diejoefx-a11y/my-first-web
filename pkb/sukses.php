<?php
require_once __DIR__ . '/config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$no_kk = isset($_GET['kk']) ? clean($_GET['kk']) : '';

if (!$id && !$no_kk) {
    header("Location: index.php");
    exit;
}

$db = get_db();
$stmt = $db->prepare("SELECT * FROM families WHERE id = ? OR no_kk = ? LIMIT 1");
$stmt->execute([$id, $no_kk]);
$family = $stmt->fetch();

if (!$family) {
    header("Location: index.php");
    exit;
}

// Fetch family members
$stmtMembers = $db->prepare("SELECT * FROM family_members WHERE family_id = ? ORDER BY id ASC");
$stmtMembers->execute([$family['id']]);
$members = $stmtMembers->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Berhasil - Aplikasi Sensus Data PKBGT</title>
    
    <!-- Google Fonts & Font Awesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <style>
        #preview-map {
            height: 260px;
            width: 100%;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            border: 1px solid var(--border-color);
        }

        /* Dynamic Leaf Green Gradient Theme */
        .btn-leaf-green {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 35%, #22c55e 70%, #4ade80 100%) !important;
            border: 2px solid #86efac !important;
            color: #ffffff !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 800 !important;
            padding: 0.85rem 1.85rem !important;
            border-radius: 30px !important;
            box-shadow: 0 8px 24px rgba(34, 197, 94, 0.4), 0 0 15px rgba(74, 222, 128, 0.25) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            text-decoration: none !important;
            cursor: pointer !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.25) !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-leaf-green:hover {
            transform: translateY(-2px) scale(1.02) !important;
            box-shadow: 0 12px 30px rgba(34, 197, 94, 0.6), 0 0 25px rgba(74, 222, 128, 0.4) !important;
            background: linear-gradient(135deg, #16a34a 0%, #22c55e 40%, #4ade80 100%) !important;
            color: #ffffff !important;
        }

        /* Dynamic Bumblebee Yellow Gradient Theme */
        .btn-bumblebee-yellow {
            background: linear-gradient(135deg, #facc15 0%, #eab308 50%, #ca8a04 100%) !important;
            border: 2px solid #fef08a !important;
            color: #1e1b4b !important;
            font-family: 'Outfit', sans-serif !important;
            font-size: 0.95rem !important;
            font-weight: 800 !important;
            padding: 0.85rem 1.85rem !important;
            border-radius: 30px !important;
            box-shadow: 0 8px 24px rgba(234, 179, 8, 0.45), 0 0 15px rgba(250, 204, 21, 0.25) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
            text-decoration: none !important;
            cursor: pointer !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            position: relative;
            overflow: hidden;
        }
        .btn-bumblebee-yellow:hover {
            transform: translateY(-2px) scale(1.02) !important;
            box-shadow: 0 12px 30px rgba(234, 179, 8, 0.65), 0 0 25px rgba(250, 204, 21, 0.45) !important;
            background: linear-gradient(135deg, #fde047 0%, #facc15 100%) !important;
            color: #1e1b4b !important;
        }

        /* Photo Box & Lightbox Modal */
        .photo-preview-box {
            position: relative;
            cursor: pointer;
            overflow: hidden;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.25s ease;
        }
        .photo-preview-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.18);
        }
        .photo-preview-box:hover .img-zoomable {
            transform: scale(1.04);
        }
        .img-zoomable {
            transition: transform 0.3s ease;
            display: block;
        }
        .photo-overlay-badge {
            position: absolute;
            bottom: 8px;
            right: 8px;
            background: rgba(15, 23, 42, 0.85);
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            gap: 5px;
            pointer-events: none;
            transition: all 0.25s;
        }
        .photo-preview-box:hover .photo-overlay-badge {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            border-color: #c084fc;
            box-shadow: 0 0 10px rgba(167, 139, 250, 0.6);
        }

        /* Lightbox Modal Full Screen */
        .modal-lightbox {
            display: none;
            position: fixed;
            z-index: 999999;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(5, 3, 15, 0.94);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 1.5rem;
            box-sizing: border-box;
            opacity: 0;
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-lightbox.active {
            display: flex;
            opacity: 1;
        }
        .lightbox-card {
            position: relative;
            max-width: 92vw;
            max-height: 88vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            animation: zoomInLight 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes zoomInLight {
            from { transform: scale(0.88); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .lightbox-img {
            max-width: 100%;
            max-height: 78vh;
            object-fit: contain;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8), 0 0 35px rgba(167, 139, 250, 0.35);
            border: 2px solid rgba(196, 181, 253, 0.5);
            background: #000;
        }
        .lightbox-caption {
            margin-top: 0.85rem;
            color: #f8fafc;
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
            text-shadow: 0 2px 10px rgba(0,0,0,0.9);
            background: rgba(15, 23, 42, 0.7);
            padding: 6px 18px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
        }
        .lightbox-close-btn {
            position: absolute;
            top: -46px;
            right: 0;
            background: rgba(255, 255, 255, 0.15);
            border: 1.5px solid rgba(255, 255, 255, 0.4);
            color: #ffffff;
            font-size: 1.25rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        }
        .lightbox-close-btn:hover {
            background: #ef4444;
            border-color: #fca5a5;
            transform: scale(1.1) rotate(90deg);
        }

        /* Dynamic Leaf Green Table with Multi-color Distinct Rows */
        .table-leaf-wrapper {
            background: linear-gradient(135deg, #f0fdf4 0%, #e8fdf0 50%, #dcfce7 100%);
            border: 2px solid #86efac;
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: 0 10px 30px rgba(22, 163, 74, 0.15), 0 0 15px rgba(74, 222, 128, 0.1);
            margin-bottom: 2rem;
        }
        .table-leaf-header-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 10px;
        }
        .table-custom-leaf {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
            font-size: 0.9rem;
        }
        .table-custom-leaf thead tr th {
            background: linear-gradient(135deg, #15803d 0%, #16a34a 50%, #22c55e 100%);
            color: #ffffff;
            font-family: 'Outfit', sans-serif;
            font-weight: 800;
            padding: 0.75rem 1rem;
            letter-spacing: 0.3px;
            border: none;
        }
        .table-custom-leaf thead tr th:first-child {
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        .table-custom-leaf thead tr th:last-child {
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        
        /* Distinct Row Color Styles */
        .row-color-kepala {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.12);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .row-color-kepala td {
            background: #ecfdf5;
            border-top: 1.5px solid #a7f3d0;
            border-bottom: 1.5px solid #a7f3d0;
            padding: 0.75rem 1rem;
        }
        .row-color-kepala td:first-child {
            border-left: 5px solid #10b981;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .row-color-kepala td:last-child {
            border-right: 1.5px solid #a7f3d0;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .row-color-istri {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(244, 63, 94, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .row-color-istri td {
            background: #fff1f2;
            border-top: 1.5px solid #fecdd3;
            border-bottom: 1.5px solid #fecdd3;
            padding: 0.75rem 1rem;
        }
        .row-color-istri td:first-child {
            border-left: 5px solid #f43f5e;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .row-color-istri td:last-child {
            border-right: 1.5px solid #fecdd3;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .row-color-anak-1 {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(2, 132, 199, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .row-color-anak-1 td {
            background: #f0f9ff;
            border-top: 1.5px solid #bae6fd;
            border-bottom: 1.5px solid #bae6fd;
            padding: 0.75rem 1rem;
        }
        .row-color-anak-1 td:first-child {
            border-left: 5px solid #0284c7;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .row-color-anak-1 td:last-child {
            border-right: 1.5px solid #bae6fd;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .row-color-anak-2 {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .row-color-anak-2 td {
            background: #faf5ff;
            border-top: 1.5px solid #e9d5ff;
            border-bottom: 1.5px solid #e9d5ff;
            padding: 0.75rem 1rem;
        }
        .row-color-anak-2 td:first-child {
            border-left: 5px solid #8b5cf6;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .row-color-anak-2 td:last-child {
            border-right: 1.5px solid #e9d5ff;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .row-color-anak-3 {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(217, 119, 6, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .row-color-anak-3 td {
            background: #fffbeb;
            border-top: 1.5px solid #fde68a;
            border-bottom: 1.5px solid #fde68a;
            padding: 0.75rem 1rem;
        }
        .row-color-anak-3 td:first-child {
            border-left: 5px solid #d97706;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }
        .row-color-anak-3 td:last-child {
            border-right: 1.5px solid #fde68a;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .table-custom-leaf tbody tr:hover {
            transform: translateY(-2px) scale(1.005);
        }

        .badge-pill-role {
            font-size: 0.76rem;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-pill-role-kepala { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .badge-pill-role-istri { background: #ffe4e6; color: #9f1239; border: 1px solid #fecdd3; }
        .badge-pill-role-anak { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
        .badge-pill-role-ortu { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-pill-role-famili { background: #f3e8ff; color: #6b21a8; border: 1px solid #d8b4fe; }

        /* Dynamic Luxury Purple Theme for Data Keluarga Terdaftar */
        .card-purple-dynamic {
            background: linear-gradient(135deg, #1e0b36 0%, #2e1065 40%, #3b0764 75%, #4c1d95 100%) !important;
            border: 2px solid #a855f7 !important;
            border-radius: 20px !important;
            padding: 1.75rem !important;
            box-shadow: 0 16px 40px rgba(46, 16, 101, 0.45), 0 0 25px rgba(168, 85, 247, 0.25) !important;
            margin-bottom: 2rem !important;
            position: relative;
            overflow: hidden;
            color: #ffffff !important;
        }
        .card-purple-dynamic::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            background: radial-gradient(circle, rgba(168, 85, 247, 0.35) 0%, rgba(139, 92, 246, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .card-purple-dynamic::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 180px;
            height: 180px;
            background: radial-gradient(circle, rgba(192, 132, 252, 0.25) 0%, rgba(139, 92, 246, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }
        .purple-header-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.55rem;
            font-weight: 800;
            color: #ffffff;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .kk-badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(147, 51, 234, 0.35);
            border: 1.5px solid rgba(216, 180, 254, 0.6);
            color: #f5f3ff;
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 0.95rem;
            font-weight: 700;
            backdrop-filter: blur(8px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            margin-bottom: 0.75rem;
        }
        .kk-badge-pill strong {
            color: #facc15;
            letter-spacing: 0.5px;
            font-family: monospace;
            font-size: 1.05rem;
        }
        .info-tile-purple {
            background: rgba(255, 255, 255, 0.07);
            border: 1.5px solid rgba(192, 132, 252, 0.35);
            border-radius: 14px;
            padding: 0.9rem 1.1rem;
            backdrop-filter: blur(10px);
            transition: all 0.25s ease;
        }
        .info-tile-purple:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: #c084fc;
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(124, 58, 237, 0.3);
        }
        .info-tile-purple small {
            color: #d8b4fe !important;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 4px;
        }
        .info-tile-purple strong {
            color: #ffffff !important;
            font-size: 1.05rem;
            font-weight: 800;
        }
        .info-tile-purple span {
            color: #f3e8ff !important;
            font-size: 0.92rem;
            line-height: 1.5;
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
    </style>
</head>
<body>

    <!-- NAVBAR HEADER (CLEAN BRAND ONLY & DYNAMIC STICKY - MATCHING INDEX.PHP) -->
    <nav class="navbar" id="navbar">
        <div class="container navbar-container">
            <a href="index.php" class="brand-logo" id="brand-logo">
                <div class="brand-logo-wrap">
                    <img src="assets/img/logo_pkbgt.png" alt="Logo PKBGT" class="brand-logo-img">
                </div>
                <div class="brand-text">
                    <span class="brand-title">PKB GEREJA TORAJA</span>
                    <span class="brand-subtitle">PERSEKUTUAN KAUM BAPAK (PKBGT)</span>
                </div>
            </a>
        </div>
    </nav>

    <div class="container main-wrapper" style="margin-top: 2rem;">
        <div class="card" style="max-width: 800px; margin: 0 auto 2rem auto;">
            
            <!-- DATA KELUARGA TERDAFTAR (DYNAMIC LUXURY PURPLE THEME) -->
            <div class="card-purple-dynamic">
                
                <div style="text-align: center; margin-bottom: 1.75rem; position: relative; z-index: 1;">
                    <div style="font-size: 2.8rem; margin-bottom: 0.35rem; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.4));">🎉</div>
                    <h2 class="purple-header-title">
                        <span>👨‍👩‍👧‍👦</span> Data Keluarga Terdaftar
                    </h2>
                    
                    <div class="kk-badge-pill">
                        <i class="fa-solid fa-id-card" style="color: #c084fc;"></i>
                        <span>Nomor KK:</span>
                        <strong><?= htmlspecialchars($family['no_kk']) ?></strong>
                    </div>

                    <div>
                        <span class="badge-status badge-pending" style="background: rgba(245, 158, 11, 0.25); border: 1.5px solid #fbbf24; color: #fef08a; font-weight: 800; padding: 5px 14px; border-radius: 20px; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.2);">
                            ⏳ Status: Menunggu Verifikasi Admin
                        </span>
                    </div>
                </div>

                <!-- Info Tiles Grid -->
                <div class="form-grid" style="gap: 0.85rem; position: relative; z-index: 1;">
                    <div class="info-tile-purple">
                        <small><i class="fa-solid fa-user-tie"></i> Nama Kepala Keluarga</small>
                        <strong style="font-size: 1.15rem; color: #ffffff;"><?= htmlspecialchars($family['nama_kepala']) ?></strong>
                    </div>
                    <div class="info-tile-purple">
                        <small><i class="fa-solid fa-address-card"></i> NIK Kepala Keluarga</small>
                        <strong style="font-family: monospace; font-size: 1.05rem;"><?= htmlspecialchars($family['nik_kepala']) ?></strong>
                    </div>
                    <div class="info-tile-purple">
                        <small><i class="fa-brands fa-whatsapp" style="color:#4ade80;"></i> No. WhatsApp</small>
                        <strong><?= htmlspecialchars($family['no_hp']) ?></strong>
                    </div>
                    <div class="info-tile-purple">
                        <small><i class="fa-solid fa-users"></i> Jumlah Anggota Terdaftar</small>
                        <strong style="color: #67e8f9; font-size: 1.15rem;"><?= count($members) ?> Orang</strong>
                    </div>
                    <div class="col-full info-tile-purple">
                        <small><i class="fa-solid fa-location-dot" style="color:#f472b6;"></i> Alamat Domisili & Lingkungan</small>
                        <span><?= htmlspecialchars($family['alamat_lengkap']) ?> (RT <?= htmlspecialchars($family['rt']) ?> / RW <?= htmlspecialchars($family['rw']) ?>, Kel. <?= htmlspecialchars($family['kelurahan']) ?>, Kec. <?= htmlspecialchars($family['kecamatan']) ?>)</span>
                    </div>
                </div>

                <!-- Foto Keluarga & Foto Rumah -->
                <?php if (!empty($family['foto_keluarga']) || !empty($family['foto_rumah'])): ?>
                    <div style="display: flex; gap: 1rem; margin-top: 1.25rem; flex-wrap: wrap; position: relative; z-index: 1;">
                        <?php if (!empty($family['foto_keluarga'])): ?>
                            <div style="flex: 1; min-width: 200px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <small style="color: #d8b4fe; font-weight: 700; display: flex; align-items: center; gap: 5px;">
                                        <span>👨‍👩‍👧‍👦</span> Foto Keluarga:
                                    </small>
                                    <span style="font-size: 0.75rem; color: #c084fc; font-weight: 600;">(Klik untuk Fullscreen)</span>
                                </div>
                                <div class="photo-preview-box" onclick="openPhotoModal('<?= base_url('uploads/' . $family['foto_keluarga']) ?>', 'Foto Keluarga: <?= htmlspecialchars(addslashes($family['nama_kepala'])) ?> (KK: <?= htmlspecialchars(addslashes($family['no_kk'])) ?>)')" title="Klik untuk melihat foto full">
                                    <img src="<?= base_url('uploads/' . $family['foto_keluarga']) ?>" alt="Foto Keluarga" class="img-zoomable" style="max-height: 180px; width: 100%; object-fit: cover; border-radius: 12px; border: 2px solid #c084fc;">
                                    <div class="photo-overlay-badge">
                                        <i class="fa-solid fa-expand"></i> <span>Perbesar Foto</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($family['foto_rumah'])): ?>
                            <div style="flex: 1; min-width: 200px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <small style="color: #7dd3fc; font-weight: 700; display: flex; align-items: center; gap: 5px;">
                                        <span>🏠</span> Foto Rumah:
                                    </small>
                                    <span style="font-size: 0.75rem; color: #7dd3fc; font-weight: 600;">(Klik untuk Fullscreen)</span>
                                </div>
                                <div class="photo-preview-box" onclick="openPhotoModal('<?= base_url('uploads/' . $family['foto_rumah']) ?>', 'Foto Rumah: <?= htmlspecialchars(addslashes($family['nama_kepala'])) ?> - <?= htmlspecialchars(addslashes($family['alamat_lengkap'])) ?>')" title="Klik untuk melihat foto full">
                                    <img src="<?= base_url('uploads/' . $family['foto_rumah']) ?>" alt="Foto Rumah" class="img-zoomable" style="max-height: 180px; width: 100%; object-fit: cover; border-radius: 12px; border: 2px solid #38bdf8;">
                                    <div class="photo-overlay-badge">
                                        <i class="fa-solid fa-expand"></i> <span>Perbesar Foto</span>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Family Members List (Light Leaf Green Base & Multi-Color Distinct Rows) -->
            <?php if (!empty($members)): ?>
                <div class="table-leaf-wrapper">
                    <div class="table-leaf-header-title">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 1.35rem;">👨‍👩‍👧‍👦</span>
                            <h4 style="font-size: 1.1rem; font-weight: 800; color: #166534; margin: 0; font-family: 'Outfit', sans-serif;">
                                Susunan Anggota Keluarga Terdaftar
                            </h4>
                        </div>
                        <span style="background: #15803d; color: #ffffff; font-size: 0.8rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; box-shadow: 0 2px 8px rgba(21, 128, 61, 0.3);">
                            <?= count($members) ?> Jiwa Terdata
                        </span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table-custom-leaf">
                            <thead>
                                <tr>
                                    <th style="width: 45px; text-align: center;">No</th>
                                    <th>Nama Lengkap</th>
                                    <th>NIK (KTP)</th>
                                    <th>Hubungan Keluarga</th>
                                    <th style="text-align: center;">L/P</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $anakCounter = 0;
                                foreach ($members as $idx => $m): 
                                    $hub = strtolower(trim($m['hubungan_keluarga']));
                                    $rowClass = 'row-color-anak-1';
                                    $badgeClass = 'badge-pill-role-anak';
                                    $badgeIcon = '👶';
                                    
                                    if (strpos($hub, 'kepala') !== false || $idx === 0) {
                                        $rowClass = 'row-color-kepala';
                                        $badgeClass = 'badge-pill-role-kepala';
                                        $badgeIcon = '👑';
                                    } elseif (strpos($hub, 'istri') !== false) {
                                        $rowClass = 'row-color-istri';
                                        $badgeClass = 'badge-pill-role-istri';
                                        $badgeIcon = '💍';
                                    } elseif (strpos($hub, 'anak') !== false) {
                                        $anakCounter++;
                                        if ($anakCounter % 3 === 1) {
                                            $rowClass = 'row-color-anak-1'; // Sky blue
                                        } elseif ($anakCounter % 3 === 2) {
                                            $rowClass = 'row-color-anak-2'; // Soft Purple
                                        } else {
                                            $rowClass = 'row-color-anak-3'; // Soft Amber
                                        }
                                        $badgeClass = 'badge-pill-role-anak';
                                        $badgeIcon = '👶';
                                    } elseif (strpos($hub, 'orang tua') !== false || strpos($hub, 'mertua') !== false || strpos($hub, 'ayah') !== false || strpos($hub, 'ibu') !== false) {
                                        $rowClass = 'row-color-anak-3';
                                        $badgeClass = 'badge-pill-role-ortu';
                                        $badgeIcon = '🧓';
                                    } else {
                                        $rowClass = 'row-color-anak-2';
                                        $badgeClass = 'badge-pill-role-famili';
                                        $badgeIcon = '👤';
                                    }
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td style="text-align: center; font-weight: 800;"><?= $idx + 1 ?></td>
                                        <td style="font-weight: 700; color: #0f172a;">
                                            <?= htmlspecialchars($m['nama_lengkap']) ?>
                                        </td>
                                        <td style="font-family: monospace; font-size: 0.88rem; color: #334155; font-weight: 600;">
                                            <?= !empty($m['nik']) ? htmlspecialchars($m['nik']) : '<span style="color:#94a3b8; font-style:italic;">- Belum Diisi -</span>' ?>
                                        </td>
                                        <td>
                                            <span class="badge-pill-role <?= $badgeClass ?>">
                                                <span><?= $badgeIcon ?></span> <?= htmlspecialchars($m['hubungan_keluarga']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: center; font-weight: 800; color: <?= $m['jenis_kelamin'] === 'L' ? '#0284c7' : '#db2777' ?>;">
                                            <?= htmlspecialchars($m['jenis_kelamin']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Coordinates & Google Maps Link -->
            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 1rem; font-weight: 700; color: var(--secondary);">Titik Lokasi Rumah:</h4>
                <div id="preview-map"></div>

                <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.75rem; justify-content: center;">
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= $family['latitude'] ?>,<?= $family['longitude'] ?>" target="_blank" class="btn btn-primary" style="background: #ea4335; border-radius: 20px; font-weight: 700;">
                        🗺️ Buka Rute di Google Maps
                    </a>
                </div>
            </div>

            <hr style="margin: 2rem 0; border: none; border-top: 1px solid var(--border-color);">

            <div style="text-align: center; display: flex; justify-content: center; margin-top: 1rem;">
                <a href="index.php" class="btn-leaf-green">
                    <i class="fa-solid fa-house"></i> <span>Kembali ke Aplikasi Sensus Data</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Lightbox Modal Full Screen Preview -->
    <div id="photo-lightbox-modal" class="modal-lightbox" onclick="if(event.target === this) closePhotoModal();">
        <div class="lightbox-card">
            <button type="button" class="lightbox-close-btn" onclick="closePhotoModal()" title="Tutup (Esc)">✕</button>
            <img id="lightbox-modal-img" class="lightbox-img" src="" alt="Pratinjau Foto Full">
            <div id="lightbox-modal-caption" class="lightbox-caption"></div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        const lat = <?= $family['latitude'] ?>;
        const lng = <?= $family['longitude'] ?>;

        const previewMap = L.map('preview-map', {
            center: [lat, lng],
            zoom: 17,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(previewMap);

        const marker = L.marker([lat, lng]).addTo(previewMap);
        marker.bindPopup("<b>Rumah <?= htmlspecialchars(addslashes($family['nama_kepala'])) ?></b><br><?= htmlspecialchars(addslashes($family['alamat_lengkap'])) ?>").openPopup();

        // Lightbox Functions
        function openPhotoModal(imgSrc, captionText) {
            const modal = document.getElementById('photo-lightbox-modal');
            const modalImg = document.getElementById('lightbox-modal-img');
            const modalCap = document.getElementById('lightbox-modal-caption');
            modalImg.src = imgSrc;
            modalCap.textContent = captionText;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closePhotoModal() {
            const modal = document.getElementById('photo-lightbox-modal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePhotoModal();
            }
        });
    </script>
</body>
</html>
